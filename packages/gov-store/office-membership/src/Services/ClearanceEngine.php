<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;

class ClearanceEngine
{
    protected array $rules = [];

    public function registerRule(IClearanceRule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Executes all registered rules. Returns an array of ClearanceResults.
     * Array is keyed by the rule's name.
     */
    public function runChecks(User $user, int $locationId): array
    {
        $results = [];
        foreach ($this->rules as $rule) {
            $results[$rule->getName()] = $rule->check($user, $locationId);
        }
        return $results;
    }

    /**
     * Returns true ONLY if all checks passed.
     */
    public function isCleared(array $results): bool
    {
        foreach ($results as $result) {
            if (!$result->isPassed) {
                return false;
            }
        }
        return true;
    }
}