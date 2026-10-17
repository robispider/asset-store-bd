<?php

namespace GovStore\StoreOperations\DTOs;

/** One unit's tag plus the evidence written to gov_asset_registrations. */
final class IssuedTag
{
    public function __construct(
        public readonly string $tag,
        public readonly string $formatVersion,
        public readonly ?string $govCode = null,
        public readonly ?string $purchaseDate = null,
        public readonly array $snapshot = [],
    ) {}
}
