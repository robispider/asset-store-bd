<?php

return [
    // =========================================================================
    // Origin: admin/staff.blade.php
    // =========================================================================
    'staff_title_prefix' => 'কর্মচারী ব্যবস্থাপনা: ',
    'staff_active_label' => 'সক্রিয় কার্যালয় কর্মচারী',
    'staff_table_employee' => 'কর্মচারী',
    'staff_table_username' => 'ব্যবহারকারীর নাম',
    'staff_table_type' => 'সদস্যপদের ধরন',
    'staff_table_status' => 'স্থিতি (স্ট্যাটাস)',
    'staff_unknown_employee' => 'অজ্ঞাত কর্মচারী',
    'staff_no_active' => 'কোনো সক্রিয় কর্মচারী নেই।',
    'staff_pending_label' => 'অপেক্ষমাণ যোগদানের অনুরোধসমূহ',
    'staff_add_external_label' => 'বহিরাগত কর্মচারী যুক্ত করুন',
    'staff_add_external_hint' => 'কর্মচারীর ব্যবহারকারীর নাম এবং ৬-সংখ্যার ব্যক্তিগত যাচাইকরণ কোড লিখুন।',
    'staff_add_username_placeholder' => 'ব্যবহারকারীর নাম',
    'staff_add_code_placeholder' => '৬-সংখ্যার কোড',
    'staff_add_verify_button' => 'যাচাই ও যুক্ত করুন',
    'staff_mass_invite_label' => 'গণ আমন্ত্রণ কোড (Mass Invitation Code)',
    'staff_share_code_hint' => 'কর্মচারীদের কার্যালয়ে যোগদানের অনুমতি দিতে এই কোডটি তাদের সাথে শেয়ার করুন।',
    'staff_no_active_code' => 'কোনো সক্রিয় আমন্ত্রণ কোড নেই।',
    'staff_generate_code_button' => 'নতুন কোড তৈরি করুন',
    'staff_claim_label' => 'বদলিকৃত কর্মচারী দাবি (Claim) করুন',
    'staff_claim_hint' => 'যেসব কর্মচারী তাদের পূর্ববর্তী মূল কার্যালয় থেকে আনুষ্ঠানিকভাবে ছাড়পত্র (রিলিজ) অনুরোধ করেছেন তাদের অনুসন্ধান করুন।',
    'staff_claim_select_placeholder' => '-- ছাড়প্রাপ্ত কর্মচারী নির্বাচন করুন --',
    'staff_claim_button' => 'বদলির অনুমোদন ও দাবি করুন',
    'staff_home_base_label' => 'মূল কার্যালয় (Home Base)',
    'staff_secondary_label' => 'সেকেন্ডারি',

    // =========================================================================
    // Origin: admin/override_console.blade.php
    // =========================================================================
    'override_console_title' => 'জরুরি ওভাররাইড কনসোল',
    'override_execute_label' => 'জরুরি ওভাররাইড কার্যকর করুন',
    'override_target_label' => 'টার্গেট কর্মচারী',
    'override_target_placeholder' => '-- কর্মচারী অনুসন্ধান করুন --',
    'override_action_label' => 'ওভাররাইড পদক্ষেপ',
    'override_strip_roles_option' => 'বলপূর্বক সকল অপারেশনাল ভূমিকা বাতিল করুন',
    'override_force_release_option' => 'বলপূর্বক সদস্যপদ বাতিল (Release) করুন',
    'override_justification_label' => 'বাধ্যতামূলক যৌক্তিকতা',
    'override_justification_placeholder' => 'সাধারণ প্রোটোকল ওভাররাইড করার কারণ উল্লেখ করুন...',
    'override_confirm_warning' => 'সতর্কতা: এটি সকল ছাড়পত্র (Clearance) নিয়ম এড়িয়ে যায়। অগ্রসর হবেন?',
    'override_execute_button' => 'ওভাররাইড কার্যকর করুন',
    'override_audit_title' => 'কমপ্লায়েন্স অডিট লগ',
    'override_audit_date' => 'তারিখ',
    'override_audit_executor' => 'নির্বাহকারী',
    'override_audit_target' => 'টার্গেট ব্যবহারকারী',
    'override_audit_action' => 'পদক্ষেপ',
    'override_audit_justification' => 'যৌক্তিকতা',
    'override_audit_no_entries' => 'কোনো জরুরি ওভাররাইড কার্যকর করা হয়নি।',

    // =========================================================================
    // Origin: provisioning/hub.blade.php
    // =========================================================================
    'hub_local_staff_title' => 'স্থানীয় কর্মচারী ডিরেক্টরি',
    'hub_table_employee_name' => 'কর্মচারীর নাম',
    'hub_table_username' => 'ব্যবহারকারীর নাম',
    'hub_table_email' => 'ইমেইল ঠিকানা',
    'hub_table_job_title' => 'পদবী',
    'hub_no_employees' => 'এই স্থানে কোনো কর্মচারী ম্যাপ করা নেই।',
    'hub_claim_label' => 'আগত কর্মচারী দাবি (Claim) করুন',
    'hub_claim_hint' => 'যেসব কর্মচারী তাদের পূর্ববর্তী কার্যালয় থেকে ছাড়পত্র (রিলিজ) অনুরোধ করেছেন, তাদের এই স্থানে যুক্ত করতে অনুসন্ধান করুন।',
    'hub_claim_select_placeholder' => '-- ছাড়প্রাপ্ত কর্মচারী নির্বাচন করুন --',
    'hub_claim_button' => 'বদলির অনুমোদন ও দাবি করুন',

    // =========================================================================
    // Origin: provisioning/index.blade.php
    // =========================================================================
    'provisioning_registry_title' => 'সরকারি কার্যালয় রেজিস্ট্রি',
    'provisioning_metric_total' => 'মোট নিবন্ধিত কার্যালয়',
    'provisioning_metric_operational' => 'সক্রিয় কার্যালয়',
    'provisioning_metric_pending' => 'কনফিগারেশন অপেক্ষমাণ',
    'provisioning_metric_ministries' => 'সংযুক্ত মন্ত্রণালয়সমূহ',
    'provisioning_filter_search_label' => 'অনুসন্ধান:',
    'provisioning_filter_ministry_label' => 'মন্ত্রণালয়:',
    'provisioning_filter_district_label' => 'জেলা:',
    'provisioning_filter_status_label' => 'স্থিতি (স্ট্যাটাস):',
    'provisioning_filter_all_ministries' => '-- সকল মন্ত্রণালয় --',
    'provisioning_filter_all_districts' => '-- সকল জেলা --',
    'provisioning_filter_all_statuses' => '-- সকল স্থিতি --',
    'provisioning_filter_operational' => 'সক্রিয় (Operational)',
    'provisioning_filter_configured' => 'কনফিগার করা হয়েছে',
    'provisioning_filter_provisioned' => 'নিবন্ধিত (অপেক্ষমাণ)',
    'provisioning_filter_button' => 'ফিল্টার করুন',
    'provisioning_reset_button' => 'রিসেট',
    'provisioning_onboard_button' => 'বিদ্যমান অবস্থান অনবোর্ড করুন',
    'provisioning_create_button' => 'নতুন কার্যালয় প্রোভিশন করুন',
    'provisioning_grid_title' => 'নিবন্ধিত সরকারি কার্যালয়সমূহ',
    'provisioning_grid_office' => 'কার্যালয় ভবন',
    'provisioning_grid_territory' => 'প্রশাসনিক অঞ্চল',
    'provisioning_grid_ministry' => 'মালিকানাধীন মন্ত্রণালয়',
    'provisioning_grid_admin' => 'কার্যালয় প্রশাসক',
    'provisioning_grid_status' => 'প্রস্তুতির স্থিতি',
    'provisioning_grid_actions' => 'পদক্ষেপসমূহ',
    'provisioning_grid_parent' => 'অভিভাবক:',
    'provisioning_grid_root_office' => 'মূল কার্যালয় (রুট অফিস)',
    'provisioning_grid_type' => 'ধরন:',
    'provisioning_grid_standalone' => 'স্বতন্ত্র কার্যালয়',
    'provisioning_grid_unmapped' => 'অ-ম্যাপকৃত',
    'provisioning_status_operational' => 'সক্রিয়',
    'provisioning_status_configured' => 'কনফিগারড',
    'provisioning_status_provisioned' => 'নিবন্ধিত',
    'provisioning_status_needs' => 'প্রয়োজন:',
    'provisioning_status_primary' => 'প্রাথমিক',
    'provisioning_status_storekeeper' => 'স্টোরকিপার',
    'provisioning_view_hub_button' => 'হাব দেখুন',
    'provisioning_view_hub_title' => 'কার্যালয় হাব খুলুন',
    'provisioning_no_offices' => 'আপনার অনুসন্ধানের মানদণ্ডের সাথে মেলে এমন কোনো সরকারি কার্যালয় পাওয়া যায়নি।',

    // =========================================================================
    // Origin: provisioning/onboard.blade.php
    // =========================================================================
    'onboard_page_title' => 'বিদ্যমান Snipe-IT অবস্থান অনবোর্ড করুন',
    'onboard_map_title' => 'বিদ্যমান কার্যালয় অবস্থান ম্যাপ করুন',
    'onboard_section_identity' => '১. নিবন্ধিত ভবন নির্বাচন করুন',
    'onboard_location_label' => 'Snipe-IT অবস্থান নির্বাচন করুন',
    'onboard_location_placeholder' => '-- অ-নিবন্ধিত ভবন নির্বাচন করুন --',
    'onboard_location_hint' => 'এটি কেবল ভৌগোলিক ম্যাপিংবিহীন সক্রিয় Snipe-IT অবস্থানসমূহ প্রদর্শন করে।',
    'onboard_section_geography' => '২. ভৌগোলিক সীমানা ট্যাগ',
    'onboard_geo_label' => 'প্রশাসনিক অঞ্চল সীমানা',
    'onboard_geo_placeholder' => '-- বিভাগ, জিলা, উপজেলা বা ইউনিয়ন অনুসন্ধান এবং নির্বাচন করুন --',
    'onboard_section_hierarchy' => '৩. প্রাতিষ্ঠানিক শ্রেণিবিন্যাস ও সেটআপ',
    'onboard_ministry_label' => 'মন্ত্রণালয় / বিভাগ / দপ্তর মালিকানা (ঐচ্ছিক)',
    'onboard_ministry_placeholder' => '-- স্বতন্ত্র কার্যালয় রাখুন (কোনো মন্ত্রণালয় ছাড়াই) --',
    'onboard_admin_label' => 'কার্যালয় প্রশাসক নিযুক্ত করুন (ঐচ্ছিক)',
    'onboard_admin_placeholder' => '-- আপাতত অ-বরাদ্দকৃত রাখুন --',
    'onboard_return_button' => 'রেজিস্ট্রিতে ফিরে যান',
    'onboard_submit_button' => 'অনবোর্ড ও কার্যালয় ম্যাপ করুন',
    'onboard_guidelines_title' => 'অনবোর্ডিং নির্দেশিকা',
    'onboard_guidelines_text' => 'এই প্যানেলটি আপনাকে আপনার নতুন ভৌগোলিক মডেলের সাথে প্রাক-বিদ্যমান Snipe-IT অবস্থান রেকর্ডগুলোকে একত্রিত করার সুযোগ দেয়।',
    'onboard_guidelines_point1' => 'একটি বিদ্যমান ভবন নির্বাচন করে তা gov_geo_areas-এ তার শারীরিক অঞ্চলের সাথে ম্যাপ করা হয়।',
    'onboard_guidelines_point2' => 'এই প্রক্রিয়াটি Snipe-IT-এর মূল ডিরেক্টরিগুলোর ভেতরে ভবনটিকে ডুপ্লিকেট করে না; এটি কেবল স্থানিক তথ্য সমৃদ্ধ করে।',
    'onboard_search_geo_placeholder' => 'বিভাগ, জেলা, উপজেলা বা ইউনিয়ন অনুসন্ধান করতে টাইপ করুন...',

    // =========================================================================
    // Origin: user/index.blade.php
    // =========================================================================
    'user_page_title' => 'আমার কার্যালয় সদস্যপদ ও হস্তান্তর (Handovers)',
    'user_active_memberships_title' => 'সক্রিয় কার্যালয় সদস্যপদ',
    'user_table_office' => 'কার্যালয় ভবন',
    'user_table_status' => 'সদস্যপদ স্থিতি',
    'user_table_clearance' => 'ছাড়পত্র নিয়ম (Clearance Rules)',
    'user_table_action' => 'পদক্ষেপ',
    'user_status_active' => 'সক্রিয়',
    'user_status_home_base' => 'মূল কার্যালয়',
    'user_status_release_requested' => 'ছাড়পত্রের জন্য অনুরোধকৃত',
    'user_status_released' => 'ছাড়পত্র প্রদানকৃত (Released)',
    'user_clearance_na' => 'প্রযোজ্য নয়',
    'user_request_release_button' => 'ছাড়পত্রের অনুরোধ করুন',
    'user_request_release_confirm' => 'এই কার্যালয় থেকে আনুষ্ঠানিকভাবে ছাড়পত্রের অনুরোধ করতে চান?',
    'user_locked_button' => 'লক করা আছে',
    'user_no_memberships' => 'আপনি কোনো নিবন্ধিত কার্যালয়ের সদস্য নন।',
    'user_credential_title' => 'কার্যালয়ে যোগদানের শংসাপত্র (Credential)',
    'user_credential_hint' => 'আপনাকে কার্যালয়ে যুক্ত করার অনুমতি দিতে আপনার ব্যবহারকারীর নাম এবং এই অস্থায়ী যাচাইকরণ কোডটি স্থানীয় কার্যালয় প্রশাসককে প্রদান করুন।',
    'user_token_active_label' => 'আপনার সক্রিয় কোড',
    'user_token_no_active' => 'কোনো সক্রিয় কোড নেই',
    'user_token_regenerate' => 'পুনরায় কোড তৈরি করুন',
    'user_token_generate' => 'যাচাইকরণ কোড তৈরি করুন',
    'user_join_title' => 'কার্যালয়ে যোগদান করুন',
    'user_join_hint' => 'যদি আপনার কার্যালয় প্রশাসক আপনাকে কোনো কার্যালয় আমন্ত্রণ কোড প্রদান করেন, তবে অ্যাক্সেসের অনুরোধ করতে সেটি এখানে লিখুন।',
    'user_join_code_placeholder' => 'যেমন: OFF-ABCD-1234',
    'user_join_send_button' => 'যোগদানের অনুরোধ পাঠান',
    'user_handover_title' => 'পদক্ষেপ প্রয়োজন: আগত দায়িত্ব হস্তান্তর',
    'user_handover_delegate_text' => 'আপনাকে',
    'user_handover_role_to_you_for' => 'দায়িত্বটি অর্পণ করতে চান, কার্যালয়:',
    'user_handover_accept_button' => 'গ্রহণ করুন',
    'user_handover_accept_confirm' => 'গ্রহণ নিশ্চিত করবেন? এটি ডাটাবেসের সক্রিয় দায়িত্বগুলো তাৎক্ষণিকভাবে আপডেট করবে।',
    'user_handover_reject_button' => 'প্রত্যাখ্যান করুন',
    'user_responsibilities_title' => 'কার্যালয় দায়িত্ব হস্তান্তর',
    'user_responsibilities_hint' => 'যদি আপনার কোনো সক্রিয় দায়িত্ব থাকে, তবে আপনাকে রিলিজ করা যাবে না। আপনাকে অবশ্যই নিচে কোনো সহকর্মীর কাছে আপনার দায়িত্ব হস্তান্তর করতে হবে।',
    'user_no_active_roles' => 'বর্তমানে সক্রিয় কার্যালয়গুলোতে আপনার কোনো প্রশাসনিক দায়িত্ব নেই।',
    'user_modal_title' => 'দায়িত্ব হস্তান্তরের প্রস্তাব দিন (Role Handshake)',
    'user_modal_hint' => 'দায়িত্ব গ্রহণের জন্য একজন স্থানীয় সহকর্মীকে নির্বাচন করুন:',
    'user_modal_colleague_label' => 'সহকর্মী নির্বাচন করুন',
    'user_modal_colleague_placeholder' => '-- সহকর্মী নির্বাচন করুন --',
    'user_modal_cancel_button' => 'বাতিল',
    'user_modal_propose_button' => 'হস্তান্তরের প্রস্তাব দিন',

    // =========================================================================
    // Origin: hooks/menu-injection.blade.php
    // =========================================================================
    'menu_my_memberships' => 'আমার কার্যালয় সদস্যপদ',
    'menu_working_as' => 'প্রেক্ষাপট (Working As):',
    'menu_global_overview' => 'গ্লোবাল ওভারভিউ (সকল কার্যালয়)',
    'menu_choose_context' => 'কাজের প্রেক্ষাপট নির্বাচন করুন',

    // =========================================================================
    // Origin: MembershipController.php
    // =========================================================================
    'membership_token_generated' => 'নতুন যাচাইকরণ কোড সফলভাবে তৈরি করা হয়েছে। এটি ২৪ ঘণ্টার মধ্যে মেয়াদোত্তীর্ণ হবে।',
    'membership_only_active_release' => 'শুধুমাত্র সক্রিয় সদস্যপদ বাতিল করার জন্য অনুরোধ করা যেতে পারে।',
    'membership_clearance_failed' => 'ছাড়পত্র ব্যর্থ হয়েছে। প্রথমে অসম্পূর্ণ বিষয়গুলো সমাধান করুন।',
    'membership_context_restored' => 'কাজের প্রেক্ষাপট গ্লোবাল ওভারভিউতে পুনরুদ্ধার করা হয়েছে।',
    'membership_context_switched' => 'প্রেক্ষাপট পরিবর্তন করা হয়েছে।',
    'membership_context_switched_to' => 'কাজের প্রেক্ষাপট :office -এ পরিবর্তন করা হয়েছে।',
    'membership_invalid_code' => 'কার্যালয় কোডটি অবৈধ অথবা এর মেয়াদ শেষ হয়ে গেছে।',
    'membership_already_member' => 'আপনি ইতিমধ্যে একজন সক্রিয় সদস্য।',
    'membership_request_pending' => 'আপনার অনুরোধটি ইতিমধ্যে অনুমোদনের অপেক্ষায় রয়েছে।',
    'membership_request_sent' => 'সদস্যপদের অনুরোধ পাঠানো হয়েছে! অনুমোদনের জন্য অপেক্ষা করুন।',

    // =========================================================================
    // Origin: MembershipAdminController.php
    // =========================================================================
    'admin_unauthorized_override' => 'অননুমোদিত। জরুরি ওভাররাইড করার জন্য সিস্টেম সুপার-অ্যাডমিনিস্ট্রেটর অ্যাক্সেস প্রয়োজন।',
    'admin_access_denied' => 'অ্যাক্সেস অস্বীকার করা হয়েছে: আপনি এই কার্যালয়ের প্রশাসক নন।',
    'admin_user_not_found' => 'ব্যবহারকারীকে পাওয়া যায়নি।',
    'admin_invalid_code' => 'কোডটি অবৈধ অথবা এর মেয়াদ শেষ।',
    'admin_permanently_transferring' => 'এই কর্মচারী স্থায়ীভাবে বদলি হচ্ছেন। অনুগ্রহ করে নিচের "বদলিকৃত কর্মচারী দাবি করুন" উইজেটটি ব্যবহার করুন।',
    'admin_already_member' => 'কর্মচারী ইতিমধ্যে এই কার্যালয়ের একজন সক্রিয় সদস্য।',
    'admin_secondary_access_granted' => 'কর্মচারীকে এই কার্যালয়ে সেকেন্ডারি অ্যাক্সেস দেওয়া হয়েছে।',
    'admin_employee_claimed' => 'কর্মচারীকে সফলভাবে দাবি করা হয়েছে এবং এটি তাদের নতুন মূল কার্যালয় হিসেবে সেট করা হয়েছে।',
    'admin_invite_code_generated' => 'নতুন কার্যালয় আমন্ত্রণ কোড তৈরি করা হয়েছে।',
    'admin_membership_approved' => 'কর্মচারীর সদস্যপদের অনুরোধ অনুমোদিত হয়েছে।',
    'admin_membership_rejected' => 'কর্মচারীর সদস্যপদের অনুরোধ প্রত্যাখ্যাত হয়েছে।',
    'admin_override_executed' => 'জরুরি কমপ্লায়েন্স ওভাররাইড রেকর্ড এবং কার্যকর করা হয়েছে।',

    // =========================================================================
    // Origin: RoleAssignmentController.php
    // =========================================================================
    'assignment_proposed' => 'দায়িত্ব হস্তান্তরের প্রস্তাব দেওয়া হয়েছে। সহকর্মীর অনুমোদনের অপেক্ষায় রয়েছে।',
    'assignment_accepted' => 'দায়িত্ব সফলভাবে গ্রহণ করা হয়েছে। আপনার সহকর্মীর ছাড়পত্র আপডেট করা হয়েছে।',
    'assignment_rejected' => 'দায়িত্ব হস্তান্তরের প্রস্তাব প্রত্যাখ্যাত হয়েছে।',
    'assignment_cancelled' => 'অপেক্ষমাণ দায়িত্ব হস্তান্তর বাতিল করা হয়েছে।',

    // =========================================================================
    // Origin: RoleHandshakeController.php
    // =========================================================================
    'handshake_proposed' => 'হস্তান্তরের প্রস্তাব দেওয়া হয়েছে। সহকর্মীর অনুমোদনের অপেক্ষায় রয়েছে।',
    'handshake_accepted' => 'হস্তান্তর গ্রহণ করা হয়েছে। আপনার সহকর্মীর ছাড়পত্র আপডেট করা হয়েছে।',
    'handshake_rejected' => 'হস্তান্তরের প্রস্তাব প্রত্যাখ্যাত হয়েছে।',
    'handshake_cancelled' => 'হস্তান্তরের প্রস্তাব বাতিল করা হয়েছে।',

    // =========================================================================
    // Origin: NoActiveAssetsRule.php
    // =========================================================================
    'rule_physical_inventory_name' => 'শারীরিক ইনভেন্টরি চেক',
    'rule_assets_held' => 'আপনার কাছে বর্তমানে :countটি সক্রিয় সম্পদ রয়েছে। আপনাকে অবশ্যই সেগুলো স্টোরকিপারের কাছে জমা দিতে (Check-in) হবে।',
    'rule_assets_returned' => 'সকল শারীরিক সম্পদ জমা দেওয়া হয়েছে।',

    // =========================================================================
    // Origin: NoActiveRolesRule.php
    // =========================================================================
    'rule_office_responsibility_name' => 'কার্যালয় দায়িত্ব চেক',
    'rule_roles_held' => 'আপনি বর্তমানে এখানে :countটি প্রশাসনিক/স্টোরকিপার দায়িত্ব পালন করছেন। আপনাকে প্রথমে কোনো সহকর্মীর কাছে এই দায়িত্ব হস্তান্তর করতে হবে।',
    'rule_no_blocking_roles' => 'কোনো বাধা প্রদানকারী প্রশাসনিক দায়িত্ব নেই।',

    // =========================================================================
    // Origin: NoPendingRequestsRule.php
    // =========================================================================
    'rule_pending_requests_name' => 'পেন্ডিং পরিষেবা অনুরোধসমূহ',
    'rule_requests_active' => 'আপনার :countটি সক্রিয় পরিষেবা অনুরোধ চলমান রয়েছে। অনুগ্রহ করে সেগুলো বাতিল করুন বা সম্পন্ন হওয়া পর্যন্ত অপেক্ষা করুন।',
    'rule_requests_completed' => 'সকল পরিষেবা অনুরোধ সম্পন্ন হয়েছে।',

    // =========================================================================
    // Origin: OfficeMembershipService.php
    // =========================================================================
    'service_home_office_reset' => 'ব্যবহারকারীর জন্য মূল কার্যালয় বেস রিসেট করা হয়েছে।',
    'service_membership_granted' => 'কার্যালয়ের সদস্যপদ মঞ্জুর করা হয়েছে।',
    'service_membership_revoked' => 'কার্যালয়ের অ্যাক্সেস বাতিল করা হয়েছে।',

    // =========================================================================
    // Origin: RoleAssignmentService.php
    // =========================================================================
    'assignment_self_delegate_error' => 'আপনি নিজের কাছে কোনো দায়িত্ব অর্পণ করতে পারবেন না।',
    'assignment_pending_exists' => 'এই দায়িত্বটির জন্য আপনার ইতিমধ্যে একটি বদলির অনুরোধ চলমান রয়েছে।',
    'assignment_audit_message' => 'দায়িত্ব হস্তান্তর (Role Handshake): ইউজার আইডি :userId থেকে :role দায়িত্ব গ্রহণ করা হয়েছে।',

    // =========================================================================
    // Origin: RoleHandshakeService.php
    // =========================================================================
    'handshake_self_delegate_error' => 'আপনি নিজের কাছে কোনো দায়িত্ব অর্পণ করতে পারবেন না।',
    'handshake_no_role_error' => 'আপনার কাছে যে দায়িত্ব নেই তা আপনি হস্তান্তর করতে পারবেন না।',
    'handshake_pending_exists' => 'এই দায়িত্বটির জন্য আপনার ইতিমধ্যে একটি হস্তান্তরের প্রস্তাব চলমান রয়েছে।',
    'handshake_audit_message' => 'দায়িত্ব ম্যাট্রিক্স আপডেট করা হয়েছে। \':role\' দায়িত্বটি ইউজার আইডি :fromId থেকে ইউজার আইডি :toId এর কাছে হস্তান্তর করা হয়েছে।',

    // =========================================================================
    // Origin: LegacyUserSynchronizationService.php
    // =========================================================================
    'sync_auto_onboarding_note' => 'সিস্টেম অটো-অনবোর্ডিং',
    'sync_transfer_blocked_warning' => 'নেটিভ ব্যবহারকারী বদলি অবরুদ্ধ: ব্যবহারকারী :username নেটিভ অবস্থান পরিবর্তনের চেষ্টা করেছেন, কিন্তু অবস্থান :locationId-এ তার ছাড়পত্রবিহীন সম্পদ/দায়িত্ব রয়েছে।',
    'sync_transfer_reverted_flash' => 'সতর্কতা: ব্যবহারকারীর অবস্থান আপডেট বাতিল করা হয়েছে। বদলি হওয়ার আগে ব্যবহারকারীকে অবশ্যই সম্পদ জমা দিতে হবে এবং দায়িত্ব হস্তান্তর করতে হবে।',
    'sync_native_transfer_note' => 'নেটিভ অ্যাডমিন বদলি',

    // =========================================================================
    // Origin: MembershipActivityLogObserver.php
    // =========================================================================
    'log_membership_granted' => 'কার্যালয়ের সদস্যপদ মঞ্জুর করা হয়েছে।',
    'log_status_changed' => 'সদস্যপদ স্থিতি \':old\' থেকে \':new\'-এ পরিবর্তিত হয়েছে।',
    'log_membership_revoked' => 'কার্যালয়ের সদস্যপদ বাতিল করা হয়েছে।',

    // =========================================================================
    // Origin: SetWorkingContext.php (middleware flash messages)
    // =========================================================================
    'context_home_resolved' => 'ডিফল্ট কাজের প্রেক্ষাপট মূল কার্যালয়ে (Home Office) সেট করা হয়েছে।',
];