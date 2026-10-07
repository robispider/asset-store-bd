<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Asset;
use App\Models\User;
use GovStore\CustomRequests\Factories\RequestableFactory;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FulfillmentService
{
    public function __construct(protected StockIssuingServiceInterface $stockIssuer) {}

    private function lockedOpenRequest(ServiceRequest $request, User $actor): ServiceRequest
    {
        $request = ServiceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
        app(RequestAccess::class)->check($request, $actor, 'requests.fulfill');
        // The stock issuer uses the working context, including for a superuser.
        abort_unless((int) $request->office_id === app(TenantContext::class)->locationId, 404);
        abort_unless(in_array($request->approval_status, RequestWorkflow::APPROVED)
            && in_array($request->fulfillment_status, RequestWorkflow::OPEN_FULFILLMENT), 409);

        return $request;
    }

    public function issueItems(ServiceRequest $request, User $storekeeper, array $issuePayload, array $substitutions = [], ?string $notes = null): ServiceRequest
    {
        Validator::make(['notes' => $notes], ['notes' => 'nullable|string|max:2000'])->validate();

        return DB::transaction(function () use ($request, $storekeeper, $issuePayload, $substitutions, $notes) {
            $request = $this->lockedOpenRequest($request, $storekeeper);
            $lines = $request->items()->orderBy('requested_type')->orderBy('requested_id')->get();
            abort_if(array_diff(array_keys($issuePayload), $lines->pluck('id')->all())
                || array_diff(array_keys($substitutions), $lines->pluck('id')->all()), 422);
            $ledger = [];
            $serials = [];
            $inventory = app(RequestInventory::class);
            foreach ($lines as $line) {
                $payload = $issuePayload[$line->id] ?? null;
                if ($line->line_approval_status !== 'approved') {
                    abort_if(! empty($payload) || ! empty($substitutions[$line->id]), 422);

                    continue;
                }
                $type = $line->fulfilled_type ?: $line->requested_type;
                $id = $line->fulfilled_id ?: $line->requested_id;
                $remaining = $line->approved_qty - $line->issued_qty;
                if ($type === 'asset_model') {
                    Validator::make(['ids' => $payload ?? []], ['ids' => 'array', 'ids.*' => 'nullable|integer|min:1'])->validate();
                    $assetIds = array_values(array_filter($payload ?? []));
                    abort_if(count($assetIds) !== count(array_unique($assetIds)), 422);
                    sort($assetIds, SORT_NUMERIC);
                    $qty = count($assetIds);
                } else {
                    Validator::make(['qty' => $payload ?? 0], ['qty' => 'integer|min:0|max:'.RequestWorkflow::MAX_QUANTITY])->validate();
                    $qty = (int) ($payload ?? 0);
                }
                abort_if($qty > $remaining, 422);
                if ($qty === 0) {
                    abort_if(! empty($substitutions[$line->id]) && (int) $substitutions[$line->id] !== (int) $id, 422);

                    continue;
                }
                $model = $inventory->validateItem($type, $id, true);
                $subId = ! empty($substitutions[$line->id]) ? $substitutions[$line->id] : $id;
                Validator::make(['id' => $subId], ['id' => 'required|integer|min:1'])->validate();
                if ((int) $subId !== (int) $id) {
                    // No change of stock identity after the first issue. Higher-value substitutes need a new approval.
                    abort_if($line->issued_qty > 0, 409);
                    $alternate = $inventory->validateItem($type, (int) $subId, true);
                    abort_unless($model->category_id && (int) $model->category_id === (int) $alternate->category_id, 422);
                    abort_if($model->purchase_cost === null || $alternate->purchase_cost === null
                        || (float) $alternate->purchase_cost > (float) $model->purchase_cost, 422);
                    abort_if($remaining > $inventory->available($type, $alternate, $request->office_id, $line->id), 409);
                    $line->update(['fulfilled_type' => $type, 'fulfilled_id' => $alternate->id, 'reserved_qty' => $remaining]);
                    RequestEvent::create(['request_id' => $request->id, 'user_id' => $storekeeper->id,
                        'event_type' => 'item_substituted', 'details' => ['original_id' => $model->id, 'original' => $model->name,
                            'substituted_id' => $alternate->id, 'substituted_with' => $alternate->name]]);
                    $model = $alternate;
                    $id = $alternate->id;
                }
                abort_if($qty > $inventory->available($type, $model, $request->office_id, $line->id), 409);
                if ($type === 'asset_model') {
                    foreach ($assetIds as $assetId) {
                        $asset = Asset::whereKey($assetId)->lockForUpdate()->firstOrFail();
                        abort_unless((int) $asset->location_id === (int) $request->office_id, 404);
                        $company = app(TenantContext::class)->companyId;
                        abort_if($company && $asset->company_id && (int) $asset->company_id !== $company, 404);
                        // Use a current locking read for status after any wait on the asset row.
                        $asset->setRelation('status', $asset->status()->lockForUpdate()->first());
                        abort_unless((int) $asset->model_id === (int) $id && $asset->requestable && $asset->availableForCheckout(), 422);
                        if (! $asset->checkOut($request->requester, $storekeeper, now(), null,
                            'GovStore '.$request->request_number, $asset->name, $request->delivery_location_id ?: $request->office_id)) {
                            // Native validation must remain authoritative. Explain the failure without exposing
                            // custom-field values, database column names or an internal exception.
                            $fields = $asset->model?->fieldset?->fields
                                ->whereIn('db_column', array_keys($asset->getErrors()->messages()))
                                ->where('field_encrypted', false)->pluck('name')->implode(', ');
                            throw ValidationException::withMessages(['issue.'.$line->id => __($fields ? 'requestlabels::requests.asset_checkout_invalid_fields' : 'requestlabels::requests.asset_checkout_invalid',
                                ['asset' => $asset->asset_tag, 'fields' => $fields])]);
                        }
                        $serials[] = $asset;
                    }
                    $this->recordIssue($request, $line, $storekeeper, $qty, $model->name, ['asset_ids' => $assetIds, 'notes' => $notes]);
                } else {
                    $ledger[] = ['type' => $type, 'id' => $id, 'qty' => $qty, 'line_id' => $line->id];
                }
            }
            abort_unless($ledger || $serials, 422);
            if ($ledger) {
                $documents = $this->stockIssuer->issueSystemStock($ledger, $request->requested_by, $request);
                foreach ($ledger as $payload) {
                    abort_unless(isset($documents[$payload['line_id']]), 409);
                    $line = $request->items()->findOrFail($payload['line_id']);
                    $adapter = RequestableFactory::make($payload['type'], $payload['id']);
                    // The immutable ledger owns stock; adapters write history only, never checkout pivots.
                    $adapter->checkout($request->requester, $storekeeper, $payload['qty'], 'Logged in Goods Issue: '.$documents[$line->id]);
                    $this->recordIssue($request, $line, $storekeeper, $payload['qty'], $adapter->getDisplayName(), ['goods_issue' => $documents[$line->id], 'notes' => $notes]);
                }
            }
            if ($serials) {
                $document = GoodsIssue::create([
                    'issue_no' => 'GI-SR-'.Str::uuid(), 'issue_type' => 'SYSTEM_FULFILLMENT',
                    'issued_to_id' => $request->requested_by, 'reference_type' => ServiceRequest::class,
                    'reference_id' => $request->id, 'status' => 'SUBMITTED',
                    'company_id' => app(TenantContext::class)->companyId, 'location_id' => $request->office_id, 'created_by' => $storekeeper->id,
                ]);
                foreach ($serials as $asset) {
                    $document->items()->create(['stockable_type' => Asset::class, 'stockable_id' => $asset->id, 'quantity' => 1]);
                }
            }
            $approved = $request->items()->where('line_approval_status', 'approved');
            $complete = (clone $approved)->exists() && ! (clone $approved)->whereColumn('issued_qty', '<', 'approved_qty')->exists();
            if ($complete) {
                $request->update(['fulfillment_status' => 'issued', 'closed_at' => now()]);
                RequestEvent::create(['request_id' => $request->id, 'user_id' => $storekeeper->id, 'event_type' => 'issued', 'details' => []]);
            } elseif ($request->items()->where('issued_qty', '>', 0)->exists()) {
                $request->update(['fulfillment_status' => 'partially_issued']);
            }

            return $request;
        }, 3);
    }

    private function recordIssue($request, $line, $actor, int $qty, string $name, array $details): void
    {
        $issued = $line->issued_qty + $qty;
        $line->update(['issued_qty' => $issued, 'reserved_qty' => max(0, $line->reserved_qty - $qty),
            'line_fulfillment_status' => $issued >= $line->approved_qty ? 'issued' : 'partially_issued']);
        RequestEvent::create(['request_id' => $request->id, 'user_id' => $actor->id, 'event_type' => 'item_issued',
            'details' => $details + ['item' => $name, 'issued_qty' => $qty, 'total_issued' => $issued, 'approved_qty' => $line->approved_qty]]);
    }

    public function forceClose(ServiceRequest $request, User $storekeeper, ?string $reason = null): ServiceRequest
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:2000'])->validate();

        return DB::transaction(function () use ($request, $storekeeper, $reason) {
            $request = $this->lockedOpenRequest($request, $storekeeper);
            $request->items()->where('line_approval_status', 'approved')->where('line_fulfillment_status', '!=', 'issued')
                ->update(['line_fulfillment_status' => 'cancelled', 'reserved_qty' => 0]);
            $request->update(['fulfillment_status' => 'closed', 'closed_at' => now()]);
            RequestEvent::create(['request_id' => $request->id, 'user_id' => $storekeeper->id,
                'event_type' => 'closed', 'details' => ['reason' => $reason]]);

            return $request;
        });
    }
}
