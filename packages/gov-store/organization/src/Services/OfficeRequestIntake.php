<?php

namespace GovStore\Organization\Services;

use GovStore\Organization\Models\LocationProfile;

class OfficeRequestIntake
{
    /** Caller locks profiles inside submission transactions to serialize suspension. */
    public function assertOpen(array $officeIds, bool $lock = false): void
    {
        $query = LocationProfile::whereIn('location_id', array_unique($officeIds))->orderBy('location_id');
        if ($lock) {
            $query->lockForUpdate();
        }
        foreach ($query->get() as $profile) {
            abort_unless(in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true), 409,
                __('organization_labels::orglabel.lifecycle_intake_paused'));
        }
    }
}
