<?php

return [
    // =========================================================================
    // Origin: CustomRequestServiceProvider.php
    // Navigation menu titles registered in MenuRegistry
    // =========================================================================
    'serviceprovider_menu_browse_catalog'       => 'ক্যাটালগ দেখুন',
    'serviceprovider_menu_track_my_requests'    => 'আমার রিকোয়েস্ট ট্র্যাক করুন',
    'serviceprovider_menu_gov_approvals'        => 'সরকারি অনুমোদন',
    'serviceprovider_menu_fulfillment_queue'    => 'ফুলফিলমেন্ট কিউ',
    'serviceprovider_menu_fulfillment_register' => 'ফুলফিলমেন্ট রেজিস্টার',

    // =========================================================================
    // Origin: BasketController.php
    // Flash messages for basket CRUD operations
    // =========================================================================
    'basketcontroller_flash_item_added'            => 'আপনার সার্ভিস রিকোয়েস্ট বাস্কেটে আইটেম যোগ করা হয়েছে।',
    'basketcontroller_flash_item_added_ajax'       => 'আপনার বাস্কেটে আইটেম যোগ করা হয়েছে।',
    'basketcontroller_flash_qty_updated'           => 'বাস্কেটের পরিমাণ আপডেট করা হয়েছে।',
    'basketcontroller_flash_item_removed'          => 'বাস্কেট থেকে আইটেম মুছে ফেলা হয়েছে।',
    'basketcontroller_flash_request_submitted'     => 'সার্ভিস রিকোয়েস্ট [:numbers] সফলভাবে জমা দেওয়া হয়েছে!',
    'basketcontroller_error_empty_basket'          => 'আপনি একটি খালি সার্ভিস রিকোয়েস্ট বাস্কেট জমা দিতে পারবেন না।',
    'basketcontroller_error_no_office_location'    => 'আপনার ইউজার অ্যাকাউন্টে কোনো কার্যালয় লোকেশন অ্যাসাইন করা নেই।',
    'basketcontroller_error_no_approval_roles'     => 'আপনার কার্যালয় লোকেশনে কোনো অনুমোদন রোল কনফিগার করা নেই। অনুগ্রহ করে একজন প্রশাসকের সাথে যোগাযোগ করুন।',

    // =========================================================================
    // Origin: FulfillmentRegisterController.php
    // Authorization abort messages
    // =========================================================================
    'fulfillmentregistercontroller_abort_unauthorized' => 'ফুলফিলমেন্ট রেজিস্টারে অননুমোদিত অ্যাক্সেস।',

    // =========================================================================
    // Origin: GovApprovalController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govapprovalcontroller_abort_unauthorized'            => 'অনুমোদন ওয়ার্কফ্লোতে অননুমোদিত অ্যাক্সেস।',
    'govapprovalcontroller_abort_admin_required'          => 'অননুমোদিত। পলিসি কনফিগারেশনের জন্য সিস্টেম অ্যাডমিনিস্ট্রেটর প্রিভিলেজ প্রয়োজন।',
    'govapprovalcontroller_flash_processed'               => 'সার্ভিস রিকোয়েস্ট [:number] প্রসেস করা হয়েছে।',
    'govapprovalcontroller_flash_workflow_error'          => 'ওয়ার্কফ্লো ত্রুটি: :message',
    'govapprovalcontroller_flash_policy_updated'          => 'ক্যাটাগরি অনুমোদন পলিসি সফলভাবে আপডেট করা হয়েছে।',

    // =========================================================================
    // Origin: GovFulfillmentController.php
    // Authorization abort messages and flash messages
    // =========================================================================
    'govfulfillmentcontroller_abort_unauthorized'   => 'ফুলফিলমেন্ট লগে অননুমোদিত অ্যাক্সেস।',
    'govfulfillmentcontroller_flash_fulfillment'    => 'ফুলফিলমেন্ট লগ করা হয়েছে। Snipe-IT ইনভেন্টরি আপডেট করা হয়েছে।',
    'govfulfillmentcontroller_flash_fulfillment_error' => 'ফুলফিলমেন্ট ত্রুটি: :message',
    'govfulfillmentcontroller_flash_closed'         => 'সার্ভিস রিকোয়েস্ট [:number] স্থায়ীভাবে বন্ধ করা হয়েছে।',

    // =========================================================================
    // Origin: GovRequestController.php
    // Flash messages and log messages
    // =========================================================================
    'govrequestcontroller_flash_request_submitted'  => 'আইটেম রিকোয়েস্ট সফলভাবে জমা দেওয়া হয়েছে এবং অনুমোদনের জন্য পেন্ডিং আছে।',
    'govrequestcontroller_log_submit_error'         => 'রিকোয়েস্ট সাবমিট ত্রুটি: :message',

    // =========================================================================
    // Origin: ApprovalService.php
    // Exception messages for approval workflow decisions
    // =========================================================================
    'approvals service_exception_already_processed' => 'এই সার্ভিস রিকোয়েস্টটি ইতিমধ্যে প্রসেস করা হয়েছে।',
    'approvals_service_exception_no_decision'       => 'কোনো সিদ্ধান্ত প্রদান করা হয়নি।',
    'approvals_service_exception_qty_must_be_positive' => 'অনুমোদিত পরিমাণ ০ এর বেশি হতে হবে।',

    // =========================================================================
    // Origin: BasketService.php
    // Exception messages for basket operations
    // =========================================================================
    'basketservice_exception_qty_minimum'       => 'পরিমাণ অন্তত ১ হতে হবে।',
    'basketservice_exception_empty_basket'      => 'আপনি একটি খালি সার্ভিস রিকোয়েস্ট বাস্কেট জমা দিতে পারবেন না।',
    'basketservice_exception_no_office_location'=> 'আপনার ইউজার অ্যাকাউন্টে কোনো কার্যালয় লোকেশন অ্যাসাইন করা নেই।',
    'basketservice_exception_no_approval_roles' => 'আপনার কার্যালয় লোকেশনে কোনো অনুমোদন রোল কনফিগার করা নেই। অনুগ্রহ করে একজন প্রশাসকের সাথে যোগাযোগ করুন।',

    // =========================================================================
    // Origin: FulfillmentService.php
    // Exception messages for fulfillment operations
    // =========================================================================
    'fulfillmentservice_exception_already_closed'   => 'এই সার্ভিস রিকোয়েস্টটি ইতিমধ্যে বন্ধ করা হয়েছে।',
    'fulfillmentservice_exception_over_issue_qty'   => 'আপনি অনুমোদিত অবশিষ্ট পরিমাণের বেশি প্রদান করতে পারবেন না।',

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
    'catalog_title'                           => 'সার্ভিস ক্যাটালগ',
    'catalog_hero_question'                   => 'আজ আপনার কাজ সম্পন্ন করতে কোন সরঞ্জাম বা সামগ্রীর প্রয়োজন?',
];
