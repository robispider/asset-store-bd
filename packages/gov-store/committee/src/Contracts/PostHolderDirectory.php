<?php

namespace GovStore\Committee\Contracts;

interface PostHolderDirectory
{
    public function currentHolder(string $postTitle, int $locationId, \DateTimeInterface $asOf): ?int;
}

