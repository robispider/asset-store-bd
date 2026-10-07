<?php

namespace GovStore\TenantScope\Contracts;

interface MembershipContextResolver
{
    /** Returns only an active membership owned by this actor in an existing office. */
    public function workingMembership(int $userId, mixed $selection): ?array;

    public function hasMemberships(int $userId): bool;

    public function hasActiveMembershipAt(int $userId, int $locationId): bool;

    public function responsibilityRoles(int $userId, int $locationId): array;

    public function responsibilityUserIds(int $locationId, array $roles): array;
}
