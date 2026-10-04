<?php

namespace GovStore\Committee\DTOs;

final readonly class RosterSnapshot implements \JsonSerializable
{
    public function __construct(public array $roster, public string $fingerprint) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

