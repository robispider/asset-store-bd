<?php

namespace GovStore\Committee\Events;

final readonly class CommitteeActivated implements \Illuminate\Contracts\Events\ShouldDispatchAfterCommit
{
    public function __construct(public ?string $committeeId, public string $lineageId, public ?int $actorId, public string $occurredAt, public array $data = []) {}
}

