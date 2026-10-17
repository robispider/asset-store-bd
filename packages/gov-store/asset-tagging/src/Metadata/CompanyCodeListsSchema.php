<?php

namespace GovStore\AssetTagging\Metadata;

use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Metadata\Contracts\MetadataValueSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * asset-tagging.codelists on a company: {locations: [{code, name}], divisions: [{code, name}]}.
 * Two-digit codes, unique per list, at most 99 entries. An entry in use can be renamed, never re-coded or removed.
 */
class CompanyCodeListsSchema implements MetadataValueSchema
{
    public function subjectTypes(): array
    {
        return ['company'];
    }

    public function scope(string $subjectType, int $subjectId): array
    {
        return ['company', $subjectId];
    }

    public function normalize(string $subjectType, int $subjectId, array $payload, ?array $current): array
    {
        abort_unless(DB::table('companies')->where('id', $subjectId)->exists(), 404);
        $usage = app(OfficeTagFacts::class)->usage($subjectId);
        $normalized = [];
        foreach (['locations', 'divisions'] as $list) {
            $entries = [];
            foreach (array_values($payload[$list] ?? []) as $i => $entry) {
                $code = trim((string) ($entry['code'] ?? ''));
                $name = trim((string) ($entry['name'] ?? ''));
                if ($code === '' && $name === '') {
                    continue;
                }
                if (! preg_match('/^(?!00)\d{2}$/', $code)) {
                    throw ValidationException::withMessages(["{$list}.{$i}.code" => __('asset-tagging::tagging.codes.invalid_code')]);
                }
                if ($name === '' || mb_strlen($name) > 120) {
                    throw ValidationException::withMessages(["{$list}.{$i}.name" => __('asset-tagging::tagging.codes.invalid_name')]);
                }
                if (isset($entries[$code])) {
                    throw ValidationException::withMessages(["{$list}.{$i}.code" => __('asset-tagging::tagging.codes.duplicate', ['code' => $code])]);
                }
                $entries[$code] = ['code' => $code, 'name' => $name];
            }
            if (count($entries) > 99) {
                throw ValidationException::withMessages([$list => __('asset-tagging::tagging.codes.too_many')]);
            }
            foreach (array_keys($usage[$list]) as $used) {
                if (! isset($entries[$used])) {
                    throw ValidationException::withMessages([$list => __('asset-tagging::tagging.codes.in_use', ['code' => $used])]);
                }
            }
            ksort($entries, SORT_STRING);
            $normalized[$list] = array_values($entries);
        }

        return $normalized;
    }
}
