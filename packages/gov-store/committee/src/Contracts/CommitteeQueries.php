<?php

namespace GovStore\Committee\Contracts;

interface CommitteeQueries
{
    public function find(string $committeeId): ?\GovStore\Committee\DTOs\CommitteeView;
    public function findByNumber(string $committeeNumber): ?\GovStore\Committee\DTOs\CommitteeView;
    public function activeOfType(string $typeCode, \GovStore\Committee\DTOs\ScopeRef $scope, \DateTimeInterface $asOf): array;
    public function membersOf(string $committeeId, \DateTimeInterface $asOf): array;
    public function isMember(int $userId, string $committeeId, \DateTimeInterface $asOf): bool;
    public function holdsRole(int $userId, string $committeeId, string $seatRole, \DateTimeInterface $asOf): bool;
    public function isPresiding(int $userId, string $committeeId, \DateTimeInterface $asOf): bool;
    public function committeesOf(int $userId, \DateTimeInterface $asOf): array;
    public function health(string $committeeId, \DateTimeInterface $asOf): \GovStore\Committee\DTOs\HealthReport;
    public function quorumOf(string $committeeId): ?int;
    public function lineage(string $lineageId): array;
}

