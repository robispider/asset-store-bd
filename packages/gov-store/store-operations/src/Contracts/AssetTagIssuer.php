<?php

namespace GovStore\StoreOperations\Contracts;

use GovStore\StoreOperations\DTOs\IssuedTag;
use GovStore\StoreOperations\Models\Document;

/**
 * Chooses the asset tag for every unit a receipt creates. The default (NullAssetTagIssuer) keeps
 * today's behaviour; the asset-tagging package binds an adapter when it is installed.
 */
interface AssetTagIssuer
{
    /** Called inside the posting transaction after the document lock and before the line loop. */
    public function prepare(Document $document): void;

    /** The tag (and bridge evidence) for one unit; $enteredTag is a tag typed in the unit metadata. */
    public function tagFor(object $item, int $unitIndex, ?string $enteredTag): IssuedTag;

    /** Counts for the posting modal (['govt' => n, 'standard' => n]) or null when tags are not issued here. */
    public function summary(Document $document): ?array;

    /** HTML for the posted workspace's right column; an empty string adds nothing. */
    public function postedPanel(Document $document): string;
}
