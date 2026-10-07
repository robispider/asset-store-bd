<?php

namespace GovStore\Committee\Contracts;

interface PurposeRegistry
{
    public function declare(\GovStore\Committee\DTOs\PurposeDefinition $purpose): void;
    public function get(string $code): ?\GovStore\Committee\DTOs\PurposeDefinition;
    public function all(): array;
}

