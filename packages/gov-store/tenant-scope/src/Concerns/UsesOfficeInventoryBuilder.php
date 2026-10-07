<?php

namespace GovStore\TenantScope\Concerns;

use GovStore\TenantScope\Builders\OfficeInventoryBuilder;
use Illuminate\Database\Eloquent\Builder;

trait UsesOfficeInventoryBuilder
{
    public function newEloquentBuilder($query)
    {
        return new OfficeInventoryBuilder($query);
    }

    protected function performInsert(Builder $query)
    {
        return $query->forModelInsert(fn () => parent::performInsert($query));
    }
}
