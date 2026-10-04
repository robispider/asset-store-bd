<?php
namespace GovStore\Committee\Services;
class Purposes implements \GovStore\Committee\Contracts\PurposeRegistry
{
    public function __construct(private Registries $registry) {}
    public function declare(\GovStore\Committee\DTOs\PurposeDefinition $purpose): void { $this->registry->declare($purpose); }
    public function get(string $code): ?\GovStore\Committee\DTOs\PurposeDefinition { return $this->registry->purpose($code); }
    public function all(): array { return $this->registry->purposes(); }
}

