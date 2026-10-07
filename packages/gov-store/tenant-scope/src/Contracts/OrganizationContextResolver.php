<?php

namespace GovStore\TenantScope\Contracts;

interface OrganizationContextResolver
{
    public function companyForAdministrator(int $userId): ?int;

    /** Null means no assignment; an empty array means an assignment with no offices. */
    public function jurisdictionLocations(int $userId): ?array;

    public function isOfficeAdministrator(int $userId, int $locationId): bool;

    public function isCompanyAdministrator(int $userId, int $companyId): bool;

    public function hasJurisdiction(int $userId): bool;

    public function officeAdministratorIds(int $locationId): array;
}
