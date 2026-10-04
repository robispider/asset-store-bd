<?php

return [
    'independent_approver_required' => 'অপেক্ষমাণ অনুরোধে এই ধাপের দায়িত্বপ্রাপ্ত একজন স্বতন্ত্র অনুমোদনকারী প্রয়োজন। এই অনুরোধ বর্তমানে শুধু দেখা যাবে।',
    'serial_fulfillment_required' => 'ক্রমিক নম্বরযুক্ত সম্পদ অনুরোধের সরবরাহ কর্মক্ষেত্র থেকে সরবরাহ করুন।',
    'return_reason' => 'ফেরতের কারণ',
    'return_bulk' => 'সরবরাহকৃত সকল সামগ্রী ও আনুষঙ্গিক সরঞ্জাম ফেরতের অনুরোধ করুন',
    'return_help' => 'এই অনুরোধে সরবরাহকৃত সকল সাধারণ সামগ্রীর জন্য স্টোরকিপার গ্রহণ নথি তৈরি করবেন। বাস্তবে গ্রহণ যাচাই ও স্বাভাবিক নথি পোস্টিংয়ের পর মজুত পরিবর্তন হবে। ক্রমিক নম্বরযুক্ত সম্পদ মূল সম্পদ চেক-ইন ব্যবস্থায় ফেরত দিন।',
    'event_return_requested' => 'ফেরতের অনুরোধ করা হয়েছে',
    'event_return_drafted' => 'পর্যালোচনার জন্য ফেরতের গ্রহণ নথি তৈরি হয়েছে',
    'open_return_receipt' => 'ফেরতের গ্রহণ নথি খুলুন',
    'draft_return_receipt' => 'ফেরতের গ্রহণ নথির খসড়া তৈরি করুন',
    'legacy_office_review' => 'পরবর্তী পদক্ষেপের আগে এই পুরোনো অনুরোধের দপ্তর যাচাই প্রয়োজন।',
    // =========================================================================
    // Origin: CustomRequestServiceProvider.php
    // Navigation menu titles registered in MenuRegistry
    // =========================================================================
    'serviceprovider_menu_browse_catalog' => 'ক্যাটালগ দেখুন',
    'serviceprovider_menu_track_my_requests' => 'আমার রিকোয়েস্ট ট্র্যাক করুন',
    'serviceprovider_menu_gov_approvals' => 'সরকারি অনুমোদন',
    'serviceprovider_menu_fulfillment_queue' => 'ফুলফিলমেন্ট কিউ',
    'serviceprovider_menu_fulfillment_register' => 'ফুলফিলমেন্ট রেজিস্টার',

    // =========================================================================
    // Origin: BasketController.php
    // Flash messages for basket CRUD operations
    // =========================================================================
    'basketcontroller_flash_item_added' => 'আপনার সার্ভিস রিকোয়েস্ট বাস্কেটে আইটেম যোগ করা হয়েছে।',
    'basketcontroller_flash_item_added_ajax' => 'আপনার বাস্কেটে আইটেম যোগ করা হয়েছে।',
    'basketcontroller_flash_qty_updated' => 'বাস্কেটের পরিমাণ আপডেট করা হয়েছে।',
    'basketcontroller_flash_item_removed' => 'বাস্কেট থেকে আইটেম মুছে ফেলা হয়েছে।',
    'basketcontroller_flash_request_submitted' => 'সার্ভিস রিকোয়েস্ট [:numbers] সফলভাবে জমা দেওয়া হয়েছে!',
    'basketcontroller_error_empty_basket' => 'আপনি একটি খালি সার্ভিস রিকোয়েস্ট বাস্কেট জমা দিতে পারবেন না।',
    'basketcontroller_error_no_office_location' => 'আপনার ইউজার অ্যাকাউন্টে কোনো কার্যালয় লোকেশন অ্যাসাইন করা নেই।',
    'basketcontroller_error_no_approval_roles' => 'আপনার কার্যালয় লোকেশনে কোনো অনুমোদন রোল কনফিগার করা নেই। অনুগ্রহ করে একজন প্রশাসকের সাথে যোগাযোগ করুন।',

    // =========================================================================
    // Origin: FulfillmentRegisterController.php
    // Authorization abort messages
    // =========================================================================
    'fulfillmentregistercontroller_abort_unauthorized' => 'ফুলফিলমেন্ট রেজিস্টারে অননুমোদিত অ্যাক্সেস।',

    // =========================================================================
    // Origin: GovApprovalController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govapprovalcontroller_abort_unauthorized' => 'অনুমোদন ওয়ার্কফ্লোতে অননুমোদিত অ্যাক্সেস।',
    'govapprovalcontroller_abort_admin_required' => 'অননুমোদিত। পলিসি কনফিগারেশনের জন্য সিস্টেম অ্যাডমিনিস্ট্রেটর প্রিভিলেজ প্রয়োজন।',
    'govapprovalcontroller_flash_processed' => 'সার্ভিস রিকোয়েস্ট [:number] প্রসেস করা হয়েছে।',
    'govapprovalcontroller_flash_workflow_error' => 'ওয়ার্কফ্লো ত্রুটি: :message',
    'govapprovalcontroller_flash_policy_updated' => 'ক্যাটাগরি অনুমোদন পলিসি সফলভাবে আপডেট করা হয়েছে।',

    // =========================================================================
    // Origin: GovFulfillmentController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govfulfillmentcontroller_abort_unauthorized' => 'ফুলফিলমেন্ট লগে অননুমোদিত অ্যাক্সেস।',
    'govfulfillmentcontroller_flash_fulfillment' => 'ফুলফিলমেন্ট লগ করা হয়েছে। Snipe-IT ইনভেন্টরি আপডেট করা হয়েছে।',
    'govfulfillmentcontroller_flash_fulfillment_error' => 'ফুলফিলমেন্ট ত্রুটি: :message',
    'govfulfillmentcontroller_flash_closed' => 'সার্ভিস রিকোয়েস্ট [:number] স্থায়ীভাবে বন্ধ করা হয়েছে।',

    // =========================================================================
    // Origin: GovRequestController.php
    // Flash messages and log messages
    // =========================================================================
    'govrequestcontroller_flash_request_submitted' => 'আইটেম রিকোয়েস্ট সফলভাবে জমা দেওয়া হয়েছে এবং অনুমোদনের জন্য পেন্ডিং আছে।',
    'govrequestcontroller_log_submit_error' => 'রিকোয়েস্ট সাবমিট ত্রুটি: :message',

    // =========================================================================
    // Origin: ApprovalService.php
    // Exception messages for approval workflow decisions
    // =========================================================================
    'approvals service_exception_already_processed' => 'এই সার্ভিস রিকোয়েস্টটি ইতিমধ্যে প্রসেস করা হয়েছে।',
    'approvals_service_exception_no_decision' => 'কোনো সিদ্ধান্ত প্রদান করা হয়নি।',
    'approvals_service_exception_qty_must_be_positive' => 'অনুমোদিত পরিমাণ ০ এর বেশি হতে হবে।',

    // =========================================================================
    // Origin: BasketService.php
    // Exception messages for basket operations
    // =========================================================================
    'basketservice_exception_qty_minimum' => 'পরিমাণ অন্তত ১ হতে হবে।',
    'basketservice_exception_empty_basket' => 'আপনি একটি খালি সার্ভিস রিকোয়েস্ট বাস্কেট জমা দিতে পারবেন না।',
    'basketservice_exception_no_office_location' => 'আপনার ইউজার অ্যাকাউন্টে কোনো কার্যালয় লোকেশন অ্যাসাইন করা নেই।',
    'basketservice_exception_no_approval_roles' => 'আপনার কার্যালয় লোকেশনে কোনো অনুমোদন রোল কনফিগার করা নেই। অনুগ্রহ করে একজন প্রশাসকের সাথে যোগাযোগ করুন।',

    // =========================================================================
    // Origin: FulfillmentService.php
    // Exception messages for fulfillment operations
    // =========================================================================
    'fulfillmentservice_exception_already_closed' => 'এই সার্ভিস রিকোয়েস্টটি ইতিমধ্যে বন্ধ করা হয়েছে।',
    'fulfillmentservice_exception_over_issue_qty' => 'আপনি অনুমোদিত অবশিষ্ট পরিমাণের বেশি প্রদান করতে পারবেন না।',

    // =========================================================================
    // Origin: RequestService.php
    // Exception messages for request submission
    // =========================================================================
    'requestservice_exception_duplicate_pending' => 'এই আইটেমের জন্য আপনার ইতিমধ্যে একটি পেন্ডিং রিকোয়েস্ট আছে।',

    // =========================================================================
    // Origin: RequestableFactory.php
    // Exception message for unsupported types
    // =========================================================================
    'requestablefactory_exception_unsupported_type' => 'অসমর্থিত রিকোয়েস্টেবল টাইপ: :type',

    // =========================================================================
    // Origin: catalog/index.blade.php
    // Catalog page UI strings
    // =========================================================================
    'catalog_title' => 'সার্ভিস ক্যাটালগ',
    'catalog_hero_question' => 'আজ আপনার কাজ সম্পন্ন করতে কোন সরঞ্জাম বা সামগ্রীর প্রয়োজন?',
    'catalog_search_placeholder' => "আইটেম, ব্র্যান্ড বা ক্যাটাগরি খুঁজুন (উদাঃ 'ল্যাপটপ', 'মাউস', 'কাগজ')...",
    'catalog_quick_requests_label' => 'সচরাচর অনুরোধ করা আইটেম:',
    'catalog_pipeline_pending_label' => 'পেন্ডিং',
    'catalog_pipeline_approved_label' => 'অনুমোদিত',
    'catalog_pipeline_rejected_label' => 'প্রত্যাখ্যাত',
    'catalog_empty_state_title' => 'ক্যাটালগে কোনো আইটেম উপলব্ধ নেই।',
    'catalog_empty_state_subtitle' => 'পরে আবার চেষ্টা করুন অথবা আপনার প্রশাসকের সাথে যোগাযোগ করুন।',
    'catalog_card_details_label' => 'ডিটেইলস',
    'catalog_card_add_to_basket' => 'রিকোয়েস্ট বাস্কেটে যোগ করুন',
    'catalog_view_list_label' => 'লিস্ট',
    'catalog_view_grid_label' => 'গ্রিড',

    // =========================================================================
    // Origin: components/request-button.blade.php
    // Add-to-basket button and AJAX feedback
    // =========================================================================
    'requestbutton_btn_add_to_basket' => 'রিকোয়েস্ট বাস্কেটে যোগ করুন',
    'requestbutton_btn_adding' => 'যোগ করা হচ্ছে...',
    'requestbutton_btn_added' => 'যোগ করা হয়েছে!',
    'requestbutton_ajax_error' => 'আইটেম যোগ করতে ত্রুটি',

    // =========================================================================
    // Origin: fulfillment/index.blade.php
    // Fulfillment queue page UI strings
    // =========================================================================
    'fulfillment_title' => 'ফুলফিলমেন্ট কিউ',
    'fulfillment_header_title' => 'প্রদান অপেক্ষমাণ অনুমোদিত আইটেম',
    'fulfillment_status_awaiting_picking' => 'পিকিংয়ের জন্য অপেক্ষমাণ',
    'fulfillment_status_partially_dispatched' => 'আংশিকভাবে প্রেরণ করা হয়েছে',
    'fulfillment_btn_pick_issue' => 'আইটেম পিক এবং প্রদান করুন',
    'fulfillment_empty_state' => 'ইনভেন্টরি ফুলফিলমেন্টের জন্য বর্তমানে কোনো রিকোয়েস্ট অপেক্ষমাণ নেই।',

    // =========================================================================
    // Origin: fulfillment/show.blade.php
    // Fulfillment detail page UI strings
    // =========================================================================
    'fulfillment_show_title_prefix' => 'আইটেম পিক এবং প্রদান করুন: ',
    'fulfillment_show_header_log_handover' => 'ইনভেন্টরি হ্যান্ডওভার লগ করুন',
    'fulfillment_show_col_approved' => 'অনুমোদিত',
    'fulfillment_show_col_already_issued' => 'ইতিমধ্যে প্রদান করা হয়েছে',
    'fulfillment_show_col_remaining' => 'অবশিষ্ট',
    'fulfillment_show_col_issue_qty' => 'এখন প্রদান করার পরিমাণ',
    'fulfillment_show_fully_issued' => 'সম্পূর্ণ প্রদান করা হয়েছে',
    'fulfillment_show_btn_substitute' => 'বিকল্প আইটেম',
    'fulfillment_show_btn_back' => 'ফিরে যান',
    'fulfillment_show_confirm_handover' => 'হ্যান্ডওভার নিশ্চিত করছেন? এটি Snipe-IT ইনভেন্টরি থেকে বিয়োগ করবে এবং হিস্ট্রি লগে লিখবে।',
    'fulfillment_show_btn_log_checkout' => 'চেকআউট লগ করুন এবং আইটেম প্রদান করুন',
    'fulfillment_show_header_terminate' => 'রিকোয়েস্ট সমাপ্ত / বন্ধ করুন',
    'fulfillment_show_text_stockout' => 'স্থায়ী স্টকআউটের কারণে আইটেম প্রদান করা সম্ভব না হলে, আপনি অবশিষ্ট লাইন আইটেমগুলো ফোর্স ক্লোজ করতে পারেন।',
    'fulfillment_show_input_reason_placeholder' => 'ফোর্স ক্লোজ করার কারণ লিখুন...',
    'fulfillment_show_confirm_force_close' => 'আপনি কি এই রিকোয়েস্টটি সমাপ্ত করতে চান? অপ্রদানকৃত লাইনগুলো বাতিল করা হবে।',
    'fulfillment_show_btn_force_close' => 'রিকোয়েস্ট ফোর্স ক্লোজ করুন',
    'fulfillment_show_header_timeline' => 'রিকোয়েস্ট টাইমলাইন',
    'fulfillment_show_modal_title' => 'বিকল্প প্রতিস্থাপন',
    'fulfillment_show_modal_search_label' => 'স্টক বিকল্প খুঁজুন',
    'fulfillment_show_modal_btn_cancel' => 'বাতিল করুন',
    'fulfillment_show_modal_btn_save' => 'প্রতিস্থাপন সেভ করুন',

    // =========================================================================
    // Origin: fulfillment-register/index.blade.php
    // Fulfillment register list page UI strings
    // =========================================================================
    'fulfillment_register_title' => 'ফুলফিলমেন্ট রেজিস্টার',
    'fulfillment_register_header_title' => 'মাস্টার ফুলফিলমেন্ট রেজিস্টার (ঐতিহাসিক)',
    'fulfillment_register_status_label' => 'ফুলফিল করা কার্যালয় রিকোয়েস্ট',
    'fulfillment_register_btn_view_ledger' => 'লেজার ডিটেইলস দেখুন',
    'fulfillment_register_empty_state' => 'আপনার কার্যালয় লোকেশনের জন্য ঐতিহাসিকভাবে ফুলফিল করা কোনো রিকোয়েস্ট পাওয়া যায়নি।',

    // =========================================================================
    // Origin: fulfillment-register/show.blade.php
    // Fulfillment ledger detail page UI strings
    // =========================================================================
    'fulfillment_register_show_title_prefix' => 'ফুলফিলমেন্ট লেজার: ',
    'fulfillment_register_show_header_summary' => 'সার্ভিস রিকোয়েস্ট সারাংশ',
    'fulfillment_register_show_header_documents' => 'লিঙ্কড গুডস ইস্যু ডকুমেন্ট (ইনভেন্টরি লেজার)',
    'fulfillment_register_show_doc_label' => 'ডকুমেন্ট নং: ',
    'fulfillment_register_show_empty_ledger' => 'এই রিকোয়েস্টের জন্য কোনো লেজার ডকুমেন্ট তৈরি করা হয়নি।',
    'fulfillment_register_show_header_audit' => 'অডিট টাইমলাইন',

    // =========================================================================
    // Origin: hooks/basket-widget.blade.php
    // Floating basket widget strings (JavaScript)
    // =========================================================================
    'basket_widget_console_init' => 'Gov-Store: বাস্কেট উইজেট ইনিশিয়ালাইজ করা হচ্ছে।',
    'basket_widget_basket_label' => 'বাস্কেট',

    // =========================================================================
    // Origin: hooks/menu-injection.blade.php
    // Dynamic sidebar menu strings (JavaScript)
    // =========================================================================
    'menu_injection_console_build' => 'Gov-Store: ডাইনামিক ই-কমার্স মেনু তৈরি করা হচ্ছে।',
    'menu_injection_store_menu_title' => 'সরকারি স্টোর',
    'menu_injection_header_operations' => 'স্টোর অপারেশনস',

    // =========================================================================
    // Origin: user/index.blade.php
    // My Service Requests page UI strings
    // =========================================================================
    'user_index_title' => 'আমার সার্ভিস রিকোয়েস্ট',
    'user_index_header_my_requests' => 'আমার জমা দেওয়া সার্ভিস রিকোয়েস্ট',
    'user_index_btn_new_request' => 'নতুন রিকোয়েস্ট',
    'user_index_status_under_review' => 'পর্যবেক্ষণাধীন',
    'user_index_status_approved' => 'অনুমোদিত',
    'user_index_status_partially_approved' => 'আংশিকভাবে অনুমোদিত',
    'user_index_status_closed_fulfilled' => 'বন্ধ (ফুলফিল করা হয়েছে)',
    'user_index_status_rejected' => 'প্রত্যাখ্যাত',
    'user_index_empty_state_title' => 'আপনার এখনও কোনো সার্ভিস রিকোয়েস্ট জমা দেওয়া হয়নি।',

    // =========================================================================
    // Origin: admin/index.blade.php
    // Admin approvals dashboard UI strings
    // =========================================================================
    'admin_index_title' => 'সরকারি অনুমোদন ড্যাশবোর্ড',
    'admin_index_header_pending' => 'পর্যবেক্ষণের জন্য অপেক্ষমাণ রিকোয়েস্ট',
    'admin_index_empty_pending' => 'অনুমোদনের জন্য অপেক্ষমাণ কোনো রিকোয়েস্ট নেই।',
    'admin_index_header_processed' => 'সম্প্রতি প্রসেস করা রিকোয়েস্ট',
    'admin_index_status_approved' => 'অনুমোদিত',
    'admin_index_status_partially_approved' => 'আংশিকভাবে অনুমোদিত',
    'admin_index_status_closed_fulfilled' => 'বন্ধ / ফুলফিল করা হয়েছে',
    'admin_index_status_rejected' => 'প্রত্যাখ্যাত',
    'admin_index_empty_processed' => 'কোনো প্রসেস করা হিস্ট্রি উপলব্ধ নেই।',

    // =========================================================================
    // Origin: admin/show.blade.php
    // Admin review detail page UI strings
    // =========================================================================
    'admin_show_title_prefix' => 'সার্ভিস রিকোয়েস্ট পর্যালোচনা করুন: ',
    'admin_show_header_adjust_items' => 'লাইন আইটেমগুলো অ্যাডজাস্ট এবং প্রসেস করুন',
    'admin_show_label_purpose' => 'উদ্দেশ্য:',
    'admin_show_label_no_deadline' => 'কোনো ডেডলাইন সেট করা হয়নি',
    'admin_show_label_no_location' => 'কোনো লোকেশন নির্দিষ্ট করা হয়নি',
    'admin_show_col_item_details' => 'আইটেম ডিটেইলস',
    'admin_show_col_requested' => 'অনুরোধ করা হয়েছে',
    'admin_show_col_approved_qty' => 'অনুমোদিত পরিমাণ',
    'admin_show_col_decision' => 'সিদ্ধান্ত',
    'admin_show_btn_approve' => 'অনুমোদন করুন',
    'admin_show_btn_reject' => 'প্রত্যাখ্যান করুন',
    'admin_show_input_reason_placeholder' => 'পরিবর্তন/প্রত্যাখ্যানের কারণ...',
    'admin_show_btn_cancel' => 'বাতিল করুন',
    'admin_show_confirm_finalize' => 'আপনি কি এই লাইন-আইটেম সিদ্ধান্তগুলো চূড়ান্ত করতে চান?',
    'admin_show_btn_finalize' => 'সিদ্ধান্ত চূড়ান্ত করুন',
    'admin_show_header_timeline' => 'রিকোয়েস্ট টাইমলাইন',
    'admin_show_event_draft_created' => 'ড্রাফট তৈরি করা হয়েছে',
    'admin_show_event_submitted' => 'জমা দেওয়া হয়েছে',
    'admin_show_event_under_review' => 'পর্যবেক্ষণাধীন',

    // =========================================================================
    // Origin: admin/policies.blade.php
    // =========================================================================
    'policies_title' => 'ক্যাটাগরি পলিসি সেটিংস',
    'policies_header_title' => 'ক্যাটাগরি অনুমোদন পলিসি বরাদ্দ করুন',
    'policies_header_description' => 'প্রতিটি প্রোডাক্ট ক্যাটাগরির জন্য ডিফল্ট অনুমোদন রাউটিং রুল নির্দিষ্ট করুন। আইটেমগুলো স্বয়ংক্রিয়ভাবে এই পলিসিগুলো গ্রহণ করে।',
    'policies_policy_auto_approve' => 'AUTO_APPROVE (ম্যানেজারের অনুমোদন ছাড়াই তাৎক্ষণিকভাবে ফুলফিল করুন)',
    'policies_policy_primary_only' => 'PRIMARY_ONLY (প্রাইমারি অ্যাপ্রুভারের স্বাক্ষর প্রয়োজন)',
    'policies_policy_primary_and_final' => 'PRIMARY_AND_FINAL (প্রাইমারি + ফাইনাল অ্যাপ্রুভারের স্বাক্ষর প্রয়োজন)',
    'policies_btn_update' => 'আপডেট করুন',

    // =========================================================================
    // Origin: admin/locations.blade.php
    // =========================================================================
    'locations_title' => 'কার্যালয় বরাদ্দ সেটিংস',
    'locations_header_title' => 'কার্যালয় ওয়ার্কফ্লো রোল বরাদ্দ করুন',
    'locations_header_description' => 'প্রতিটি কার্যালয় লোকেশনের জন্য প্রাইমারি অ্যাপ্রুভার, অপশনাল ফাইনাল অ্যাপ্রুভার এবং স্টোরকিপার কনফিগার করুন।',
    'locations_select_primary' => '-- প্রাইমারি বরাদ্দ করুন --',
    'locations_select_no_final' => '-- কোনো ফাইনাল অ্যাপ্রুভার নেই (কেবল লেভেল ১) --',
    'locations_select_storekeeper' => '-- স্টোরকিপার বরাদ্দ করুন --',
    'locations_btn_save' => 'সেভ করুন',

    // =========================================================================
    // Origin: basket/index.blade.php
    // Basket page UI strings
    // =========================================================================
    'basket_index_title' => 'আমার সার্ভিস বাস্কেট',
    'basket_index_header_draft_items' => 'বাস্কেটে থাকা ড্রাফট আইটেম',
    'basket_index_empty_basket' => 'আপনার বাস্কেট খালি। আইটেম যোগ করতে ক্যাটালগ দেখুন।',
    'basket_index_btn_browse_catalog' => 'ক্যাটালগ দেখুন',
    'basket_index_tooltip_asset_restricted' => 'অ্যাসেট কেবল ১টি পরিমাণে অনুরোধ করা যাবে।',
    'basket_index_header_service_request_details' => 'সার্ভিস রিকোয়েস্ট ডিটেইলস',
    'basket_index_label_request_type' => 'রিকোয়েস্ট টাইপ',
    'basket_index_label_purpose' => 'উদ্দেশ্য',
    'basket_index_placeholder_purpose' => 'এই রিকোয়েস্টের সংক্ষিপ্ত উদ্দেশ্য লিখুন',
    'basket_index_label_justification' => 'যৌক্তিকতা',
    'basket_index_placeholder_justification' => 'কেন এই আইটেমগুলো প্রয়োজন তার ব্যাখ্যা দিন...',
    'basket_index_label_required_by_date' => 'প্রয়োজনীয় তারিখ',
    'basket_index_label_cost_center' => 'কস্ট সেন্টার',
    'basket_index_placeholder_cost_center' => 'উদাঃ DEPT-1234',
    'basket_index_label_delivery_location' => 'ডেলিভারি লোকেশন',
    'basket_index_select_no_location' => 'কোনো লোকেশন নেই',
    'basket_index_btn_submit' => 'সার্ভিস রিকোয়েস্ট জমা দিন',
    'insufficient_available_stock' => 'উপলভ্য মজুত সংরক্ষিত বা অপর্যাপ্ত। অনুমোদিত পরিমাণ কমান অথবা নতুন মজুত গ্রহণ করুন।',
    'saved' => 'সংরক্ষিত',
    'duplicate_asset' => 'একই সম্পদ দুইবার নির্বাচন করা যাবে না।',
    'all_categories' => 'সকল শ্রেণি',
    'all_groups' => 'সকল ধরন',
    'type_asset_model' => 'হার্ডওয়্যার',
    'type_accessory' => 'আনুষঙ্গিক সরঞ্জাম',
    'type_consumable' => 'সরবরাহ সামগ্রী',
    'search' => 'অনুসন্ধান',
    'notifications' => 'অনুরোধের হালনাগাদ',
    'notification_mail_subject' => 'গভস্টোর অনুরোধের হালনাগাদ',
    'notification_mail_body' => 'অনুরোধের হালনাগাদ এসেছে। আপনার অনুরোধ বা কার্যালয়ের তালিকা দেখতে প্রবেশ করুন: :url',
    'request_withdrawn' => 'অনুরোধ প্রত্যাহার করা হয়েছে।',
    'receipt_recorded' => 'প্রাপ্তি নিশ্চিত করা হয়েছে।',
    'withdraw' => 'প্রত্যাহার',
    'confirm_receipt' => 'প্রাপ্তি নিশ্চিত করুন',
    'event_pending_primary' => 'প্রাথমিক সিদ্ধান্তের অপেক্ষায়',
    'event_pending_final' => 'চূড়ান্ত সিদ্ধান্তের অপেক্ষায়',
    'event_submitted' => 'দাখিলকৃত',
    'event_auto_approved' => 'নীতিমালা অনুযায়ী স্বয়ংক্রিয়ভাবে অনুমোদিত',
    'event_approved' => 'অনুমোদিত',
    'event_partially_approved' => 'আংশিক অনুমোদিত',
    'event_rejected' => 'প্রত্যাখ্যাত',
    'event_cancelled' => 'প্রত্যাহৃত',
    'event_item_substituted' => 'বিকল্প সামগ্রী নির্ধারিত',
    'event_item_issued' => 'সামগ্রী বিতরণ করা হয়েছে',
    'event_issued' => 'বিতরণকৃত',
    'event_closed' => 'সমাপ্ত',
    'event_received' => 'প্রাপ্তি নিশ্চিত',
    'event_escalated' => 'বিলম্বিত সিদ্ধান্তের জন্য কার্যালয় প্রশাসনকে জানানো হয়েছে',
    'item_override' => 'সামগ্রীর পৃথক অনুমোদন নীতি',
    'item_type' => 'সামগ্রীর ধরন',
    'item_id' => 'ইনভেন্টরি সামগ্রীর আইডি',
    'approval_policy' => 'অনুমোদন নীতি',
    'threshold_qty' => 'দুই ধাপ অনুমোদনের পরিমাণসীমা',
    'threshold_value' => 'দুই ধাপ অনুমোদনের মূল্যসীমা',
    'threshold_help' => 'যেকোনো সীমায় পৌঁছালে বা অতিক্রম করলে দুইজন পৃথক অনুমোদনকারী প্রয়োজন। মূল্যসীমা থাকলে অজানা দামের সামগ্রীও দুই ধাপে পর্যালোচিত হবে।',
    'request_number' => 'অনুরোধ নম্বর',
    'purpose' => 'উদ্দেশ্য',
    'submitted_date' => 'দাখিলের তারিখ',
    'items' => 'সামগ্রী',
    'document_status' => 'নথির অবস্থা',
    'item_details' => 'সামগ্রীর বিবরণ',
    'requested' => 'অনুরোধকৃত',
    'approved_qty' => 'অনুমোদিত পরিমাণ',
    'decision' => 'সিদ্ধান্ত',
    'adjustment_notes' => 'সিদ্ধান্তের মন্তব্য',
    'executed_by' => 'সম্পাদন করেছেন',
    'substituted_original' => 'মূল সামগ্রী',
    'substituted_with' => 'বিকল্প সামগ্রী',
    'issued_label' => 'বিতরণকৃত',
    'quantity' => 'পরিমাণ',
    'no_events' => 'এখনও কোনো ঘটনার তথ্য নেই।',
    'no_requests' => 'আপনি এখনও কোনো সেবা অনুরোধ দাখিল করেননি।',
    'category' => 'ইনভেন্টরি শ্রেণি',
    'action' => 'পদক্ষেপ',
    'items_available' => 'উপলভ্য সামগ্রী',
    'in_stock' => 'মজুত আছে',
    'remaining' => 'অবশিষ্ট',
    'request_type_new_employee' => 'নতুন কর্মীর সরঞ্জাম',
    'request_type_replacement' => 'সরঞ্জাম প্রতিস্থাপন',
    'request_type_project' => 'প্রকল্পের চাহিদা',
    'request_type_office_setup' => 'কার্যালয় স্থানান্তর বা স্থাপন',
    'request_type_repair' => 'মেরামত বা রক্ষণাবেক্ষণ',
    'request_type_emergency' => 'জরুরি অনুরোধ',
    'request_type_other' => 'সাধারণ সরবরাহ',
    'substitution_help' => 'একই শ্রেণির এবং মূল সামগ্রীর চেয়ে বেশি দাম নয় এমন বিকল্প বাছুন। বেশি মূল্যের বিকল্পের জন্য নতুন অনুমোদন প্রয়োজন।',
    'requester' => 'অনুরোধকারী',
    'processed_date' => 'সিদ্ধান্তের তারিখ',
    'approval_date' => 'অনুমোদনের তারিখ',
    'fulfillment_status' => 'সরবরাহের অবস্থা',
    'purpose_location' => 'উদ্দেশ্য / সরবরাহের স্থান',
    'completion_date' => 'সম্পন্নের তারিখ',
    'justification' => 'যৌক্তিকতা',
    'required_location' => 'প্রয়োজনের তারিখ / সরবরাহের স্থান',
    'approved_by' => 'অনুমোদনকারী',
    'date' => 'তারিখ',
    'fulfillment_action' => 'হস্তান্তর নথিভুক্ত করুন',
    'handover_notes' => 'হস্তান্তরের মন্তব্য (ঐচ্ছিক)',
    'handover_placeholder' => 'গ্রহণকারী ও হস্তান্তরের তথ্য লিখুন',
    'select_serials' => 'সরবরাহের জন্য সম্পদ নির্বাচন করুন',
    'confirm_close' => 'বন্ধ নিশ্চিত করুন',
    'pipeline' => 'আমার অনুরোধের অগ্রগতি',
    'quick_laptop' => 'ল্যাপটপ',
    'quick_mouse' => 'মাউস',
    'quick_keyboard' => 'কিবোর্ড',
    'quick_toner' => 'টোনার',
    'quick_paper' => 'কাগজ',
    'quick_chair' => 'চেয়ার',
    'complete_issue' => 'সরবরাহ সম্পন্ন করুন',
    'stock_available' => 'এই অনুরোধের জন্য উপলব্ধ মজুত',
    'select_asset' => 'বারকোড স্ক্যান করুন বা সম্পদ নির্বাচন করুন',
    'serial' => 'ক্রমিক নম্বর',
    'location' => 'স্থান',
    'substitute_for' => 'বিকল্প নির্বাচন করুন',
    'lines' => 'টি লাইন',
    'unknown_user' => 'অজানা ব্যবহারকারী',
    'unknown_item' => 'অনুপলব্ধ সামগ্রী',
    'working_office' => 'কর্মরত দপ্তর',
    'system' => 'সিস্টেম',
    'not_available' => 'উপলব্ধ নয়',
    'event_unstarted' => 'সরবরাহের অপেক্ষায়',
    'event_partially_issued' => 'আংশিক সরবরাহ করা হয়েছে',
    'type_component' => 'যন্ত্রাংশ',
    'type_license' => 'লাইসেন্স',
    'type_asset' => 'সম্পদ',
    'asset_checkout_invalid' => 'সম্পদ :asset-এর আবশ্যক তথ্য অনুপস্থিত বা ভুল। আবার সরবরাহের আগে সম্পদের রেকর্ড যাচাই করুন।',
    'asset_checkout_invalid_fields' => 'সম্পদ :asset-এর এই তথ্যগুলো অনুপস্থিত বা ভুল: :fields। আবার সরবরাহের আগে সম্পদের রেকর্ড সংশোধন করুন।',
    'event_asset_metadata_repaired' => 'সম্পদের প্রাপ্তির তথ্য পুনরুদ্ধার করা হয়েছে',
];
