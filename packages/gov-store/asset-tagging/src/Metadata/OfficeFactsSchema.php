<?php

namespace GovStore\AssetTagging\Metadata;

use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Metadata\Contracts\MetadataValueSchema;
use GovStore\Metadata\Models\MetadataValue;
use Illuminate\Validation\ValidationException;

/**
 * asset-tagging.office on an office: {location_code, division_codes[], default_division_code}, every value
 * chosen from the company lists. A location code belongs to at most one office of the company.
 */
class OfficeFactsSchema implements MetadataValueSchema
{
    public function subjectTypes(): array
    {
        return ['office'];
    }

    public function scope(string $subjectType, int $subjectId): array
    {
        return ['office', $subjectId];
    }

    public function normalize(string $subjectType, int $subjectId, array $payload, ?array $current): array
    {
        $facts = app(OfficeTagFacts::class);
        $companyId = $facts->companyOf($subjectId);
        abort_unless($companyId, 404);
        // Serialise office choices per company on its code-list row, so two offices cannot take one location.
        MetadataValue::where('subject_type', 'company')->where('subject_id', $companyId)
            ->where('namespace', OfficeTagFacts::CODELISTS)->lockForUpdate()->first();
        $lists = $facts->codeLists($companyId);
        $locations = array_column($lists['locations'], 'code');
        $divisions = array_column($lists['divisions'], 'code');

        $location = (string) ($payload['location_code'] ?? '');
        if (! in_array($location, $locations, true)) {
            throw ValidationException::withMessages(['location_code' => __('asset-tagging::tagging.office.pick_location')]);
        }
        $taken = $facts->takenLocations($companyId, $subjectId)[$location] ?? null;
        if ($taken) {
            throw ValidationException::withMessages(['location_code' => __('asset-tagging::tagging.office.code_taken', ['office' => $taken['name']])]);
        }
        $chosen = array_values(array_unique(array_map('strval', (array) ($payload['division_codes'] ?? []))));
        sort($chosen, SORT_STRING);
        if (! $chosen || array_diff($chosen, $divisions)) {
            throw ValidationException::withMessages(['division_codes' => __('asset-tagging::tagging.office.pick_divisions')]);
        }
        $default = (string) ($payload['default_division_code'] ?? ($chosen[0] ?? ''));
        if (! in_array($default, $chosen, true)) {
            throw ValidationException::withMessages(['default_division_code' => __('asset-tagging::tagging.office.default_in_divisions')]);
        }

        return ['location_code' => $location, 'division_codes' => $chosen, 'default_division_code' => $default];
    }
}
