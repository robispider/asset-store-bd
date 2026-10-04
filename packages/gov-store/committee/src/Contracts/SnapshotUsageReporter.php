<?php

namespace GovStore\Committee\Contracts;

interface SnapshotUsageReporter
{
    public function snapshotsTakenAfter(string $committeeId, \DateTimeInterface $date): array;
}

