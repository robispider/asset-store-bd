<?php

namespace GovStore\AssetTagging\Formats;

use App\Models\Setting;
use GovStore\AssetTagging\Contracts\TagFormat;

/**
 * Standard tag, native-v1: the installation's own auto-increment prefix and zero-fill, for example
 * GS-004217. The sequence is the native next_auto_tag_base, read under the settings row lock.
 */
class NativeSequenceV1Format implements TagFormat
{
    public const VERSION = 'native-v1';

    public function version(): string
    {
        return self::VERSION;
    }

    public function prefix(array $facts): ?string
    {
        return (string) ($facts['native_prefix'] ?? Setting::getSettings()?->auto_increment_prefix ?? '');
    }

    public function encode(array $facts, int $sequence): string
    {
        $zerofill = (int) ($facts['zerofill'] ?? Setting::getSettings()?->zerofill_count ?? 0);

        return $this->prefix($facts).($zerofill > 0 ? str_pad((string) $sequence, $zerofill, '0', STR_PAD_LEFT) : (string) $sequence);
    }

    public function decode(string $tag): ?array
    {
        $prefix = (string) (Setting::getSettings()?->auto_increment_prefix ?? '');
        if ($prefix === '' || ! str_starts_with($tag, $prefix) || ! ctype_digit(substr($tag, strlen($prefix)))) {
            return null;
        }

        return ['format' => self::VERSION, 'prefix' => $prefix, 'sequence' => (int) substr($tag, strlen($prefix))];
    }

    public function segments(array $facts, ?int $sequence): array
    {
        $zerofill = max(1, (int) ($facts['zerofill'] ?? Setting::getSettings()?->zerofill_count ?? 6));

        return [
            ['value' => $this->prefix($facts), 'label' => __('asset-tagging::tagging.segment.prefix'), 'tone' => 'muted'],
            $sequence === null
                ? ['value' => str_repeat('·', $zerofill), 'label' => __('asset-tagging::tagging.segment.sequence'), 'tone' => 'muted', 'pending' => true]
                : ['value' => str_pad((string) $sequence, $zerofill, '0', STR_PAD_LEFT), 'label' => __('asset-tagging::tagging.segment.sequence'), 'tone' => 'warning'],
        ];
    }
}
