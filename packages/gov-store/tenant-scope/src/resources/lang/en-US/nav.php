<?php

/*
| Navigation glossary: sidebar section headers, group names and tenant-scope leaves.
| Package leaves live in each package's own lang file under `nav_*` keys.
*/

return [
    // Sidebar section headers
    'zone_work' => 'Government Store',
    'zone_assets' => 'Asset management',
    'zone_admin' => 'Administration',

    // Groups (config/navigation.php)
    'workspace' => 'My workspace',
    'queues' => 'Waiting for me',
    'store' => 'Store',
    'catalogue' => 'Catalogue',
    'committees' => 'Committees',
    'tracking' => 'Programme tracking',
    'offices' => 'Offices & staff',
    'rules' => 'Rules & settings',
    'catalogue_master' => 'Catalogue master data',
    'access' => 'Access & security',
    'system' => 'System tools',

    // Tenant-scope leaves
    'enforcement_preview' => 'Enforcement preview',
    'boundaries' => 'Data boundaries',
    'boundary_policy' => 'Boundary policy',
    'boundary_explorer' => 'Boundary explorer',

    // Navbar
    'new' => 'New',
    'inbox_label' => 'Waiting for me: :count',
    'inbox_empty' => 'Nothing is waiting for you',
    'language' => 'Language',
];
