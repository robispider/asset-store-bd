<?php

// Merged into tenant-scope's `govstore-abilities`, which defines the Gates and lists them in the Access matrix.
// Every admin ability is a *choose* ability: nobody can create, upload or edit a theme in the application.
return [
    'theming.appearance.self' => ['roles' => ['authenticated'], 'enforce' => true],
    'theming.assign.office' => ['roles' => ['office_admin'], 'enforce' => true],
    'theming.assign.company' => ['roles' => ['company_admin'], 'enforce' => true],
    'theming.assign.organization' => ['roles' => ['superuser'], 'national' => true],
    'theming.lab.view' => ['roles' => ['superuser'], 'national' => true, 'enforce' => true],
];
