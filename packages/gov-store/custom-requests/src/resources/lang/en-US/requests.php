<?php

return [
    'independent_approver_required' => 'A pending request requires an independent approver with the role for this stage. This request is currently available for viewing only.',
    'serial_fulfillment_required' => 'Issue serialized equipment through the request fulfillment workspace.',
    'return_reason' => 'Reason for return',
    'return_bulk' => 'Request return of all issued supplies and accessories',
    'return_help' => 'The storekeeper will prepare a receipt for all bulk items issued on this request. Stock changes only after physical verification and normal document posting. Serialized equipment uses native asset check-in.',
    'event_return_requested' => 'Return requested',
    'event_return_drafted' => 'Return receipt prepared for review',
    'open_return_receipt' => 'Open return receipt',
    'draft_return_receipt' => 'Prepare return receipt draft',
    'legacy_office_review' => 'This historical request needs an office review before further actions.',
    // =========================================================================
    // Origin: CustomRequestServiceProvider.php
    // Navigation menu titles registered in MenuRegistry
    // =========================================================================
    'serviceprovider_menu_browse_catalog' => 'Browse Catalog',
    'serviceprovider_menu_track_my_requests' => 'Track My Requests',
    'serviceprovider_menu_gov_approvals' => 'Gov Approvals',
    'serviceprovider_menu_fulfillment_queue' => 'Fulfillment Queue',
    'serviceprovider_menu_fulfillment_register' => 'Fulfillment Register',

    // =========================================================================
    // Origin: BasketController.php
    // Flash messages for basket CRUD operations
    // =========================================================================
    'basketcontroller_flash_item_added' => 'Item added to your service request basket.',
    'basketcontroller_flash_item_added_ajax' => 'Item added to your basket.',
    'basketcontroller_flash_qty_updated' => 'Basket quantity updated.',
    'basketcontroller_flash_item_removed' => 'Item removed from basket.',
    'basketcontroller_flash_request_submitted' => 'Service Request(s) [:numbers] submitted successfully!',
    'basketcontroller_error_empty_basket' => 'You cannot submit an empty service request basket.',
    'basketcontroller_error_no_office_location' => 'Your user account does not have an assigned office location.',
    'basketcontroller_error_no_approval_roles' => 'Your office location does not have approval roles configured. Please contact an administrator.',

    // =========================================================================
    // Origin: FulfillmentRegisterController.php
    // Authorization abort messages
    // =========================================================================
    'fulfillmentregistercontroller_abort_unauthorized' => 'Unauthorized access to the Fulfillment Register.',

    // =========================================================================
    // Origin: GovApprovalController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govapprovalcontroller_abort_unauthorized' => 'Unauthorized access to approval workflows.',
    'govapprovalcontroller_abort_admin_required' => 'Unauthorized. Policy configuration requires system administrator privileges.',
    'govapprovalcontroller_flash_processed' => 'Service Request [:number] has been processed.',
    'govapprovalcontroller_flash_workflow_error' => 'Workflow error: :message',
    'govapprovalcontroller_flash_policy_updated' => 'Category approval policy updated successfully.',

    // =========================================================================
    // Origin: GovFulfillmentController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govfulfillmentcontroller_abort_unauthorized' => 'Unauthorized access to fulfillment logs.',
    'govfulfillmentcontroller_flash_fulfillment' => 'Fulfillment logged. Snipe-IT inventory updated.',
    'govfulfillmentcontroller_flash_fulfillment_error' => 'Fulfillment error: :message',
    'govfulfillmentcontroller_flash_closed' => 'Service Request [:number] closed permanently.',

    // =========================================================================
    // Origin: GovRequestController.php
    // Flash messages and log messages
    // =========================================================================
    'govrequestcontroller_flash_request_submitted' => 'Item request submitted successfully and is pending approval.',
    'govrequestcontroller_log_submit_error' => 'Request Submit Error: :message',

    // =========================================================================
    // Origin: ApprovalService.php
    // Exception messages for approval workflow decisions
    // =========================================================================
    'approvals service_exception_already_processed' => 'This service request has already been processed.',
    'approvals_service_exception_no_decision' => 'No decision provided.',
    'approvals_service_exception_qty_must_be_positive' => 'Approved quantity must be greater than 0.',

    // =========================================================================
    // Origin: BasketService.php
    // Exception messages for basket operations
    // =========================================================================
    'basketservice_exception_qty_minimum' => 'Quantity must be at least 1.',
    'basketservice_exception_empty_basket' => 'You cannot submit an empty service request basket.',
    'basketservice_exception_no_office_location' => 'Your user account does not have an assigned office location.',
    'basketservice_exception_no_approval_roles' => 'Your office location does not have approval roles configured. Please contact an administrator.',

    // =========================================================================
    // Origin: basket/index.blade.php
    // Basket page UI strings
    // =========================================================================
    'basket_index_title' => 'My Service Basket',
    'basket_index_header_draft_items' => 'Draft Items in Basket',
    'basket_index_empty_basket' => 'Your basket is empty. Browse the catalog to add items.',
    'basket_index_btn_browse_catalog' => 'Browse Catalog',
    'basket_index_tooltip_asset_restricted' => 'Assets can only be requested in quantity of 1.',
    'basket_index_header_service_request_details' => 'Service Request Details',
    'basket_index_label_request_type' => 'Request Type',
    'basket_index_label_purpose' => 'Purpose',
    'basket_index_placeholder_purpose' => 'Brief purpose of this request',
    'basket_index_label_justification' => 'Justification',
    'basket_index_placeholder_justification' => 'Explain why these items are needed...',
    'basket_index_label_required_by_date' => 'Required By Date',
    'basket_index_label_cost_center' => 'Cost Center',
    'basket_index_placeholder_cost_center' => 'e.g., DEPT-1234',
    'basket_index_label_delivery_location' => 'Delivery Location',
    'basket_index_select_no_location' => 'No location',
    'basket_index_btn_submit' => 'Submit Service Request',

    // =========================================================================
    // Origin: FulfillmentService.php
    // Exception messages for fulfillment operations
    // =========================================================================
    'fulfillmentservice_exception_already_closed' => 'This service request has already been closed.',
    'fulfillmentservice_exception_over_issue_qty' => 'You cannot issue more than the remaining approved quantity.',

    // =========================================================================
    // Origin: RequestService.php
    // Exception messages for request submission
    // =========================================================================
    'requestservice_exception_duplicate_pending' => 'You already have a pending request for this item.',

    // =========================================================================
    // Origin: RequestableFactory.php
    // Exception message for unsupported types
    // =========================================================================
    'requestablefactory_exception_unsupported_type' => 'Unsupported requestable type: :type',

    // =========================================================================
    // Origin: catalog/index.blade.php
    // Catalog page UI strings
    // =========================================================================
    'catalog_title' => 'Service Catalog',
    'catalog_hero_question' => 'What equipment or supplies do you need to fulfill your tasks today?',
    'catalog_search_placeholder' => "Search for items, brands, or categories (e.g. 'Laptop', 'Mouse', 'Paper')...",
    'catalog_quick_requests_label' => 'Frequently Requested Items:',
    'catalog_pipeline_pending_label' => 'Pending',
    'catalog_pipeline_approved_label' => 'Approved',
    'catalog_pipeline_rejected_label' => 'Rejected',
    'catalog_empty_state_title' => 'No items available in the catalog.',
    'catalog_empty_state_subtitle' => 'Check back later or contact your administrator.',
    'catalog_card_details_label' => 'Details',
    'catalog_card_add_to_basket' => 'Add to Request Basket',
    'catalog_view_list_label' => 'List',
    'catalog_view_grid_label' => 'Grid',

    // =========================================================================
    // Origin: components/request-button.blade.php
    // Add-to-basket button and AJAX feedback
    // =========================================================================
    'requestbutton_btn_add_to_basket' => 'Add to Request Basket',
    'requestbutton_btn_adding' => 'Adding...',
    'requestbutton_btn_added' => 'Added!',
    'requestbutton_ajax_error' => 'Error adding item',

    // =========================================================================
    // Origin: fulfillment/index.blade.php
    // Fulfillment queue page UI strings
    // =========================================================================
    'fulfillment_title' => 'Fulfillment Queue',
    'fulfillment_header_title' => 'Approved Items Awaiting Issuance',
    'fulfillment_status_awaiting_picking' => 'Awaiting Picking',
    'fulfillment_status_partially_dispatched' => 'Partially Dispatched',
    'fulfillment_btn_pick_issue' => 'Pick & Issue Items',
    'fulfillment_empty_state' => 'No requests are currently awaiting inventory fulfillment.',

    // =========================================================================
    // Origin: fulfillment/show.blade.php
    // Fulfillment detail page UI strings
    // =========================================================================
    'fulfillment_show_title_prefix' => 'Pick & Issue Items: ',
    'fulfillment_show_header_log_handover' => 'Log Inventory Handover',
    'fulfillment_show_col_approved' => 'Approved',
    'fulfillment_show_col_already_issued' => 'Already Issued',
    'fulfillment_show_col_remaining' => 'Remaining',
    'fulfillment_show_col_issue_qty' => 'Issue Qty Now',
    'fulfillment_show_fully_issued' => 'Fully Issued',
    'fulfillment_show_btn_substitute' => 'Substitute',
    'fulfillment_show_btn_back' => 'Back',
    'fulfillment_show_confirm_handover' => 'Confirming handover? This will deduct Snipe-IT inventory and write to history logs.',
    'fulfillment_show_btn_log_checkout' => 'Log Checkout & Issue Items',
    'fulfillment_show_header_terminate' => 'Terminate / Close Request',
    'fulfillment_show_text_stockout' => 'If items cannot be fulfilled due to permanent stockout, you can force close the remaining line items.',
    'fulfillment_show_input_reason_placeholder' => 'Provide reason for force closure...',
    'fulfillment_show_confirm_force_close' => 'Are you sure you want to terminate this request? Unissued lines will be cancelled.',
    'fulfillment_show_btn_force_close' => 'Force Close Request',
    'fulfillment_show_header_timeline' => 'Request Timeline',
    'fulfillment_show_modal_title' => 'Alternative Substitution',
    'fulfillment_show_modal_search_label' => 'Search Stock Alternatives',
    'fulfillment_show_modal_btn_cancel' => 'Cancel',
    'fulfillment_show_modal_btn_save' => 'Save Substitution',

    // =========================================================================
    // Origin: fulfillment-register/index.blade.php
    // Fulfillment register list page UI strings
    // =========================================================================
    'fulfillment_register_title' => 'Fulfillment Register',
    'fulfillment_register_header_title' => 'Master Fulfillment Register (Historical)',
    'fulfillment_register_status_label' => 'Fulfilled Office Requests',
    'fulfillment_register_btn_view_ledger' => 'View Ledger Details',
    'fulfillment_register_empty_state' => 'No historically fulfilled requests found for your office location.',

    // =========================================================================
    // Origin: fulfillment-register/show.blade.php
    // Fulfillment ledger detail page UI strings
    // =========================================================================
    'fulfillment_register_show_title_prefix' => 'Fulfillment Ledger: ',
    'fulfillment_register_show_header_summary' => 'Service Request Summary',
    'fulfillment_register_show_header_documents' => 'Linked Goods Issue Documents (Inventory Ledger)',
    'fulfillment_register_show_doc_label' => 'Document No: ',
    'fulfillment_register_show_empty_ledger' => 'No ledger documents generated for this request.',
    'fulfillment_register_show_header_audit' => 'Audit Timeline',

    // =========================================================================
    // Origin: hooks/basket-widget.blade.php
    // Floating basket widget strings (JavaScript)
    // =========================================================================
    'basket_widget_console_init' => 'Gov-Store: Initializing basket widgets.',
    'basket_widget_basket_label' => 'Basket',

    // =========================================================================
    // Origin: hooks/menu-injection.blade.php
    // Dynamic sidebar menu strings (JavaScript)
    // =========================================================================
    'menu_injection_console_build' => 'Gov-Store: Building dynamic e-commerce menus.',
    'menu_injection_store_menu_title' => 'Government Store',
    'menu_injection_header_operations' => 'STORE OPERATIONS',

    // =========================================================================
    // Origin: user/index.blade.php
    // My Service Requests page UI strings
    // =========================================================================
    'user_index_title' => 'My Service Requests',
    'user_index_header_my_requests' => 'My Submitted Service Requests',
    'user_index_btn_new_request' => 'New Request',
    'user_index_status_under_review' => 'Under Review',
    'user_index_status_approved' => 'Approved',
    'user_index_status_partially_approved' => 'Partially Approved',
    'user_index_status_closed_fulfilled' => 'Closed (Fulfilled)',
    'user_index_status_rejected' => 'Rejected',
    'user_index_empty_state_title' => 'You have no submitted Service Requests yet.',

    // =========================================================================
    // Origin: admin/index.blade.php
    // Admin approvals dashboard UI strings
    // =========================================================================
    'admin_index_title' => 'Gov Approvals Dashboard',
    'admin_index_header_pending' => 'Requests Awaiting Review',
    'admin_index_empty_pending' => 'No pending requests awaiting approval.',
    'admin_index_header_processed' => 'Recently Processed Requests',
    'admin_index_status_approved' => 'Approved',
    'admin_index_status_partially_approved' => 'Partially Approved',
    'admin_index_status_closed_fulfilled' => 'Closed / Fulfilled',
    'admin_index_status_rejected' => 'Rejected',
    'admin_index_empty_processed' => 'No processed history available.',

    // =========================================================================
    // Origin: admin/show.blade.php
    // Admin review detail page UI strings
    // =========================================================================
    'admin_show_title_prefix' => 'Review Service Request: ',
    'admin_show_header_adjust_items' => 'Adjust and Process Line Items',
    'admin_show_label_purpose' => 'Purpose:',
    'admin_show_label_no_deadline' => 'No deadline set',
    'admin_show_label_no_location' => 'No location specified',
    'admin_show_col_item_details' => 'Item Details',
    'admin_show_col_requested' => 'Requested',
    'admin_show_col_approved_qty' => 'Approved Qty',
    'admin_show_col_decision' => 'Decision',
    'admin_show_btn_approve' => 'Approve',
    'admin_show_btn_reject' => 'Reject',
    'admin_show_input_reason_placeholder' => 'Reason for change/rejection...',
    'admin_show_btn_cancel' => 'Cancel',
    'admin_show_confirm_finalize' => 'Are you sure you want to finalize these line-item decisions?',
    'admin_show_btn_finalize' => 'Finalize Decisions',
    'admin_show_header_timeline' => 'Request Timeline',
    'admin_show_event_draft_created' => 'Draft Created',
    'admin_show_event_submitted' => 'Submitted',
    'admin_show_event_under_review' => 'Under Review',

    // =========================================================================
    // Origin: admin/policies.blade.php
    // Category policies settings page UI strings
    // =========================================================================
    'policies_title' => 'Category Policies Settings',
    'policies_header_title' => 'Assign Category Approval Policies',
    'policies_header_description' => 'Specify the default approval routing rule for each product category. Items automatically inherit these policies.',
    'policies_policy_auto_approve' => 'AUTO_APPROVE (Fulfill instantly without manager approval)',
    'policies_policy_primary_only' => 'PRIMARY_ONLY (Requires Primary Approver sign-off)',
    'policies_policy_primary_and_final' => 'PRIMARY_AND_FINAL (Requires Primary + Final Approver sign-off)',
    'policies_btn_update' => 'Update',

    // =========================================================================
    // Origin: admin/locations.blade.php
    // Office assignments settings page UI strings
    // =========================================================================
    'locations_title' => 'Office Assignments Settings',
    'locations_header_title' => 'Assign Office Workflow Roles',
    'locations_header_description' => 'Configure the Primary Approver, Optional Final Approver, and Storekeeper for each office location.',
    'locations_select_primary' => '-- Assign Primary --',
    'locations_select_no_final' => '-- No Final Approver (Level 1 Only) --',
    'locations_select_storekeeper' => '-- Assign Storekeeper --',
    'locations_btn_save' => 'Save',
    'insufficient_available_stock' => 'Available stock is already reserved or insufficient. Reduce the approved quantity or receive more stock.',
    'saved' => 'Saved',
    'duplicate_asset' => 'The same asset cannot be selected twice.',
    'all_categories' => 'All categories',
    'all_groups' => 'All groups',
    'type_asset_model' => 'Hardware',
    'type_accessory' => 'Accessory',
    'type_consumable' => 'Supply',
    'search' => 'Search',
    'notifications' => 'Request updates',
    'notification_mail_subject' => 'GovStore request update',
    'notification_mail_body' => 'A request update is available. Sign in to view your requests or office queue: :url',
    'request_withdrawn' => 'Request withdrawn.',
    'receipt_recorded' => 'Receipt acknowledged.',
    'withdraw' => 'Withdraw',
    'confirm_receipt' => 'Confirm receipt',
    'event_pending_primary' => 'Awaiting primary decision',
    'event_pending_final' => 'Awaiting final decision',
    'event_submitted' => 'Submitted',
    'event_auto_approved' => 'Automatically approved by policy',
    'event_approved' => 'Approved',
    'event_partially_approved' => 'Partially approved',
    'event_rejected' => 'Rejected',
    'event_cancelled' => 'Withdrawn',
    'event_item_substituted' => 'Item substituted',
    'event_item_issued' => 'Item issued',
    'event_issued' => 'Issued',
    'event_closed' => 'Closed',
    'event_received' => 'Receipt acknowledged',
    'event_escalated' => 'Overdue decision escalated to office administration',
    'item_override' => 'Item policy override',
    'item_type' => 'Item type',
    'item_id' => 'Inventory item ID',
    'approval_policy' => 'Approval policy',
    'threshold_qty' => 'Two-stage quantity threshold',
    'threshold_value' => 'Two-stage value threshold',
    'threshold_help' => 'At or above either threshold, two distinct approvers are required. Unknown item costs require two-stage review when a value threshold is set.',
    'request_number' => 'Request number',
    'purpose' => 'Purpose',
    'submitted_date' => 'Submitted date',
    'items' => 'Items',
    'document_status' => 'Document status',
    'item_details' => 'Item details',
    'requested' => 'Requested',
    'approved_qty' => 'Approved quantity',
    'decision' => 'Decision',
    'adjustment_notes' => 'Decision notes',
    'executed_by' => 'Executed by',
    'substituted_original' => 'Original',
    'substituted_with' => 'Substituted with',
    'issued_label' => 'Issued',
    'quantity' => 'Quantity',
    'no_events' => 'No timeline events recorded yet.',
    'no_requests' => 'You have no submitted service requests yet.',
    'category' => 'Inventory category',
    'action' => 'Action',
    'items_available' => 'Items available',
    'in_stock' => 'In stock',
    'remaining' => 'Remaining',
    'request_type_new_employee' => 'New employee setup',
    'request_type_replacement' => 'Equipment replacement',
    'request_type_project' => 'Project requirement',
    'request_type_office_setup' => 'Office relocation or setup',
    'request_type_repair' => 'Repair or maintenance',
    'request_type_emergency' => 'Emergency request',
    'request_type_other' => 'General supply',
    'substitution_help' => 'Choose an alternative in the same category with a known cost no higher than the approved item. Higher-value alternatives need a new approval.',
    'requester' => 'Requester',
    'processed_date' => 'Decision date',
    'approval_date' => 'Approval date',
    'fulfillment_status' => 'Fulfillment status',
    'purpose_location' => 'Purpose / delivery',
    'completion_date' => 'Completion date',
    'justification' => 'Justification',
    'required_location' => 'Required date / delivery',
    'approved_by' => 'Approved by',
    'date' => 'Date',
    'fulfillment_action' => 'Record handover',
    'handover_notes' => 'Handover notes (optional)',
    'handover_placeholder' => 'Record who received the items and any handover details',
    'select_serials' => 'Select physical assets to issue',
    'confirm_close' => 'Confirm closure',
    'pipeline' => 'My request progress',
    'quick_laptop' => 'Laptop',
    'quick_mouse' => 'Mouse',
    'quick_keyboard' => 'Keyboard',
    'quick_toner' => 'Toner',
    'quick_paper' => 'Paper',
    'quick_chair' => 'Chair',
    'complete_issue' => 'Complete issue',
    'stock_available' => 'Stock available for this request',
    'select_asset' => 'Scan barcode or select asset',
    'serial' => 'Serial',
    'location' => 'Location',
    'substitute_for' => 'Choose an alternative for',
    'lines' => 'lines',
    'unknown_user' => 'Unknown user',
    'unknown_item' => 'Unavailable item',
    'working_office' => 'Working office',
    'system' => 'System',
    'not_available' => 'Not available',
    'event_unstarted' => 'Awaiting issue',
    'event_partially_issued' => 'Partially issued',
    'type_component' => 'Component',
    'type_license' => 'License',
    'type_asset' => 'Asset',
    'asset_checkout_invalid' => 'Asset :asset has missing or invalid required information. Review its asset record before retrying the issue.',
    'asset_checkout_invalid_fields' => 'Asset :asset has missing or invalid information in: :fields. Update its asset record before retrying the issue.',
    'event_asset_metadata_repaired' => 'Asset receipt information restored',
];
