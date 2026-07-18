<?php

return [
    // =========================================================================
    // Origin: admin/staff.blade.php
    // =========================================================================
    'staff_title_prefix' => 'Staff Management: ',
    'staff_active_label' => 'Active Office Staff',
    'staff_table_employee' => 'Employee',
    'staff_table_username' => 'Username',
    'staff_table_type' => 'Membership Type',
    'staff_table_status' => 'Status',
    'staff_unknown_employee' => 'Unknown Employee',
    'staff_no_active' => 'No active staff.',
    'staff_pending_label' => 'Pending Join Requests',
    'staff_add_external_label' => 'Add External Employee',
    'staff_add_external_hint' => 'Enter the employee\'s username and 6-character personal verification code.',
    'staff_add_username_placeholder' => 'Username',
    'staff_add_code_placeholder' => '6-Char Code',
    'staff_add_verify_button' => 'Verify & Add',
    'staff_mass_invite_label' => 'Mass Invitation Code',
    'staff_share_code_hint' => 'Share this code with employees to allow them to join.',
    'staff_no_active_code' => 'No active invitation code.',
    'staff_generate_code_button' => 'Generate New Code',
    'staff_claim_label' => 'Claim Transferred Employee',
    'staff_claim_hint' => 'Search for employees who have officially requested release from their previous home office.',
    'staff_claim_select_placeholder' => '-- Select Released Employee --',
    'staff_claim_button' => 'Approve Transfer & Claim',
    'staff_home_base_label' => 'Home Base',
    'staff_secondary_label' => 'Secondary',

    // =========================================================================
    // Origin: admin/override_console.blade.php
    // =========================================================================
    'override_console_title' => 'Emergency Override Console',
    'override_execute_label' => 'Execute Emergency Override',
    'override_target_label' => 'Target Employee',
    'override_target_placeholder' => '-- Search Employee --',
    'override_action_label' => 'Override Action',
    'override_strip_roles_option' => 'Force Strip All Operational Roles',
    'override_force_release_option' => 'Force Release Membership',
    'override_justification_label' => 'Mandatory Justification',
    'override_justification_placeholder' => 'State reason for overriding standard protocols...',
    'override_confirm_warning' => 'WARNING: This bypasses all clearance rules. Proceed?',
    'override_execute_button' => 'Execute Override',
    'override_audit_title' => 'Compliance Audit Log',
    'override_audit_date' => 'Date',
    'override_audit_executor' => 'Executor',
    'override_audit_target' => 'Target User',
    'override_audit_action' => 'Action',
    'override_audit_justification' => 'Justification',
    'override_audit_no_entries' => 'No emergency overrides executed.',

    // =========================================================================
    // Origin: provisioning/hub.blade.php
    // =========================================================================
    'hub_local_staff_title' => 'LOCAL STAFF DIRECTORY',
    'hub_table_employee_name' => 'Employee Name',
    'hub_table_username' => 'Username',
    'hub_table_email' => 'Email Address',
    'hub_table_job_title' => 'Job Title',
    'hub_no_employees' => 'No employees are mapped here.',
    'hub_claim_label' => 'Claim Incoming Employee',
    'hub_claim_hint' => 'Search for employees who have requested release from their previous office to add them to this location.',
    'hub_claim_select_placeholder' => '-- Select Released Employee --',
    'hub_claim_button' => 'Approve Transfer & Claim',

    // =========================================================================
    // Origin: provisioning/index.blade.php
    // =========================================================================
    'provisioning_registry_title' => 'Government Office Registry',
    'provisioning_metric_total' => 'Total Registered Offices',
    'provisioning_metric_operational' => 'Operational Offices',
    'provisioning_metric_pending' => 'Configuration Pending',
    'provisioning_metric_ministries' => 'Ministries Engaged',
    'provisioning_filter_search_label' => 'Search:',
    'provisioning_filter_ministry_label' => 'Ministry:',
    'provisioning_filter_district_label' => 'District:',
    'provisioning_filter_status_label' => 'Status:',
    'provisioning_filter_all_ministries' => '-- All Ministries --',
    'provisioning_filter_all_districts' => '-- All Districts --',
    'provisioning_filter_all_statuses' => '-- All Statuses --',
    'provisioning_filter_operational' => 'Operational',
    'provisioning_filter_configured' => 'Configured',
    'provisioning_filter_provisioned' => 'Provisioned (Pending)',
    'provisioning_filter_button' => 'Filter',
    'provisioning_reset_button' => 'Reset',
    'provisioning_onboard_button' => 'Onboard Existing Location',
    'provisioning_create_button' => 'Provision New Office',
    'provisioning_grid_title' => 'Registered Government Offices',
    'provisioning_grid_office' => 'Office Building',
    'provisioning_grid_territory' => 'Administrative Territory',
    'provisioning_grid_ministry' => 'Owning Ministry',
    'provisioning_grid_admin' => 'Office Administrator',
    'provisioning_grid_status' => 'Readiness Status',
    'provisioning_grid_actions' => 'Actions',
    'provisioning_grid_parent' => 'Parent:',
    'provisioning_grid_root_office' => 'Root Office',
    'provisioning_grid_type' => 'Type:',
    'provisioning_grid_standalone' => 'Standalone Office',
    'provisioning_grid_unmapped' => 'Unmapped',
    'provisioning_status_operational' => 'Operational',
    'provisioning_status_configured' => 'Configured',
    'provisioning_status_provisioned' => 'Provisioned',
    'provisioning_status_needs' => 'Needs:',
    'provisioning_status_primary' => 'Primary',
    'provisioning_status_storekeeper' => 'Storekeeper',
    'provisioning_view_hub_button' => 'View Hub',
    'provisioning_view_hub_title' => 'Open Office Hub',
    'provisioning_no_offices' => 'No government offices found matching your criteria.',

    // =========================================================================
    // Origin: provisioning/onboard.blade.php
    // =========================================================================
    'onboard_page_title' => 'Onboard Existing Snipe-IT Location',
    'onboard_map_title' => 'Map Existing Office Location',
    'onboard_section_identity' => '1. Select Registered Building',
    'onboard_location_label' => 'Select Snipe-IT Location',
    'onboard_location_placeholder' => '-- Choose unprovisioned building --',
    'onboard_location_hint' => 'This lists only active Snipe-IT Locations currently missing geographic configuration mappings.',
    'onboard_section_geography' => '2. Geographical Boundary Tag',
    'onboard_geo_label' => 'Administrative Territory Boundary',
    'onboard_geo_placeholder' => '-- Search and select Division, Zila, Upazila or Union --',
    'onboard_section_hierarchy' => '3. Organizational Hierarchy & Setup',
    'onboard_ministry_label' => 'Ministry / Department Ownership (Optional)',
    'onboard_ministry_placeholder' => '-- Leave Standalone (No Ministry override) --',
    'onboard_admin_label' => 'Assign Office Administrator (Optional)',
    'onboard_admin_placeholder' => '-- Leave Unassigned for Now --',
    'onboard_return_button' => 'Return to Registry',
    'onboard_submit_button' => 'Onboard & Map Office',
    'onboard_guidelines_title' => 'Onboarding Guidelines',
    'onboard_guidelines_text' => 'This panel allows you to integrate pre-existing, legacy Snipe-IT Location records into your new geographical workspace model.',
    'onboard_guidelines_point1' => 'Selecting an existing building maps it to its physical territory in gov_geo_areas.',
    'onboard_guidelines_point2' => 'This process does not duplicate the building inside Snipe-IT\'s core directories; it enriches it with spatial context.',
    'onboard_search_geo_placeholder' => 'Type to search Division, District, Upazila, or Union...',

    // =========================================================================
    // Origin: user/index.blade.php
    // =========================================================================
    'user_page_title' => 'My Office Memberships & Handovers',
    'user_active_memberships_title' => 'Active Office Memberships',
    'user_table_office' => 'Office Building',
    'user_table_status' => 'Membership Status',
    'user_table_clearance' => 'Clearance Rules',
    'user_table_action' => 'Action',
    'user_status_active' => 'Active',
    'user_status_home_base' => 'Home Base',
    'user_status_release_requested' => 'Release Requested',
    'user_status_released' => 'Released',
    'user_clearance_na' => 'N/A',
    'user_request_release_button' => 'Request Release',
    'user_request_release_confirm' => 'Request formal release from this office?',
    'user_locked_button' => 'Locked',
    'user_no_memberships' => 'You do not belong to any registered office.',
    'user_credential_title' => 'Office Join Credential',
    'user_credential_hint' => 'Provide your Username and this temporary Verification Code to a local Office Administrator to securely grant them permission to add you to their office.',
    'user_token_active_label' => 'Your Active Code',
    'user_token_no_active' => 'No active code',
    'user_token_regenerate' => 'Regenerate Code',
    'user_token_generate' => 'Generate Verification Code',
    'user_join_title' => 'Join an Office',
    'user_join_hint' => 'If your Office Administrator provided you with an Office Invitation Code, enter it here to request access.',
    'user_join_code_placeholder' => 'e.g. OFF-ABCD-1234',
    'user_join_send_button' => 'Send Join Request',
    'user_handover_title' => 'Action Required: Incoming Handovers',
    'user_handover_delegate_text' => 'wishes to delegate the',
    'user_handover_role_to_you_for' => 'role to you for',
    'user_handover_accept_button' => 'Accept',
    'user_handover_accept_confirm' => 'Confirm acceptance? This updates active database roles instantly.',
    'user_handover_reject_button' => 'Reject',
    'user_responsibilities_title' => 'Handover Office Responsibilities',
    'user_responsibilities_hint' => 'If you hold an active role, you cannot be released. You must delegate your role to a colleague below.',
    'user_no_active_roles' => 'You currently hold no administrative roles inside active offices.',
    'user_modal_title' => 'Propose Role Delegation Handshake',
    'user_modal_hint' => 'Select a local colleague to take over the',
    'user_modal_colleague_label' => 'Select Colleague',
    'user_modal_colleague_placeholder' => '-- Choose Colleague --',
    'user_modal_cancel_button' => 'Cancel',
    'user_modal_propose_button' => 'Propose Handover',

    // =========================================================================
    // Origin: hooks/menu-injection.blade.php
    // =========================================================================
    'menu_my_memberships' => 'My Office Memberships',
    'menu_working_as' => 'Working As:',
    'menu_global_overview' => 'Global Overview (All Offices)',
    'menu_choose_context' => 'CHOOSE WORKING CONTEXT',

    // =========================================================================
    // Origin: MembershipController.php
    // =========================================================================
    'membership_token_generated' => 'New Verification Code generated successfully. It will expire in 24 hours.',
    'membership_only_active_release' => 'Only active memberships can be released.',
    'membership_clearance_failed' => 'Clearance failed. Resolve outstanding issues first.',
    'membership_context_restored' => 'Working context restored to Global Overview.',
    'membership_context_switched' => 'Context switched.',
    'membership_context_switched_to' => 'Working context switched to :office.',
    'membership_invalid_code' => 'The Office Code is invalid or has expired.',
    'membership_already_member' => 'You are already an active member.',
    'membership_request_pending' => 'Request is already pending approval.',
    'membership_request_sent' => 'Membership request sent! Waiting for approval.',

    // =========================================================================
    // Origin: MembershipAdminController.php
    // =========================================================================
    'admin_unauthorized_override' => 'Unauthorized. Emergency overrides require system superadministrator access.',
    'admin_access_denied' => 'Access Denied: You are not the administrator of this office.',
    'admin_user_not_found' => 'User not found.',
    'admin_invalid_code' => 'Invalid or expired code.',
    'admin_permanently_transferring' => 'This employee is permanently transferring. Please use the "Claim Transferred Employee" widget below instead.',
    'admin_already_member' => 'Employee is already an active member of this office.',
    'admin_secondary_access_granted' => 'Employee granted secondary access to this office.',
    'admin_employee_claimed' => 'Employee claimed successfully as their new Home Office.',
    'admin_invite_code_generated' => 'New Office Invitation Code generated.',
    'admin_membership_approved' => 'Employee membership request approved.',
    'admin_membership_rejected' => 'Employee membership request rejected.',
    'admin_override_executed' => 'Emergency compliance override logged and executed.',

    // =========================================================================
    // Origin: RoleAssignmentController.php
    // =========================================================================
    'assignment_proposed' => 'Role handover proposed. Awaiting colleague acceptance.',
    'assignment_accepted' => 'Role accepted successfully. Your colleague\'s clearance is updated.',
    'assignment_rejected' => 'Role handover rejected.',
    'assignment_cancelled' => 'Pending role handover cancelled.',

    // =========================================================================
    // Origin: RoleHandshakeController.php
    // =========================================================================
    'handshake_proposed' => 'Handover proposed. Awaiting colleague acceptance.',
    'handshake_accepted' => 'Handover accepted. Your colleagues clearance has been updated.',
    'handshake_rejected' => 'Handover proposal rejected.',
    'handshake_cancelled' => 'Handover proposal cancelled.',

    // =========================================================================
    // Origin: NoActiveAssetsRule.php
    // =========================================================================
    'rule_physical_inventory_name' => 'Physical Inventory Check',
    'rule_assets_held' => 'You currently hold :count active asset(s). You must check them back into the storekeeper.',
    'rule_assets_returned' => 'All physical assets returned.',

    // =========================================================================
    // Origin: NoActiveRolesRule.php
    // =========================================================================
    'rule_office_responsibility_name' => 'Office Responsibility Check',
    'rule_roles_held' => 'You currently hold :count administrative/storekeeper responsibility here. You must delegate this custody to a colleague first.',
    'rule_no_blocking_roles' => 'No blocking administrative roles.',

    // =========================================================================
    // Origin: NoPendingRequestsRule.php
    // =========================================================================
    'rule_pending_requests_name' => 'Pending Service Requests',
    'rule_requests_active' => 'You have :count active service request(s) in progress. Please cancel them or wait for fulfillment.',
    'rule_requests_completed' => 'All service requests completed.',

    // =========================================================================
    // Origin: OfficeMembershipService.php
    // =========================================================================
    'service_home_office_reset' => 'Home office base reset for user.',
    'service_membership_granted' => 'Office membership granted.',
    'service_membership_revoked' => 'Office access revoked.',

    // =========================================================================
    // Origin: RoleAssignmentService.php
    // =========================================================================
    'assignment_self_delegate_error' => 'You cannot delegate a role to yourself.',
    'assignment_pending_exists' => 'You already have a pending transfer request for this role.',
    'assignment_audit_message' => 'Role Handshake: Accepted :role from user ID :userId',

    // =========================================================================
    // Origin: RoleHandshakeService.php
    // =========================================================================
    'handshake_self_delegate_error' => 'You cannot delegate a role to yourself.',
    'handshake_no_role_error' => 'You cannot hand over a responsibility that you do not hold.',
    'handshake_pending_exists' => 'You already have a pending handover proposal in flight for this role.',
    'handshake_audit_message' => 'Responsibility matrix updated. Role \':role\' handed over from user ID :fromId to user ID :toId',

    // =========================================================================
    // Origin: LegacyUserSynchronizationService.php
    // =========================================================================
    'sync_auto_onboarding_note' => 'System Auto-Onboarding',
    'sync_transfer_blocked_warning' => 'Native User Transfer Blocked: User :username attempted native location change but holds uncleared assets/roles in Location :locationId.',
    'sync_transfer_reverted_flash' => 'Warning: User location update was reverted. The user must return assets and delegate roles before transferring.',
    'sync_native_transfer_note' => 'Native Admin Transfer',

    // =========================================================================
    // Origin: MembershipActivityLogObserver.php
    // =========================================================================
    'log_membership_granted' => 'Office membership granted.',
    'log_status_changed' => 'Membership status transitioned from \':old\' to \':new\'.',
    'log_membership_revoked' => 'Office membership revoked.',

    // =========================================================================
    // Origin: SetWorkingContext.php (middleware flash messages)
    // =========================================================================
    'context_home_resolved' => 'Default working context resolved to home office.',
];
