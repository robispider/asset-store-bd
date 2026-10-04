<?php

namespace GovStore\Committee\DTOs;

final readonly class SnapshotVerification implements \JsonSerializable
{
    public function __construct(public string $status, public array $ledgerReferences = []) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

