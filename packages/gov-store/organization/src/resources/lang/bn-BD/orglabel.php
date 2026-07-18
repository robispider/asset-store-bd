<?php

return [

    // =========================================================================
    // Origin: ConfigurationController.php
    // =========================================================================
    'configuration_controller_access_denied' => 'অ্যাক্সেস প্রত্যাখ্যাত: আপনি কার্যালয় প্রশাসক হিসেবে নিযুক্ত নন।',
    'configuration_controller_roles_saved'   => 'কার্যালয়ের রোলগুলো সফলভাবে সংরক্ষণ করা হয়েছে।',
    'configuration_controller_config_error'  => 'কনফিগারেশন ত্রুটি: :message',

    // =========================================================================
    // Origin: MinistryDirectoryController.php
    // =========================================================================
    'directory_controller_unauthorized'      => 'সরকারি ডিরেক্টরি কনফিগারেটরে অননুমোদিত অ্যাক্সেস।',
    'directory_controller_import_unauthorized' => 'অননুমোদিত।',
    'directory_controller_sync_success'      => 'সরকারি ডিরেক্টরি সিনক্রোনাইজ করা হয়েছে! রেকর্ড সিন্ক করা হয়েছে: :processed, কোম্পানি তৈরি করা হয়েছে: :created, কোম্পানি ম্যাচ করা হয়েছে: :matched।',
    'directory_controller_warnings_count'    => 'সতর্কবাণী তৈরি হয়েছে: :count',
    'directory_controller_import_failed'     => 'ইমপোর্ট ব্যর্থ হয়েছে: :message',

    // =========================================================================
    // Origin: OfficeHubController.php
    // =========================================================================
    'hub_controller_access_denied_not_profiled' => 'অ্যাক্সেস প্রত্যাখ্যাত: এই কার্যালয় ভবনটি প্রোফাইল করা হয়নি।',
    'hub_controller_not_configured'             => 'এই কার্যালয় ভবনটি এখনও ভৌগোলিক এলাকা প্যারামিটার দিয়ে কনফিগার করা হয়নি। অনুগ্রহ করে প্রথমে এটি প্রোভিশন করুন।',
    'hub_controller_profile_not_found'          => 'কার্যালয়ের প্রোফাইল ডিটেইলস পাওয়া যায়নি।',
    'hub_controller_update_success'             => 'কার্যালয়ের প্রোফাইল সফলভাবে আপডেট করা হয়েছে।',
    'hub_controller_roles_saved'                => 'কার্যালয়ের রোল কনফিগারেশন সংরক্ষণ করা হয়েছে।',
    'hub_controller_geo_verified'               => 'ভৌগোলিক ট্যাগের নির্ভুলতা যাচাই এবং লক করা হয়েছে।',
    'hub_controller_geo_verify_success'         => 'ভৌগোলিক এলাকা সফলভাবে ট্যাগ এবং যাচাই করা হয়েছে।',

    // =========================================================================
    // Origin: OnboardLocationController.php
    // =========================================================================
    'onboard_controller_unauthorized'          => 'অননুমোদিত প্রশাসনিক অনুরোধ।',
    'onboard_controller_onboarded_success'     => 'বিদ্যমান কার্যালয় সফলভাবে অনবোর্ড করা হয়েছে।',

    // =========================================================================
    // Origin: ProvisioningController.php
    // =========================================================================
    'provisioning_controller_unauthorized'     => 'অননুমোদিত প্রশাসনিক অনুরোধ।',
    'provisioning_controller_provisioned'      => 'কার্যালয় সফলভাবে প্রোভিশন করা হয়েছে।',
    'provisioning_controller_admin_updated'    => 'কার্যালয় প্রশাসক আপডেট করা হয়েছে।',
    'provisioning_controller_update_error'     => 'আপডেট ত্রুটি: :message',
    'provisioning_controller_jurisdiction_mapped' => 'আইসিটি অফিসারের বাউন্ডারি সফলভাবে ম্যাপ করা হয়েছে।',
    'provisioning_controller_mapping_error'    => 'ম্যাপিং ত্রুটি: :message',
    'provisioning_controller_jurisdiction_revoked' => 'আইসিটি অফিসারের জুরিসডিকশন বাতিল করা হয়েছে।',
    'provisioning_controller_revocation_error' => 'বাতিলকরণ ত্রুটি: :message',

    // =========================================================================
    // Origin: OfficeProvisioningService.php
    // =========================================================================
    'provisioning_service_access_denied_boundary' => 'অ্যাক্সেস প্রত্যাখ্যাত: নির্বাচিত এলাকাটি আপনার নির্ধারিত ভৌগোলিক জুরিসডিকশনের বাইরে।',
    'provisioning_service_duplicate_notice'     => 'সতর্কবাণী: এই বিভাগ/মন্ত্রণালয়ের একটি কার্যালয় ইতিমধ্যে এই ভৌগোলিক এলাকায় নিবন্ধিত।',

    // =========================================================================
    // Origin: OfficeReadinessService.php
    // =========================================================================
    // (No user-facing strings in this service — all logic is internal)

    // =========================================================================
    // Origin: MinistryDirectoryImporter.php
    // =========================================================================
    'importer_csv_not_found'                  => 'নির্দিষ্ট CSV ডেটাসেটটি এই পাথে পাওয়া যায়নি: :path',
    'importer_circular_relationship'          => 'ID :id (:name) এর সার্কুলার রিলেশনশিপ উপেক্ষা করা হয়েছে।',
    'importer_unresolved_parent'              => 'ID :id এর ওপর অমীমাংসিত প্যারেন্ট রেফারেন্স \' :parent\'।',

    // =========================================================================
    // Origin: OfficeRegistryViewModel.php
    // =========================================================================
    'view_model_root_office'                  => 'রুট কার্যালয়',
    'view_model_unmapped'                     => 'আনম্যাপড',
    'view_model_standalone'                   => 'স্ট্যান্ডঅ্যালোন কার্যালয়',
    'view_model_unassigned'                   => 'আনঅ্যাসাইনড',

    // =========================================================================
    // Origin: EnsureOfficeIsOperational.php (Middleware)
    // =========================================================================
    'middleware_operational_access_denied'    => 'অ্যাক্সেস প্রত্যাখ্যাত: কার্যালয় প্রোভিশনিংয়ের জন্য একটি সক্রিয় আইসিটি অফিসার জুরিসডিকশন অ্যাসাইনমেন্ট প্রয়োজন।',

    // =========================================================================
    // Origin: EnsureUserIsIctOfficer.php (Middleware)
    // =========================================================================
    'middleware_ict_check_denied'             => 'অ্যাক্সেস প্রত্যাখ্যাত: কার্যালয় প্রোভিশনিংয়ের জন্য একটি সক্রিয় আইসিটি অফিসার জুরিসডিকশন অ্যাসাইনমেন্ট প্রয়োজন।',

    // =========================================================================
    // Origin: configuration/index.blade.php
    // =========================================================================
    'config_title'                            => 'আমার কার্যালয় ব্যবস্থাপনা',
    'config_status_label'                     => 'কার্যালয়ের স্ট্যাটাস:',
    'config_checklist_designate_admin'        => 'কার্যালয় প্রশাসক নিযুক্ত করুন',
    'config_checklist_assign_approver'        => 'প্রাথমিক অনুমোদনকারী নিযুক্ত করুন',
    'config_checklist_assign_storekeeper'     => 'স্টোরকিপার নিযুক্ত করুন',
    'config_checklist_mapped_employees'       => 'ম্যাপ করা কর্মকর্তা/কর্মচারী (নূন্যতম: ১)',
    'config_checklist_user_count_suffix'      => 'ইউজার(সমূহ)',
    'config_checklist_configured'             => 'কনফিগার করা হয়েছে',
    'config_checklist_missing'                => 'অনুপস্থিত',
    'config_operational_verified'             => 'যাচাই করা হয়েছে। এই কার্যালয়টি সক্রিয় এবং রিকোয়েস্ট প্রসেস করার জন্য প্রস্তুত।',
    'config_pending_instruction'              => 'আপনার স্টোরফ্রন্ট সক্রিয় করতে নিচে স্থানীয় স্টাফ রোলগুলো নিযুক্ত করুন।',
    'config_profile_title'                    => 'কার্যালয়ের প্রোফাইল',
];
