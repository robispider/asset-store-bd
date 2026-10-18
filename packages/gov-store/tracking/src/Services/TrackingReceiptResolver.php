<?php

namespace GovStore\Tracking\Services;

use App\Models\Asset;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\Tracking\Models\TrackingDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Callers supply only associations within their authorized programme/office slice. */
final class TrackingReceiptResolver
{
    public function resolve(Collection $associations): array
    {
        $deliveryIds = $associations->pluck('delivery_id')->filter()->merge($associations
            ->where('associatable_type', TrackingDelivery::class)->pluck('associatable_id'))->unique();
        $deliveries = DB::table('gov_tracking_deliveries')->whereIn('id', $deliveryIds)->get()->keyBy('id');
        $movementIds = $associations->where('associatable_type', InventoryMovement::class)->pluck('associatable_id')
            ->merge($deliveries->pluck('movement_id')->filter())->unique();
        $movements = DB::table('gov_inventory_movements')->whereIn('id', $movementIds)->get()->keyBy('id');
        // Historical bulk deliveries retained a hash of the movement UUID, not the UUID itself.
        // Recover only a unique hash match within this already-authorized code/office slice.
        $legacy = $deliveries->filter(fn ($d) => ! ($d->receipt_snapshot ?? null) && ! ($d->movement_id ?? null));
        if ($legacy->isNotEmpty()) {
            $candidates = DB::table('gov_inventory_movements')->whereIn('location_id', $associations->pluck('location_id')->unique())
                ->where('movement_type', 'IN')->get();
            $hashes = $candidates->groupBy(fn ($m) => hash('sha256', 'movement:'.$m->id));
            foreach ($legacy as $delivery) {
                $matches = $hashes->get($delivery->delivery_key, collect());
                if ($matches->count() === 1) {
                    $delivery->movement_id = $matches->first()->id;
                    $movements->put($matches->first()->id, $matches->first());
                }
            }
        }
        $assetIds = $associations->where('associatable_type', Asset::class)->pluck('associatable_id');
        $registrations = DB::table('gov_asset_registrations')->whereIn('asset_id', $assetIds)->get(['asset_id', 'intake_item_id'])->groupBy('asset_id');
        $itemIds = $registrations->flatten(1)->pluck('intake_item_id')->merge($deliveries->pluck('document_item_id')->filter());
        $items = DB::table('gov_document_items')->whereIn('id', $itemIds)->get()->keyBy('id');
        $documentIds = $items->pluck('document_id')->merge($movements->where('document_type', Document::class)->pluck('document_id'))->unique();
        $documents = DB::table('gov_documents')->whereIn('id', $documentIds)->where('type', 'receipt')->where('status', 'POSTED')->get(['id', 'document_number', 'location_id', 'supplier_id', 'posted_at', 'created_at'])->keyBy('id');
        // Movement-to-line fallback is safe only if the product occurs once on its receipt.
        $movementItems = DB::table('gov_document_items')->whereIn('document_id', $documentIds)->get()->groupBy('document_id');
        $rows = [];
        foreach ($associations as $a) {
            $d = $deliveries->get($a->delivery_id ?: ($a->associatable_type === TrackingDelivery::class ? $a->associatable_id : null));
            if ($d && (int) $d->tracking_code_id !== (int) $a->tracking_code_id) {
                $d = null;
            }
            $snapshot = $d && ($d->receipt_snapshot ?? null) ? json_decode($d->receipt_snapshot, true) : null;
            if ($snapshot && ((int) $snapshot['category_id'] !== (int) $a->category_id || (int) $snapshot['location_id'] !== (int) $a->location_id)) {
                $snapshot = null;
            }
            $item = $d ? $items->get($d->document_item_id ?? null) : null;
            if (! $item && $a->associatable_type === Asset::class) {
                $reg = $registrations->get($a->associatable_id, collect());
                if ($reg->count() === 1) {
                    $item = $items->get($reg->first()->intake_item_id);
                }
            }
            $movement = $movements->get($d->movement_id ?? ($a->associatable_type === InventoryMovement::class ? $a->associatable_id : null));
            if ($movement && (int) $movement->location_id !== (int) $a->location_id) {
                $movement = null;
            }
            if (! $item && $movement && $movement->document_type === Document::class) {
                $matches = $movementItems->get($movement->document_id, collect())->filter(fn ($i) => $i->product_id == $movement->stockable_id
                    && strtolower(class_basename($i->product_type)) === strtolower(class_basename($movement->stockable_type)));
                if ($matches->count() === 1) {
                    $item = $matches->first();
                }
            }
            $document = $item ? $documents->get($item->document_id) : null;
            if ($document && (int) $document->location_id !== (int) $a->location_id) {
                $document = null;
                $item = null;
            }
            $value = $snapshot && isset($snapshot['total_cost']) && $snapshot['quantity'] > 0 ? $a->quantity * $snapshot['total_cost'] / $snapshot['quantity']
                : ($document && $item && $item->unit_cost !== null ? $a->quantity * (float) $item->unit_cost : null);
            $source = $d ? 'delivery:'.$d->id : ($item && $document ? 'line:'.$item->id : 'unknown:'.$a->id);
            $key = $a->tracking_code_id.':'.$a->category_id.':'.$a->location_id.':'.$source;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'key' => $key, 'source' => $source, 'tracking_code_id' => (int) $a->tracking_code_id,
                    'category_id' => (int) $a->category_id, 'location_id' => (int) $a->location_id,
                    'date' => $document ? ($document->posted_at ?? $document->created_at) : ($snapshot['occurred_at'] ?? null),
                    'grn' => $document->document_number ?? ($snapshot['grn'] ?? null),
                    'document_id' => $document->id ?? null, 'quantity' => 0, 'value' => $value === null ? null : 0,
                    'supplier_id' => $document->supplier_id ?? ($snapshot['supplier_id'] ?? null),
                    'manufacturer_id' => $snapshot['manufacturer_id'] ?? null, 'model_id' => $snapshot['model_id'] ?? null,
                ];
            }
            $rows[$key]['quantity'] += (float) $a->quantity;
            if ($value === null) {
                $rows[$key]['value'] = null;
            } elseif ($rows[$key]['value'] !== null) {
                $rows[$key]['value'] += $value;
            }
        }

        return array_values($rows);
    }
}
