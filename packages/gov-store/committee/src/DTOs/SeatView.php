<?php

namespace GovStore\Committee\DTOs;

final readonly class SeatView implements \JsonSerializable
{
    public function __construct(public int $id, public int $number, public string $role, public string $holderKind, public ?string $postTitleEn, public ?string $postTitleBn, public ?MemberView $holder) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

