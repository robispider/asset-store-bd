<?php

namespace GovStore\StoreOperations\Services;

use GovStore\TenantScope\Builders\OfficeInventoryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Also protect native Eloquent bulk writes, which do not dispatch model observers. */
class StoreInventoryBuilder extends OfficeInventoryBuilder
{
    public function forModelInsert(\Closure $callback): mixed
    {
        app(LedgerStockGuard::class)->saving($this->getModel());
        return parent::forModelInsert($callback);
    }
    private function guardStockMutation(array $values = [], bool $deleting = false): void
    {
        $columns = array_map(fn ($column) => last(explode('.', $column)), array_keys($values));
        if ((! $deleting && ! array_intersect($columns, ['qty', 'location_id', 'company_id', 'deleted_at']))
            || ! Schema::hasTable('gov_store_ledger_openings')) {
            return;
        }
        $opened = DB::table('gov_store_ledger_openings')->pluck('location_id')->all();
        if (! $opened) return;
        if (isset($values['location_id']) && in_array((int) $values['location_id'], array_map('intval', $opened), true)) {
            throw ValidationException::withMessages(['qty' => __('storeops::storeops.ledger_managed_stock')]);
        }
        $candidate = clone $this;
        // Group caller OR conditions before applying the ownership predicate.
        if ($candidate->getQuery()->wheres) {
            $query = $candidate->getQuery();
            $nested = $query->forNestedWhere();
            $nested->wheres = $query->wheres;
            $nested->setBindings($query->getRawBindings()['where'], 'where');
            $query->wheres = [];
            $query->setBindings([], 'where');
            $query->addNestedWhereQuery($nested);
        }
        if ($candidate->whereIn($this->getModel()->qualifyColumn('location_id'), $opened)->exists()) {
            throw ValidationException::withMessages(['qty' => __('storeops::storeops.ledger_managed_stock')]);
        }
    }

    public function update(array $values)
    {
        $this->guardStockMutation($values);
        return parent::update($values);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->guardStockMutation([$column => $amount] + $extra);
        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->guardStockMutation([$column => $amount] + $extra);
        return parent::decrement($column, $amount, $extra);
    }

    public function delete()
    {
        $this->guardStockMutation([], true);
        return parent::delete();
    }

    public function forceDelete()
    {
        $this->guardStockMutation([], true);
        return parent::forceDelete();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if ($values && ! is_array(reset($values))) $values = [$values];
        foreach ($values as $row) $this->guardStockMutation($row);
        return parent::upsert($values, $uniqueBy, $update);
    }
}
