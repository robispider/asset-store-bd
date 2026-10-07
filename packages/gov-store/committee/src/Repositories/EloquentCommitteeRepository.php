<?php

namespace GovStore\Committee\Repositories;

use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Models\Committee;
use Illuminate\Support\Collection;

class EloquentCommitteeRepository implements CommitteeRepository
{
    public function lockForUpdate(string $id): Committee { return Committee::whereKey($id)->lockForUpdate()->firstOrFail(); }
    public function findVersion(string $id): ?Committee { return Committee::find($id); }

    public function activeInScope(array $typeIds, ScopeRef $scope, string $date): Collection
    {
        return Committee::with('type')->whereIn('committee_type_id',$typeIds)->whereNotNull('activated_at')
            ->where('effective_from','<=',$date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to','>=',$date))
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on','>=',$date))
            ->whereHas('scopes', fn ($q) => $q->where('scope_type',$scope->type)->where('scope_id',$scope->id)
                ->where('effective_from','<=',$date)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to','>=',$date)))
            ->orderBy('committee_number')->get();
    }
}
