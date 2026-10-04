<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Consumable;
use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class RequestInventory
{
    /** Nullable company stock is shared; a different assigned company is never in scope. */
    public function scopeCompany($query, string $column = 'company_id')
    {
        $company = app(TenantContext::class)->companyId;

        return $query->when($company, fn ($q) => $q->where(fn ($q) => $q->whereNull($column)->orWhere($column, $company)));
    }

    public function validateItem(string $type, int $id, bool $lock = false)
    {
        abort_unless(in_array($type, RequestWorkflow::TYPES, true), 422);
        $class = ['asset_model' => AssetModel::class, 'accessory' => Accessory::class, 'consumable' => Consumable::class][$type];
        $query = $class::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        $model = $query->findOrFail($id);
        $office = app(TenantContext::class)->locationId;
        abort_unless($office, 422);
        if ($type !== 'asset_model') {
            abort_unless((int) $model->location_id === $office, 404);
            $company = app(TenantContext::class)->companyId;
            abort_if($company && $model->company_id && (int) $model->company_id !== $company, 404);
        }

        return $model;
    }

    public function available(string $type, $model, int $office, ?int $excludeLine = null): int
    {
        $locking = DB::transactionLevel() > 0;
        if ($type === 'asset_model') {
            $assets = Asset::where('model_id', $model->id)->where('location_id', $office)->whereNull('assigned_to')
                ->where('requestable', 1)->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0));
            $this->scopeCompany($assets);
            $stock = $locking ? $assets->lockForUpdate()->get(['assets.id'])->count() : $assets->count();
        } else {
            $pivot = $type === 'accessory' ? 'accessories_checkout' : 'consumables_users';
            $checkouts = DB::table($pivot)->where($type.'_id', $model->id);
            $count = $locking ? $checkouts->lockForUpdate()->get(['id'])->count() : $checkouts->count();
            $stock = $model->qty - $count;
        }
        // Locking reads see the latest committed reservations under MySQL REPEATABLE READ,
        // even when this transaction established a snapshot before waiting on the stock row.
        $reserved = RequestItem::join('custom_service_requests as requests', 'requests.id', '=', 'custom_service_request_items.request_id')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('fulfilled_id')->where('requested_type', $type)->where('requested_id', $model->id))
                ->orWhere(fn ($q) => $q->where('fulfilled_type', $type)->where('fulfilled_id', $model->id)))
            ->where('requests.office_id', $office)->whereNull('requests.deleted_at')
            ->whereIn('requests.approval_status', RequestWorkflow::APPROVED)
            ->whereIn('requests.fulfillment_status', RequestWorkflow::OPEN_FULFILLMENT);
        if ($excludeLine) {
            $reserved->where('custom_service_request_items.id', '!=', $excludeLine);
        }
        $held = $locking
            ? $reserved->lockForUpdate()->get(['custom_service_request_items.reserved_qty'])->sum('reserved_qty')
            : $reserved->sum('reserved_qty');

        return max(0, $stock - (int) $held);
    }

    /** Call inside the locked transaction. Item locks serialize competing reservations. */
    public function reserve(Request $request): void
    {
        foreach ($request->items()->where('line_approval_status', 'approved')->orderBy('requested_type')->orderBy('requested_id')->get() as $line) {
            $model = $this->validateItem($line->requested_type, $line->requested_id, true);
            $remaining = $line->approved_qty - $line->issued_qty;
            abort_if($remaining > $this->available($line->requested_type, $model, $request->office_id, $line->id), 409,
                __('requestlabels::requests.insufficient_available_stock'));
            $line->update(['reserved_qty' => $remaining]);
        }
    }
}
