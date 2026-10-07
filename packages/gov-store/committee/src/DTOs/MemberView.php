<?php

namespace GovStore\Committee\DTOs;

final readonly class MemberView implements \JsonSerializable
{
    public function __construct(public int $tenureId, public string $seatRole, public string $holderKind, public ?int $userId, public ?int $externalMemberId, public string $nameEn, public string $nameBn, public string $designationEn, public string $designationBn, public ?int $homeLocationId, public ?int $homeCompanyId, public bool $isExternal, public string $fromDate, public ?string $toDate, public string $declarationStatus, public bool $isPresiding = false, public bool $isSecretary = false, public bool $countsTowardStrength = true) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

