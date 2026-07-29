<?php

return [

    // ── Goods Issue (Outbound) Views ──────────────────────────────
    'issue_goods_title' => 'Issue Goods',
    'create_goods_issue' => 'Create Goods Issue (Outbound)',
    'issue_type_label' => 'Issue Type',
    'issue_to_employee' => 'Issue to Employee',
    'issue_to_department' => 'Issue to Department / General Use',
    'issued_to_label' => 'Issued To',
    'select_employee' => '-- Select Employee --',
    'items_to_issue' => 'Items to Issue',
    'item_name' => 'Item Name',
    'available_stock' => 'Available Stock',
    'issue_qty' => 'Issue Qty',
    'select_item' => '-- Select Item --',
    'add_item' => 'Add Item',
    'issue_stock_button' => 'Issue Stock',
    'success_goods_issued' => 'Goods successfully issued and stock updated!',

    // ── Goods Receipt (Inbound) Views ─────────────────────────────
    'receive_goods_title' => 'Receive Goods',
    'create_goods_receipt' => 'Create Goods Receipt',
    'purchase_type_label' => 'Purchase Type',
    'cash_purchase' => 'Cash Purchase / Direct',
    'tender_rfq' => 'Tender / RFQ',
    'office_transfer' => 'Office Transfer',
    'reference_no_label' => 'Reference No (Invoice/Memo)',
    'reference_placeholder' => 'e.g., INV-001',
    'received_items' => 'Received Items',
    'quantity' => 'Quantity',
    'action' => 'Action',
    'qty_placeholder' => 'Qty',
    'submit_update_stock' => 'Submit & Update Stock',
    'success_receipt_submitted' => 'Goods Receipt submitted and inventory projected successfully!',

    // ── Stock Register Dashboard ──────────────────────────────────
    'stock_register_dashboard' => 'Stock Register Dashboard',
    'consumables_tab' => 'Consumables',
    'accessories_tab' => 'Accessories',
    'components_tab' => 'Components',
    'current_projected_qty' => 'Current Projected Qty',
    'view_stock_card' => 'View Stock Card (Kardex)',
    'no_consumables' => 'No consumables found in this warehouse.',
    'no_accessories' => 'No accessories found in this warehouse.',
    'no_components' => 'No components found in this warehouse.',

    // ── Kardex (Stock Card) Views ─────────────────────────────────
    'stock_card_title' => 'Stock Card: :name',
    'immutable_stock_register' => 'Immutable Stock Register (Kardex)',
    'current_snipeit_projection' => 'Current Snipe-IT Projection:',
    'item_label' => 'Item:',
    'date_time' => 'Date & Time',
    'reference_document' => 'Reference Document',
    'operator' => 'Operator',
    'in_column' => 'IN (+)',
    'out_column' => 'OUT (-)',
    'running_balance' => 'Running Balance',
    'system_initialization' => 'System Initialization',
    'no_movements_recorded' => 'No inventory movements recorded yet.',

    // ── Menu Injection ────────────────────────────────────────────
    'govstore_portal' => 'Gov-Store Portal',
    'my_self_service' => 'MY SELF SERVICE',
    'browse_catalog' => 'Browse Catalog',
    'track_requests' => 'Track Requests',
    'office_approvals' => 'OFFICE APPROVALS',
    'approval_queue' => 'Approval Queue',
    'stores_and_accounting' => 'STORES & ACCOUNTING',
    'fulfillment_queue' => 'Fulfillment Queue',
    'receive_goods_menu' => 'Receive Goods (GRN)',
    'ad_hoc_direct_issue' => 'Ad-Hoc Direct Issue',

    // ── Service Layer Messages ────────────────────────────────────
    'already_processed' => 'This :document has already been processed.',
    'empty_document_error' => 'Cannot process an empty :document.',
    'insufficient_stock' => 'Insufficient stock for :item. Available: :available, Requested: :requested.',
    'invalid_stockable_type' => 'Invalid stockable type',

    // ── Console Commands ──────────────────────────────────────────
    'scanning_movements' => 'Scanning inventory movement records for missing balances...',
    'all_balances_healthy' => 'All ledger balances are already calculated and healthy!',
    'found_unbalanced_items' => 'Found :count items with uncalculated balances. Repairing sequentially...',

];
