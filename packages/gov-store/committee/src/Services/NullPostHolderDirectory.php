<?php
namespace GovStore\Committee\Services;
class NullPostHolderDirectory implements \GovStore\Committee\Contracts\PostHolderDirectory
{
    public function currentHolder(string $postTitle, int $locationId, \DateTimeInterface $asOf): ?int { return null; }
}

