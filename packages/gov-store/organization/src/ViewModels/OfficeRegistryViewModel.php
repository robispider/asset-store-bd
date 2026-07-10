<?php

namespace GovStore\Organization\ViewModels;

class OfficeRegistryViewModel
{
    // Removed strict type-hints to prevent any PHP 8+ TypeError crashes on null database records
    public $id;
    public $name;
    public $parentName;
    public $geoName;
    public $geoType;
    public $ministryName;
    public $adminName;
    public $status;
    public $hasPrimary;
    public $hasStorekeeper;

    public function __construct($loc, $role = null)
    {
        $profile = $loc->profile ?? null;
        
        $this->id = $loc->id;
        $this->name = $loc->name;
        $this->parentName = $loc->parent->name ?? 'Root Office';
        
        $this->geoName = $profile && $profile->geoArea ? $profile->geoArea->en_name : 'Unmapped';
        $this->geoType = $profile && $profile->geoArea ? ucfirst($profile->geoArea->geo_type) : 'N/A';
        
        $this->ministryName = $loc->company->name ?? 'Standalone Office';
        
        // Defensive presenter resolver: safeguards against empty/deleted admin records
        $fullName = null;
        if ($profile && $profile->officeAdmin && $profile->officeAdmin->present()) {
            $fullName = $profile->officeAdmin->present()->fullName;
        }
        $this->adminName = $fullName ?: 'Unassigned';
        
        $this->status = $profile ? $profile->lifecycle_status : 'unconfigured';
        
        // Roles configuration checklist evaluations
        $this->hasPrimary = $role && !is_null($role->primary_approver_id);
        $this->hasStorekeeper = $role && !is_null($role->storekeeper_id);
    }
}