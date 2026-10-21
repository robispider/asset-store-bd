<?php

namespace GovStore\AssetTagging\Guards;

use App\Models\Asset;
use App\Models\Setting;
use GovStore\AssetTagging\Services\EnablementGate;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\AssetTagging\Services\TagIssuanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Model guards registered from the provider, like store-operations' GRN-only intake guards. They cover
 * the native UI, API and importer: an 18-digit tag can only come from issuance, an issued tag never
 * changes, and the native sequence cannot be broken (G1) while any office is enabled.
 */
class ManagedTagGuard
{
    public function asset(Asset $asset): void
    {
        if (! $asset->isDirty('asset_tag')) {
            return;
        }
        $tag = (string) $asset->asset_tag;
        if (strlen($tag) === 18 && ctype_digit($tag) && ! app(TagIssuanceService::class)->isReserved($tag)) {
            $this->refuse('asset_tag', __('asset-tagging::tagging.guard.reserved_format'));
        }
        if ($asset->exists && Schema::hasColumn('gov_asset_registrations', 'format_version')
            && DB::table('gov_asset_registrations')->where('asset_id', $asset->getKey())->whereIn('format_version', ['fd18-v1', 'native-v1'])->exists()) {
            $this->refuse('asset_tag', __('asset-tagging::tagging.guard.issued_immutable'));
        }
    }

    public function setting(Setting $setting): void
    {
        if (! $setting->isDirty(['auto_increment_assets', 'auto_increment_prefix'])) {
            return;
        }
        if (! app(EnablementGate::class)->nativeSequenceReady($setting) && app(OfficeTagFacts::class)->enabledOffices()) {
            $this->refuse('auto_increment_prefix', __('asset-tagging::tagging.guard.native_locked'));
        }
    }

    private function refuse(string $field, string $message): void
    {
        Log::warning('Asset tagging guard refused a change', ['field' => $field, 'actor' => auth()->id(), 'message' => $message]);

        throw ValidationException::withMessages([$field => $message]);
    }
}
