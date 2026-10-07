<?php

namespace GovStore\TenantScope\Builders;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;
use GovStore\TenantScope\Policies\AssetBoundaryPolicy;
use GovStore\TenantScope\Services\SchemaKnowledge;
use Illuminate\Database\Eloquent\Builder;

/** Broader SELECT scopes must never broaden Eloquent mass mutations. */
class OfficeInventoryBuilder extends Builder
{
    private bool $modelInsert = false;

    /** Internal Model::performInsert path; retains validation and creating observers. */
    public function forModelInsert(\Closure $callback): mixed
    {
        $this->modelInsert = true;
        try {
            return $callback();
        } finally {
            $this->modelInsert = false;
        }
    }

    private function constrainMutation(array $values = []): void
    {
        $context = app(TenantContext::class);
        if (! $context->isActive) {
            return; // Explicit console/system maintenance, outside an actor context.
        }
        if (! $context->canUseInventoryOffice()) {
            throw new TenantBoundaryException(__('tenantops::ops.exception_out_of_bounds'), 'OUT_OF_BOUNDS', 403);
        }
        if ($this->model->exists && ! app(AssetBoundaryPolicy::class)->canMutate($this->model, $context)) {
            throw new TenantBoundaryException(__('tenantops::ops.exception_out_of_bounds'), 'OUT_OF_BOUNDS', 403);
        }
        foreach ($values as $column => $value) {
            $column = last(explode('.', $column));
            $id = match ($column) {
                'company_id' => $context->companyId, 'location_id' => $context->locationId, default => null
            };
            if ($id !== null && (! is_numeric($value) || (int) $value !== $id)) {
                throw new TenantBoundaryException(__('tenantops::ops.exception_out_of_bounds'), 'OUT_OF_BOUNDS', 403);
            }
        }
        // Scope predicates must apply to the entire caller expression, including
        // OR branches and forceDelete(), which skips Eloquent global scopes.
        $query = $this->getQuery();
        if ($query->wheres) {
            $nested = $query->forNestedWhere();
            $nested->wheres = $query->wheres;
            $nested->setBindings($query->getRawBindings()['where'], 'where');
            $query->wheres = [];
            $query->setBindings([], 'where');
            $query->addNestedWhereQuery($nested);
        }
        $schema = app(SchemaKnowledge::class);
        foreach (['company_id' => $context->companyId, 'location_id' => $context->locationId] as $column => $id) {
            if (! $schema->hasColumn($this->model, $column)) {
                continue;
            }
            if (array_key_exists($column, $values) && (! is_numeric($values[$column]) || (int) $values[$column] !== $id)) {
                throw new TenantBoundaryException(__('tenantops::ops.exception_out_of_bounds'), 'OUT_OF_BOUNDS', 403);
            }
            $this->where($this->model->qualifyColumn($column), $id);
        }
    }

    public function update(array $values)
    {
        $this->constrainMutation($values);

        return parent::update($values);
    }

    public function delete()
    {
        $this->constrainMutation();

        return parent::delete();
    }

    public function forceDelete()
    {
        $this->constrainMutation();

        return parent::forceDelete();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->constrainMutation($extra);
        $this->rejectOwnershipCounter($column);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->constrainMutation($extra);
        $this->rejectOwnershipCounter($column);

        return parent::decrement($column, $amount, $extra);
    }

    private function rejectOwnershipCounter($column): void
    {
        if (app(TenantContext::class)->isActive && (! is_string($column) || in_array(last(explode('.', $column)), ['company_id', 'location_id']))) {
            throw new TenantBoundaryException(__('tenantops::ops.exception_out_of_bounds'), 'OUT_OF_BOUNDS', 403);
        }
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (app(TenantContext::class)->isActive) {
            throw new TenantBoundaryException(__('tenantops::ops.exception_out_of_bounds'), 'OUT_OF_BOUNDS', 403);
        }

        return parent::upsert($values, $uniqueBy, $update);
    }

    public function __call($method, $parameters)
    {
        if (app(TenantContext::class)->isActive && in_array(strtolower($method),
            ['insert', 'insertgetid', 'insertorignore', 'insertusing', 'insertorignoreusing'])) {
            if (! $this->modelInsert || ! app(AssetBoundaryPolicy::class)
                ->canMutate($this->model, app(TenantContext::class))) {
                // Direct inserts skip validation and tenant observers. Model
                // inserts still receive an ownership check even with quiet saves.
                throw new TenantBoundaryException(__('tenantops::ops.exception_out_of_bounds'), 'OUT_OF_BOUNDS', 403);
            }
        }

        return parent::__call($method, $parameters);
    }
}
