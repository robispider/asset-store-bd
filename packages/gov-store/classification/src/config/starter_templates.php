<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Starter Templates to Collection Mappings
    |--------------------------------------------------------------------------
    | Automatically apply these curations when a specific type of office
    | is provisioned in the Organization module.
    */
    
    'office_types' => [
        'hospital' => [
            'Medical & Clinical Equipment',
            'ICT & Computing',
            'Office Furniture',
            'Janitorial & Sanitation'
        ],
        'school' => [
            'Educational Materials',
            'ICT & Computing',
            'Office Furniture',
            'Sports & Recreation'
        ],
        'ict_office' => [
            'ICT & Computing',
            'Office Furniture',
            'Server & Networking'
        ],
        'default' => [
            'Office Furniture',
            'Basic Stationery'
        ]
    ]
];