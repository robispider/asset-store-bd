<?php

namespace GovStore\StoreOperations\Services;

use App\Models\Accessory;
use App\Models\Component;
use App\Models\Consumable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Ledger ownership is an inventory invariant, including for native administrators. */
class LedgerStockGuard
{
    public function manages(?Model $item): bool
    {
        return $item && in_array(get_class($item), [Consumable::class, Accessory::class, Component::class], true)
            && $item->location_id && Schema::hasTable('gov_store_ledger_openings')
            && DB::table('gov_store_ledger_openings')->where('location_id', $item->location_id)->exists();
    }

    public function assertNativeMovementAllowed(?Model $item): void
    {
        if ($this->manages($item)) {
            throw ValidationException::withMessages(['qty' => __('storeops::storeops.ledger_managed_stock')]);
        }
    }

    public function saving(Model $item): void
    {
        $originalOffice = $item->getRawOriginal('location_id');
        $wasManaged = $originalOffice && Schema::hasTable('gov_store_ledger_openings')
            && DB::table('gov_store_ledger_openings')->where('location_id', $originalOffice)->exists();
        if (($wasManaged || $this->manages($item))
            && ((! $item->exists && (int) $item->qty !== 0)
                || ($item->exists && $item->isDirty(['qty', 'location_id', 'company_id', 'deleted_at'])))) {
            throw ValidationException::withMessages(['qty' => __('storeops::storeops.ledger_managed_stock')]);
        }
    }

    public function deleting(Model $item): void
    {
        $this->assertNativeMovementAllowed($item);
    }

    public function restoring(Model $item): void
    {
        $this->assertNativeMovementAllowed($item);
    }
}
