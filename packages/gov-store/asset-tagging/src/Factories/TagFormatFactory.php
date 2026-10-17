<?php

namespace GovStore\AssetTagging\Factories;

use GovStore\AssetTagging\Contracts\TagFormat;
use InvalidArgumentException;

class TagFormatFactory
{
    public function make(string $version): TagFormat
    {
        $class = config('asset-tagging.formats', [])[$version] ?? null;
        if (! $class || ! is_subclass_of($class, TagFormat::class)) {
            throw new InvalidArgumentException("Tag format [{$version}] is not on the allowlist.");
        }

        return app($class);
    }

    /** Decode any tag with the first allowlisted format that recognises it. */
    public function decode(string $tag): ?array
    {
        foreach (array_keys(config('asset-tagging.formats', [])) as $version) {
            if ($decoded = $this->make($version)->decode($tag)) {
                return $decoded;
            }
        }

        return null;
    }
}
