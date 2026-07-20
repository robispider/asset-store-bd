<?php

return [

    // =========================================================================
    // Origin: ConfigurationController.php
    // =========================================================================
    'configuration_controller_access_denied' => 'Access Denied: You are not assigned as an Office Administrator.',
    'configuration_controller_roles_saved'   => 'Office roles successfully saved.',
    'configuration_controller_config_error'  => 'Configuration error: :message',

    // =========================================================================
    // Origin: MinistryDirectoryController.php
    // =========================================================================
    'directory_controller_unauthorized'      => 'Unauthorized access to the Government Directory Configurator.',
    'directory_controller_import_unauthorized' => 'Unauthorized.',
    'directory_controller_sync_success'      => 'Government Directory Synchronized! Records Synced: :processed, Companies Created: :created, Companies Matched: :matched.',
    'directory_controller_warnings_count'    => 'Warnings generated: :count',
    'directory_controller_import_failed'     => 'Import failed: :message',

    // =========================================================================
    // Origin: OfficeHubController.php
    // =========================================================================
    'hub_controller_access_denied_not_profiled' => 'Access Denied: This office building is not profiled.',
    'hub_controller_not_configured'             => 'This office building has not been configured with geographic territory parameters yet. Please provision it first.',
    'hub_controller_profile_not_found'          => 'Office profile details not found.',
    'hub_controller_update_success'             => 'Office profiles updated successfully.',
    'hub_controller_roles_saved'                => 'Office role configuration saved.',
    'hub_controller_geo_verified'               => 'Geographic tag accuracy verified and locked.',
    'hub_controller_geo_verify_success'         => 'Geographical territory tagged and verified successfully.',

    // =========================================================================
    // Origin: OnboardLocationController.php
    // =========================================================================
    'onboard_controller_unauthorized'          => 'Unauthorized administrative request.',
    'onboard_controller_onboarded_success'     => 'Existing office successfully onboarded.',

    // =========================================================================
    // Origin: ProvisioningController.php
    // =========================================================================
    'provisioning_controller_unauthorized'     => 'Unauthorized administrative request.',
    'provisioning_controller_provisioned'      => 'Office successfully provisioned.',
    'provisioning_controller_admin_updated'    => 'Office Administrator updated.',
    'provisioning_controller_update_error'     => 'Update error: :message',
    'provisioning_controller_jurisdiction_mapped' => 'ICT Officer boundary successfully mapped.',
    'provisioning_controller_mapping_error'    => 'Mapping error: :message',
    'provisioning_controller_jurisdiction_revoked' => 'ICT Officer jurisdiction revoked.',
    'provisioning_controller_revocation_error' => 'Revocation error: :message',

    // =========================================================================
    // Origin: OfficeProvisioningService.php
    // =========================================================================
    'provisioning_service_access_denied_boundary' => 'Access Denied: The chosen territory lies outside of your assigned geographical jurisdiction.',
    'provisioning_service_duplicate_notice'     => 'Notice: An office belonging to this Department/Ministry is already registered within this geographic territory.',

    // =========================================================================
    // Origin: OfficeReadinessService.php
    // =========================================================================
    // (No user-facing strings in this service — all logic is internal)

    // =========================================================================
    // Origin: MinistryDirectoryImporter.php
    // =========================================================================
    'importer_csv_not_found'                  => 'The specified CSV dataset was not found at path: :path',
    'importer_circular_relationship'          => 'Circular relationship ignored on ID :id (:name).',
    'importer_unresolved_parent'              => 'Unresolved parent reference \' :parent\' on ID :id.',

    // =========================================================================
    // Origin: OfficeRegistryViewModel.php
    // =========================================================================
    'view_model_root_office'                  => 'Root Office',
    'view_model_unmapped'                     => 'Unmapped',
    'view_model_standalone'                   => 'Standalone Office',
    'view_model_unassigned'                   => 'Unassigned',

    // =========================================================================
    // Origin: EnsureOfficeIsOperational.php (Middleware)
    // =========================================================================
    'middleware_operational_access_denied'    => 'Access Denied: Office provisioning requires an active ICT Officer jurisdiction assignment.',

    // =========================================================================
    // Origin: EnsureUserIsIctOfficer.php (Middleware)
    // =========================================================================
    'middleware_ict_check_denied'             => 'Access Denied: Office provisioning requires an active ICT Officer jurisdiction assignment.',

    // =========================================================================
    // Origin: configuration/index.blade.php
    // =========================================================================
    'config_title'                            => 'My Office Management',
    'config_status_label'                     => 'Office Status:',
    'config_checklist_designate_admin'        => 'Designate Office Administrator',
    'config_checklist_assign_approver'        => 'Assign Primary Approver',
    'config_checklist_assign_storekeeper'     => 'Assign Storekeeper',
    'config_checklist_mapped_employees'       => 'Mapped Employees (Min: 1)',
    'config_checklist_user_count_suffix'      => 'User(s)',
    'config_checklist_configured'             => 'Configured',
    'config_checklist_missing'                => 'Missing',
    'config_operational_verified'             => 'Verified. This office is active and ready to process requests.',
    'config_pending_instruction'              => 'Assign local staff roles below to activate your storefront.',
    'config_profile_title'                    => 'Office Profile',
    'config_field_physical_office'            => 'Physical Office:',
    'config_field_ministry_division'          => 'Ministry / Division:',
    'config_field_territory_tag'              => 'Territory Tag:',
    'config_field_standalone'                 => 'Standalone Office',
    'config_field_unspecified'                => 'Unspecified',
    'config_roles_title'                      => 'Assign Office Workflow Roles',
    'config_role_primary_approver'            => 'Primary Approver (Supervisor)',
    'config_role_select_employee'             => '-- Select Mapped Employee --',
    'config_help_primary_approver'            => 'Line manager responsible for checking and authorizing employee baskets first.',
    'config_role_final_approver'              => 'Final Approver (Optional)',
    'config_role_none_single_level'           => '-- None (Single Level Approval Only) --',
    'config_help_final_approver'              => 'If specified, requests automatically move to this final director after primary sign-off.',
    'config_role_storekeeper'                 => 'Storekeeper (Inventory Officer)',
    'config_help_storekeeper'                 => 'Fulfiller responsible for packing and registering physical checkout handovers.',
    'config_save_button'                      => 'Save Office Assignments',
    'config_employees_title'                  => 'Assigned Office Employees',
    'config_employee_name'                    => 'Name',
    'config_employee_username'                => 'Username',
    'config_employee_jobtitle'                => 'Job Title',
    'config_no_employees'                     => 'No employees are assigned to this location yet. Map user profiles in Snipe-IT to this Location to satisfy the checklist.',

    // =========================================================================
    // Origin: directory/index.blade.php
    // =========================================================================
    'directory_title'                         => 'Government Directory Import',
    'directory_sync_title'                    => 'Synchronize Directory',
    'directory_sync_description'              => 'This service imports the authoritative Bangladesh Government directory. It recursively builds hierarchical indexes and automatically registers matching flat Company entries inside Snipe-IT\'s core catalog.',
    'directory_sync_complete'                 => 'Synchronization Complete!',
    'directory_option_bundled_title'          => 'Option A: Run Bundled Dataset',
    'directory_option_bundled_desc'           => 'Imports the pre-verified core dataset included in the GovStore package (bangladesh_ministries_bilingual.csv).',
    'directory_option_bundled_button'         => 'Run Bundled Package Import',
    'directory_option_custom_title'           => 'Option B: Upload Custom Dataset',
    'directory_upload_label'                  => 'Upload CSV File',
    'directory_upload_button'                 => 'Upload & Synchronize Directory',
    'directory_status_title'                  => 'Registry Status',
    'directory_total_registered'              => 'Total Registered Nodes:',
    'directory_preview_title'                 => 'Recent Directory Preview (Root Level Nodes)',
    'directory_col_id'                        => 'ID',
    'directory_col_en_name'                   => 'English Name',
    'directory_col_bn_name'                   => 'Bangla Name',
    'directory_col_type'                      => 'Type',
    'directory_empty_state'                   => 'Directory has not been populated yet. Run an import on the left to begin.',

    // =========================================================================
    // Origin: provisioning/create.blade.php
    // =========================================================================
    'create_title'                            => 'Provision Government Office',
    'create_workspace_title'                  => 'Office Registration Workspace',
    'create_section_identity'                 => '1. Office Building Identity',
    'create_field_office_name'                => 'Office Name',
    'create_placeholder_office_name'          => 'e.g. Debidwar Upazila Health Complex',
    'create_section_geography'                => '2. Geographical Boundary Tag',
    'create_field_geo_area'                   => 'Administrative Territory Boundary',
    'create_placeholder_geo_area'             => '-- Type to search Division, Zila, Upazila or Union --',
    'create_help_geo_area'                    => 'Mandatory. This locks the building to its standard geo-code parameters.',
    'create_section_hierarchy'                => '3. Organizational Hierarchy & Setup',
    'create_field_ministry'                   => 'Ministry / Department Ownership (Optional)',
    'create_placeholder_standalone'           => '-- Standalone Office (No Ministry) --',
    'create_field_parent_office'              => 'Parent Regional/District Office (Optional)',
    'create_placeholder_no_parent'            => '-- No Parent (Root Location) --',
    'create_field_delegate_admin'             => 'Delegate Office Administrator (Optional)',
    'create_placeholder_leave_unassigned'     => '-- Leave Unassigned for Now --',
    'create_help_delegate_admin'              => 'The delegated administrator receives email setup credentials to configure their own workflow roles.',
    'create_button_return_registry'           => 'Return to Registry',
    'create_button_save_provision'            => 'Save & Provision Office',
    'create_duplicate_warning_title'          => 'Registry Warning: Similar Office Found',
    'create_duplicate_warning_desc'           => 'An office belonging to the selected Ministry is already registered within this geographic boundary. Verify if this is an intended separate building before saving:',
    'create_duplicate_note'                   => 'Note: This does not block registration; it acts as a data-integrity pre-check.',
    'create_guidelines_title'                 => 'Field Deployment Guidelines',
    'create_advisory_spatial_title'           => 'Spatial-First Principle',
    'create_advisory_spatial_desc'            => 'Every office physically exists somewhere. By establishing its standardized geographic territory first, you enable spatial audit tracking, proximity dispatching, and coverage density statistics.',
    'create_step1_label'                      => 'Step 1 (Office Identity):',
    'create_step1_desc'                       => 'Use standardized spellings aligned with government directories.',
    'create_step2_label'                      => 'Step 2 (Territory Tag):',
    'create_step2_desc'                       => 'Select any administrative tier (Zila, Upazila, or Union) mapped inside the platform databases.',
    'create_step3_label'                      => 'Step 3 (Ownership):',
    'create_step3_desc'                       => 'Assigning parent and ministry structures is fully optional on day-one and can be mapped during secondary configurations inside the Hub.',

    // =========================================================================
    // Origin: provisioning/hub.blade.php
    // =========================================================================
    'hub_title_prefix'                        => 'Office Hub:',
    'hub_status_operational'                  => 'OPERATIONAL',
    'hub_status_configured'                   => 'CONFIGURED',
    'hub_status_provisioned'                  => 'PROVISIONED (PENDING)',
    'hub_tab_overview'                        => 'General Info',
    'hub_tab_roles'                           => 'Workflow Roles',
    'hub_tab_employees'                       => 'Local Employees',
    'hub_tab_geography'                       => 'Spatial Integrity',
    'hub_tab_timeline'                        => 'Activity Timeline',
    'hub_field_office_name'                   => 'Office Building Name',
    'hub_field_ministry'                      => 'Ministry / Department Ownership (Optional)',
    'hub_field_parent_office'                 => 'Parent Regional / District Office (Optional)',
    'hub_field_geo_area'                      => 'Geographical Boundary Territory',
    'hub_field_office_admin'                  => 'Designated Office Administrator',
    'hub_placeholder_no_admin'                => '-- No Administrator Assigned --',
    'hub_save_button'                         => 'Save Office Details',
    'hub_checklist_admin_assigned'            => 'Office Administrator Assigned',
    'hub_checklist_primary_assigned'          => 'Primary Approver Assigned',
    'hub_checklist_storekeeper_assigned'      => 'Storekeeper Assigned',
    'hub_checklist_staff_count'               => 'Staff Count (Min: 1)',
    'hub_checklist_ready'                     => 'Ready',
    'hub_checklist_mapped'                    => 'Mapped',
    'hub_checklist_verified_passed'           => 'Verification passed. The employee storefront is active.',
    'hub_checklist_pending_unlock'            => 'Complete the outstanding items above to unlock the catalog for local employees.',
    'hub_employee_name'                       => 'Employee Name',
    'hub_employee_username'                   => 'Username',
    'hub_employee_email'                      => 'Email Address',
    'hub_employee_jobtitle'                   => 'Job Title',
    'hub_no_employees_message'                => 'No employee profiles are currently mapped to this location inside Snipe-IT. <br>To add staff, edit their User profiles natively inside Snipe-IT and assign their <strong>Location</strong> field to this building.',
    'hub_geo_mapped_district'                 => 'Mapped District (Zila):',
    'hub_geo_mapped_upazila'                  => 'Mapped Upazila/City:',
    'hub_geo_geographical_level'              => 'Geographical Level:',
    'hub_geo_hierarchy_path'                  => 'Hierarchy ID Path:',
    'hub_geo_unassigned'                      => 'Unassigned',
    'hub_geo_admin_verification'              => 'Administrative Verification',
    'hub_geo_verified_title'                  => 'Geographic Coordinates Verified',
    'hub_geo_signoff_label'                   => 'Sign-off executed on:',
    'hub_geo_audited_by'                      => 'Audited by:',
    'hub_geo_system_administrator'            => 'System Administrator',
    'hub_geo_not_verified'                    => 'Geographic territory has not been verified yet.',
    'hub_geo_verify_button'                   => 'Verify Geographic Tag Accuracy',
    'hub_activity_title'                      => 'Activity Timeline',
    'hub_activity_empty'                      => 'No activity has been logged for this office yet.',
    'hub_activity_col_timestamp'              => 'Timestamp',
    'hub_activity_col_event'                  => 'Event Type',
    'hub_activity_col_performer'              => 'Performed By',

    // =========================================================================
    // Origin: provisioning/index.blade.php
    // =========================================================================
    'registry_title'                          => 'Government Office Registry',
    'registry_metric_total_offices'           => 'Total Registered Offices',
    'registry_metric_operational'             => 'Operational Offices',
    'registry_metric_pending'                 => 'Configuration Pending',
    'registry_metric_ministries'              => 'Ministries Engaged',
    'registry_search_label'                   => 'Search:',
    'registry_search_placeholder'             => 'Office name or admin...',
    'registry_ministry_label'                 => 'Ministry:',
    'registry_all_ministries'                 => '-- All Ministries --',
    'registry_district_label'                 => 'District:',
    'registry_all_districts'                  => '-- All Districts --',
    'registry_status_label'                   => 'Status:',
    'registry_all_statuses'                   => '-- All Statuses --',
    'registry_status_operational'             => 'Operational',
    'registry_status_configured'              => 'Configured',
    'registry_status_provisioned'             => 'Provisioned (Pending)',
    'registry_filter_button'                  => 'Filter',
    'registry_reset_button'                   => 'Reset',
    'registry_onboard_button'                 => 'Onboard Existing Location',
    'registry_create_button'                  => 'Provision New Office',
    'registry_table_offices_count'            => 'Registered Government Offices',
    'registry_col_office_building'             => 'Office Building',
    'registry_col_administrative_territory'   => 'Administrative Territory',
    'registry_col_owning_ministry'            => 'Owning Ministry',
    'registry_col_office_administrator'       => 'Office Administrator',
    'registry_col_readiness_status'           => 'Readiness Status',
    'registry_col_actions'                    => 'Actions',
    'registry_parent_label'                   => 'Parent:',
    'registry_status_operational_label'       => 'Operational',
    'registry_status_configured_label'        => 'Configured',
    'registry_status_provisioned_label'       => 'Provisioned',
    'registry_status_needs_primary'           => 'Needs:',
    'registry_status_unconfigured'            => 'Unconfigured',
    'registry_onboard_office_button'          => 'Onboard Office',
    'registry_onboard_tooltip'                => 'Map Geography to this Location',
    'registry_view_hub_button'                => 'View Hub',
    'registry_view_hub_tooltip'               => 'Open Office Hub',
    'registry_empty_state'                    => 'No government offices found matching your criteria.',

    // =========================================================================
    // Origin: provisioning/jurisdictions.blade.php
    // =========================================================================
    'jurisdictions_title'                     => 'ICT Officer Jurisdictions',
    'jurisdictions_map_title'                 => 'Map ICT Officer Boundary',
    'jurisdictions_select_employee_label'     => 'Select Employee User',
    'jurisdictions_select_employee_placeholder' => '-- Select Employee --',
    'jurisdictions_help_employee'             => 'Select the employee user account who will act as the field ICT Officer.',
    'jurisdictions_jurisdiction_label'        => 'Operational Jurisdiction Boundary',
    'jurisdictions_jurisdiction_placeholder'  => '-- Start typing to search --',
    'jurisdictions_help_jurisdiction'         => 'Assign this officer to a specific Division, District, or Upazila. They can only provision offices within this bound.',
    'jurisdictions_save_button'               => 'Save & Delegate Officer',
    'jurisdictions_assigned_title'            => 'Mapped ICT Provisioning Officers',
    'jurisdictions_col_officer_details'       => 'Officer Details',
    'jurisdictions_col_home_office'           => 'Home Office Base',
    'jurisdictions_col_jurisdiction_boundary' => 'Assigned Jurisdiction Boundary',
    'jurisdictions_col_action'                => 'Action',
    'jurisdictions_username_label'            => 'Username:',
    'jurisdictions_no_home_office'            => 'No home office set',
    'jurisdictions_unmapped'                  => 'Unmapped',
    'jurisdictions_revoke_confirm'            => 'Revoke geographic provisioning privileges for this user?',
    'jurisdictions_revoke_button'             => 'Revoke',
    'jurisdictions_empty_state'               => 'No ICT Officers mapped in the database yet.',

    // =========================================================================
    // Origin: provisioning/onboard.blade.php
    // =========================================================================
    'onboard_title'                           => 'Onboard Existing Snipe-IT Location',
    'onboard_workspace_title'                 => 'Map Existing Office Location',
    'onboard_section_mapped_office'           => '1. Mapped Office Building',
    'onboard_field_select_location_label'     => 'Select Snipe-IT Location',
    'onboard_placeholder_choose_unprovisioned' => '-- Choose unprovisioned building --',
    'onboard_help_unprovisioned'              => 'This lists only active Snipe-IT Locations currently missing geographic configuration mappings.',
    'onboard_section_geography'               => '2. Geographical Boundary Tag',
    'onboard_field_geo_area_label'            => 'Administrative Territory Boundary',
    'onboard_placeholder_search_geo'          => '-- Search and select Division, Zila, Upazila or Union --',
    'onboard_section_hierarchy'               => '3. Organizational Hierarchy & Setup',
    'onboard_field_ministry_label'            => 'Ministry / Department Ownership (Optional)',
    'onboard_placeholder_standalone'          => '-- Leave Standalone (No Ministry override) --',
    'onboard_field_admin_label'               => 'Assign Office Administrator (Optional)',
    'onboard_placeholder_leave_unassigned'    => '-- Leave Unassigned for Now --',
    'onboard_button_return_registry'          => 'Return to Registry',
    'onboard_button_onboard_map'              => 'Onboard & Map Office',
    'onboard_guidelines_title'                => 'Onboarding Guidelines',
    'onboard_guidelines_desc'                 => 'This panel allows you to integrate pre-existing, legacy Snipe-IT Location records into your new geographical workspace model.',
    'onboard_guidelines_point1'               => 'Selecting an existing building maps it to its physical territory in <code>gov_geo_areas</code>.',
    'onboard_guidelines_point2'               => 'This process does **not** duplicate the building inside Snipe-IT\'s core directories; it enriches it with spatial context.',

    // =========================================================================
    // Origin: readiness/unassigned.blade.php
    // =========================================================================
    'unassigned_title'                        => 'Office Location Missing',
    'unassigned_message'                      => 'Your user account is not currently mapped to an active office location in the database. You must be assigned to an office location before you can view the catalog and submit requests.',
    'unassigned_help'                         => 'Please contact your local Office Administrator or ICT Officer to update your profile location inside Snipe-IT.',
    'unassigned_return_home'                  => 'Return Home',

    // =========================================================================
    // Origin: readiness/waiting.blade.php
    // =========================================================================
    'waiting_title'                           => 'Office Awaiting Activation',
    'waiting_heading'                         => 'Office Activation Pending',
    'waiting_message'                         => 'The <strong>:name</strong> is currently mapped in the system but has not completed operational setup. The catalog will unlock once the following checklist is completed:',
    'waiting_checklist_admin_designated'      => 'Office Administrator Designated',
    'waiting_checklist_completed'             => 'Completed',
    'waiting_checklist_primary_approver'      => 'Primary Approver (Supervisor)',
    'waiting_checklist_assigned'              => 'Assigned',
    'waiting_checklist_awaiting_setup'        => 'Awaiting Setup',
    'waiting_checklist_storekeeper'           => 'Storekeeper (Inventory Officer)',
    'waiting_checklist_assigned_staff'        => 'Assigned Staff (Min: 1)',
    'waiting_who_can_activate'                => 'Who can activate this?',
    'waiting_contact_admin'                   => 'Contact your Office Administrator:',
    'waiting_return_dashboard'                => 'Return to Main Dashboard',

    // ==========================================
    // 19. Company Administrator Assignments
    // ==========================================
    
    'company_admin_title' => 'Company Administrators',
    'company_admin_assign_title' => 'Assign Ministry Administrator',
    'company_admin_select_user' => 'Select Employee User',
    'company_admin_help_user' => 'Select the employee account who will act as the Global Administrator for this Ministry.',
    'company_admin_select_company' => 'Select Ministry / Department',
    'company_admin_help_company' => 'This employee will be granted cross-regional administrative oversight over all offices, staff, and assets belonging to this specific Ministry.',
    'company_admin_btn_save' => 'Assign Administrator',
    'company_admin_list_title' => 'Active Ministry Administrators',
    'company_admin_col_user' => 'Administrator Details',
    'company_admin_col_company' => 'Assigned Ministry',
    'company_admin_col_home_office' => 'Home Office Base',
    'company_admin_col_action' => 'Action',
    'company_admin_btn_revoke' => 'Revoke',
    'company_admin_confirm_revoke' => 'Revoke organizational oversight privileges for this user?',
    'company_admin_empty_state' => 'No Company Administrators mapped in the database yet.',
    
    // Controller Messages
    'company_admin_unauthorized' => 'Unauthorized. Administrative assignments require superadministrator privileges.',
    'company_admin_assigned_success' => 'Company Administrator successfully assigned.',
    'company_admin_revoked_success' => 'Company Administrator privileges revoked.',

    // ==========================================
    // Sidebar / Menu Integration
    // ==========================================
    'menu_provisioning_root'   => 'Office Provisioning',
    'menu_office_registry'     => 'Office Registry',
    'menu_ict_jurisdictions'   => 'ICT Jurisdictions',
    'menu_company_admins'      => 'Assign Company Admins',
    'menu_office_setup'        => 'My Office Setup',
    'menu_gov_directory'       => 'Government Directory',

];
