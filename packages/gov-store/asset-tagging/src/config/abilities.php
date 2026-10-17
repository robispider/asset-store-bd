<?php

// Merged into tenant-scope's `govstore-abilities`, which defines the Gates and lists them in the Access matrix.
return [
    'asset-tags.office.configure' => ['roles' => ['office_admin'], 'enforce' => true],
    'asset-tags.company.codelists' => ['roles' => ['company_admin'], 'enforce' => true],
    // Lookup is open to signed-in users; the asset itself is checked object by object.
    'asset-tags.lookup' => ['roles' => ['authenticated'], 'enforce' => true],
    'asset-tags.scope.enable' => ['roles' => ['superuser'], 'national' => true, 'enforce' => true],
];
