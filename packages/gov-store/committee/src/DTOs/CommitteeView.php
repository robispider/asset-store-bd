<?php

namespace GovStore\Committee\DTOs;

final readonly class CommitteeView implements \JsonSerializable
{
    public function __construct(public string $id, public string $lineageId, public int $version, public string $number, public string $typeCode, public string $nameEn, public string $nameBn, public int $ownerLocationId, public int $ownerCompanyId, public string $status, public string $effectiveFrom, public ?string $effectiveTo, public ?string $endedOn, public ?array $constitutionOrder, public array $seats = []) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

