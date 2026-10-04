<?php

namespace GovStore\Committee\Policies;

use GovStore\Committee\Contracts\ScopeTypeRegistry;
use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Models\Committee;
use GovStore\Committee\Scopes\CommitteeBoundaryScope;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;

class CommitteePolicy
{
    public function __construct(private TenantContext $context, private GovAccess $access, private CommitteeBoundaryScope $boundary, private ScopeTypeRegistry $scopes) {}

    public function ability(string $ability): void
    {
        abort_unless($this->access->permitsRequest(auth()->user(),$ability),403);
    }
    public function check(Committee $committee, string $ability, bool $write = false, array $states = []): void
    {
        $this->ability($ability);
        abort_unless($this->boundary->apply(Committee::whereKey($committee->id))->exists(),404);
        if ($write) {
            // Even a company administrator changes individual committees only in the selected office.
            abort_unless($this->context->locationId === (int)$committee->owner_location_id
                && $this->context->companyId === (int)$committee->owner_company_id,404);
        }
        abort_if($states && ! in_array($committee->status,$states,true),409);
    }
    public function scope(ScopeRef $scope): void
    {
        abort_unless(in_array($scope->type,$this->scopes->keys(),true),422);
        $resolver = $this->scopes->get($scope->type);
        abort_unless($resolver->exists($scope->id),404);
        abort_unless($resolver->ownerCompanyId($scope->id) === $this->context->companyId,404);
        $office = $resolver->ownerLocationId($scope->id);
        if ($office) {
            abort_unless(in_array($office,$this->context->allowedLocationIds ?? [$this->context->locationId],true),404);
        } else {
            abort_unless(in_array('company_admin',$this->access->roles(auth()->user()),true) || auth()->user()->isSuperUser(),404);
        }
    }
}
