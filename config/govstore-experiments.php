<?php

return [
    'enabled' => (bool) env('GOVSTORE_EXPERIMENTS_ENABLED', false),
    'database_reset_enabled' => (bool) env('GOVSTORE_EXPERIMENT_DATABASE_RESET', false),
    'installation' => env('GOVSTORE_EXPERIMENT_INSTALLATION', 'govstore-experiment'),
    'queue' => env('GOVSTORE_EXPERIMENT_QUEUE', 'govstore-experiments'),
    'connection' => env('GOVSTORE_EXPERIMENT_QUEUE_CONNECTION', 'govstore-experiments'),
    'profiles' => [
        'quick' => ['offices' => 4, 'users' => 64, 'assets' => 120, 'consumables' => 48, 'accessories' => 32, 'components' => 16, 'models' => 12, 'categories' => 20, 'licenses' => 4, 'documents' => 12, 'requests' => 24, 'baskets' => 8, 'initiatives' => 2, 'codes' => 4, 'maintenance' => 4, 'onboarding' => 4, 'handshakes' => 4, 'catalog_nodes' => 40],
        'government' => ['offices' => 48, 'users' => 480, 'assets' => 2400, 'consumables' => 528, 'accessories' => 352, 'components' => 176, 'models' => 64, 'categories' => 64, 'licenses' => 48, 'documents' => 180, 'requests' => 264, 'baskets' => 44, 'initiatives' => 6, 'codes' => 24, 'maintenance' => 36, 'onboarding' => 24, 'handshakes' => 16, 'catalog_nodes' => 200],
        'large' => ['offices' => 240, 'users' => 2400, 'assets' => 11800, 'consumables' => 2832, 'accessories' => 1888, 'components' => 944, 'models' => 120, 'categories' => 96, 'licenses' => 240, 'documents' => 900, 'requests' => 944, 'baskets' => 236, 'initiatives' => 18, 'codes' => 72, 'maintenance' => 180, 'onboarding' => 120, 'handshakes' => 80, 'catalog_nodes' => 200],
    ],
];
