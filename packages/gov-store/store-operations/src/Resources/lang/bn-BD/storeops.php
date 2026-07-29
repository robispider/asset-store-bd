<?php

return [

    // ── Goods Issue (Outbound) Views ──────────────────────────────
    'issue_goods_title' => 'পণ্য প্রদান',
    'create_goods_issue' => 'পণ্য প্রদান (আউটবাউন্ড) তৈরি করুন',
    'issue_type_label' => 'প্রদানের ধরন',
    'issue_to_employee' => 'কর্মকর্তা/কর্মচারীকে প্রদান',
    'issue_to_department' => 'বিভাগ/সাধারণ ব্যবহারের জন্য প্রদান',
    'issued_to_label' => 'প্রদান করা হয়েছে',
    'select_employee' => '-- কর্মকর্তা/কর্মচারী নির্বাচন করুন --',
    'items_to_issue' => 'প্রদানযোগ্য আইটেম',
    'item_name' => 'আইটেমের নাম',
    'available_stock' => 'বিদ্যমান স্টক',
    'issue_qty' => 'প্রদানের পরিমাণ',
    'select_item' => '-- আইটেম নির্বাচন করুন --',
    'add_item' => 'আইটেম যোগ করুন',
    'issue_stock_button' => 'স্টক প্রদান করুন',
    'success_goods_issued' => 'পণ্য সফলভাবে প্রদান করা হয়েছে এবং স্টক আপডেট করা হয়েছে!',

    // ── Goods Receipt (Inbound) Views ─────────────────────────────
    'receive_goods_title' => 'পণ্য গ্রহণ',
    'create_goods_receipt' => 'পণ্য গ্রহণ রসিদ তৈরি করুন',
    'purchase_type_label' => 'ক্রয়ের ধরন',
    'cash_purchase' => 'নগদ ক্রয় / সরাসরি',
    'tender_rfq' => 'টেন্ডার / RFQ',
    'office_transfer' => 'কার্যালয় স্থানান্তর',
    'reference_no_label' => 'রেফারেন্স নম্বর (ইনভয়েস/মেমো)',
    'reference_placeholder' => 'উদাঃ INV-001',
    'received_items' => 'গৃহীত আইটেম',
    'quantity' => 'পরিমাণ',
    'action' => 'অ্যাকশন',
    'qty_placeholder' => 'পরিমাণ',
    'submit_update_stock' => 'জমা দিন এবং স্টক আপডেট করুন',
    'success_receipt_submitted' => 'পণ্য গ্রহণ রসিদ জমা দেওয়া হয়েছে এবং ইনভেন্টরি প্রজেকশন সফল হয়েছে!',

    // ── Stock Register Dashboard ──────────────────────────────────
    'stock_register_dashboard' => 'স্টক রেজিস্টার ড্যাশবোর্ড',
    'consumables_tab' => 'ব্যবহারযোগ্য পণ্য (Consumables)',
    'accessories_tab' => 'আনুষঙ্গিক সরঞ্জাম (Accessories)',
    'components_tab' => 'কম্পোনেন্ট (Components)',
    'current_projected_qty' => 'বর্তমান প্রজেক্টেড পরিমাণ',
    'view_stock_card' => 'স্টক কার্ড দেখুন (Kardex)',
    'no_consumables' => 'এই ওয়্যারহাউসে কোনো ব্যবহারযোগ্য পণ্য পাওয়া যায়নি।',
    'no_accessories' => 'এই ওয়্যারহাউসে কোনো আনুষঙ্গিক সরঞ্জাম পাওয়া যায়নি।',
    'no_components' => 'এই ওয়্যারহাউসে কোনো কম্পোনেন্ট পাওয়া যায়নি।',

    // ── Kardex (Stock Card) Views ─────────────────────────────────
    'stock_card_title' => 'স্টক কার্ড: :name',
    'immutable_stock_register' => 'অপরিবর্তনীয় স্টক রেজিস্টার (Kardex)',
    'current_snipeit_projection' => 'বর্তমান Snipe-IT প্রজেকশন:',
    'item_label' => 'আইটেম:',
    'date_time' => 'তারিখ এবং সময়',
    'reference_document' => 'রেফারেন্স ডকুমেন্ট',
    'operator' => 'অপারেটর',
    'in_column' => 'প্রবেশ (+)',
    'out_column' => 'প্রস্থান (-)',
    'running_balance' => 'চলতি ব্যালেন্স',
    'system_initialization' => 'সিস্টেম ইনিশিয়ালাইজেশন',
    'no_movements_recorded' => 'এখন পর্যন্ত কোনো ইনভেন্টরি মুভমেন্ট রেকর্ড করা হয়নি।',

    // ── Menu Injection ────────────────────────────────────────────
    'govstore_portal' => 'Gov-Store পোর্টাল',
    'my_self_service' => 'আমার সেলফ সার্ভিস',
    'browse_catalog' => 'ক্যাটালগ দেখুন',
    'track_requests' => 'রিকোয়েস্ট ট্র্যাক করুন',
    'office_approvals' => 'কার্যালয় অনুমোদন',
    'approval_queue' => 'অনুমোদন কিউ',
    'stores_and_accounting' => 'স্টোর এবং হিসাবরক্ষণ',
    'fulfillment_queue' => 'ফুলফিলমেন্ট কিউ',
    'receive_goods_menu' => 'পণ্য গ্রহণ (GRN)',
    'ad_hoc_direct_issue' => 'অ্যাড-হক সরাসরি প্রদান',

    // ── Service Layer Messages ────────────────────────────────────
    'already_processed' => 'এই :document ইতিমধ্যে প্রসেস করা হয়েছে।',
    'empty_document_error' => 'খালি :document প্রসেস করা সম্ভব নয়।',
    'insufficient_stock' => ':item এর জন্য পর্যাপ্ত স্টক নেই। বিদ্যমান: :available, অনুরোধকৃত: :requested।',
    'invalid_stockable_type' => 'অকার্যকর স্টকযোগ্য টাইপ',

    // ── Console Commands ──────────────────────────────────────────
    'scanning_movements' => 'মিসিং ব্যালেন্সের জন্য ইনভেন্টরি মুভমেন্ট রেকর্ড স্ক্যান করা হচ্ছে...',
    'all_balances_healthy' => 'সব লেজার ব্যালেন্স ইতিমধ্যে গণনা করা হয়েছে এবং সঠিক আছে!',
    'found_unbalanced_items' => ':count টি আইটেম পাওয়া গেছে যাদের ব্যালেন্স গণনা করা হয়নি। পর্যায়ক্রমে মেরামত করা হচ্ছে...',

];
