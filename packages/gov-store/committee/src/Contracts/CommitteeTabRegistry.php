<?php

namespace GovStore\Committee\Contracts;

interface CommitteeTabRegistry
{
    public function register(string $key, string $label, string $url): void;
    public function all(): array;
}

