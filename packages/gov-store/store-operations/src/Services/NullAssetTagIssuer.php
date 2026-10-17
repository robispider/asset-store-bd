<?php

namespace GovStore\StoreOperations\Services;

use App\Models\Asset;
use GovStore\StoreOperations\Contracts\AssetTagIssuer;
use GovStore\StoreOperations\DTOs\IssuedTag;
use GovStore\StoreOperations\Models\Document;

/** Today's behaviour: the typed tag, otherwise Snipe-IT's auto-increment. */
class NullAssetTagIssuer implements AssetTagIssuer
{
    public function prepare(Document $document): void {}

    public function tagFor(object $item, int $unitIndex, ?string $enteredTag): IssuedTag
    {
        $tag = $enteredTag ?: Asset::autoincrement_asset();

        return new IssuedTag((string) $tag, 'entered-v1', null, null,
            ['source' => $enteredTag ? 'typed' : 'native_autoincrement', 'actor' => auth()->id()]);
    }

    public function summary(Document $document): ?array
    {
        return null;
    }

    public function postedPanel(Document $document): string
    {
        return '';
    }
}
