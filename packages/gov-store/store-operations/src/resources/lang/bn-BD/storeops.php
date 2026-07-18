<?php

return [
    // ── Goods Issue (Outbound) Views ──────────────────────────────
    'issue_goods_title' => 'পণ্য প্রদান',
    'create_goods_issue' => 'পণ্য প্রদান রশিদ তৈরি করুন (বহির্গামী)',
    'issue_type_label' => 'পণ্য প্রদানের ধরন',
    'issue_to_employee' => 'কর্মচারীকে প্রদান',
    'issue_to_department' => 'বিভাগ / সাধারণ ব্যবহারে প্রদান',
    'issued_to_label' => 'কাকে প্রদান করা হলো',
    'select_employee' => '-- কর্মচারী নির্বাচন করুন --',
    'items_to_issue' => 'প্রদানযোগ্য সামগ্রীসমূহ',
    'item_name' => 'সামগ্রীর নাম',
    'available_stock' => 'অবশিষ্ট স্টক',
    'issue_qty' => 'প্রদানের পরিমাণ',
    'select_item' => '-- সামগ্রী নির্বাচন করুন --',
    'add_item' => 'সামগ্রী যোগ করুন',
    'issue_stock_button' => 'পণ্য প্রদান সম্পন্ন করুন',
    'success_goods_issued' => 'পণ্য সফলভাবে প্রদান করা হয়েছে এবং স্টক আপডেট করা হয়েছে!',

    // ── Goods Receipt (Inbound) Views ─────────────────────────────
    'receive_goods_title' => 'মালপত্র গ্রহণ',
    'create_goods_receipt' => 'পণ্য গ্রহণ রশিদ তৈরি করুন',
    'purchase_type_label' => 'ক্রয়ের ধরন',
    'cash_purchase' => 'নগদ ক্রয় / সরাসরি',
    'tender_rfq' => 'দরপত্র / আরএফকিউ',
    'office_transfer' => 'কার্যালয় স্থানান্তর',
    'reference_no_label' => 'রেফারেন্স নম্বর (ইনভয়েস/চালান)',
    'reference_placeholder' => 'যেমন: INV-001',
    'received_items' => 'গৃহীত সামগ্রীসমূহ',
    'quantity' => 'পরিমাণ',
    'action' => 'পদক্ষেপ',
    'qty_placeholder' => 'পরিমাণ',
    'submit_update_stock' => 'জমা দিন এবং স্টক আপডেট করুন',
    'success_receipt_submitted' => 'পণ্য গ্রহণ রশিদ সফলভাবে জমা দেওয়া হয়েছে এবং স্টক আপডেট করা হয়েছে!',

    // ── Stock Register Dashboard ──────────────────────────────────
    'stock_register_dashboard' => 'স্টক রেজিস্টার ড্যাশবোর্ড',
    'consumables_tab' => 'ব্যবহার্য সামগ্রীসমূহ',
    'accessories_tab' => 'আনুষঙ্গিক সরঞ্জামসমূহ',
    'components_tab' => 'উপাদানসমূহ',
    'current_projected_qty' => 'বর্তমান প্রক্ষেপিত পরিমাণ',
    'view_stock_card' => 'স্টক কার্ড দেখুন (কার্ডেক্স)',
    'no_consumables' => 'এই গুদামে কোনো ব্যবহার্য সামগ্রী পাওয়া যায়নি।',
    'no_accessories' => 'এই গুদামে কোনো আনুষঙ্গিক সরঞ্জাম পাওয়া যায়নি।',
    'no_components' => 'এই গুদামে কোনো উপাদান পাওয়া যায়নি।',

    // ── Kardex (Stock Card) Views ─────────────────────────────────
    'stock_card_title' => 'স্টক কার্ড: :name',
    'immutable_stock_register' => 'অপরিবর্তনশীল স্টক রেজিস্টার (কার্ডেক্স)',
    'current_snipeit_projection' => 'বর্তমান প্রক্ষেপিত পরিমাণ:',
    'item_label' => 'সামগ্রী:',
    'date_time' => 'তারিখ ও সময়',
    'reference_document' => 'রেফারেন্স ডকুমেন্ট',
    'operator' => 'অপারেটর',
    'in_column' => 'গ্রহণ (+)',
    'out_column' => 'প্রদান (-)',
    'running_balance' => 'চলতি জের (ব্যালেন্স)',
    'system_initialization' => 'সিস্টেম প্রারম্ভিককরণ',
    'no_movements_recorded' => 'এখনো কোনো ইনভেন্টরি মুভমেন্ট রেকর্ড করা হয়নি।',

    // ── Menu Injection ────────────────────────────────────────────
    'govstore_portal' => 'গভ-স্টোর পোর্টাল',
    'my_self_service' => 'আমার স্ব-সেবা',
    'browse_catalog' => 'ক্যাটালগ ব্রাউজ করুন',
    'track_requests' => 'অনুরোধ ট্র্যাকিং',
    'office_approvals' => 'কার্যালয় অনুমোদনসমূহ',
    'approval_queue' => 'অনুমোদন সারি',
    'stores_and_accounting' => 'স্টোর ও অ্যাকাউন্টিং',
    'fulfillment_queue' => 'ফুলফিলমেন্ট সারি',
    'receive_goods_menu' => 'মালপত্র গ্রহণ (GRN)',
    'ad_hoc_direct_issue' => 'সরাসরি পণ্য প্রদান',

    // ── Service Layer Messages ────────────────────────────────────
    'already_processed' => 'এই :document-টি ইতিমধ্যে প্রসেস করা হয়েছে।',
    'empty_document_error' => 'খালি :document প্রসেস করা সম্ভব নয়।',
    'insufficient_stock' => ':item-এর পর্যাপ্ত স্টক নেই। উপলব্ধ: :available, অনুরোধকৃত: :requested.',
    'invalid_stockable_type' => 'অনুপযুক্ত স্টকযোগ্য ধরন',

    // ── Console Commands ──────────────────────────────────────────
    'scanning_movements' => 'নিখোঁজ ব্যালেন্সের জন্য ইনভেন্টরি মুভমেন্ট রেকর্ড স্ক্যান করা হচ্ছে...',
    'all_balances_healthy' => 'সকল লেজার ব্যালেন্স ইতিমধ্যে হিসাব করা এবং সঠিক আছে!',
    'found_unbalanced_items' => 'অসংগতিপূর্ণ ব্যালেন্স সহ :countটি আইটেম পাওয়া গেছে। ক্রমানুসারে মেরামত করা হচ্ছে...',
];