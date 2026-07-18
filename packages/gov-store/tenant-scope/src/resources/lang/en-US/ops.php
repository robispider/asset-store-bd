<?php

return [

    // ==========================================
    // CONTROLLER FLASH MESSAGES
    // ==========================================
    'unauthorized_access' => 'Unauthorized. Data isolation settings require system superadministrator privileges.',
    'strategy_saved'      => 'Global Scoping Policies successfully saved.',
    'mapping_created'     => 'Data isolation boundary mapped successfully.',
    'mapping_deleted'     => 'Scoping boundary rule permanently deleted.',

    // ==========================================
    // DASHBOARD VIEW
    // ==========================================
    'dashboard_title'             => 'Data Isolation Dashboard',
    'stat_total_mappings'         => 'Total Scoping Mappings',
    'stat_ministry_scoped'        => 'Ministry-Scoped Items',
    'stat_office_scoped'          => 'Office-Scoped Items',
    'stat_active_policies'        => 'Active Policies',
    'footer_view_grid'            => 'View Grid',
    'footer_filter_ministry'      => 'Filter Ministry',
    'footer_filter_office'        => 'Filter Office',
    'footer_configure_policies'   => 'Configure Policies',
    'quick_actions_title'         => 'Quick Actions',
    'action_scoping_explorer'     => 'Scoping Explorer',
    'action_policy_configurator'  => 'Policy Configurator',
    'recent_actions_title'        => 'Recent Scoping Actions',
    'table_item_name'             => 'Item Name',
    'table_scoped_target'         => 'Scoped Target',
    'table_date'                  => 'Date',
    'empty_no_actions'            => 'No scoping actions executed yet.',

    // ==========================================
    // CONFIG VIEW
    // ==========================================
    'config_title'                => 'Global Scoping Policies',
    'config_header'               => 'Reference Scoping Strategy Policies',
    'config_description'          => 'Specify which spatial or corporate boundary limits apply to each catalog data model.',
    'label_catalog_type'          => 'Catalog Reference Type',
    'label_isolation_boundary'    => 'Isolation Boundary',
    'label_show_only_used'        => '"Show Only Used"',
    'strategy_global'             => '🌎 Global (Shared by all)',
    'strategy_company'            => '🏛 Company (Ministry scoped)',
    'strategy_location'           => '📍 Office (Local building scoped)',
    'btn_save_policies'           => 'Save Scoping Policies',

    // ==========================================
    // EXPLORER VIEW
    // ==========================================
    'explorer_title'              => 'Boundary Mappings Explorer',
    'btn_assign_mapping'          => 'Assign New Mapping Rule',
    'filter_header'               => 'Filter Grid',
    'filter_reference_type'       => 'Reference Type',
    'filter_all_types'            => '-- All Types --',
    'filter_scope_type'           => 'Scope Type',
    'filter_all_scopes'           => '-- All Scopes --',
    'filter_company'              => 'Company (Ministry)',
    'filter_location'             => 'Location (Office)',
    'btn_reset'                   => 'Reset',
    'btn_apply_filters'           => 'Apply Filters',
    'table_reference_item'        => 'Reference Item',
    'table_scoped_boundary'       => 'Scoped Scope Boundary',
    'table_action'                => 'Action',
    'label_type'                  => 'Type:',
    'empty_no_mappings'           => 'No private mapping rules found.',

    // ==========================================
    // EXPLORER MODAL
    // ==========================================
    'modal_title'                 => 'Map Private Scoping Bounds',
    'modal_step1_label'           => '1. Choose Catalog Type',
    'modal_step1_placeholder'     => '-- Choose Type --',
    'modal_option_category'       => 'Product Category',
    'modal_option_model'          => 'Brand & Model',
    'modal_step2_label'           => '2. Select Specific Item',
    'modal_step2_placeholder'     => '-- Select type first --',
    'modal_step3_label'           => '3. Choose Scoping Level',
    'modal_step3_placeholder'     => '-- Choose Scope --',
    'modal_option_company'        => 'Company (Ministry Scope)',
    'modal_option_location'       => 'Location (Local Office Scope)',
    'modal_step4_label'           => '4. Map Scoped Boundary Target',
    'modal_step4_placeholder'     => '-- Select scope level first --',
    'btn_cancel'                  => 'Cancel',
    'btn_lock_reference'          => 'Lock Reference',
    'btn_delete'                  => 'Delete',
    'confirm_revoke'              => 'Revoke this data isolation limit?',

    // ==========================================
    // INDEX VIEW (COMBINED DASHBOARD)
    // ==========================================
    'index_title'                 => 'Tenant Scoping & Data Isolation',
    'index_header'                => 'Reference Scoping Strategy Policies',
    'index_description'           => 'Specify which spatial or corporate boundary limits apply to each catalog data model.',
    'index_explicit_map'          => 'Explicit Scoping Boundaries Map',
    'index_empty_boundaries'      => 'No private boundaries mapped yet. All items default to Global.',
    'index_map_header'            => 'Map Private Scoping Bounds',
    'index_btn_lock'              => 'Lock Reference to Boundary',

    // ==========================================
    // SIDEBAR / MENU LABELS
    // ==========================================
    'sidebar_tenant_scoping'      => 'Tenant Scoping',
    'menu_gov_store'              => 'Government Store',
    'menu_multitenant_admin'      => 'Multitenant Administration',
    'menu_scoping_dashboard'      => 'Scoping Dashboard',
    'menu_policy_configurator'    => 'Policy Configurator',
    'menu_boundary_explorer'      => 'Boundary Explorer',

    // ==========================================
    // SERVICE EXCEPTION MESSAGES (user-facing)
    // ==========================================
    'exception_ownership_denied'  => 'Access Denied: Your assigned office does not hold ownership rights to modify this :model.',
    'exception_checkout_violation'=> 'Security Violation: You do not hold active storekeeper responsibility inside this office context to execute checkouts.',
    'exception_out_of_bounds'     => 'Access Denied: The target item belongs to another office context.',
    'exception_not_found'         => 'Setup Error: The selected :column (ID: :id) does not exist in the database.',
    'exception_relationship'      => 'Security Violation: You are not authorized to assign :item \'[:name]\' to this resource. It lies outside your active data boundary.',
    'exception_deletion_guard'    => 'Data Integrity Guard: You cannot delete this :model because it is currently assigned to one or more active items in the inventory.',

    // ==========================================
    // AJAX PLACEHOLDERS
    // ==========================================
    'ajax_placeholder_reference'  => 'Search reference lists...',
    'ajax_placeholder_scope'      => 'Search boundary targets...',

];
