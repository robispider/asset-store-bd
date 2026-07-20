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

    // =========================================================================
    // Origin: directory/index.blade.php
    // =========================================================================
    'directory_title'                         => 'সরকারি ডিরেক্টরি ইমপোর্ট',
    'directory_sync_title'                    => 'ডিরেক্টরি সিনক্রোনাইজ করুন',
    'directory_sync_description'              => 'এই সার্ভিসটি প্রামাণিক বাংলাদেশ সরকারি ডিরেক্টরি ইমপোর্ট করে। এটি রিকার্সিভলি হায়ারার্কিকাল ইনডেক্স তৈরি করে এবং Snipe-IT-এর কোর ক্যাটালগে ম্যাচিং ফ্ল্যাট কোম্পানি এন্ট্রিগুলো স্বয়ংক্রিয়ভাবে রেজিস্টার করে।',
    'directory_sync_complete'                 => 'সিনক্রোনাইজেশন সম্পূর্ণ!',
    'directory_option_bundled_title'          => 'অপশন এ: বান্ডেলড ডেটাসেট চালান',
    'directory_option_bundled_desc'           => 'GovStore প্যাকেজে অন্তর্ভুক্ত প্রি-ভেরিফাইড কোর ডেটাসেট (bangladesh_ministries_bilingual.csv) ইমপোর্ট করে।',
    'directory_option_bundled_button'         => 'বান্ডেলড প্যাকেজ ইমপোর্ট চালান',
    'directory_option_custom_title'           => 'অপশন বি: কাস্টম ডেটাসেট আপলোড করুন',
    'directory_upload_label'                  => 'CSV ফাইল আপলোড করুন',
    'directory_upload_button'                 => 'আপলোড এবং ডিরেক্টরি সিনক্রোনাইজ করুন',
    'directory_status_title'                  => 'রেজিস্ট্রি স্ট্যাটাস',
    'directory_total_registered'              => 'মোট নিবন্ধিত নোড:',
    'directory_preview_title'                 => 'সাম্প্রতিক ডিরেক্টরি প্রিভিউ (রুট লেভেল নোড)',
    'directory_col_id'                        => 'ID',
    'directory_col_en_name'                   => 'ইংরেজি নাম',
    'directory_col_bn_name'                   => 'বাংলা নাম',
    'directory_col_type'                      => 'টাইপ',
    'directory_empty_state'                   => 'ডিরেক্টরি এখনও পপুলেট করা হয়নি। শুরু করতে বাম দিকে একটি ইমপোর্ট চালান।',

    // =========================================================================
    // Origin: provisioning/create.blade.php
    // =========================================================================
    'create_title'                            => 'সরকারি কার্যালয় প্রোভিশন করুন',
    'create_workspace_title'                  => 'কার্যালয় নিবন্ধন ওয়ার্কস্পেস',
    'create_section_identity'                 => '১. কার্যালয় ভবনের পরিচয়',
    'create_field_office_name'                => 'কার্যালয়ের নাম',
    'create_placeholder_office_name'          => 'উদাঃ দেবিদ্বার উপজেলা স্বাস্থ্য কমপ্লেক্স',
    'create_section_geography'                => '২. ভৌগোলিক সীমানা ট্যাগ',
    'create_field_geo_area'                   => 'প্রশাসনিক এলাকা সীমানা',
    'create_placeholder_geo_area'             => '-- বিভাগ, জেলা, উপজেলা বা ইউনিয়ন খুঁজতে টাইপ করুন --',
    'create_help_geo_area'                    => 'বাধ্যতামূলক। এটি ভবনটিকে তার স্ট্যান্ডার্ড জিও-কোড প্যারামিটারের সাথে লক করে।',
    'create_section_hierarchy'                => '৩. সাংগঠনিক হায়ারার্কি এবং সেটআপ',
    'create_field_ministry'                   => 'মন্ত্রণালয় / বিভাগীয় মালিকানা (ঐচ্ছিক)',
    'create_placeholder_standalone'           => '-- স্ট্যান্ডঅ্যালোন কার্যালয় (মন্ত্রণালয় নেই) --',
    'create_field_parent_office'              => 'প্যারেন্ট রিজিওনাল/ডিস্ট্রিক্ট কার্যালয় (ঐচ্ছিক)',
    'create_placeholder_no_parent'            => '-- কোনো প্যারেন্ট নেই (রুট লোকেশন) --',
    'create_field_delegate_admin'             => 'প্রতিনিধিত্বকারী কার্যালয় প্রশাসক (ঐচ্ছিক)',
    'create_placeholder_leave_unassigned'     => '-- আপাতত বরাদ্দহীন রাখুন --',
    'create_help_delegate_admin'              => 'প্রতিনিধিত্বকারী প্রশাসক তার নিজস্ব ওয়ার্কফ্লো রোল কনফিগার করার জন্য ইমেল সেটআপ ক্রেডেনশিয়াল পাবেন।',
    'create_button_return_registry'           => 'রেজিস্ট্রিতে ফিরে যান',
    'create_button_save_provision'            => 'সেভ এবং কার্যালয় প্রোভিশন করুন',
    'create_duplicate_warning_title'          => 'রেজিস্ট্রি সতর্কবাণী: অনুরূপ কার্যালয় পাওয়া গেছে',
    'create_duplicate_warning_desc'           => 'নির্বাচিত মন্ত্রণালয়ের একটি কার্যালয় ইতিমধ্যে এই ভৌগোলিক সীমানায় নিবন্ধিত। সেভ করার আগে যাচাই করুন এটি একটি পৃথক ভবন কি না:',
    'create_duplicate_note'                   => 'নোট: এটি নিবন্ধন ব্লক করে না; এটি একটি ডেটা-ইনটিগ্রিটি প্রি-চেক হিসেবে কাজ করে।',
    'create_guidelines_title'                 => 'ফিল্ড ডিপ্লয়মেন্ট নির্দেশিকা',
    'create_advisory_spatial_title'           => 'স্পেশিয়াল-ফার্স্ট প্রিন্সিপাল',
    'create_advisory_spatial_desc'            => 'প্রতিটি কার্যালয় ভৌতভাবে কোথাও অবস্থিত। প্রথমে এর স্ট্যান্ডার্ড ভৌগোলিক এলাকা নির্ধারণ করে, আপনি স্পেশিয়াল অডিট ট্র্যাকিং, প্রক্সিমিটি ডিসপ্যাচিং এবং কভারেজ ডেনসিটি স্ট্যাটিস্টিকস সক্ষম করতে পারেন।',
    'create_step1_label'                      => 'ধাপ ১ (কার্যালয় পরিচয়):',
    'create_step1_desc'                       => 'সরকারি ডিরেক্টরির সাথে সামঞ্জস্যপূর্ণ স্ট্যান্ডার্ড বানান ব্যবহার করুন।',
    'create_step2_label'                      => 'ধাপ ২ (এলাকা ট্যাগ):',
    'create_step2_desc'                       => 'প্ল্যাটফর্ম ডেটাবেসে ম্যাপ করা যেকোনো প্রশাসনিক স্তর (জেলা, উপজেলা বা ইউনিয়ন) নির্বাচন করুন।',
    'create_step3_label'                      => 'ধাপ ৩ (মালিকানা):',
    'create_step3_desc'                       => 'প্যারেন্ট এবং মন্ত্রণালয় কাঠামো নির্ধারণ করা প্রথম দিনে সম্পূর্ণ ঐচ্ছিক এবং পরবর্তীতে হাব-এর ভেতরে সেকেন্ডারি কনফিগারেশনের সময় ম্যাপ করা যেতে পারে।',

    // =========================================================================
    // Origin: provisioning/hub.blade.php
    // =========================================================================
    'hub_title_prefix'                        => 'কার্যালয় হাব: ',
    'hub_status_operational'                  => 'কার্যকর',
    'hub_status_configured'                   => 'কনফিগার করা হয়েছে',
    'hub_status_provisioned'                  => 'প্রোভিশনড (পেন্ডিং)',
    'hub_tab_overview'                        => 'সাধারণ তথ্য',
    'hub_tab_roles'                           => 'ওয়ার্কফ্লো রোল',
    'hub_tab_employees'                       => 'স্থানীয় কর্মকর্তা/কর্মচারী',
    'hub_tab_geography'                       => 'স্পেশিয়াল ইনটিগ্রিটি',
    'hub_tab_timeline'                        => 'অ্যাক্টিভিটি টাইমলাইন',
    'hub_field_office_name'                   => 'কার্যালয় ভবনের নাম',
    'hub_field_ministry'                      => 'মন্ত্রণালয় / বিভাগীয় মালিকানা (ঐচ্ছিক)',
    'hub_field_parent_office'                 => 'প্যারেন্ট রিজিওনাল / ডিস্ট্রিক্ট কার্যালয় (ঐচ্ছিক)',
    'hub_field_geo_area'                      => 'ভৌগোলিক সীমানা এলাকা',
    'hub_field_office_admin'                  => 'নিযুক্ত কার্যালয় প্রশাসক',
    'hub_placeholder_no_admin'                => '-- কোনো প্রশাসক নিযুক্ত নেই --',
    'hub_save_button'                         => 'কার্যালয়ের ডিটেইলস সেভ করুন',
    'hub_checklist_admin_assigned'            => 'কার্যালয় প্রশাসক নিযুক্ত করা হয়েছে',
    'hub_checklist_primary_assigned'          => 'প্রাথমিক অনুমোদনকারী নিযুক্ত করা হয়েছে',
    'hub_checklist_storekeeper_assigned'      => 'স্টোরকিপার নিযুক্ত করা হয়েছে',
    'hub_checklist_staff_count'               => 'স্টাফ সংখ্যা (নূন্যতম: ১)',
    'hub_checklist_ready'                     => 'প্রস্তুত',
    'hub_checklist_mapped'                    => 'ম্যাপ করা হয়েছে',
    'hub_checklist_verified_passed'           => 'যাচাই সফল। কর্মকর্তা স্টোরফ্রন্ট সক্রিয়।',
    'hub_checklist_pending_unlock'            => 'স্থানীয় কর্মকর্তা কর্মচারীদের জন্য ক্যাটালগ আনলক করতে উপরের অসম্পূর্ণ আইটেমগুলো সম্পন্ন করুন।',
    'hub_employee_name'                       => 'কর্মকর্তা/কর্মচারীর নাম',
    'hub_employee_username'                   => 'ইউজারনেম',
    'hub_employee_email'                      => 'ইমেল ঠিকানা',
    'hub_employee_jobtitle'                   => 'পদবী',
    'hub_no_employees_message'                => 'Snipe-IT-এর ভেতরে এই লোকেশনে বর্তমানে কোনো কর্মকর্তা/কর্মচারী প্রোফাইল ম্যাপ করা নেই। <br>স্টাফ যোগ করতে, Snipe-IT-এর ভেতরে তাদের ইউজার প্রোফাইল এডিট করুন এবং তাদের <strong>Location</strong> ফিল্ডটি এই ভবনের সাথে সেট করুন।',
    'hub_geo_mapped_district'                 => 'ম্যাপ করা জেলা (Zila):',
    'hub_geo_mapped_upazila'                  => 'ম্যাপ করা উপজেলা/সিটি:',
    'hub_geo_geographical_level'              => 'ভৌগোলিক স্তর:',
    'hub_geo_hierarchy_path'                  => 'হায়ারার্কি ID পাথ:',
    'hub_geo_unassigned'                      => 'আনঅ্যাসাইনড',
    'hub_geo_admin_verification'              => 'প্রশাসনিক যাচাইকরণ',
    'hub_geo_verified_title'                  => 'ভৌগোলিক স্থানাঙ্ক যাচাই করা হয়েছে',
    'hub_geo_signoff_label'                   => 'সাইন-অফ কার্যকর হয়েছে:',
    'hub_geo_audited_by'                      => 'অডিট করা হয়েছে:',
    'hub_geo_system_administrator'            => 'সিস্টেম অ্যাডমিনিস্ট্রেটর',
    'hub_geo_not_verified'                    => 'ভৌগোলিক এলাকা এখনও যাচাই করা হয়নি।',
    'hub_geo_verify_button'                   => 'ভৌগোলিক ট্যাগের নির্ভুলতা যাচাই করুন',
    'hub_activity_title'                      => 'অ্যাক্টিভিটি টাইমলাইন',
    'hub_activity_empty'                      => 'এই কার্যালয়ের জন্য এখনও কোনো অ্যাক্টিভিটি লগ করা হয়নি।',
    'hub_activity_col_timestamp'              => 'টাইমস্ট্যাম্প',
    'hub_activity_col_event'                  => 'ইভেন্ট টাইপ',
    'hub_activity_col_performer'              => 'সম্পাদনকারী',

    // =========================================================================
    // Origin: provisioning/index.blade.php
    // =========================================================================
    'registry_title'                          => 'সরকারি কার্যালয় রেজিস্ট্রি',
    'registry_metric_total_offices'           => 'মোট নিবন্ধিত কার্যালয়',
    'registry_metric_operational'             => 'কার্যকর কার্যালয়',
    'registry_metric_pending'                 => 'কনফিগারেশন পেন্ডিং',
    'registry_metric_ministries'              => 'যুক্ত মন্ত্রণালয়',
    'registry_search_label'                   => 'খুঁজুন:',
    'registry_search_placeholder'             => 'কার্যালয়ের নাম বা প্রশাসক...',
    'registry_ministry_label'                 => 'মন্ত্রণালয়:',
    'registry_all_ministries'                 => '-- সব মন্ত্রণালয় --',
    'registry_district_label'                 => 'জেলা:',
    'registry_all_districts'                  => '-- সব জেলা --',
    'registry_status_label'                   => 'স্ট্যাটাস:',
    'registry_all_statuses'                   => '-- সব স্ট্যাটাস --',
    'registry_status_operational'             => 'কার্যকর',
    'registry_status_configured'              => 'কনফিগার করা হয়েছে',
    'registry_status_provisioned'             => 'প্রোভিশনড (পেন্ডিং)',
    'registry_filter_button'                  => 'ফিল্টার',
    'registry_reset_button'                   => 'রিসেট',
    'registry_onboard_button'                 => 'বিদ্যমান কার্যালয় অনবোর্ড করুন',
    'registry_create_button'                  => 'নতুন কার্যালয় প্রোভিশন করুন',
    'registry_table_offices_count'            => 'নিবন্ধিত সরকারি কার্যালয়',
    'registry_col_office_building'             => 'কার্যালয় ভবন',
    'registry_col_administrative_territory'   => 'প্রশাসনিক এলাকা',
    'registry_col_owning_ministry'            => 'মালিকানা মন্ত্রণালয়',
    'registry_col_office_administrator'       => 'কার্যালয় প্রশাসক',
    'registry_col_readiness_status'           => 'প্রস্তুতি স্ট্যাটাস',
    'registry_col_actions'                    => 'অ্যাকশন',
    'registry_parent_label'                   => 'প্যারেন্ট:',
    'registry_status_operational_label'       => 'কার্যকর',
    'registry_status_configured_label'        => 'কনফিগার করা হয়েছে',
    'registry_status_provisioned_label'       => 'প্রোভিশনড',
    'registry_status_needs_primary'           => 'প্রয়োজন:',
    'registry_status_unconfigured'            => 'আনকনফিগারড',
    'registry_onboard_office_button'          => 'কার্যালয় অনবোর্ড করুন',
    'registry_onboard_tooltip'                => 'এই লোকেশনে জিওগ্রাফি ম্যাপ করুন',
    'registry_view_hub_button'                => 'হাব দেখুন',
    'registry_view_hub_tooltip'               => 'কার্যালয় হাব খুলুন',
    'registry_empty_state'                    => 'আপনার মানদণ্ডের সাথে মিলিয়ে কোনো সরকারি কার্যালয় পাওয়া যায়নি।',

    // =========================================================================
    // Origin: provisioning/jurisdictions.blade.php
    // =========================================================================
    'jurisdictions_title'                     => 'আইসিটি অফিসার জুরিসডিকশন',
    'jurisdictions_map_title'                 => 'আইসিটি অফিসার বাউন্ডারি ম্যাপ করুন',
    'jurisdictions_select_employee_label'     => 'কর্মকর্তা/কর্মচারী ইউজার নির্বাচন করুন',
    'jurisdictions_select_employee_placeholder' => '-- কর্মকর্তা/কর্মচারী নির্বাচন করুন --',
    'jurisdictions_help_employee'             => 'সেই কর্মকর্তা/কর্মচারী ইউজার অ্যাকাউন্টটি নির্বাচন করুন যিনি ফিল্ড আইসিটি অফিসার হিসেবে কাজ করবেন।',
    'jurisdictions_jurisdiction_label'        => 'অপারেশনাল জুরিসডিকশন বাউন্ডারি',
    'jurisdictions_jurisdiction_placeholder'  => '-- খুঁজতে টাইপ করুন --',
    'jurisdictions_help_jurisdiction'         => 'এই অফিসারকে একটি নির্দিষ্ট বিভাগ, জেলা বা উপজেলায় বরাদ্দ করুন। তারা কেবল এই সীমানার ভেতরে কার্যালয় প্রোভিশন করতে পারবেন।',
    'jurisdictions_save_button'               => 'সেভ এবং অফিসার ডেলিগেট করুন',
    'jurisdictions_assigned_title'            => 'ম্যাপ করা আইসিটি প্রোভিশনিং অফিসার',
    'jurisdictions_col_officer_details'       => 'অফিসারের ডিটেইলস',
    'jurisdictions_col_home_office'           => 'হোম কার্যালয় বেস',
    'jurisdictions_col_jurisdiction_boundary' => 'বরাদ্দকৃত জুরিসডিকশন বাউন্ডারি',
    'jurisdictions_col_action'                => 'অ্যাকশন',
    'jurisdictions_username_label'            => 'ইউজারনেম:',
    'jurisdictions_no_home_office'            => 'কোনো হোম কার্যালয় সেট করা নেই',
    'jurisdictions_unmapped'                  => 'আনম্যাপড',
    'jurisdictions_revoke_confirm'            => 'এই ইউজারের ভৌগোলিক প্রোভিশনিং প্রিভিলেজ বাতিল করবেন কি?',
    'jurisdictions_revoke_button'             => 'বাতিল করুন',
    'jurisdictions_empty_state'               => 'ডেটাবেসে এখনও কোনো আইসিটি অফিসার ম্যাপ করা হয়নি।',

    // =========================================================================
    // Origin: provisioning/onboard.blade.php
    // =========================================================================
    'onboard_title'                           => 'বিদ্যমান Snipe-IT লোকেশন অনবোর্ড করুন',
    'onboard_workspace_title'                 => 'বিদ্যমান কার্যালয় লোকেশন ম্যাপ করুন',
    'onboard_section_mapped_office'           => '১. ম্যাপ করা কার্যালয় ভবন',
    'onboard_field_select_location_label'     => 'Snipe-IT লোকেশন নির্বাচন করুন',
    'onboard_placeholder_choose_unprovisioned' => '-- প্রোভিশনহীন ভবন নির্বাচন করুন --',
    'onboard_help_unprovisioned'              => 'এখানে কেবল সেই সক্রিয় Snipe-IT লোকেশনগুলো দেখানো হচ্ছে যেগুলোর ভৌগোলিক কনফিগারেশন ম্যাপিং নেই।',
    'onboard_section_geography'               => '২. ভৌগোলিক সীমানা ট্যাগ',
    'onboard_field_geo_area_label'            => 'প্রশাসনিক এলাকা সীমানা',
    'onboard_placeholder_search_geo'          => '-- বিভাগ, জেলা, উপজেলা বা ইউনিয়ন খুঁজুন এবং নির্বাচন করুন --',
    'onboard_section_hierarchy'               => '৩. সাংগঠনিক হায়ারার্কি এবং সেটআপ',
    'onboard_field_ministry_label'            => 'মন্ত্রণালয় / বিভাগীয় মালিকানা (ঐচ্ছিক)',
    'onboard_placeholder_standalone'          => '-- স্ট্যান্ডঅ্যালোন কার্যালয় (মন্ত্রণালয় নেই) --',
    'onboard_field_admin_label'               => 'কার্যালয় প্রশাসক বরাদ্দ করুন (ঐচ্ছিক)',
    'onboard_placeholder_leave_unassigned'    => '— আপাতত বরাদ্দহীন রাখুন —',
    'onboard_button_return_registry'          => 'রেজিস্ট্রিতে ফিরে যান',
    'onboard_button_onboard_map'              => 'অনবোর্ড এবং কার্যালয় ম্যাপ করুন',
    'onboard_guidelines_title'                => 'অনবোর্ডিং নির্দেশিকা',
    'onboard_guidelines_desc'                 => 'এই প্যানেলটি আপনাকে আপনার নতুন ভৌগোলিক ওয়ার্কস্পেস মডেলে আগে থেকে বিদ্যমান লিগ্যাসি Snipe-IT লোকেশন রেকর্ডগুলো ইন্টিগ্রেট করতে সাহায্য করে।',
    'onboard_guidelines_point1'               => 'একটি বিদ্যমান ভবন নির্বাচন করলে এটি <code>gov_geo_areas</code>-এ তার ভৌত এলাকার সাথে ম্যাপ হয়।',
    'onboard_guidelines_point2'               => 'এই প্রক্রিয়াটি Snipe-IT-এর কোর ডিরেক্টরিতে ভবনের কোনো ডুপ্লিকেট তৈরি করে না; বরং এটি স্থানিক কনটেক্সট দিয়ে সমৃদ্ধ করে।',

    // =========================================================================
    // Origin: readiness/unassigned.blade.php
    // =========================================================================
    'unassigned_title'                        => 'কার্যালয় লোকেশন অনুপস্থিত',
    'unassigned_message'                      => 'আপনার ইউজার অ্যাকাউন্টটি বর্তমানে ডেটাবেসে কোনো সক্রিয় কার্যালয় লোকেশনের সাথে ম্যাপ করা নেই। ক্যাটালগ দেখতে এবং রিকোয়েস্ট জমা দিতে আপনাকে অবশ্যই একটি কার্যালয় লোকেশনে বরাদ্দ করতে হবে।',
    'unassigned_help'                         => 'অনুগ্রহ করে আপনার স্থানীয় কার্যালয় প্রশাসক বা আইসিটি অফিসারের সাথে যোগাযোগ করুন যাতে তারা Snipe-IT-এর ভেতরে আপনার প্রোফাইল লোকেশন আপডেট করতে পারেন।',
    'unassigned_return_home'                  => 'হোমে ফিরে যান',

    // =========================================================================
    // Origin: readiness/waiting.blade.php
    // =========================================================================
    'waiting_title'                           => 'কার্যালয় সক্রিয়করণের জন্য অপেক্ষমাণ',
    'waiting_heading'                         => 'কার্যালয় সক্রিয়করণ পেন্ডিং',
    'waiting_message'                         => ' <strong class="text-muted">:name</strong> বর্তমানে সিস্টেমে ম্যাপ করা হয়েছে কিন্তু অপারেশনাল সেটআপ সম্পন্ন হয়নি। নিচের চেকলিস্টটি সম্পন্ন হলে ক্যাটালগ আনলক হবে:',
    'waiting_checklist_admin_designated'      => 'কার্যালয় প্রশাসক নিযুক্ত করা হয়েছে',
    'waiting_checklist_completed'             => 'সম্পন্ন',
    'waiting_checklist_primary_approver'      => 'প্রাথমিক অনুমোদনকারী (সুপারভাইজার)',
    'waiting_checklist_assigned'              => 'বরাদ্দ করা হয়েছে',
    'waiting_checklist_awaiting_setup'        => 'সেটআপের জন্য অপেক্ষমাণ',
    'waiting_checklist_storekeeper'           => 'স্টোরকিপার (ইনভেন্টরি অফিসার)',
    'waiting_checklist_assigned_staff'        => 'বরাদ্দকৃত স্টাফ (নূন্যতম: ১)',
    'waiting_who_can_activate'                => 'কে এটি সক্রিয় করতে পারেন?',
    'waiting_contact_admin'                   => 'আপনার কার্যালয় প্রশাসকের সাথে যোগাযোগ করুন:',
    'waiting_return_dashboard'                => 'মূল ড্যাশবোর্ডে ফিরে যান',

    // ==========================================
    // 19. Company Administrator Assignments
    // ==========================================
    
    'company_admin_title' => 'কোম্পানি প্রশাসকগণ',
    'company_admin_assign_title' => 'মন্ত্রণালয়ের প্রশাসক নিয়োগ করুন',
    'company_admin_select_user' => 'কর্মচারী ব্যবহারকারী নির্বাচন করুন',
    'company_admin_help_user' => 'মন্ত্রণালয়ের জন্য গ্লোবাল প্রশাসক হিসেবে দায়িত্ব পালনের জন্য কর্মচারীর অ্যাকাউন্ট নির্বাচন করুন।',
    'company_admin_select_company' => 'মন্ত্রণালয় / বিভাগ নির্বাচন করুন',
    'company_admin_help_company' => 'এই কর্মচারীকে এই নির্দিষ্ট মন্ত্রণালয়ের আওতাভুক্ত সকল কার্যালয়, কর্মী এবং সম্পদের ওপর ক্রস-রিজিওনাল প্রশাসনিক তদারকির ক্ষমতা দেওয়া হবে।',
    'company_admin_btn_save' => 'প্রশাসক নিয়োগ করুন',
    'company_admin_list_title' => 'সক্রিয় মন্ত্রণালয় প্রশাসকগণ',
    'company_admin_col_user' => 'প্রশাসকের বিবরণ',
    'company_admin_col_company' => 'নিযুক্ত মন্ত্রণালয়',
    'company_admin_col_home_office' => 'মূল কার্যালয় বেস',
    'company_admin_col_action' => 'পদক্ষেপ',
    'company_admin_btn_revoke' => 'বাতিল করুন',
    'company_admin_confirm_revoke' => 'এই ব্যবহারকারীর জন্য প্রাতিষ্ঠানিক তদারকি সুবিধা বাতিল করবেন?',
    'company_admin_empty_state' => 'এখনো ডেটাবেসে কোনো কোম্পানি প্রশাসক ম্যাপ করা হয়নি।',
    
    // Controller Messages
    'company_admin_unauthorized' => 'অননুমোদিত অ্যাক্সেস। প্রশাসনিক নিয়োগের জন্য সুপার-অ্যাডমিন প্রিভিলেজ প্রয়োজন।',
    'company_admin_assigned_success' => 'কোম্পানি প্রশাসক সফলভাবে নিযুক্ত করা হয়েছে।',
    'company_admin_revoked_success' => 'কোম্পানি প্রশাসকের সুবিধা বাতিল করা হয়েছে।',

    // ==========================================
    // Sidebar / Menu Integration
    // ==========================================
    'menu_provisioning_root'   => 'কার্যালয় প্রোভিশনিং',
    'menu_office_registry'     => 'কার্যালয় রেজিস্ট্রি',
    'menu_ict_jurisdictions'   => 'আইসিটি ভৌগোলিক সীমানা',
    'menu_company_admins'      => 'কোম্পানি অ্যাডমিন নিয়োগ',
    'menu_office_setup'        => 'আমার কার্যালয় সেটআপ',
    'menu_gov_directory'       => 'সরকারি ডিরেক্টরি',

];
