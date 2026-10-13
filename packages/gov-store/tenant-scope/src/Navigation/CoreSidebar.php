<?php

namespace GovStore\TenantScope\Navigation;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\PredefinedKit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Mirrors the gates of Snipe-IT's own sidebar sections (layouts/default) between the work
 * zone and the admin zone, so the "Asset management" header only shows above real entries.
 */
final class CoreSidebar
{
    public const GATES = [
        ['index', Asset::class],
        ['view', License::class],
        ['index', Accessory::class],
        ['view', Consumable::class],
        ['view', Component::class],
        ['view', PredefinedKit::class],
        ['view', User::class],
        ['import', null],
        ['backend.interact', null],
        ['reports.view', null],
        ['viewRequestable', Asset::class],
    ];

    public static function visibleTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        foreach (self::GATES as [$ability, $model]) {
            if (Gate::forUser($user)->allows($ability, $model ? [$model] : [])) {
                return true;
            }
        }

        return false;
    }
}
