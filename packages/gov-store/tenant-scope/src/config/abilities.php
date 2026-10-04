<?php

// Only explicitly named abilities are registered; Snipe-IT's gates stay intact.
return [
    'storeops.documents.view' => ['roles' => ['storekeeper', 'primary_approver', 'final_approver', 'office_admin']],
    'storeops.documents.draft' => ['roles' => ['storekeeper']],
    'storeops.documents.post' => ['roles' => ['storekeeper']],
    'storeops.rules.view' => ['roles' => ['superuser'], 'national' => true],
    'storeops.rules.publish' => ['roles' => ['superuser'], 'national' => true],
    'catalog.master.manage' => ['roles' => ['superuser'], 'national' => true],
    'catalog.view' => ['roles' => ['authenticated']],
    'catalog.office.adopt' => ['roles' => ['office_admin', 'storekeeper', 'company_admin']],
    'requests.submit' => ['roles' => ['authenticated']],
    'requests.approve' => ['roles' => ['primary_approver', 'final_approver']],
    'requests.fulfill' => ['roles' => ['storekeeper']],
    'requests.configure' => ['roles' => ['superuser'], 'national' => true],
    'access.view' => ['roles' => ['authenticated'], 'enforce' => true],
    'access.manage' => ['roles' => ['office_admin'], 'enforce' => true],
    'access.matrix' => ['roles' => ['superuser', 'ict_officer'], 'enforce' => true],
    'access.audit' => ['roles' => ['superuser'], 'national' => true],
    'access.shadow' => ['roles' => ['superuser', 'ict_officer'], 'enforce' => true],
];
