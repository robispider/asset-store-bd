<?php

namespace GovStore\StoreOperations\Services;

use App\Models\Location;
use GovStore\StoreOperations\Enums\StockableType;
use GovStore\StoreOperations\Models\Document;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\TenantExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Immediate transfer requires the executing actor's live posting authority in both offices. */
class TransferPostingService
{
    public function atDestination(Document $document, int $actorId, \Closure $callback): mixed
    {
        $destination = Location::withoutGlobalScopes()->whereNull('deleted_at')->find($document->destination_location_id);
        abort_unless($destination && (int) $destination->company_id === (int) $document->company_id
            && (int) $destination->id !== (int) $document->location_id, 404);
        return app(TenantExecution::class)->run([
            'actor_id' => $actorId, 'scope_type' => 'location', 'scope_id' => (int) $destination->id,
            'ability' => 'storeops.documents.post',
        ], $callback);
    }

    public function destinationItems(Document $document, string $productType, int $productId, int $actorId)
    {
        $type = StockableType::fromString($productType);
        abort_if($type === StockableType::ASSET_MODEL, 422, __('storeops::storeops.transfer_bulk_only'));
        $source = $type->value::query()->findOrFail($productId);
        abort_unless((int) $source->location_id === (int) $document->location_id
            && (int) $source->company_id === (int) $document->company_id, 404);
        return $this->atDestination($document, $actorId, function () use ($type, $source) {
            return $type->value::query()->where('location_id', app(TenantContext::class)->locationId)
                ->where('company_id', app(TenantContext::class)->companyId)
                ->where('name', $source->name)->where('category_id', $source->category_id)
                ->orderBy('id')->get()->filter(fn ($target) => $this->matches($source, $target));
        });
    }

    private function matches($source, $destination): bool
    {
        foreach (['name', 'category_id', 'manufacturer_id', 'model_number'] as $field) {
            if ((string) $source->$field !== (string) $destination->$field) {
                return false;
            }
        }
        return filled($source->name) && filled($source->category_id);
    }

    public function post(Document $document, int $actorId): void
    {
        DB::transaction(function () use ($document, $actorId) {
            $locked = Document::withoutGlobalScopes()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->type === 'transfer' && in_array($locked->status, ['DRAFT', 'READY'], true), 409);
            $this->postLocked($locked, $actorId);
        });
    }

    private function postLocked(Document $document, int $actorId): void
    {
        $context = app(TenantContext::class);
        abort_unless((int) $document->location_id === $context->locationId
            && (int) $document->company_id === $context->companyId, 404);
        abort_unless(auth()->id() === $actorId && app(GovAccess::class)->decide(auth()->user(), 'storeops.documents.post')->allowed, 403);
        if (mb_strlen(trim($document->transfer_reason ?? '')) < 5 || ! $document->items()->exists()) {
            throw ValidationException::withMessages(['transfer_reason' => __('storeops::storeops.transfer_reason_required')]);
        }
        $pairs = [];
        $locks = [];
        foreach ($document->items as $item) {
            $type = StockableType::fromString($item->product_type);
            abort_if($type === StockableType::ASSET_MODEL, 422, __('storeops::storeops.transfer_bulk_only'));
            $targetId = filter_var($item->metadata()->where('field_key', 'destination_stockable_id')->value('value'), FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]);
            abort_unless($targetId && $item->quantity > 0, 422);
            $pairs[] = [$item, $type, $targetId];
            $locks[$type->value][] = (int) $item->product_id;
            $locks[$type->value][] = $targetId;
        }
        // Stable ordering prevents opposite-direction transfers from inverting stock locks.
        ksort($locks);
        foreach ($locks as $class => $ids) {
            $class::withoutGlobalScopes()->whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
        }
        $this->atDestination($document, $actorId, function () use ($document, $actorId, $pairs) {
            $destinationContext = app(TenantContext::class);
            foreach ($pairs as [$item, $type, $targetId]) {
                $source = $type->value::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($item->product_id);
                $target = $type->value::query()->where('location_id', $destinationContext->locationId)
                    ->where('company_id', $destinationContext->companyId)->findOrFail($targetId);
                abort_unless((int) $source->location_id === (int) $document->location_id
                    && (int) $source->company_id === (int) $document->company_id && $this->matches($source, $target), 404);
            }
        });

        $ledger = app(LedgerPostingService::class);
        foreach ($pairs as [$item, $type, $targetId]) {
            $ledger->postMovement($item->product_type, $item->product_id, 'OUT', $item->quantity,
                $document, $document->company_id, $document->location_id, $actorId, $document->transfer_reason);
        }
        $this->atDestination($document, $actorId, function () use ($document, $actorId, $pairs, $ledger) {
            $receipt = Document::create([
                'document_number' => app(DocumentNumberService::class)->generate('GR', 'gov_documents', 'document_number'),
                'type' => 'receipt', 'status' => 'POSTED', 'purchase_type' => 'Transfer',
                'company_id' => $document->company_id, 'location_id' => $document->destination_location_id,
                'created_by' => $actorId, 'drafted_by' => $actorId, 'managed_by' => $actorId,
                'posted_by' => $actorId, 'posted_at' => now(), 'source_document_id' => $document->id,
                'compiled_profile_snapshot' => ['kind' => 'paired_transfer', 'source' => $document->id],
            ]);
            $receipt->references()->create(['reference_type' => 'Office Transfer', 'reference_number' => $document->document_number]);
            foreach ($pairs as [$item, $type, $targetId]) {
                $receipt->items()->create(['product_type' => $item->product_type, 'product_id' => $targetId,
                    'quantity' => $item->quantity, 'unit_cost' => $item->unit_cost]);
                $ledger->postMovement($item->product_type, $targetId, 'IN', $item->quantity, $receipt,
                    $receipt->company_id, $receipt->location_id, $actorId, $document->transfer_reason);
            }
            $receipt->timelines()->create(['state' => 'POSTED', 'user_id' => $actorId, 'notes' => $document->transfer_reason]);
        });
        $document->update(['status' => 'POSTED', 'posted_by' => $actorId, 'posted_at' => now()]);
        $document->timelines()->create(['state' => 'POSTED', 'user_id' => $actorId, 'notes' => $document->transfer_reason]);
    }
}
