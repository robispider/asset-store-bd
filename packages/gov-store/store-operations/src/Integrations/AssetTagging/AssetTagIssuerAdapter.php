<?php

namespace GovStore\StoreOperations\Integrations\AssetTagging;

use GovStore\AssetTagging\Services\TagIssuanceService;
use GovStore\StoreOperations\Contracts\AssetTagIssuer;
use GovStore\StoreOperations\DTOs\IssuedTag;
use GovStore\StoreOperations\Models\Document;

/** Bound only when the asset-tagging package is installed, as the tracking verifier is. */
class AssetTagIssuerAdapter implements AssetTagIssuer
{
    public function __construct(private TagIssuanceService $issuance) {}

    public function prepare(Document $document): void
    {
        $this->issuance->prepare($document);
    }

    public function tagFor(object $item, int $unitIndex, ?string $enteredTag): IssuedTag
    {
        $tag = $this->issuance->tagFor($item, $unitIndex, $enteredTag);

        return new IssuedTag($tag['tag'], $tag['format_version'], $tag['gov_code'], $tag['purchase_date'], $tag['snapshot']);
    }

    public function summary(Document $document): ?array
    {
        return $this->issuance->summary($document);
    }

    public function postedPanel(Document $document): string
    {
        return $this->issuance->postedPanel($document);
    }
}
