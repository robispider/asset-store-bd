<?php

namespace GovStore\Committee\DTOs;

final readonly class PurposeDefinition implements \JsonSerializable
{
    public function __construct(public string $code, public string $labelEn, public string $labelBn, public array $allowedScopeTypes, public array $suggestedTypeCodes = [], public string $declaringPackage = '', public string $description = '') {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

