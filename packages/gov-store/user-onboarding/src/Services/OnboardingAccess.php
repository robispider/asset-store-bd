<?php

namespace GovStore\UserOnboarding\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Services\OfficeLifecycleService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\UserOnboarding\Models\UserOnboarding;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OnboardingAccess
{
    private function current($query)
    {
        return DB::transactionLevel() ? $query->lockForUpdate() : $query;
    }

    public function actor(): User
    {
        $id = auth()->id();
        abort_unless($id, 403);
        $actor = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        abort_unless($actor->activated && app(GovAccess::class)->decide($actor, 'onboarding.manage')->allowed, 403);

        return $actor;
    }

    private function geoIds(int $user): array
    {
        $origins = $this->current(DB::table('gov_ict_jurisdictions as j')->join('gov_geo_areas as g', 'g.GeoAreaId', '=', 'j.geo_area_id')->where('j.user_id', $user))->pluck('g.hid')->filter()->all();
        if (! $origins) {
            return [];
        }

        return DB::table('gov_geo_areas')->where(function ($q) use ($origins) {
            foreach ($origins as $origin) {
                $q->orWhere('hid', 'like', addcslashes($origin, '%_\\').'%');
            }
        })->pluck('GeoAreaId')->map(fn ($id) => (int) $id)->all();
    }

    public function withinGeo(int $user, ?int $geo): bool
    {
        return $geo && in_array($geo, $this->geoIds($user), true);
    }

    /** SYSTEM orphans have no invented owner or territory; only a superuser can route them. */
    public function queue(User $actor)
    {
        $query = UserOnboarding::query()->whereExists(fn ($q) => $q->selectRaw('1')->from('users as subject')->whereColumn('subject.id', 'gov_user_onboardings.user_id')->whereNull('subject.deleted_at'));
        if ($actor->isSuperUser()) {
            return $query;
        }
        $companies = $this->current(DB::table('gov_company_admins')->where('user_id', $actor->id))->pluck('company_id');
        $geos = $this->geoIds($actor->id);
        $office = app(TenantContext::class)->locationId;
        $query->where('owner_id', $actor->id)->where(function ($q) use ($companies, $geos, $office, $actor) {
            $q->where(function ($q) use ($companies) {
                $q->where('owner_type', 'COMPANY_ADMIN')->whereExists(fn ($s) => $s->selectRaw('1')->from('users as subject')->whereColumn('subject.id', 'gov_user_onboardings.user_id')->whereIn('subject.company_id', $companies));
            })->orWhere(fn ($q) => $q->where('owner_type', 'ICT_OFFICER')->whereIn('geo_area_id', $geos));
            if ($office && $this->isOfficeAdmin($actor->id, $office)) {
                $q->orWhere(fn ($q) => $q->where('owner_type', 'OFFICE_ADMIN')->where('managed_location_id', $office));
            }
        });

        return $query;
    }

    public function isOfficeAdmin(int $actor, int $office): bool
    {
        return (bool) $this->current(DB::table('users as manager')->join('locations as office', 'office.company_id', '=', 'manager.company_id')
            ->where('manager.id', $actor)->where('manager.activated', true)->whereNull('manager.deleted_at')->where('office.id', $office)->whereNull('office.deleted_at'))->first()
            && (bool) $this->current(DB::table('gov_location_profiles')->where('location_id', $office)->where('office_admin_id', $actor))->first()
            && (bool) $this->current(DB::table('gov_office_memberships')->where('user_id', $actor)->where('location_id', $office)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString())))->first();
    }

    public function office(User $actor, Location $office, LocationProfile $profile): void
    {
        if ($actor->isSuperUser()) {
            return;
        }
        $company = (bool) $this->current(DB::table('gov_company_admins')->where('user_id', $actor->id)->where('company_id', $office->company_id))->first();
        $local = app(TenantContext::class)->locationId === (int) $office->id && $this->isOfficeAdmin($actor->id, $office->id);
        abort_unless($company || $local || $this->withinGeo($actor->id, $profile->geo_area_id), 404);
    }

    public function offices(User $actor)
    {
        return Location::withoutGlobalScopes()->whereNull('deleted_at')->whereHas('profile', fn ($q) => $q->whereIn('lifecycle_status', OfficeLifecycleService::ACTIVE))
            ->with('profile')->orderBy('name')->get()->filter(function ($office) use ($actor) {
                try {
                    $this->office($actor, $office, $office->profile);

                    return true;
                } catch (HttpException $e) {
                    if ($e->getStatusCode() === 404) {
                        return false;
                    } throw $e;
                }
            });
    }

    public function manager(UserOnboarding $item, User $subject, int $id, string $type): void
    {
        $manager = $this->current(User::withoutGlobalScopes()->whereNull('deleted_at')->where('activated', true))->findOrFail($id);
        $allowed = match ($type) {
            'COMPANY_ADMIN' => $subject->company_id && (bool) $this->current(DB::table('gov_company_admins')->where('user_id', $id)->where('company_id', $subject->company_id))->first(),
            'ICT_OFFICER' => $this->withinGeo($id, $item->geo_area_id),
            'OFFICE_ADMIN' => $item->managed_location_id && $this->isOfficeAdmin($id, $item->managed_location_id),
            default => false,
        };
        abort_unless($allowed && $manager->id !== $subject->id, 404);
    }

    public function managerChoices(UserOnboarding $item): array
    {
        $subject = $item->user;
        if (! $subject) {
            return [];
        }
        $candidates = [];
        foreach (DB::table('gov_company_admins')->where('company_id', $subject->company_id)->pluck('user_id') as $id) {
            $candidates[] = ['COMPANY_ADMIN', $id];
        }
        foreach (DB::table('gov_ict_jurisdictions')->pluck('user_id')->unique() as $id) {
            if ($this->withinGeo($id, $item->geo_area_id)) {
                $candidates[] = ['ICT_OFFICER', $id];
            }
        }
        if ($item->managed_location_id) {
            $id = DB::table('gov_location_profiles')->where('location_id', $item->managed_location_id)->value('office_admin_id');
            if ($id && $this->isOfficeAdmin($id, $item->managed_location_id)) {
                $candidates[] = ['OFFICE_ADMIN', $id];
            }
        }
        $choices = [];
        foreach ($candidates as [$type, $id]) {
            $user = DB::table('users')->where('id', $id)->where('activated', true)->whereNull('deleted_at')->first(['id', 'first_name', 'last_name']);
            if ($user && $id != $subject->id && ! ($id == $item->owner_id && $type === $item->owner_type)) {
                $choices[$type.':'.$id] = trim($user->first_name.' '.$user->last_name).' — '.__('govonboard::onboard.owner_'.$type);
            }
        }

        return $choices;
    }
}
