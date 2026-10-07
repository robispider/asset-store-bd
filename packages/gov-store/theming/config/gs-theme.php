<?php

return [
    // Configured organisation fallback (resolution step 7, §12.2).
    'default' => env('GS_THEME_DEFAULT', 'institutional-green'),

    // Users may pick their own theme unless an enforced assignment applies.
    'allow_user_choice' => (bool) env('GS_THEME_ALLOW_USER_CHOICE', true),

    // Kill switch only; access to the Lab is decided by the theming.lab.view ability.
    'lab_enabled' => (bool) env('GS_THEME_LAB', true),

    // Shipped themes. Themes are files in the release; nothing is stored in the database.
    'themes_path' => base_path('packages/gov-store/theming/themes'),

    // Built assets (gs-theme:build) and their public URL prefix.
    'build_path' => public_path('vendor/gs-theme'),
    'build_url' => 'vendor/gs-theme',

    // null = automatic: compile on request in local / staging / testing, use the built manifest elsewhere.
    'dev_assets' => env('GS_THEME_DEV_ASSETS'),

    // Permission strings appended to tenant-scope capability profiles (§12.6).
    // Only choose-only strings are honoured here; Lab access never has a permission path.
    'permission_profiles' => [
        'company_operations' => ['theming.assign.company'],
        'office_operations' => ['theming.assign.office'],
    ],

    // Number of Snipe-IT fallback chart colours exported to the Chart.js plugin for re-mapping.
    'chart_fallback_count' => 40,

    // Assignment cache lifetime (seconds). Saves bust the cache immediately.
    'cache_ttl' => 3600,
];
