<?php

return [
    // =========================================================================
    // Origin: CustomRequestServiceProvider.php
    // Navigation menu titles registered in MenuRegistry
    // =========================================================================
    'serviceprovider_menu_browse_catalog'       => 'ক্যাটালগ ব্রাউজ করুন',
    'serviceprovider_menu_track_my_requests'    => 'অনুরোধ ট্র্যাকিং',
    'serviceprovider_menu_gov_approvals'        => 'সরকারি অনুমোদনসমূহ (Gov Approvals)',
    'serviceprovider_menu_fulfillment_queue'    => 'ফুলফিলমেন্ট সারি',
    'serviceprovider_menu_fulfillment_register' => 'ফুলফিলমেন্ট রেজিস্টার',

    // =========================================================================
    // Origin: BasketController.php
    // Flash messages for basket CRUD operations
    // =========================================================================
    'basketcontroller_flash_item_added'            => 'আপনার পরিষেবা অনুরোধ বাস্কেটে আইটেমটি যুক্ত করা হয়েছে।',
    'basketcontroller_flash_item_added_ajax'       => 'বাস্কেটে আইটেম যুক্ত করা হয়েছে।',
    'basketcontroller_flash_qty_updated'           => 'বাস্কেটের পরিমাণ হালনাগাদ করা হয়েছে।',
    'basketcontroller_flash_item_removed'          => 'বাস্কেট থেকে আইটেমটি সরানো হয়েছে।',
    'basketcontroller_flash_request_submitted'     => 'পরিষেবা অনুরোধ [:numbers] সফলভাবে জমা দেওয়া হয়েছে!',
    'basketcontroller_error_empty_basket'          => 'খালি বাস্কেট জমা দেওয়া সম্ভব নয়।',
    'basketcontroller_error_no_office_location'    => 'আপনার ব্যবহারকারী অ্যাকাউন্টের সাথে কোনো কার্যালয় (Office Location) সংযুক্ত নেই।',
    'basketcontroller_error_no_approval_roles'     => 'আপনার কার্যালয়ে কোনো অনুমোদনকারীর ভূমিকা কনফিগার করা নেই। অনুগ্রহ করে প্রশাসকের সাথে যোগাযোগ করুন।',

    // =========================================================================
    // Origin: FulfillmentRegisterController.php
    // Authorization abort messages
    // =========================================================================
    'fulfillmentregistercontroller_abort_unauthorized' => 'ফুলফিলমেন্ট রেজিস্টারে অননুমোদিত অ্যাক্সেস।',

    // =========================================================================
    // Origin: GovApprovalController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govapprovalcontroller_abort_unauthorized'            => 'অনুমোদন কার্যক্রমে অননুমোদিত অ্যাক্সেস।',
    'govapprovalcontroller_abort_admin_required'          => 'অননুমোদিত। পলিসি কনফিগারেশনের জন্য সিস্টেম প্রশাসকের প্রিভিলেজ প্রয়োজন।',
    'govapprovalcontroller_flash_processed'               => 'পরিষেবা অনুরোধ [:number] প্রক্রিয়া করা হয়েছে।',
    'govapprovalcontroller_flash_workflow_error'          => 'ওয়ার্কফ্লো ত্রুটি: :message',
    'govapprovalcontroller_flash_policy_updated'          => 'ক্যাটাগরি অনুমোদন পলিসি সফলভাবে হালনাগাদ করা হয়েছে।',

    // =========================================================================
    // Origin: GovFulfillmentController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govfulfillmentcontroller_abort_unauthorized'   => 'ফুলফিলমেন্ট লগে অননুমোদিত অ্যাক্সেস।',
    'govfulfillmentcontroller_flash_fulfillment'    => 'ফুলফিলমেন্ট লগ করা হয়েছে। Snipe-IT ইনভেন্টরি হালনাগাদ হয়েছে।',
    'govfulfillmentcontroller_flash_fulfillment_error' => 'ফুলফিলমেন্ট ত্রুটি: :message',
    'govfulfillmentcontroller_flash_closed'         => 'পরিষেবা অনুরোধ [:number] স্থায়ীভাবে বন্ধ করা হয়েছে।',

    // =========================================================================
    // Origin: GovRequestController.php
    // Flash messages and log messages
    // =========================================================================
    'govrequestcontroller_flash_request_submitted'  => 'আইটেম অনুরোধ সফলভাবে জমা দেওয়া হয়েছে এবং অনুমোদনের অপেক্ষায় রয়েছে।',
    'govrequestcontroller_log_submit_error'         => 'অনুরোধ জমা দেওয়ার ত্রুটি: :message',

    // =========================================================================
    // Origin: ApprovalService.php
    // Exception messages for approval workflow decisions
    // =========================================================================
    'approvals service_exception_already_processed' => 'এই পরিষেবা অনুরোধটি ইতিমধ্যে প্রক্রিয়া করা হয়েছে।',
    'approvals_service_exception_no_decision'       => 'কোনো সিদ্ধান্ত প্রদান করা হয়নি।',
    'approvals_service_exception_qty_must_be_positive' => 'অনুমোদিত পরিমাণ অবশ্যই ০-এর বেশি হতে হবে।',

    // =========================================================================
    // Origin: BasketService.php
    // Exception messages for basket operations
    // =========================================================================
    'basketservice_exception_qty_minimum'       => 'পরিমাণ কমপক্ষে ১ হতে হবে।',
    'basketservice_exception_empty_basket'      => 'খালি বাস্কেট জমা দেওয়া সম্ভব নয়।',
    'basketservice_exception_no_office_location'=> 'আপনার অ্যাকাউন্টের সাথে কোনো কার্যালয় সংযুক্ত নেই।',
    'basketservice_exception_no_approval_roles' => 'আপনার কার্যালয়ে কোনো অনুমোদনকারীর ভূমিকা কনফিগার করা নেই। অনুগ্রহ করে প্রশাসকের সাথে যোগাযোগ করুন।',

    // =========================================================================
    // Origin: FulfillmentService.php
    // Exception messages for fulfillment operations
    // =========================================================================
    'fulfillmentservice_exception_already_closed'   => 'এই পরিষেবা অনুরোধটি ইতিমধ্যে বন্ধ করা হয়েছে।',
    'fulfillmentservice_exception_over_issue_qty'   => 'অনুমোদিত পরিমাণের চেয়ে বেশি ইস্যু করা সম্ভব নয়।',

    // =========================================================================
    // Origin: RequestService.php
    // Exception messages for request submission
    // =========================================================================
    'requestservice_exception_duplicate_pending' => 'এই আইটেমের জন্য আপনার ইতিমধ্যে একটি পেন্ডিং অনুরোধ রয়েছে।',

    // =========================================================================
    // Origin: RequestableFactory.php
    // Exception message for unsupported types
    // =========================================================================
    'requestablefactory_exception_unsupported_type' => 'অসমর্থিত অনুরোধযোগ্য ধরন: :type',

    // =========================================================================
    // Origin: catalog/index.blade.php
    // Catalog page UI strings
    // =========================================================================
    'catalog_title'                           => 'পরিষেবা ক্যাটালগ (Service Catalog)',
    'catalog_hero_question'                   => 'আজ আপনার দৈনন্দিন কাজের জন্য কী কী সরঞ্জাম বা মালামাল প্রয়োজন?',
    'catalog_search_placeholder'              => "আইটেম, ব্র্যান্ড বা ক্যাটাগরি অনুসন্ধান করুন (যেমন: 'Laptop', 'Mouse', 'Paper')...",
    'catalog_quick_requests_label'            => 'অধিক ব্যবহৃত আইটেমসমূহ:',
    'catalog_pipeline_pending_label'          => 'অপেক্ষমান (Pending)',
    'catalog_pipeline_approved_label'         => 'অনুমোদিত',
    'catalog_pipeline_rejected_label'         => 'প্রত্যাখ্যাত',
    'catalog_empty_state_title'               => 'ক্যাটালগে কোনো আইটেম উপলব্ধ নেই।',
    'catalog_empty_state_subtitle'            => 'পরে আবার চেষ্টা করুন অথবা আপনার প্রশাসকের সাথে যোগাযোগ করুন।',
    'catalog_card_details_label'              => 'বিস্তারিত',
    'catalog_card_add_to_basket'              => 'বাস্কেটে যোগ করুন',
    'catalog_view_list_label'                 => 'তালিকা',
    'catalog_view_grid_label'                 => 'গ্রিড',

    // =========================================================================
    // Origin: components/request-button.blade.php
    // Add-to-basket button and AJAX feedback
    // =========================================================================
    'requestbutton_btn_add_to_basket'     => 'বাস্কেটে যোগ করুন',
    'requestbutton_btn_adding'            => 'যোগ করা হচ্ছে...',
    'requestbutton_btn_added'             => 'যুক্ত করা হয়েছে!',
    'requestbutton_ajax_error'            => 'আইটেম যোগ করতে ত্রুটি',

    // =========================================================================
    // Origin: fulfillment/index.blade.php
    // Fulfillment queue page UI strings
    // =========================================================================
    'fulfillment_title'                     => 'ফুলফিলমেন্ট সারি',
    'fulfillment_header_title'              => 'ইস্যু করার অপেক্ষায় থাকা অনুমোদিত আইটেমসমূহ',
    'fulfillment_status_awaiting_picking'   => 'সংগ্রহের অপেক্ষায়',
    'fulfillment_status_partially_dispatched' => 'আংশিকভাবে প্রেরিত',
    'fulfillment_btn_pick_issue'            => 'সংগ্রহ ও ইস্যু করুন',
    'fulfillment_empty_state'               => 'বর্তমানে ইনভেন্টরি ফুলফিলমেন্টের জন্য কোনো অনুরোধ অপেক্ষায় নেই।',

    // =========================================================================
    // Origin: fulfillment/show.blade.php
    // Fulfillment detail page UI strings
    // =========================================================================
    'fulfillment_show_title_prefix'         => 'সংগ্রহ ও ইস্যু: ',
    'fulfillment_show_header_log_handover'  => 'হস্তান্তর (Handover) লগ করুন',
    'fulfillment_show_col_approved'         => 'অনুমোদিত',
    'fulfillment_show_col_already_issued'   => 'ইতিমধ্যে ইস্যুকৃত',
    'fulfillment_show_col_remaining'        => 'অবশিষ্ট',
    'fulfillment_show_col_issue_qty'        => 'এখন ইস্যু করার পরিমাণ',
    'fulfillment_show_fully_issued'         => 'সম্পূর্ণ ইস্যুকৃত',
    'fulfillment_show_btn_substitute'       => 'বিকল্প (Substitute)',
    'fulfillment_show_btn_back'             => 'ফিরে যান',
    'fulfillment_show_confirm_handover'     => 'হস্তান্তর নিশ্চিত করবেন? এটি Snipe-IT ইনভেন্টরি থেকে বিয়োগ করবে এবং হিস্ট্রি লগে রেকর্ড করবে।',
    'fulfillment_show_btn_log_checkout'     => 'চেকআউট লগ করুন ও ইস্যু করুন',
    'fulfillment_show_header_terminate'     => 'অনুরোধ বাতিল / বন্ধ করুন',
    'fulfillment_show_text_stockout'        => 'স্টক শেষ হওয়ার কারণে যদি আইটেমগুলো সরবরাহ করা সম্ভব না হয়, তবে আপনি বাকি আইটেমগুলোর জন্য অনুরোধটি বলপূর্বক (Force Close) বন্ধ করতে পারেন।',
    'fulfillment_show_input_reason_placeholder' => 'বলপূর্বক বন্ধ করার কারণ উল্লেখ করুন...',
    'fulfillment_show_confirm_force_close'  => 'আপনি কি নিশ্চিত যে আপনি এই অনুরোধটি বাতিল করতে চান? ইস্যু না করা আইটেমগুলো বাতিল হয়ে যাবে।',
    'fulfillment_show_btn_force_close'      => 'বলপূর্বক বন্ধ করুন',
    'fulfillment_show_header_timeline'      => 'অনুরোধের সময়রেখা (Timeline)',
    'fulfillment_show_modal_title'          => 'বিকল্প প্রতিস্থাপন (Alternative Substitution)',
    'fulfillment_show_modal_search_label'   => 'বিকল্প স্টক অনুসন্ধান করুন',
    'fulfillment_show_modal_btn_cancel'     => 'বাতিল',
    'fulfillment_show_modal_btn_save'       => 'প্রতিস্থাপন সংরক্ষণ করুন',

    // =========================================================================
    // Origin: fulfillment-register/index.blade.php
    // Fulfillment register list page UI strings
    // =========================================================================
    'fulfillment_register_title'            => 'ফুলফিলমেন্ট রেজিস্টার',
    'fulfillment_register_header_title'     => 'মাস্টার ফুলফিলমেন্ট রেজিস্টার (ঐতিহাসিক)',
    'fulfillment_register_status_label'     => 'সম্পন্নকৃত কার্যালয় অনুরোধসমূহ',
    'fulfillment_register_btn_view_ledger'  => 'লেজার বিস্তারিত দেখুন',
    'fulfillment_register_empty_state'      => 'আপনার কার্যালয়ের জন্য ঐতিহাসিকভাবে সম্পন্ন কোনো অনুরোধ পাওয়া যায়নি।',

    // =========================================================================
    // Origin: fulfillment-register/show.blade.php
    // Fulfillment ledger detail page UI strings
    // =========================================================================
    'fulfillment_register_show_title_prefix'  => 'ফুলফিলমেন্ট লেজার: ',
    'fulfillment_register_show_header_summary'=> 'পরিষেবা অনুরোধের সারসংক্ষেপ',
    'fulfillment_register_show_header_documents' => 'সংযুক্ত পণ্য প্রদান রশিদ (ইনভেন্টরি লেজার)',
    'fulfillment_register_show_doc_label'     => 'ডকুমেন্ট নম্বর: ',
    'fulfillment_register_show_empty_ledger'  => 'এই অনুরোধের জন্য কোনো লেজার ডকুমেন্ট তৈরি হয়নি।',
    'fulfillment_register_show_header_audit'  => 'অডিট টাইমলাইন',

    // =========================================================================
    // Origin: hooks/basket-widget.blade.php
    // Floating basket widget strings (JavaScript)
    // =========================================================================
    'basket_widget_console_init'        => 'গভ-স্টোর: বাস্কেট উইজেট চালু করা হচ্ছে।',
    'basket_widget_basket_label'        => 'বাস্কেট (:count)',

    // =========================================================================
    // Origin: hooks/menu-injection.blade.php
    // Dynamic sidebar menu strings (JavaScript)
    // =========================================================================
    'menu_injection_console_build'      => 'গভ-স্টোর: ডায়নামিক ই-কমার্স মেনু তৈরি করা হচ্ছে।',
    'menu_injection_store_menu_title'   => 'সরকারি স্টোর',
    'menu_injection_header_operations'  => 'স্টোর কার্যক্রম',

    // =========================================================================
    // Origin: user/index.blade.php
    // My Service Requests page UI strings
    // =========================================================================
    'user_index_title'                  => 'আমার পরিষেবা অনুরোধসমূহ',
    'user_index_header_my_requests'     => 'আমার জমাকৃত পরিষেবা অনুরোধসমূহ',
    'user_index_btn_new_request'        => 'নতুন অনুরোধ',
    'user_index_status_under_review'    => 'পর্যালোচনার অধীনে',
    'user_index_status_approved'        => 'অনুমোদিত',
    'user_index_status_partially_approved' => 'আংশিক অনুমোদিত',
    'user_index_status_closed_fulfilled' => 'বন্ধ (সম্পন্ন)',
    'user_index_status_rejected'        => 'প্রত্যাখ্যাত',
    'user_index_empty_state_title'      => 'আপনার এখনো কোনো পরিষেবা অনুরোধ জমা দেওয়া নেই।',

    // =========================================================================
    // Origin: admin/index.blade.php
    // Admin approvals dashboard UI strings
    // =========================================================================
    'admin_index_title'                 => 'সরকারি অনুমোদন ড্যাশবোর্ড',
    'admin_index_header_pending'        => 'পর্যালোচনার অপেক্ষায় থাকা অনুরোধসমূহ',
    'admin_index_empty_pending'         => 'অনুমোদনের জন্য কোনো অনুরোধ অপেক্ষমাণ নেই।',
    'admin_index_header_processed'      => 'সম্প্রতি প্রক্রিয়া করা অনুরোধসমূহ',
    'admin_index_status_approved'       => 'অনুমোদিত',
    'admin_index_status_partially_approved' => 'আংশিক অনুমোদিত',
    'admin_index_status_closed_fulfilled' => 'বন্ধ / সম্পন্ন',
    'admin_index_status_rejected'       => 'প্রত্যাখ্যাত',
    'admin_index_empty_processed'       => 'কোনো পূর্ববর্তী প্রক্রিয়াকরণের ইতিহাস নেই।',

    // =========================================================================
    // Origin: admin/show.blade.php
    // Admin review detail page UI strings
    // =========================================================================
    'admin_show_title_prefix'           => 'অনুরোধ পর্যালোচনা করুন: ',
    'admin_show_header_adjust_items'    => 'আইটেম সমন্বয় ও প্রক্রিয়া করুন',
    'admin_show_label_purpose'          => 'উদ্দেশ্য:',
    'admin_show_label_no_deadline'      => 'কোনো সময়সীমা নির্ধারিত নেই',
    'admin_show_label_no_location'      => 'কোনো অবস্থান নির্দিষ্ট করা হয়নি',
    'admin_show_col_item_details'       => 'আইটেমের বিবরণ',
    'admin_show_col_requested'          => 'অনুরোধকৃত',
    'admin_show_col_approved_qty'       => 'অনুমোদিত পরিমাণ',
    'admin_show_col_decision'           => 'সিদ্ধান্ত',
    'admin_show_btn_approve'            => 'অনুমোদন করুন',
    'admin_show_btn_reject'             => 'প্রত্যাখ্যান করুন',
    'admin_show_input_reason_placeholder' => 'পরিবর্তন/প্রত্যাখ্যানের কারণ...',
    'admin_show_btn_cancel'             => 'বাতিল',
    'admin_show_confirm_finalize'       => 'আপনি কি নিশ্চিত যে আপনি এই আইটেমগুলোর সিদ্ধান্ত চূড়ান্ত করতে চান?',
    'admin_show_btn_finalize'           => 'সিদ্ধান্ত চূড়ান্ত করুন',
    'admin_show_header_timeline'        => 'অনুরোধের সময়রেখা (Timeline)',
    'admin_show_event_draft_created'    => 'খসড়া তৈরি হয়েছে',
    'admin_show_event_submitted'        => 'জমা দেওয়া হয়েছে',
    'admin_show_event_under_review'     => 'পর্যালোচনার অধীনে',

    // =========================================================================
    // Origin: admin/policies.blade.php
    // Category policies settings page UI strings
    // =========================================================================
    'policies_title'                    => 'ক্যাটাগরি পলিসি সেটিংস',
    'policies_header_title'             => 'ক্যাটাগরি অনুমোদন পলিসি বরাদ্দ করুন',
    'policies_header_description'       => 'প্রতিটি পণ্য ক্যাটাগরির জন্য ডিফল্ট অনুমোদন রুল নির্ধারণ করুন। আইটেমগুলো স্বয়ংক্রিয়ভাবে এই পলিসিগুলো অনুসরণ করবে।',
    'policies_policy_auto_approve'      => 'স্বয়ংক্রিয় অনুমোদন (ম্যানেজারের অনুমোদন ছাড়াই তাৎক্ষণিকভাবে সম্পন্ন করুন)',
    'policies_policy_primary_only'      => 'শুধুমাত্র প্রাথমিক (প্রাথমিক অনুমোদনকারীর স্বাক্ষর প্রয়োজন)',
    'policies_policy_primary_and_final' => 'প্রাথমিক ও চূড়ান্ত (প্রাথমিক + চূড়ান্ত অনুমোদনকারীর স্বাক্ষর প্রয়োজন)',
    'policies_btn_update'               => 'আপডেট করুন',

    // =========================================================================
    // Origin: admin/locations.blade.php
    // Office assignments settings page UI strings
    // =========================================================================
    'locations_title'                   => 'কার্যালয় অ্যাসাইনমেন্ট সেটিংস',
    'locations_header_title'            => 'কার্যালয়ের কাজের ভূমিকা বরাদ্দ করুন',
    'locations_header_description'      => 'প্রতিটি কার্যালয়ের জন্য প্রাথমিক অনুমোদনকারী, ঐচ্ছিক চূড়ান্ত অনুমোদনকারী এবং স্টোরকিপার কনফিগার করুন।',
    'locations_select_primary'          => '-- প্রাথমিক অনুমোদনকারী নির্বাচন করুন --',
    'locations_select_no_final'         => '-- কোনো চূড়ান্ত অনুমোদনকারী নেই (কেবল স্তর ১) --',
    'locations_select_storekeeper'      => '-- স্টোরকিপার নির্বাচন করুন --',
    'locations_btn_save'                => 'সংরক্ষণ করুন',
];