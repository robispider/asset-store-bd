<?php

namespace GovStore\Metadata\Events;

/** Dispatched inside the writing transaction with the before/after payloads of one subject + namespace. */
class MetadataValueChanged
{
    public function __construct(
        public string $subjectType,
        public int $subjectId,
        public string $namespace,
        public ?array $before,
        public array $after,
        public int $revision,
        public ?int $actorId,
        public ?string $reason = null,
    ) {}
}
