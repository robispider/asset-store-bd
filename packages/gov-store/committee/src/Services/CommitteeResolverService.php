<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Contracts\{CommitteeResolver,PurposeRegistry,ScopeTypeRegistry};
use GovStore\Committee\DTOs\{ScopeRef,CommitteeResolution};
use GovStore\Committee\Enums\{ResolutionStatus,HealthStatus};
use GovStore\Committee\Models\{PurposeBinding,CommitteeType};
use GovStore\Committee\Repositories\CommitteeRepository;

class CommitteeResolverService implements CommitteeResolver
{
    public function __construct(private PurposeRegistry $purposes, private ScopeTypeRegistry $scopes, private CommitteeRepository $repository, private CommitteeQueryService $queries) {}
    public function resolve(string $purpose, ScopeRef $scope, \DateTimeInterface $asOf): CommitteeResolution
    {
        $date = $asOf->format('Y-m-d');
        $definition = $this->purposes->get($purpose);
        if (! $definition || ! in_array($scope->type,$definition->allowedScopeTypes,true)) { return new CommitteeResolution(ResolutionStatus::NOT_FOUND,reasons:['UNDECLARED_PURPOSE'],asOf:$date); }
        if (! in_array($scope->type,$this->scopes->keys(),true) || ! $this->scopes->get($scope->type)->exists($scope->id)) { return new CommitteeResolution(ResolutionStatus::NOT_FOUND,reasons:['UNKNOWN_SCOPE'],asOf:$date); }
        $company = $this->scopes->get($scope->type)->ownerCompanyId($scope->id);
        $bindings = PurposeBinding::where('purpose_code',$purpose)->where('owner_company_id',$company)->get();
        if ($bindings->isEmpty()) { $bindings = PurposeBinding::where('purpose_code',$purpose)->whereNull('owner_company_id')->get(); }
        $bindings = $bindings->where('is_active',true)->sortBy('priority');
        if ($bindings->isEmpty()) { return new CommitteeResolution(ResolutionStatus::NOT_FOUND,reasons:['NO_BINDING'],asOf:$date); }
        foreach ($bindings as $binding) {
            $type = CommitteeType::find($binding->committee_type_id);
            if (! $type) { continue; }
            $target = $scope; $visited = []; $via = 'EXACT';
            do {
                $key = $target->type.':'.$target->id;
                if (isset($visited[$key])) { break; } $visited[$key] = true;
                $found = $this->repository->activeInScope([$type->id],$target,$date)->where('owner_company_id',$company);
                if ($found->count() > 1) {
                    return new CommitteeResolution($type->allow_concurrent ? ResolutionStatus::AMBIGUOUS : ResolutionStatus::CONFLICT,candidates:$found->map(fn ($c) => $this->queries->view($c,$asOf))->all(),resolvedVia:$via,reasons:['MULTIPLE_COMMITTEES'],asOf:$date);
                }
                if ($found->count() === 1) {
                    $c = $found->first(); $health = $this->queries->health($c->id,$asOf);
                    return new CommitteeResolution($health->status === HealthStatus::INOPERABLE ? ResolutionStatus::INOPERABLE : ResolutionStatus::FOUND,$this->queries->view($c,$asOf),resolvedVia:$via,reasons:$health->issues,asOf:$date);
                }
                $target = $binding->allow_ancestor_fallback ? $this->scopes->get($target->type)->parent($target->id) : null;
                if ($target) { $via = 'ANCESTOR:'.$target->type; }
            } while ($target && count($visited) < 10);
        }
        return new CommitteeResolution(ResolutionStatus::NOT_FOUND,reasons:['NO_COVERAGE'],asOf:$date);
    }
    public function resolveAll(string $purpose, ScopeRef $scope, \DateTimeInterface $asOf): CommitteeResolution { return $this->resolve($purpose,$scope,$asOf); }
}
