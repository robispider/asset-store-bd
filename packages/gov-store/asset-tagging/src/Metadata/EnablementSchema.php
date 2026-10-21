<?php

namespace GovStore\AssetTagging\Metadata;

use GovStore\Metadata\Contracts\MetadataValueSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** asset-tagging.enablement on an office (national scope): written only by the enablement console after the gates pass. */
class EnablementSchema implements MetadataValueSchema
{
    public function subjectTypes(): array
    {
        return ['office'];
    }

    public function scope(string $subjectType, int $subjectId): array
    {
        return ['national', 0];
    }

    public function normalize(string $subjectType, int $subjectId, array $payload, ?array $current): array
    {
        abort_unless(DB::table('locations')->where('id', $subjectId)->whereNull('deleted_at')->exists(), 404);
        $reason = trim((string) ($payload['reason'] ?? ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('asset-tagging::tagging.enable.reason_required')]);
        }

        return ['enabled' => (bool) ($payload['enabled'] ?? false), 'reason' => $reason,
            'gates_passed_at' => $payload['gates_passed_at'] ?? null];
    }
}
