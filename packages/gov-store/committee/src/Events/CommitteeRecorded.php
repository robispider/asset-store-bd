<?php

namespace GovStore\Committee\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** Minimal audit descriptor: never carries contacts, files, or lookup codes. */
final readonly class CommitteeRecorded implements ShouldDispatchAfterCommit
{
    public function __construct(public string $committeeId, public string $number, public int $officeId, public int $companyId,
        public ?int $actorId, public string $eventType, public int $ledgerId) {}
}
