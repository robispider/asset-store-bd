<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\Asset;
use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;
use Illuminate\Support\Facades\DB;

class NoActiveAssetsRule implements IClearanceRule
{
    public function getName(): string
    {
        return __('office_membership::member.rule_physical_inventory_name');
    }

    public function check(User $user, int $locationId): ClearanceResult
    {
        // Raw queries deliberately bypass the actor's working-office scopes. Every query
        // is bounded by the subject user and the releasing inventory office instead.
        // Include assets mounted in another asset carried by this employee.
        $lock = fn ($q) => DB::transactionLevel() > 0 ? $q->lockForUpdate() : $q;
        $count = fn ($q) => DB::transactionLevel() > 0 ? $q->lockForUpdate()->get()->count() : $q->count();
        $held = $lock(DB::table('assets')->where(fn ($q) => $q->where('assigned_type', User::class)->orWhereNull('assigned_type'))
            ->where('assigned_to', $user->id))->pluck('id')->all();
        $frontier = $held;
        while ($frontier) {
            $children = $lock(DB::table('assets')->where('assigned_type', Asset::class)->whereIn('assigned_to', $frontier))->pluck('id')->all();
            $frontier = array_values(array_diff($children, $held));
            $held = array_unique(array_merge($held, $frontier));
        }
        $counts = [];
        // Deleted checked-out stock is still an unresolved custody obligation.
        $counts['assets'] = $count(DB::table('assets')->whereIn('id', $held)
            ->where(fn ($q) => $q->where('location_id', $locationId)->orWhere('rtd_location_id', $locationId)));
        $counts['accessories'] = $count(DB::table('accessories_checkout as checkout')->join('accessories as stock', 'stock.id', '=', 'checkout.accessory_id')
            ->where('stock.location_id', $locationId)->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('checkout.assigned_type', User::class)->where('checkout.assigned_to', $user->id))
            ->orWhere(fn ($q) => $q->where('checkout.assigned_type', Asset::class)->whereIn('checkout.assigned_to', $held))));
        $counts['components'] = $count(DB::table('components_assets as checkout')->join('components as stock', 'stock.id', '=', 'checkout.component_id')
            ->where('stock.location_id', $locationId)->whereIn('checkout.asset_id', $held)->where('checkout.assigned_qty', '>', 0));
        $company = DB::table('locations')->where('id', $locationId)->value('company_id');
        // Native licences have no office ownership column. Unattributable user seats
        // conservatively block releases within their company; do not invent office scope.
        $counts['licences'] = $count(DB::table('license_seats as seats')->join('licenses as stock', 'stock.id', '=', 'seats.license_id')
            ->whereNull('seats.deleted_at')->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('seats.assigned_to', $user->id)
                ->where(fn ($q) => $q->where('stock.company_id', $company)->orWhereNull('stock.company_id')))
            ->orWhereIn('seats.asset_id', DB::table('assets')->select('id')->whereIn('id', $held)
                ->where(fn ($q) => $q->where('location_id', $locationId)->orWhere('rtd_location_id', $locationId)))));
        // Native consumables_users is consumption history, not a returnable custody
        // balance. Keep the history; consumers register unresolved return obligations.
        $consumed = DB::table('consumables_users as checkout')->join('consumables as stock', 'stock.id', '=', 'checkout.consumable_id')
            ->where('stock.location_id', $locationId)->where('checkout.assigned_to', $user->id)->count();
        if (array_sum($counts)) {
            return new ClearanceResult(false, __('office_membership::member.holdings_blocked', $counts));
        }

        return new ClearanceResult(true, __('office_membership::member.holdings_clear', ['count' => $consumed]));
    }
}
