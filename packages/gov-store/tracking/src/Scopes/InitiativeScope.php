<?php

namespace GovStore\Tracking\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Added Import

class InitiativeScope implements Scope
{
    /**
     * Apply the multi-tenant and project-membership isolation boundaries to the query.
     * Diagnostic and prefix-proof enabled.
     */
    public function apply(Builder $builder, Model $model)
    {
        try {
            // 1. CLI / Console Bypass
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

            // Resolve company ID dynamically from the company_user pivot table
            $resolvedCompanyId = DB::table('company_user')
                ->where('user_id', $user->id)
                ->value('company_id');

            if (!$resolvedCompanyId) {
                $builder->whereRaw('1 = 0');
                return;
            }

            // 4. Company Admin (Ministry Overseer) Scope
            $isCompanyAdmin = $user->hasAccess('admin') || \GovStore\Organization\Models\CompanyAdmin::where('user_id', $user->id)->exists();

            if ($isCompanyAdmin) {
                $builder->where($table . '.owner_company_id', $resolvedCompanyId);
                return;
            }

            // FIXED (Prefix-Proof): Resolve the table name dynamically using the model 
            // instance to prevent any database prefix or suffix mismatches inside raw subqueries.
            $opUnitTable = (new \GovStore\Tracking\Models\OperationUnit)->getTable();

            // 5. Standard Project Team Member Scope
            $builder->where($table . '.owner_company_id', $resolvedCompanyId)
                ->whereIn($table . '.id', function($query) use ($user, $opUnitTable) {
                    $query->select('initiative_id')
                          ->from($opUnitTable)
                          ->where('user_id', $user->id);
                });

        } catch (\Exception $e) {
            // Write the error cleanly to laravel.log on failure
            Log::error('GovStore: InitiativeScope Failure: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            
            // Fallback to a secure block to prevent unauthorized data leaks on system errors
            $builder->whereRaw('1 = 0');
        }
    }
}