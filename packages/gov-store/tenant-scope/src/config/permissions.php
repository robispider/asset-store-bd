<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Responsibility-to-Profile Mappings
    |--------------------------------------------------------------------------
    |
    | Maps local office role slugs (from 'gov_office_responsibilities') to 
    | abstract capability profiles.
    |
    */
    'responsibilities' => [
        'storekeeper'      => 'inventory_operator',
        'primary_approver' => 'workflow_operator',
        'final_approver'   => 'workflow_operator',
        'office_admin'     => 'office_operations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Capability Profiles & Permissions
    |--------------------------------------------------------------------------
    |
    | Defines the granular permissions associated with each capability profile.
    | These match the permission strings expected by both custom Laravel 
    | Policies and translated Snipe-IT UI Gates.
    |
    */
    'profiles' => [
        'inventory_operator' => [
            'assets.view',
            'assets.checkout',
            'assets.checkin',
            'consumables.view',
            'consumables.checkout',
            'accessories.view',
            'accessories.checkout',
            'accessories.checkin',
        ],
        
        'workflow_operator' => [
            'requests.view',
            'requests.approve',
            'requests.reject',
        ],
        
       'office_operations' => [
            'office.configure',
            'office.assign_responsibilities',
            'users.view',
            'users.create',
            'users.edit',
            'locations.view',
        ],
        
        'employee' => [
            'catalog.view',
            'catalog.request',
        ],
    ],

];