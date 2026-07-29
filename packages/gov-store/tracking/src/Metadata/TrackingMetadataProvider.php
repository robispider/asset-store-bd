<?php

namespace GovStore\Tracking\Metadata;

use GovStore\Metadata\Contracts\MetadataProviderInterface;
use GovStore\Metadata\Support\LogicalField;
use GovStore\Tracking\Models\TrackingAssociation;

class TrackingMetadataProvider implements MetadataProviderInterface
{
    public function getName(): string
    {
        return 'Program Compliance Metadata';
    }

    public function getVersion(): string
    {
        return 'v1';
    }

    public function getFields(): array
    {
        return [
            new LogicalField(
                'tracking.donor_asset_id',
                'Donor Program Asset Tag',
                'text',
                'Operational reference compliance identifier assigned by international partner or donor.',
                false
            ),
            new LogicalField(
                'tracking.maint_cycle_months',
                'Project Maintenance Cycle (Months)',
                'text',
                'Mandated maintenance schedules configured under project reference agreements.',
                false
            ),
        ];
    }

    /**
     * Assert if the current asset needs donor tracking metadata fields
     * based on its association status.
     */
    public function supports(array $context): bool
    {
        $assetId = $context['asset_id'] ?? null;
        if (!$assetId) {
            return false;
        }

        // Apply only to assets associated with active, high-priority Donor-type tracking references
        return TrackingAssociation::where('associatable_type', 'App\Models\Asset')
            ->where('associatable_id', $assetId)
            ->where('status', 'ACTIVE')
            ->whereHas('reference.trackingType', function ($query) {
                $query->whereIn('code', ['GRANT', 'DONOR_GRANT', 'ADP']);
            })
            ->exists();
    }
}