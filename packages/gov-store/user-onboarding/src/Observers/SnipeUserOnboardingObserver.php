<?php

namespace GovStore\UserOnboarding\Observers;

use App\Models\User;
use GovStore\Organization\Services\OfficeRequestIntake;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\UserOnboarding\Models\UserOnboarding;
use GovStore\UserOnboarding\Services\OnboardingAccess;
use GovStore\UserOnboarding\Services\UserOnboardingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SnipeUserOnboardingObserver
{
    public function creating(User $model): void
    {
        $creator = auth()->user();
        $office = app(TenantContext::class)->locationId;
        if ($creator && ! $creator->isSuperUser() && $office && app(OnboardingAccess::class)->isOfficeAdmin($creator->id, $office)) {
            // A supplied foreign company/office must not conflict with automatic membership.
            $location = DB::table('locations')->where('id', $office)->whereNull('deleted_at')->first();
            abort_unless($location, 404);
            abort_unless(! $model->location_id || (int) $model->location_id === $office, 404);
            abort_unless(! $model->company_id || (int) $model->company_id === (int) $location->company_id, 404);
            app(OfficeRequestIntake::class)->assertOpen([$office]);
            $model->location_id = $office;
            $model->company_id = $location->company_id;
        }
        // A ministry manager may create an orphan without selecting a native office.
        if ($creator && ! $creator->isSuperUser() && app(TenantContext::class)->isCompanyAdmin) {
            $company = $model->company_id ?: app(TenantContext::class)->companyId;
            abort_unless($company && DB::table('gov_company_admins')->where('user_id', $creator->id)->where('company_id', $company)->exists(), 404);
            $model->company_id = $company;
            if ($model->location_id) {
                $location = DB::table('locations')->where('id', $model->location_id)->whereNull('deleted_at')->first();
                abort_unless($location && (int) $location->company_id === (int) $company, 404);
            }
        }
    }

    public function created(User $model): void
    {
        // Fresh installations and isolated native fixtures can precede this package's schema.
        if (! Schema::hasTable('gov_user_onboardings')) {
            return;
        }
        DB::transaction(function () use ($model) {
            $creator = auth()->user();
            $office = app(TenantContext::class)->locationId;
            $ownerType = 'SYSTEM';
            $owner = null;
            $geo = null;
            $local = $creator && ! $creator->isSuperUser() && $office && app(OnboardingAccess::class)->isOfficeAdmin($creator->id, $office);
            if ($local) {
                $ownerType = 'OFFICE_ADMIN';
                $owner = $creator->id;
                $geo = DB::table('gov_location_profiles')->where('location_id', $office)->value('geo_area_id');
            } elseif ($creator && $model->company_id && DB::table('gov_company_admins')->where('user_id', $creator->id)->where('company_id', $model->company_id)->exists()) {
                $ownerType = 'COMPANY_ADMIN';
                $owner = $creator->id;
            } elseif ($creator) {
                $jurisdictions = DB::table('gov_ict_jurisdictions')->where('user_id', $creator->id)->pluck('geo_area_id');
                // Multiple jurisdictions cannot supply a guessed personal geography.
                if ($jurisdictions->count() === 1) {
                    $ownerType = 'ICT_OFFICER';
                    $owner = $creator->id;
                    $geo = $jurisdictions->first();
                }
            }
            $item = UserOnboarding::firstOrCreate(['user_id' => $model->id], [
                'status' => 'WAITING', 'creator_user_id' => $creator?->id,
                'owner_type' => $ownerType, 'owner_id' => $owner, 'geo_area_id' => $geo,
                'managed_location_id' => $local ? $office : null,
            ]);
            if (! $item->wasRecentlyCreated) {
                return;
            }
            $service = app(UserOnboardingService::class);
            $service->record($item, 'queued', $creator?->id);
            if ($local) {
                $service->assignToOffice($item->id, $office);
            }
        });
    }
}
