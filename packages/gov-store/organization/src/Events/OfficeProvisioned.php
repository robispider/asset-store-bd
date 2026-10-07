<?php

namespace GovStore\Organization\Events;

use App\Models\Location;

class OfficeProvisioned
{
    public function __construct(
        public readonly Location $location,
        public readonly int $userId,
        public readonly string $officeType,
        public readonly ?int $catalogActorId = null
    ) {
    }
}
