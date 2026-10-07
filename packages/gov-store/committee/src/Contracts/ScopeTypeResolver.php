<?php

namespace GovStore\Committee\Contracts;

interface ScopeTypeResolver
{
    public function exists(string $id): bool;
    public function label(string $id, string $locale): string;
    public function ownerLocationId(string $id): ?int;
    public function ownerCompanyId(string $id): ?int;
    public function parent(string $id): ?\GovStore\Committee\DTOs\ScopeRef;
    public function search(string $term, \GovStore\TenantScope\Contexts\TenantContext $context, int $limit): array;
}

