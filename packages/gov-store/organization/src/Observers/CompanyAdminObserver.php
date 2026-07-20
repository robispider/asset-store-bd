<?php

namespace GovStore\Organization\Observers;

use GovStore\Organization\Models\CompanyAdmin;
use App\Models\Group;

class CompanyAdminObserver
{
    public function created(CompanyAdmin $companyAdmin)
    {
        $user = $companyAdmin->user;
        if (!$user) return;

        // Auto-update the native user's company_id so the core Snipe-IT system recognizes them
        if ($user->company_id !== $companyAdmin->company_id) {
            $user->company_id = $companyAdmin->company_id;
            $user->save();
        }

        $group = Group::where('name', 'Company Administration')->first();
        if ($group) {
            $user->groups()->syncWithoutDetaching([$group->id]);
        }
    }

    public function deleted(CompanyAdmin $companyAdmin)
    {
        $user = $companyAdmin->user;
        if (!$user) return;

        $group = Group::where('name', 'Company Administration')->first();
        if ($group) {
            $user->groups()->detach($group->id);
        }
    }
}