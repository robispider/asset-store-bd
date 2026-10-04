<?php

namespace GovStore\Committee\DTOs;

final readonly class HealthReport implements \JsonSerializable
{
    public function __construct(public \GovStore\Committee\Enums\HealthStatus $status, public array $issues, public string $asOf) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

