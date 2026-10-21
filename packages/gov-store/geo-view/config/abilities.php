<?php

// Merged into tenant-scope's `govstore-abilities` (labels: tenant-scope access.php `abilities.geo_monitor_view`).
// Each data source still checks its own ability and scope; this one only opens the national page (plan F).
return [
    'geo.monitor.view' => ['roles' => ['superuser', 'ict_officer', 'company_admin', 'office_admin'], 'enforce' => true],
    // Area names and shapes are open reference data (the picker intent).
    'geo.areas.view' => ['roles' => ['authenticated'], 'enforce' => true],
];
