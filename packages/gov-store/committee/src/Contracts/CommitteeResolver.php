<?php

namespace GovStore\Committee\Contracts;

interface CommitteeResolver
{
    public function resolve(string $purpose, \GovStore\Committee\DTOs\ScopeRef $scope, \DateTimeInterface $asOf): \GovStore\Committee\DTOs\CommitteeResolution;
    public function resolveAll(string $purpose, \GovStore\Committee\DTOs\ScopeRef $scope, \DateTimeInterface $asOf): \GovStore\Committee\DTOs\CommitteeResolution;
}

