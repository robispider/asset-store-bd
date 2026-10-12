<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BasketService
{
    public function getOrCreateDraftBasket(int $userId): DraftBasket
    {
        return DraftBasket::getOrCreateForUser($userId);
    }

    public function addItem(int $userId, string $itemType, int $itemId, int $qty = 1): DraftBasket
    {
        $itemType = strtolower(class_basename($itemType));
        if ($itemType === 'asset') {
            $asset = Asset::findOrFail($itemId);
            abort_unless((int) $asset->location_id === app(TenantContext::class)->locationId, 404);
            $itemType = 'asset_model';
            $itemId = $asset->model_id;
        }
        if ($itemType === 'assetmodel') {
            $itemType = 'asset_model';
        }
        app(RequestInventory::class)->validateItem($itemType, $itemId);
        $this->validateQuantity($qty);

        return DB::transaction(function () use ($userId, $itemType, $itemId, $qty) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $basket = $this->getOrCreateDraftBasket($userId);
            $item = $basket->items()->where('requested_type', $itemType)->where('requested_id', $itemId)->first();
            $quantity = ($item ? $item->requested_qty : 0) + $qty;
            $this->validateQuantity($quantity);
            $basket->items()->updateOrCreate(['requested_type' => $itemType, 'requested_id' => $itemId], ['requested_qty' => $quantity]);

            return $basket;
        });
    }

    public function updateItemQty(int $userId, int $itemId, int $qty): DraftBasket
    {
        $this->validateQuantity($qty);

        return $this->editBasket($userId, fn ($basket) => $basket->items()->whereKey($itemId)->firstOrFail()->update(['requested_qty' => $qty]));
    }

    public function removeItem(int $userId, int $itemId): DraftBasket
    {
        return $this->editBasket($userId, fn ($basket) => $basket->items()->whereKey($itemId)->firstOrFail()->delete());
    }

    private function editBasket(int $userId, callable $callback): DraftBasket
    {
        return DB::transaction(function () use ($userId, $callback) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $basket = DraftBasket::where('user_id', $userId)->where('status', 'draft')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->lockForUpdate()->firstOrFail();
            $callback($basket);

            return $basket;
        });
    }

    private function validateQuantity(int $qty): void
    {
        Validator::make(['qty' => $qty], ['qty' => 'required|integer|min:1|max:'.RequestWorkflow::MAX_QUANTITY])->validate();
    }

    public function submitBasket(int $userId, array $metadata): array
    {
        $metadata = Validator::make($metadata, [
            'request_type' => ['required', Rule::in(RequestWorkflow::REQUEST_TYPES)],
            'purpose' => 'required|string|max:255', 'justification' => 'required|string|max:10000',
            'required_by_date' => 'nullable|date|after_or_equal:today', 'cost_center' => 'nullable|string|max:50',
            'delivery_location_id' => 'nullable|integer|exists:locations,id',
        ])->validate();
        $context = app(TenantContext::class);
        $requester = User::findOrFail($userId);
        abort_unless(app(GovAccess::class)->permitsRequest($requester, 'requests.submit'), 403);
        abort_unless($context->locationId && Location::whereKey($context->locationId)->exists(), 422,
            __('requestlabels::requests.basketservice_exception_no_office_location'));
        if (! empty($metadata['delivery_location_id'])) {
            $allowed = $context->allowedLocationIds ?? [$context->locationId];
            abort_unless($requester->isSuperUser() || in_array((int) $metadata['delivery_location_id'], $allowed), 404);
            Location::findOrFail($metadata['delivery_location_id']);
        }

        return DB::transaction(function () use ($requester, $metadata, $context) {
            app(\GovStore\Organization\Services\OfficeRequestIntake::class)->assertOpen([
                $context->locationId, (int) ($metadata['delivery_location_id'] ?? $context->locationId),
            ], true);
            User::whereKey($requester->id)->lockForUpdate()->firstOrFail();
            $basket = DraftBasket::where('user_id', $requester->id)->where('status', 'draft')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->lockForUpdate()->firstOrFail();
            $items = $basket->items()->orderBy('requested_type')->orderBy('requested_id')->get();
            abort_if($items->isEmpty(), 422, __('requestlabels::requests.basketservice_exception_empty_basket'));
            $groups = [];
            foreach ($items as $item) {
                $this->validateQuantity($item->requested_qty);
                app(RequestInventory::class)->validateItem($item->requested_type, $item->requested_id);
                $policy = app(PolicyService::class)->resolvePolicy($item->requested_type, $item->requested_id, $item->requested_qty);
                $groups[$policy][] = $item;
            }
            $submitted = [];
            foreach ($groups as $policy => $lines) {
                $automatic = $policy === 'AUTO_APPROVE';
                $request = ServiceRequest::create($metadata + [
                    'office_id' => $context->locationId, 'requested_by' => $requester->id,
                    'resolved_policy' => $policy, 'approval_status' => $automatic ? 'approved' : 'pending_primary',
                    'fulfillment_status' => 'unstarted', 'submitted_at' => now(), 'approved_at' => $automatic ? now() : null,
                ]);
                foreach ($lines as $line) {
                    $request->items()->create([
                        'requested_type' => $line->requested_type, 'requested_id' => $line->requested_id,
                        'requested_qty' => $line->requested_qty, 'approved_qty' => $automatic ? $line->requested_qty : 0,
                        'line_approval_status' => $automatic ? 'approved' : 'pending',
                        'line_fulfillment_status' => $automatic ? 'waiting' : 'unstarted',
                    ]);
                }
                if ($automatic) {
                    app(RequestInventory::class)->reserve($request);
                } else {
                    app(ApprovalRouting::class)->assign($request);
                }
                RequestEvent::create(['request_id' => $request->id, 'user_id' => $requester->id,
                    'event_type' => $automatic ? 'auto_approved' : 'submitted', 'details' => ['policy' => $policy, 'office_id' => $context->locationId,
                        'stage' => $request->approval_status, 'routing_reason' => $request->assigned_approver_id ? 'independent_approver' : 'office_admin_cover_required']]);
                $submitted[] = $request;
            }
            $basket->items()->delete();
            $basket->delete();

            return $submitted;
        }, 3);
    }
}
