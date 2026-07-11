<?php

namespace GovStore\Organization\Observers;

use GovStore\Organization\Models\IctJurisdiction;
use App\Models\Group;

class IctJurisdictionObserver
{
    /**
     * Handle the IctJurisdiction "created" event.
     */
    public function created(IctJurisdiction $jurisdiction)
    {
        $user = $jurisdiction->user;
        if (!$user) {
            return;
        }

        // Find the seeded capability group
        $group = Group::where('name', 'ICT Operations')->first();

        if ($group) {
            // Attach the Snipe-IT capability group natively
            $user->groups()->attach($group->id);
        }
    }

    /**
     * Handle the IctJurisdiction "deleted" event.
     */
    public function deleted(IctJurisdiction $jurisdiction)
    {
        $user = $jurisdiction->user;
        if (!$user) {
            return;
        }

        $group = Group::where('name', 'ICT Operations')->first();

        if ($group) {
            // Detach the capability group natively
            $user->groups()->detach($group->id);
        }
    }
}