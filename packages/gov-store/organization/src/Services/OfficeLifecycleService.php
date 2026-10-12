<?php

namespace GovStore\Organization\Services;

use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OfficeLifecycleService
{
    public const ACTIVE = ['provisioned', 'configured', 'operational'];

    public function transition(int $officeId, int $actorId, array $data): void
    {
        if (isset($data['reason']) && is_string($data['reason'])) {
            $data['reason'] = trim($data['reason']);
        }
        Validator::make($data, [
            'action' => 'required|in:suspend,resume,relocate,close,merge',
            'expected_status' => 'required|string',
            'expected_geo_area_id' => 'required|integer|min:1',
            'reason' => 'required|string|min:5|max:1000',
            'confirmation' => 'required|in:CHANGE',
            'geo_area_id' => 'required_if:action,relocate|nullable|integer|min:1',
        ])->validate();

        DB::transaction(function () use ($officeId, $actorId, $data) {
            $guard = app(OfficeAdministration::class);
            $actor = $guard->actor($actorId);
            $location = Location::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($officeId);
            $profile = LocationProfile::where('location_id', $officeId)->lockForUpdate()->firstOrFail();
            $guard->office($actor, $officeId, $data['action'] === 'relocate');
            abort_unless($profile->lifecycle_status === $data['expected_status'], 409);
            abort_unless((int) $profile->geo_area_id === (int) $data['expected_geo_area_id'], 409);
            $before = ['status' => $profile->lifecycle_status, 'geo_area_id' => $profile->geo_area_id];

            // Terminal transitions require OM-2 and every consumer's clearance.
            // An empty or incomplete rule registry must never authorize closure.
            if (in_array($data['action'], ['close', 'merge'], true)) {
                abort(409, __('organization_labels::orglabel.lifecycle_clearance_pending'));
            }
            if ($data['action'] === 'suspend') {
                abort_unless(in_array($profile->lifecycle_status, self::ACTIVE, true), 409);
                $profile->lifecycle_status = 'suspended';
            } elseif ($data['action'] === 'resume') {
                abort_unless($profile->lifecycle_status === 'suspended', 409);
                $profile->lifecycle_status = $profile->office_admin_id ? 'configured' : 'provisioned';
            } else {
                abort_unless(in_array($profile->lifecycle_status, [...self::ACTIVE, 'suspended'], true), 409);
                $geo = $guard->geography($actor, (int) $data['geo_area_id']);
                abort_if((int) $profile->geo_area_id === (int) $geo->getKey(), 409);
                $names = app(\GovStore\GeoAreas\Services\GeoAreaService::class)->resolveParentNames($geo->hid ?? '');
                $location->city = $names['city'];
                $location->state = $names['state'];
                abort_unless($location->save(), 422);
                $profile->geo_area_id = $geo->getKey();
                $profile->geo_area_verified_at = null;
                $profile->geo_area_verified_by = null;
            }
            $profile->save();
            if ($data['action'] === 'resume') {
                app(OfficeReadinessService::class)->evaluateAndTransition($officeId, $actorId);
                $profile->refresh();
            }
            OrganizationActivityLog::create([
                'location_id' => $officeId, 'performed_by' => $actorId,
                'event_type' => 'office_'.$data['action'],
                'details' => ['reason' => $data['reason'], 'before' => $before,
                    'after' => ['status' => $profile->lifecycle_status, 'geo_area_id' => $profile->geo_area_id]],
            ]);
        });
    }
}
