<?php

namespace GovStore\OfficeMembership\Observers;

use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Services\MembershipNotices;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\Schema;

class MembershipActivityLogObserver
{
    /**
     * Handle the OfficeMembership "created" event.
     */
    public function created(OfficeMembership $membership)
    {
        $this->log($membership, 'membership_granted', __('office_membership::member.log_membership_granted'));
    }

    /**
     * Handle the OfficeMembership "updated" event.
     */
    public function updated(OfficeMembership $membership)
    {
        if ($membership->isDirty('status')) {
            $this->log(
                $membership,
                'status_changed',
                __('office_membership::member.log_status_changed', ['old' => $membership->getOriginal('status'), 'new' => $membership->status])
            );
        }
    }

    /**
     * Handle the OfficeMembership "deleted" event.
     */
    public function deleted(OfficeMembership $membership)
    {
        $this->log($membership, 'membership_revoked', __('office_membership::member.log_membership_revoked'));
    }

    /**
     * Helper to write records to the organization activity logging table.
     */
    protected function log(OfficeMembership $membership, string $eventType, string $message)
    {
        if (Schema::hasTable('gov_membership_notices')) {
            $admin = LocationProfile::where('location_id', $membership->location_id)->value('office_admin_id');
            app(MembershipNotices::class)->record('membership_'.$membership->status,
                $membership->location_id, [$membership->user_id, $admin], ['membership_id' => $membership->id]);
        }
        OrganizationActivityLog::create([
            'location_id' => $membership->location_id,
            'performed_by' => auth()->id() ?: 1, // Default to system user ID 1 if modified via CLI commands
            'event_type' => $eventType,
            'details' => [
                'target_user_id' => $membership->user_id,
                'message' => $message,
            ],
        ]);
    }
}
