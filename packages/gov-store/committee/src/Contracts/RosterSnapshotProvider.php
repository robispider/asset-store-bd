<?php

namespace GovStore\Committee\Contracts;

interface RosterSnapshotProvider
{
    public function snapshot(string $committeeId, \DateTimeInterface $asOf): \GovStore\Committee\DTOs\RosterSnapshot;
    public function verify(\GovStore\Committee\DTOs\RosterSnapshot $snapshot): \GovStore\Committee\DTOs\SnapshotVerification;
}

