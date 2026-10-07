<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Contracts\{PurposeRegistry, ScopeTypeRegistry, ScopeTypeResolver, CommitteeTabRegistry};
use GovStore\Committee\DTOs\PurposeDefinition;

class Registries
{
    private array $purposes = [];
    private array $scopes = [];
    private array $tabs = [];

    public function declare(PurposeDefinition $purpose): void
    {
        if (isset($this->purposes[$purpose->code]) && $this->purposes[$purpose->code] != $purpose) {
            throw new \LogicException('Purpose already declared with a different definition.');
        }
        $this->purposes[$purpose->code] = $purpose;
    }
    // The registries are separately bound adapters; see provider.
    public function purposes(): array { return array_values($this->purposes); }
    public function purpose(string $code): ?PurposeDefinition { return $this->purposes[$code] ?? null; }
    public function scope(string $key): ScopeTypeResolver { return $this->scopes[$key] ?? throw new \InvalidArgumentException('Unknown scope type.'); }
    public function registerScope(string $key, ScopeTypeResolver $resolver): void
    {
        if (isset($this->scopes[$key])) { throw new \LogicException('Scope type already registered.'); }
        $this->scopes[$key] = $resolver;
    }
    public function scopeKeys(): array { return array_keys($this->scopes); }
    public function registerTab(string $key, string $label, string $url): void { $this->tabs[$key] = compact('key','label','url'); }
    public function tabs(): array { return array_values($this->tabs); }
}

