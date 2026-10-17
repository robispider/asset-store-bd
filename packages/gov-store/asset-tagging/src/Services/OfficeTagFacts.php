<?php

namespace GovStore\AssetTagging\Services;

use GovStore\Metadata\Services\MetadataValueStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Typed read access to the company code lists, the office's chosen codes and office enablement
 * (all in gov_metadata_values). Offices pick from the company lists; nobody types an LL or DD code.
 */
class OfficeTagFacts
{
    public const CODELISTS = 'asset-tagging.codelists';

    public const OFFICE = 'asset-tagging.office';

    public const ENABLEMENT = 'asset-tagging.enablement';

    public function __construct(private MetadataValueStore $store) {}

    public function available(): bool
    {
        return Schema::hasTable('gov_metadata_values');
    }

    public function companyOf(int $officeId): ?int
    {
        $company = DB::table('locations')->where('id', $officeId)->value('company_id');

        return $company ? (int) $company : null;
    }

    public function codeLists(?int $companyId): array
    {
        $payload = $companyId && $this->available() ? $this->store->payload('company', $companyId, self::CODELISTS) : null;

        return ['locations' => $payload['locations'] ?? [], 'divisions' => $payload['divisions'] ?? []];
    }

    public function office(int $officeId): ?array
    {
        return $this->available() ? $this->store->payload('office', $officeId, self::OFFICE) : null;
    }

    /** Offices of a company keyed by id with their chosen facts (only offices that saved settings). */
    public function companyOffices(int $companyId): array
    {
        if (! $this->available()) {
            return [];
        }
        $offices = DB::table('locations')->where('company_id', $companyId)->whereNull('deleted_at')->pluck('name', 'id');
        $facts = [];
        // Keyed by subject id (an Eloquent collection's only() would match model ids instead).
        foreach ($this->store->all(self::OFFICE, 'office')->filter(fn ($row, $id) => $offices->has($id)) as $id => $row) {
            $facts[(int) $id] = ['name' => $offices[$id], 'facts' => $row->payload];
        }

        return $facts;
    }

    /** Location code => [office id, office name] for every office of the company except $exceptOffice. */
    public function takenLocations(int $companyId, ?int $exceptOffice = null): array
    {
        $taken = [];
        foreach ($this->companyOffices($companyId) as $id => $office) {
            if ($id !== $exceptOffice && ! empty($office['facts']['location_code'])) {
                $taken[$office['facts']['location_code']] = ['id' => $id, 'name' => $office['name']];
            }
        }

        return $taken;
    }

    /** How many offices use each list entry: ['locations' => [code => n], 'divisions' => [code => n]]. */
    public function usage(int $companyId): array
    {
        $usage = ['locations' => [], 'divisions' => []];
        foreach ($this->companyOffices($companyId) as $office) {
            $facts = $office['facts'];
            if (! empty($facts['location_code'])) {
                $usage['locations'][$facts['location_code']] = ($usage['locations'][$facts['location_code']] ?? 0) + 1;
            }
            foreach ($facts['division_codes'] ?? [] as $code) {
                $usage['divisions'][$code] = ($usage['divisions'][$code] ?? 0) + 1;
            }
        }
        // Codes already printed on issued tags stay explainable even after an office changes its choice.
        if (Schema::hasColumn('gov_asset_registrations', 'tag_snapshot')) {
            foreach (DB::table('gov_asset_registrations')->where('format_version', 'fd18-v1')->whereNotNull('tag_snapshot')->pluck('tag_snapshot') as $snapshot) {
                $snapshot = json_decode((string) $snapshot, true);
                if ((int) ($snapshot['company_id'] ?? 0) === $companyId) {
                    $usage['locations'][$snapshot['location_code']] ??= 1;
                    $usage['divisions'][$snapshot['division_code']] ??= 1;
                }
            }
        }

        return $usage;
    }

    public function enabled(int $officeId): bool
    {
        return $this->available() && (bool) ($this->store->payload('office', $officeId, self::ENABLEMENT)['enabled'] ?? false);
    }

    /** G4: a location and at least one division, all still present in the company lists. */
    public function complete(int $officeId): bool
    {
        $facts = $this->office($officeId);
        if (! $facts || empty($facts['location_code']) || empty($facts['division_codes'])) {
            return false;
        }
        $lists = $this->codeLists($this->companyOf($officeId));
        $locations = array_column($lists['locations'], 'code');
        $divisions = array_column($lists['divisions'], 'code');

        return in_array($facts['location_code'], $locations, true) && ! array_diff($facts['division_codes'], $divisions)
            && in_array($facts['default_division_code'] ?? null, $facts['division_codes'], true);
    }

    public function name(array $list, ?string $code): ?string
    {
        foreach ($list as $entry) {
            if ($entry['code'] === $code) {
                return $entry['name'];
            }
        }

        return null;
    }

    /** Enabled offices (for guards and the console). */
    public function enabledOffices(): array
    {
        if (! $this->available()) {
            return [];
        }

        return $this->store->all(self::ENABLEMENT, 'office')->filter(fn ($row) => $row->payload['enabled'] ?? false)->keys()->map(fn ($id) => (int) $id)->all();
    }
}
