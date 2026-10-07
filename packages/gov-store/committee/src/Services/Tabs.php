<?php
namespace GovStore\Committee\Services;
class Tabs implements \GovStore\Committee\Contracts\CommitteeTabRegistry
{
    public function __construct(private Registries $registry) {}
    public function register(string $key, string $label, string $url): void { $this->registry->registerTab($key,$label,$url); }
    public function all(): array { return $this->registry->tabs(); }
}

