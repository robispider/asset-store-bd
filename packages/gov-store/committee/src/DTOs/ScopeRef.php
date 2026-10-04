<?php

namespace GovStore\Committee\DTOs;

final readonly class ScopeRef implements \JsonSerializable
{
    public function __construct(public string $type, public string $id) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

