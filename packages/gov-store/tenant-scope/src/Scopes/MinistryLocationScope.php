<?php

namespace GovStore\TenantScope\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use GovStore\TenantScope\Contexts\TenantContext;

class MinistryLocationScope implements Scope
{
    /**
     * Per-process cache of "table.column" => bool. Prevents an information_schema
     * lookup on every query; Schema::hasColumn() runs at most once per table+column.
     *
     * @var array<string,bool>
     */
    protected static array $columnCache = [];

    protected static function tableHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (!array_key_exists($key, static::$columnCache)) {
            static::$columnCache[$key] = Schema::hasColumn($table, $column);
        }

        return static::$columnCache[$key];
    }

    public function apply(Builder $builder, Model $model)
    {
        // Fail safely if context is not bound (e.g. CLI operations)
        if (!app()->bound(TenantContext::class)) {
            return;
        }
        
        $context = app(TenantContext::class);

        // If context is inactive (Superadmin, Guest, or Background System Jobs), bypass checks
        if (!$context->isActive) {
            return;
        }

        $table = $model->getTable();

        // SPECIAL CASE — users table:
        // Scope by company (ministry) ONLY. Do NOT apply location scoping to users:
        // users move between/aren't always assigned a building, and a location filter
        // (or the whereRaw('1=0') fallback) would hide the authenticated user's own
        // record — breaking their session and admin user management. Always allow the
        // current user to see themselves.
        if ($model instanceof \App\Models\User) {
            if ($context->companyId && static::tableHasColumn($table, 'company_id')) {
                $ownId = auth()->id();
                $builder->where(function ($q) use ($table, $context, $ownId) {
                    $q->where($table . '.company_id', $context->companyId);
                    if ($ownId) {
                        $q->orWhere($table . '.id', $ownId);
                    }
                });
            }
            return;
        }

        // 1. Enforce Ministry (Company) Scoping if column exists
        if ($context->companyId && static::tableHasColumn($table, 'company_id')) {
            $builder->where($table . '.company_id', $context->companyId);
        }

        // 2. Enforce Physical Office (Location) Scoping if column exists
        if (static::tableHasColumn($table, 'location_id')) {
            if ($context->locationId) {
                $builder->where($table . '.location_id', $context->locationId);
            } else {
                // Prevent unassigned standard accounts from leaking records
                $builder->whereRaw('1 = 0');
            }
        }
    }
}