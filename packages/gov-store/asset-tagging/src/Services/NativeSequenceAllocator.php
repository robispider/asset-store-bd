<?php

namespace GovStore\AssetTagging\Services;

use App\Models\Setting;
use GovStore\AssetTagging\Formats\NativeSequenceV1Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Standard tags from the installation's native sequence. The settings row is locked FOR UPDATE for the
 * rest of the posting; numbers already used (soft-deleted assets and the bridge included) are skipped,
 * and next_auto_tag_base moves past the last reserved number. The bridge unique key is the final barrier.
 */
class NativeSequenceAllocator
{
    public function __construct(private NativeSequenceV1Format $format) {}

    /** @return string[] $count tags in order */
    public function reserve(int $count): array
    {
        if ($count < 1) {
            return [];
        }
        $settings = Setting::query()->lockForUpdate()->firstOrFail();
        if (! app(EnablementGate::class)->nativeSequenceReady($settings)) {
            throw ValidationException::withMessages(['items' => __('asset-tagging::tagging.errors.paused')]);
        }
        $facts = ['native_prefix' => (string) $settings->auto_increment_prefix, 'zerofill' => (int) $settings->zerofill_count];
        $next = max(1, (int) $settings->next_auto_tag_base);
        $tags = [];
        while (count($tags) < $count) {
            $candidate = $this->format->encode($facts, $next);
            if (! $this->used($candidate)) {
                $tags[] = $candidate;
            }
            $next++;
        }
        // Saved through the model (model events run; ManagedTagGuard allows a base change). Unrelated settings
        // validation must not silently skip the write, and the native observer must see the new base.
        $settings->next_auto_tag_base = $next;
        $settings->forceSave();
        Setting::$_cache = $settings;

        return $tags;
    }

    private function used(string $tag): bool
    {
        return DB::table('assets')->where('asset_tag', $tag)->exists()
            || DB::table('gov_asset_registrations')->where('asset_tag', $tag)->exists();
    }
}
