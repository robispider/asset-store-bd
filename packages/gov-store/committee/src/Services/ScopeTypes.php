<?php
namespace GovStore\Committee\Services;
class ScopeTypes implements \GovStore\Committee\Contracts\ScopeTypeRegistry
{
    public function __construct(private Registries $registry) {}
    public function register(string $key, \GovStore\Committee\Contracts\ScopeTypeResolver $resolver): void { $this->registry->registerScope($key, $resolver); }
    public function get(string $key): \GovStore\Committee\Contracts\ScopeTypeResolver { return $this->registry->scope($key); }
    public function keys(): array { return $this->registry->scopeKeys(); }
}

