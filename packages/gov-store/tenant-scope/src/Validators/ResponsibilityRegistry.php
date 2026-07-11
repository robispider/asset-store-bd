<?php

namespace GovStore\TenantScope\Validators;

class ResponsibilityRegistry
{
    /**
     * Map of roles to explicit permitted capabilities.
     */
    protected static array $mappings = [
        'storekeeper' => [
            'checkout_assets',
            'checkin_assets',
            'adjust_stock',
            'audit_inventory'
        ],
        'primary_approver' => [
            'approve_requests',
            'reject_requests'
        ],
        'final_approver' => [
            'approve_requests',
            'reject_requests'
        ],
        'office_admin' => [
            'configure_office',
            'assign_responsibilities'
        ]
    ];

    /**
     * Verifies if a given responsibility has the authority to execute an action.
     */
    public static function can(?string $roleSlug, string $capability): bool
    {
        if (empty($roleSlug) || !isset(self::$mappings[$roleSlug])) {
            return false;
        }

        return in_array($capability, self::$mappings[$roleSlug], true);
    }
}