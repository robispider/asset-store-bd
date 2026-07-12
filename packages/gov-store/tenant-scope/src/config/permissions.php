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
        'ict_officer'      => 'ict_operations',
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
            // Custom Business Policy Keys
            'inventory.adjust',
            
            // --- NATIVE SNIPE-IT ASSETS ---
            'assets.view',
            'assets.create',
            'assets.edit',
            'assets.checkout',
            'assets.checkin',
            'assets.audit',
            'assets.files',
            
            // --- NATIVE SNIPE-IT CONSUMABLES ---
            'consumables.view',
            'consumables.create',
            'consumables.edit',
            'consumables.checkout',
            'consumables.files',
            
            // --- NATIVE SNIPE-IT ACCESSORIES ---
            'accessories.view',
            'accessories.create',
            'accessories.edit',
            'accessories.checkout',
            'accessories.checkin',
            'accessories.files',
            
            // --- NATIVE SNIPE-IT COMPONENTS ---
            'components.view',
            'components.create',
            'components.edit',
            'components.checkout',
            'components.checkin',
            'components.files',
            
            // --- NATIVE SNIPE-IT LICENSES ---
            'licenses.view',
            'licenses.create',
            'licenses.edit',
            'licenses.checkout',
            'licenses.checkin',
            'licenses.keys',
            'licenses.files',
            
            // --- NATIVE SNIPE-IT REFERENCE CATALOGS ---
            'models.view',
            'models.create',
            'models.edit',
            'models.files',
            'categories.view',
            'categories.create',
            'categories.edit',
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.files',
            'manufacturers.view',
            'manufacturers.create',
            'manufacturers.edit',
            'statuslabels.view',
            'customfields.view',
            'reports.view',
        ],
        
        'workflow_operator' => [
            // Custom Business Policy Keys
            'requests.approve',
            'requests.reject',
            
            // --- NATIVE SNIPE-IT READ-ONLY OVER INVENTORY ---
            'assets.view',
            'consumables.view',
            'accessories.view',
            'components.view',
            'licenses.view',
            'models.view',
            'categories.view',
            'reports.view',
        ],
        
        'office_operations' => [
            // Custom Business Policy Keys
            'office.configure',
            'office.assign_responsibilities',
            
            // --- NATIVE SNIPE-IT ADMINISTRATIVE CONTROLS ---
            'users.view',
            'users.create',
            'users.edit',
            'users.files',
            'locations.view',
            'locations.create',
            'locations.edit',
            'locations.files',
            'departments.view',
            'departments.create',
            'departments.edit',
            'departments.files',
            'companies.view',
            
            // --- NATIVE SNIPE-IT READ-ONLY OVER INVENTORY ---
            'assets.view',
            'accessories.view',
            'consumables.view',
            'components.view',
            'licenses.view',
            'models.view',
            'categories.view',
            'reports.view',
        ],
        
        'ict_operations' => [
            // Custom Business Policy Keys
            'office.provision',
            'office.onboard',
            'jurisdictions.manage',

            // --- NATIVE SNIPE-IT SETUP CONTROLS ---
            'users.view',
            'users.create',
            'users.edit',
            'locations.view',
            'locations.create',
            'locations.edit',
            'departments.view',
            'departments.create',
            'departments.edit',
            'companies.view',

            // --- NATIVE SNIPE-IT READ-ONLY REFERENCES ---
            'models.view',
            'categories.view',
            'manufacturers.view',
            'suppliers.view',
            'statuslabels.view',
            'customfields.view',
            'assets.view',
            'accessories.view',
            'consumables.view',
            'components.view',
            'licenses.view',
            'reports.view',
        ],
        
        'employee' => [
            // Custom Business Policy Keys
            'catalog.request',
            
            // --- NATIVE SNIPE-IT SELF-SERVICE KEYS ---
            'assets.view.requestable',
            'self.checkout_assets',
            'self.edit_location',
            'self.two_factor',
            'self.api',
            'self.view_purchase_cost',
            'user-self-accounts',
        ],
    ],

];