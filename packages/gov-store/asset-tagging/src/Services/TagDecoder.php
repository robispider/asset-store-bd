<?php

namespace GovStore\AssetTagging\Services;

use GovStore\AssetTagging\Factories\TagFormatFactory;
use GovStore\Classification\Models\FinanceItem;

/** Turns a tag string into plain facts for lookup: Govt. Tag segments with names, or "standard tag". */
class TagDecoder
{
    public function __construct(private TagFormatFactory $formats) {}

    public function decode(string $tag): array
    {
        $tag = trim($tag);
        $decoded = $this->formats->decode($tag);
        if (! $decoded) {
            return ['kind' => 'unknown', 'tag' => $tag];
        }
        if ($decoded['format'] !== 'fd18-v1') {
            return ['kind' => 'standard', 'tag' => $tag] + $decoded;
        }
        $item = FinanceItem::find($decoded['gov_code']);

        return ['kind' => 'govt', 'tag' => $tag, 'finance_item' => $item] + $decoded;
    }
}
