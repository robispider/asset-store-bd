<?php

namespace GovStore\Tracking\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InitiativeScope implements Scope
{
    /**
     * Apply the multi-tenant and project-membership isolation boundaries to the query.
     * Resolves the user's company ID dynamically from the company_user pivot table 
     * to prevent database lockouts when FMCS is switched off.
     */
    public function apply(Builder $builder, Model $model)
    {
        // 1. CLI / Console Bypass (Seeding & Migrations)
        if (app()->runningInConsole()) {
            return;
        }

        // 2. Guest Blockade
        if (!auth()->check()) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $user = auth()->user();
        $table = $model->getTable();

        // 3. Global Superuser Bypass
        if ($user->isSuperUser() || ($user->hasAccess('admin') && !$user->company_id)) {
            return;
        }

        // FIXED: Dynamically resolve the user's Ministry ID from the company_user pivot 
        // table, bypassing the NULL column on the core users table.
        $resolvedCompanyId = DB::table('company_user')
            ->where('user_id', $user->id)
            ->value('company_id');

        if (!$resolvedCompanyId) {
            $builder->whereRaw('1 = 0'); // Standalone users with no company see nothing
            return;
        }

        // 4. Company Admin (Ministry Overseer) Scope
        // Authorized to view all initiatives owned by their specific Ministry/Company
        $isCompanyAdmin = $user->hasAccess('admin') || \GovStore\Organization\Models\CompanyAdmin::where('user_id', $user->id)->exists();

        if ($isCompanyAdmin) {
            $builder->where($table . '.owner_company_id', $resolvedCompanyId);
            return;
        }

        // 5. Standard Project Team Member Scope
        // Confined strictly to their Ministry AND only projects where they are explicitly 
        // assigned to the Operation Unit team committee (HEAD, OFFICER, SUPPORT, or MONITOR)
        $builder->where($table . '.owner_company_id', $resolvedCompanyId)
            ->whereIn($table . '.id', function($query) use ($user) {
                $query->select('initiative_id')
                      ->from('gov_tracking_operation_units')
                      ->where('user_id', $user->id);
            });
    }
}