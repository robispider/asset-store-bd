<?php

namespace GovStore\OfficeMembership\Services;

class ClearanceResult
{
    public bool $isPassed;
    public string $reason;

    public function __construct(bool $isPassed, string $reason = '')
    {
        $this->isPassed = $isPassed;
        $this->reason = $reason;
    }
}