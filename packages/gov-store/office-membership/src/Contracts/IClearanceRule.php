<?php

namespace GovStore\OfficeMembership\Contracts;

use App\Models\User;
use GovStore\OfficeMembership\Services\ClearanceResult;

interface IClearanceRule
{
    public function getName(): string;
    public function check(User $user, int $locationId): ClearanceResult;
}