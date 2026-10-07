<?php

namespace GovStore\TenantScope\Services;

use Illuminate\Auth\Access\Response;

class AccessDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $ability,
        public readonly string $reason,
        public readonly array $roles = [],
    ) {}

    public function toGateResponse(): Response
    {
        return $this->allowed ? Response::allow() : Response::deny(__('tenantops::access.'.$this->reason));
    }
}
