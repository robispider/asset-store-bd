<?php

namespace GovStore\Organization\ViewModels;

/**
 * One registry row: who and where, plus setup progress and the single next step to show.
 * Readiness order follows the setup flow: staff → administrator → approver → storekeeper.
 */
class OfficeRegistryViewModel
{
    public const STEPS = 4;

    // Removed strict type-hints to prevent any PHP 8+ TypeError crashes on null database records
    public $id;
    public $name;
    public $parentName;
    public $geoName;
    public $geoType;
    public $districtName;
    public $ministryName;
    public $adminName;
    public $status;
    public $hasPrimary;
    public $hasStorekeeper;
    public $hasAdmin;
    public $staffCount;
    public $done;
    public $nextStep;

    public function __construct($loc, $role = null, int $staffCount = 0)
    {
        $profile = $loc->profile ?? null;
        $geo = $profile?->geoArea;
        $bangla = str_starts_with(strtolower(app()->getLocale()), 'bn');

        $this->id = $loc->id;
        $this->name = $loc->name;
        $this->parentName = $loc->parent->name ?? null;
        $this->geoName = $geo ? ($bangla && $geo->bn_name ? $geo->bn_name : $geo->en_name) : null;
        $this->geoType = $geo ? $geo->geo_type : null;
        $this->districtName = $loc->state ?? null;
        $this->ministryName = $loc->company->name ?? null;

        // Defensive presenter resolver: safeguards against empty/deleted admin records
        $fullName = null;
        if ($profile && $profile->officeAdmin && $profile->officeAdmin->present()) {
            $fullName = $profile->officeAdmin->present()->fullName;
        }
        $this->adminName = $fullName ?: null;

        $this->status = $profile ? $profile->lifecycle_status : 'unconfigured';
        $this->hasPrimary = $role && !is_null($role->primary_approver_id);
        $this->hasStorekeeper = $role && !is_null($role->storekeeper_id);
        $this->hasAdmin = $profile && !is_null($profile->office_admin_id);
        $this->staffCount = $staffCount;
        $this->done = (int) ($staffCount > 0) + (int) $this->hasAdmin + (int) $this->hasPrimary + (int) $this->hasStorekeeper;
        $this->nextStep = $this->resolveNextStep();
    }

    /** Translation key under organization_labels::office for the row's next step. */
    private function resolveNextStep(): string
    {
        return match (true) {
            $this->status === 'unconfigured' => 'next_place',
            $this->status === 'suspended' => 'next_paused',
            $this->status === 'operational' => 'next_ready',
            $this->staffCount === 0 => 'next_staff',
            ! $this->hasAdmin => 'next_admin',
            ! $this->hasPrimary => 'next_approver',
            ! $this->hasStorekeeper => 'next_storekeeper',
            default => 'next_ready',
        };
    }

    public function needsAction(): bool
    {
        return ! in_array($this->nextStep, ['next_ready', 'next_paused'], true);
    }
}
