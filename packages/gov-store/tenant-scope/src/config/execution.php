<?php

return [
    // Installation maintenance runs with no inherited HTTP actor/office. These
    // commands retain their own confirmation, actor and business validation.
    'global_commands' => [
        'gov-store:sync-memberships', 'govstore:metadata-sync-phase2', 'govstore:metadata-health',
        'govstore:metadata-converge', 'govstore:rebuild-projections', 'govstore:tracking-audit',
        'govstore:repair-ledger', 'govstore:protect-attachments',
        // Read-only balance diagnostic; no inherited actor or inventory writes.
        'govstore:ledger-reconcile',
        // Existing scheduled housekeeping only: expires draft baskets and marks
        // overdue stages; does not grant access or mutate native inventory.
        'gov-requests:maintain',
        // Read-only diagnostic; requires an existing --office and filters each
        // unscoped inventory/ledger query by that explicit office (no writes).
        'gov-requests:reconcile',
        'govstore:ledger-open', 'govstore:experiment', 'govstore:experiment-worker',
        'committee:expire', 'committee:health', 'committee:verify-ledger',
    ],
];
