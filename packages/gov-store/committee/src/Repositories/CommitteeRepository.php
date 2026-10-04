<?php

namespace GovStore\Committee\Repositories;

use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Models\Committee;
use Illuminate\Support\Collection;

interface CommitteeRepository
{
    public function lockForUpdate(string $id): Committee;
    public function findVersion(string $id): ?Committee;
    public function activeInScope(array $typeIds, ScopeRef $scope, string $date): Collection;
}
