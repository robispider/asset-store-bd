<?php

namespace GovStore\Committee\Contracts;

interface ScopeTypeRegistry
{
    public function register(string $key, ScopeTypeResolver $resolver): void;
    public function get(string $key): ScopeTypeResolver;
    public function keys(): array;
}

