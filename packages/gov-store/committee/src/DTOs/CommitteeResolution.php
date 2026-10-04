<?php

namespace GovStore\Committee\DTOs;

final readonly class CommitteeResolution implements \JsonSerializable
{
    public function __construct(public \GovStore\Committee\Enums\ResolutionStatus $status, public ?CommitteeView $committee = null, public array $candidates = [], public ?string $resolvedVia = null, public array $reasons = [], public ?string $nearestHint = null, public string $asOf = '') {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

