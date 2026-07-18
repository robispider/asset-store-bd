<?php

return [
    // ==========================================
    // dashboard/index.blade.php
    // ==========================================
    'dashboard_stat_total_nodes' => 'মোট নোড',
    'dashboard_stat_catalog_nodes' => 'ক্যাটালগ নোড',
    'dashboard_stat_mapped_count' => 'ম্যাপকৃত',
    'dashboard_stat_snipe_mapped' => 'Snipe-IT ম্যাপকৃত',
    'dashboard_stat_unmapped_count' => 'অ-ম্যাপকৃত',
    'dashboard_stat_not_mapped' => 'এখনো ম্যাপ করা হয়নি',
    'dashboard_stat_import_count' => 'আমদানি (Imports)',
    'dashboard_stat_total_imports' => 'মোট আমদানি',
    'dashboard_quick_actions' => 'দ্রুত পদক্ষেপ',
    'dashboard_btn_search_catalog' => 'ক্যাটালগ অনুসন্ধান করুন',
    'dashboard_btn_import_data' => 'ডেটা আমদানি করুন',
    'dashboard_btn_settings' => 'সেটিংস',

    // ==========================================
    // governance/index.blade.php
    // ==========================================
    'governance_title' => 'ক্যাটাগরি গভার্নেন্স সেন্টার',
    'governance_registry_title' => 'যৌথ অপারেশনাল ক্যাটাগরি রেজিস্ট্রি',
    'governance_registry_desc' => 'গ্লোবাল ক্যাটালগে ম্যাপ করা সকল ক্যাটাগরির জন্য প্রশাসনিক নিয়ন্ত্রণ কেন্দ্র।',
    'governance_col_operational_category' => 'অপারেশনাল ক্যাটাগরি',
    'governance_col_unspsc_code' => 'UNSPSC কোড',
    'governance_col_governance_type' => 'গভার্নেন্স ধরন',
    'governance_col_origin_owner' => 'মূল মালিক (Origin Owner)',
    'governance_col_orgs_using' => 'ব্যবহারকারী সংস্থাসমূহ',
    'governance_col_mapped_models' => 'ম্যাপকৃত মডেলসমূহ',
    'governance_col_action' => 'পদক্ষেপ',
    'governance_gov_standard' => 'সরকারি মান (Gov Standard)',
    'governance_org_managed' => 'সংস্থা পরিচালিত (Org Managed)',
    'governance_unmanaged_core' => 'অপরিচালিত (মূল সিস্টেম)',
    'governance_btn_inspect' => 'পরিদর্শন করুন',
    'governance_empty_state' => 'কোনো অপারেশনাল ক্যাটাগরি পাওয়া যায়নি।',

    // ==========================================
    // governance/show.blade.php
    // ==========================================
    'governance_show_title_prefix' => 'ক্যাটাগরি পরিদর্শন: ',
    'governance_show_profile_title' => 'ক্যাটাগরি প্রোফাইল',
    'governance_show_op_name' => 'অপারেশনাল নাম',
    'governance_show_category_type' => 'ক্যাটাগরির ধরন',
    'governance_show_core_id' => 'মূল Snipe-IT আইডি',
    'governance_show_mapping_title' => 'গ্লোবাল মাস্টার ডেটা ম্যাপিং',
    'governance_show_unspsc_code' => 'UNSPSC কোড',
    'governance_show_classification_title' => 'শ্রেণিবিভাগের শিরোনাম',
    'governance_show_hierarchy' => 'কাঠামোগত শ্রেণিবিন্যাস',
    'governance_show_orphan_alert' => 'এই ক্যাটাগরিটি একটি অরফান এবং এটি গ্লোবাল UNSPSC ক্যাটালগের সাথে সংযুক্ত নয়।',
    'governance_show_governance_title' => 'গভার্নেন্সের বিস্তারিত',
    'governance_show_gov_scope' => 'গভার্নেন্স স্কোপ',
    'governance_show_shared_gov_standard' => 'যৌথ সরকারি মান',
    'governance_show_org_managed' => 'সংস্থা পরিচালিত',
    'governance_show_unmanaged_core_asset' => 'অপরিচালিত মূল সম্পদ',
    'governance_show_origin_owner' => 'মূল মালিক',
    'governance_show_created_by' => 'তৈরি করেছেন',
    'governance_show_creation_timestamp' => 'তৈরির সময়কাল',
    'governance_show_analytics_title' => 'লাইভ অপারেশনাল অ্যানালিটিক্স',
    'governance_show_orgs_adopted' => 'সংস্থা কর্তৃক গৃহীত (Adopted)',
    'governance_show_mapped_models' => 'ম্যাপকৃত সম্পদ মডেল',
    'governance_show_active_assets' => 'সক্রিয় হার্ডওয়্যার সম্পদ',
    'governance_show_consumables' => 'ব্যবহার্য সামগ্রীসমূহ (Consumables)',
    'governance_show_accessories' => 'আনুষঙ্গিক সরঞ্জামসমূহ',
    'governance_show_components' => 'উপাদানসমূহ (Components)',
    'governance_show_licenses' => 'লাইসেন্সসমূহ',

    // ==========================================
    // livewire/catalog-search-dropdown.blade.php
    // ==========================================
    'livewire_label_classification_code' => 'শ্রেণিবিভাগ কোড',
    'livewire_placeholder_search_catalog' => 'ক্যাটালগ অনুসন্ধান করুন...',
    'livewire_badge_mapped' => 'ম্যাপকৃত',
    'livewire_badge_not_mapped' => 'ম্যাপ করা হয়নি',
    'livewire_alert_category_selected' => 'ক্যাটাগরি স্বয়ংক্রিয়ভাবে নির্বাচিত হয়েছে!',
    'livewire_js_category_auto_selected' => 'ক্যাটাগরি স্বয়ংক্রিয়ভাবে নির্বাচিত হয়েছে।',
    'livewire_js_prompt_mapping_title' => 'ক্যাটাগরিতে ম্যাপ করবেন?',
    'livewire_js_prompt_mapping_message' => 'আপনি কি এখন এই শ্রেণিবিভাগটি একটি Snipe-IT ক্যাটাগরিতে ম্যাপ করতে চান?',

    // ==========================================
    // manager/external.blade.php
    // ==========================================
    'external_title' => 'বাহ্যিক ম্যাপিং',
    'external_header_title' => 'বাহ্যিক ক্যাটালগ ম্যাপিং',
    'external_desc' => 'বাহ্যিক শ্রেণিবিন্যাস স্কিম (যেমন CGA বা HS কোড) এবং গ্লোবাল ক্যাটালগের মধ্যে ম্যাপিং পরিচালনা করুন।',
    'external_btn_disabled' => 'নতুন বাহ্যিক ম্যাপিং (পর্যায় ৩)',
    'external_col_source_scheme' => 'উৎস স্কিম',
    'external_col_target_scheme' => 'টার্গেট স্কিম',
    'external_col_mapping_rule' => 'ম্যাপিং রুল',
    'external_col_status' => 'স্থিতি (Status)',
    'external_col_actions' => 'পদক্ষেপসমূহ',
    'external_empty_state' => 'কোনো বাহ্যিক ম্যাপিং কনফিগার করা নেই। পর্যায় ৩-এ এক্সটার্নাল ক্রসওয়াক ইন্টিগ্রেশন নির্ধারিত আছে।',

    // ==========================================
    // manager/history.blade.php
    // ==========================================
    'history_title' => 'আমদানির ইতিহাস (Import History)',
    'history_header_title' => 'ক্যাটালগ আমদানির ইতিহাস',
    'history_col_date' => 'তারিখ',
    'history_col_scheme' => 'স্কিম',
    'history_col_nodes_imported' => 'আমদানিকৃত নোডসমূহ',
    'history_col_status' => 'স্থিতি',
    'history_col_details' => 'বিস্তারিত',
    'history_label_success' => 'সফল',
    'history_label_failed' => 'ব্যর্থ',
    'history_btn_view_errors' => 'ত্রুটিসমূহ দেখুন',
    'history_empty_state' => 'কোনো আমদানির ইতিহাস পাওয়া যায়নি।',

    // ==========================================
    // manager/import.blade.php
    // ==========================================
    'import_title' => 'শ্রেণিবিভাগ ক্যাটালগ আপডেট করুন',
    'import_error_header' => 'সিস্টেম ব্যতিক্রম (System Exception)',
    'import_step_select' => '১. নির্বাচন',
    'import_step_review' => '২. পর্যালোচনা',
    'import_step_import' => '৩. আমদানি',
    'import_header_title' => 'শ্রেণিবিভাগ ক্যাটালগ আপডেট করুন',
    'import_lead_desc' => 'সর্বশেষ অনুমোদিত প্রাক-সংকলিত শ্রেণিবিভাগ ক্যাটালগ আমদানি করুন। আপনার স্থানীয় বাংলা অনুবাদ, নোট এবং ক্যাটাগরি ম্যাপিং সম্পূর্ণ অক্ষত থাকবে।',
    'import_option_a_title' => 'বিকল্প ক: বান্ডেল্ড কোর ডেটাসেট চালান',
    'import_option_a_recommended' => 'সুপারিশকৃত',
    'import_option_a_desc' => 'এই মডিউলে অন্তর্ভুক্ত প্রাক-প্যাকেজড, যাচাইকৃত <strong>UNv260801</strong> অফিশিয়াল ডেটাসেট ব্যবহার করে সিস্টেম স্বয়ংক্রিয়ভাবে প্রস্তুত করে।',
    'import_btn_direct_import_skip_review' => 'সরাসরি আমদানি (পর্যালোচনা এড়িয়ে যান)',
    'import_btn_analyze_review' => 'বিশ্লেষণ ও পর্যালোচনা',
    'import_option_b_title' => 'বিকল্প খ: কাস্টম ডেটাসেট আপলোড করুন',
    'import_option_b_desc' => 'যদি নতুন কোনো স্ট্যান্ডার্ড প্রকাশিত হয় (যেমন, UNv27) অথবা যদি কোনো বিকল্প সরকারি স্কিম (যেমন CGA) থেকে স্থানান্তর করতে চান, তবে এই বিকল্পটি ব্যবহার করুন।',
    'import_label_target_scheme' => 'টার্গেট স্কিম',
    'import_placeholder_target_scheme' => 'যেমন: UNSPSC বা CGA',
    'import_label_version_tag' => 'ভার্সন ট্যাগ',
    'import_placeholder_version_tag' => 'যেমন: UNv270101',
    'import_label_official_dataset' => 'অফিসিয়াল ডেটাসেট (বাধ্যতামূলক) <small class="text-muted">সংজ্ঞা এবং নোড অন্তর্ভুক্ত।</small>',
    'import_label_hierarchy_validation' => 'হায়ারার্কি ভ্যালিডেশন ডেটাসেট (ঐচ্ছিক) <small class="text-muted">কাঠামো যাচাই করার জন্য।</small>',
    'import_validation_source_verification' => 'উৎস যাচাইকরণ',
    'import_validation_datasets_found' => 'অফিসিয়াল প্রাক-সংকলিত ডেটাসেট ডিস্কে পাওয়া গেছে',
    'import_validation_scheme_name' => 'স্কিমের নাম:',
    'import_validation_release_tag' => 'রিলিজ ট্যাগ:',
    'import_validation_source' => 'উৎস:',
    'import_validation_impact_title' => 'ক্যাটালগ আপডেটের প্রভাব',
    'import_validation_new_nodes' => 'নতুন তৈরিযোগ্য নোডসমূহ',
    'import_validation_existing_update' => 'আপডেটযোগ্য বিদ্যমান নোডসমূহ',
    'import_validation_missing_nodes' => 'নিখোঁজ নোডসমূহ (উপেক্ষিত)',
    'import_protection_title' => 'ডেটা সুরক্ষা গ্যারান্টি',
    'import_protection_desc' => 'এই আমদানি প্রক্রিয়াটি কঠোরভাবে সংযোজনমূলক এবং সুরক্ষামূলক। ডাটাবেসের নিম্নলিখিত অংশগুলো ওভাররাইট হওয়ার হাত থেকে <strong>লক করা এবং সুরক্ষিত</strong>:',
    'import_protection_bangla' => 'বাংলা অনুবাদসমূহ',
    'import_protection_safe' => 'সুরক্ষিত',
    'import_protection_local_notes' => 'স্থানীয় স্টোরকিপারের নোটসমূহ',
    'import_protection_mappings' => 'Snipe-IT ক্যাটাগরি ম্যাপিংসমূহ',
    'import_btn_update_catalog' => 'ক্যাটালগ আপডেট করুন',
    'import_btn_cancel' => 'বাতিল',
    'import_success_title' => 'ক্যাটালগ সফলভাবে আপডেট হয়েছে',
    'import_success_catalog_scheme' => 'ক্যাটালগ স্কিম:',
    'import_success_release_tag' => 'রিলিজ ট্যাগ:',
    'import_success_imported_nodes' => 'আমদানিকৃত নোডসমূহ:',
    'import_success_enriched_defs' => 'সমৃদ্ধকৃত সংজ্ঞাসমূহ:',
    'import_success_mapped_synonyms' => 'ম্যাপকৃত সমার্থক শব্দ (Synonyms):',
    'import_success_execution_time' => 'প্রক্রিয়াকরণের সময়:',
    'import_btn_view_catalog' => 'ক্যাটালগ দেখুন',
    'import_btn_view_history' => 'আমদানির ইতিহাস দেখুন',

    // ==========================================
    // manager/mapping.blade.php
    // ==========================================
    'mapping_title' => 'ক্যাটাগরি ম্যাপিং',
    'mapping_header_title' => 'ক্যাটালগ → Snipe-IT ক্যাটাগরি ম্যাপিং',
    'mapping_col_catalog_code' => 'ক্যাটালগ কোড',
    'mapping_col_catalog_title' => 'ক্যাটালগ শিরোনাম',
    'mapping_col_scheme' => 'স্কিম',
    'mapping_col_snipe_category' => 'Snipe-IT ক্যাটাগরি',
    'mapping_col_actions' => 'পদক্ষেপসমূহ',
    'mapping_btn_edit' => 'সম্পাদনা করুন',
    'mapping_empty_state' => 'কোনো ম্যাপিং পাওয়া যায়নি। প্রথমে একটি ক্যাটালগ আমদানি করুন।',

    // ==========================================
    // my-catalog/index.blade.php
    // ==========================================
    'mycatalog_title' => 'আমার অর্গানাইজেশন ক্যাটালগ',
    'mycatalog_header_title' => 'গৃহীত অপারেশনাল ক্যাটাগরিসমূহ',
    'mycatalog_header_desc' => 'এই ক্যাটাগরিগুলো বর্তমানে সক্রিয় এবং আপনার প্রতিষ্ঠানে ব্যবহারের জন্য উপলব্ধ।',
    'mycatalog_col_operational_category' => 'অপারেশনাল ক্যাটাগরি',
    'mycatalog_col_category_type' => 'ক্যাটাগরির ধরন',
    'mycatalog_col_governance_source' => 'গভার্নেন্স উৎস',
    'mycatalog_col_adoption_date' => 'গ্রহণের তারিখ',
    'mycatalog_col_status' => 'স্থিতি',
    'mycatalog_col_action' => 'পদক্ষেপ',
    'mycatalog_gov_standard' => 'সরকারি মান',
    'mycatalog_org_standard' => 'প্রাতিষ্ঠানিক মান',
    'mycatalog_native_creation' => 'নেটিভ সৃষ্টি',
    'mycatalog_label_active' => 'সক্রিয়',
    'mycatalog_label_archived' => 'আর্কাইভকৃত',
    'mycatalog_btn_manage' => 'পরিচালনা করুন',
    'mycatalog_empty_state' => 'আপনার প্রতিষ্ঠান এখনো কোনো ক্যাটাগরি গ্রহণ করেনি।',

    // ==========================================
    // my-catalog/show.blade.php
    // ==========================================
    'mycatalog_show_title_prefix' => 'ক্যাটাগরি পরিচালনা করুন: ',
    'mycatalog_show_local_usage_title' => 'স্থানীয় অপারেশনাল ব্যবহার',
    'mycatalog_show_active_office_label' => 'সক্রিয় কার্যালয়',
    'mycatalog_show_active_assets' => 'সক্রিয় হার্ডওয়্যার সম্পদ',
    'mycatalog_show_consumables' => 'ব্যবহার্য সামগ্রীসমূহ',
    'mycatalog_show_accessories' => 'আনুষঙ্গিক সরঞ্জামসমূহ',
    'mycatalog_show_components' => 'উপাদানসমূহ',
    'mycatalog_show_licenses' => 'লাইসেন্সসমূহ',
    'mycatalog_show_lifecycle_title' => 'ক্যাটাগরি লাইফসাইকেল কন্ট্রোল',
    'mycatalog_show_archived_state_title' => 'ক্যাটাগরিটি আর্কাইভ করা হয়েছে',
    'mycatalog_show_archived_desc' => 'এই ক্যাটাগরিটি বর্তমানে সব ধরনের নতুন তৈরি করার ফর্ম এবং ড্রপডাউন থেকে লুকানো রয়েছে। আপনি যেকোনো সময় এটি পুনরুদ্ধার করতে পারেন।',
    'mycatalog_btn_restore_reactivate' => 'পুনরুদ্ধার / পুনরায় সক্রিয় করুন',
    'mycatalog_show_safe_to_stop_title' => 'ব্যবহার বন্ধ করা নিরাপদ',
    'mycatalog_show_safe_to_stop_desc' => 'আপনার সক্রিয় কার্যালয়ে কোনো আইটেম নিবন্ধিত নেই। আপনি নিরাপদে এই ক্যাটাগরির ব্যবহার সম্পূর্ণ বন্ধ (ডিলিট) করতে পারেন, অথবা কেবল আর্কাইভ করতে পারেন।',
    'mycatalog_btn_stop_using_completely' => 'সম্পূর্ণরূপে ব্যবহার বন্ধ করুন',
    'mycatalog_btn_soft_archive' => 'সফট-আর্কাইভ করুন',
    'mycatalog_show_in_use_title' => 'ক্যাটাগরি ব্যবহৃত হচ্ছে',
    'mycatalog_show_in_use_desc_prefix' => 'আপনি এই ক্যাটাগরিটি মুছে ফেলতে পারবেন না কারণ ',
    'mycatalog_show_in_use_desc_suffix' => 'টি সক্রিয় আইটেম বর্তমানে এটি ব্যবহার করছে। তবে, আপনি নতুন ড্রপডাউন থেকে এটি লুকাতে **সফট-আর্কাইভ** করতে পারেন।',
    'mycatalog_js_confirm_abandon' => 'আপনি কি নিশ্চিত যে আপনি এই ক্যাটাগরির ব্যবহার সম্পূর্ণ বন্ধ করতে চান? এই পদক্ষেপটি স্থায়ী।',
    'mycatalog_js_governance_blocked' => 'গভার্নেন্স ব্লকড: ',
    'mycatalog_js_cannot_abandon' => 'ক্যাটাগরির ব্যবহার বন্ধ করা সম্ভব নয়।',
    'mycatalog_js_error_prefix' => 'ত্রুটি: ',
    'mycatalog_js_failed_archive' => 'ক্যাটাগরি আর্কাইভ করতে ব্যর্থ হয়েছে।',
    'mycatalog_js_failed_restore' => 'ক্যাটাগরি পুনরুদ্ধার করতে ব্যর্থ হয়েছে।',

    // ==========================================
    // my-catalog/unassigned.blade.php
    // ==========================================
    'unassigned_title' => 'স্থানীয় কার্যালয় ক্যাটালগ',
    'unassigned_pending_title' => 'কার্যালয়ের মন্ত্রণালয় বরাদ্দকরণ অপেক্ষমান',
    'unassigned_pending_desc' => 'এই শারীরিক কার্যালয়ের অবস্থানটি বর্তমানে সিস্টেমে কোনো সরকারি মন্ত্রণালয় বা মূল কোম্পানির সাথে সংযুক্ত নয়। এ কারণে, আপনার স্থানীয় কার্যালয়ের নিজস্ব কোনো প্রাইভেট কোম্পানি-ভিত্তিক ক্যাটালগ নেই।',
    'unassigned_pending_note' => 'তবে, আপনি আপনার দৈনন্দিন কার্যক্রমে ব্যবহারের জন্য নিচে তালিকাভুক্ত যৌথ সরকারি মানের ক্যাটাগরিগুলো দেখতে এবং ব্যবহার করতে পারেন।',
    'unassigned_header_title' => 'গ্লোবালি উপলব্ধ স্ট্যান্ডার্ড ক্যাটাগরিসমূহ',
    'unassigned_label_shared_ref_data' => 'যৌথ রেফারেন্স ডেটা',
    'unassigned_col_category_name' => 'ক্যাটাগরির নাম',
    'unassigned_col_category_type' => 'ক্যাটাগরির ধরন',
    'unassigned_col_unspsc_code' => 'UNSPSC কোড',
    'unassigned_col_governance_status' => 'গভার্নেন্স স্থিতি',
    'unassigned_shared_gov_standard' => 'যৌথ সরকারি মান',
    'unassigned_empty_state' => 'মাস্টার ক্যাটালগে এখনো কোনো গ্লোবাল স্ট্যান্ডার্ড ক্যাটাগরি প্রোভিশন করা হয়নি।',

    // ==========================================
    // search/index.blade.php
    // ==========================================
    'search_title' => 'গ্লোবাল ক্যাটালগ এক্সপ্লোরার',
    'search_header_title' => 'গ্লোবাল ক্যাটালগ এক্সপ্লোরার',
    'search_header_desc' => 'অফিসিয়াল UNSPSC শ্রেণিবিভাগ অনুসন্ধান করুন, যাচাই করুন এবং Snipe-IT ক্যাটাগরিতে ম্যাপ করুন।',
    'search_col_results' => 'অনুসন্ধানের ফলাফল',
    'search_placeholder_code_or_keyword' => 'কোড বা কিওয়ার্ড টাইপ করুন (যেমন, Laptop, 10101501)...',
    'search_filter_unmapped_only' => 'শুধুমাত্র অ-ম্যাপকৃত',
    'search_filter_commodities_only' => 'শুধুমাত্র কমোডিটিস',
    'search_recent_label' => 'সাম্প্রতিক:',
    'search_begin_typing_title' => 'অনুসন্ধান করতে টাইপ করা শুরু করুন',
    'search_begin_typing_desc' => 'যাচাই করার জন্য একটি শ্রেণিবিভাগের শিরোনাম বা অফিসিয়াল UNSPSC কোড লিখুন।',
    'search_no_item_selected' => 'কোনো আইটেম নির্বাচিত হয়নি',
    'search_no_item_desc' => 'বাম দিকের অনুসন্ধান ফলাফল থেকে একটি শ্রেণিবিভাগ নির্বাচন করুন তার সংজ্ঞা, সমার্থক শব্দ এবং ম্যাপিং স্থিতি দেখতে।',
    'search_searching_catalog_records' => 'অফিসিয়াল ক্যাটালগ রেকর্ড অনুসন্ধান করা হচ্ছে...',
    'search_no_matches_found' => 'কোনো মিল পাওয়া যায়নি',
    'search_verify_spelling_filters' => 'বানান বা ফিল্টার যাচাই করুন এবং আবার চেষ্টা করুন।',
    'search_retrieving_metadata' => 'মেটাডেটা সংগ্রহ করা হচ্ছে...',

    // ==========================================
    // search/mapping.blade.php
    // ==========================================
    'mapping_detail_level_classification' => 'লেভেল :level শ্রেণিবিভাগ',
    'mapping_detail_official_code' => 'অফিসিয়াল রেফারেন্স কোড: ',
    'mapping_detail_official_definition' => 'অফিসিয়াল সংজ্ঞা',
    'mapping_detail_recognized_synonyms' => 'স্বীকৃত সমার্থক শব্দসমূহ',
    'mapping_detail_contextual_hierarchy' => 'প্রাসঙ্গিক শ্রেণিবিন্যাস (Hierarchy)',
    'mapping_drawer_title' => 'Snipe-IT ক্যাটাগরির সাথে লিঙ্ক করুন',
    'mapping_drawer_search_label' => 'নেটিভ Snipe-IT ক্যাটাগরি অনুসন্ধান করুন',
    'mapping_drawer_select_placeholder' => 'ক্যাটাগরি নির্বাচন করুন...',
    'mapping_drawer_btn_cancel' => 'বাতিল',
    'mapping_drawer_btn_save' => 'লিঙ্ক সংরক্ষণ করুন',
    'mapping_drawer_saving' => 'সংরক্ষণ করা হচ্ছে...',
    'mapping_drawer_success_title' => 'ক্যাটাগরি সফলভাবে লিঙ্ক করা হয়েছে',
    'mapping_drawer_change_link' => 'লিঙ্ক পরিবর্তন করুন',
    'mapping_drawer_error_failed_save' => 'ম্যাপিং লিঙ্ক সংরক্ষণ করতে ব্যর্থ হয়েছে।',

    // ==========================================
    // search/partials/adoption-card.blade.php
    // ==========================================
    'adoption_no_category_exists' => 'কোনো ক্যাটাগরি বিদ্যমান নেই',
    'adoption_not_linked_desc' => 'এই অফিসিয়াল শ্রেণিবিভাগটি কোনো অপারেশনাল ক্যাটাগরির সাথে লিঙ্ক করা নেই।',
    'adoption_label_category_name' => 'ক্যাটাগরির নাম',
    'adoption_label_type' => 'ধরন',
    'adoption_type_asset' => 'সম্পদ (Asset)',
    'adoption_type_consumable' => 'ব্যবহার্য সামগ্রী (Consumable)',
    'adoption_type_accessory' => 'আনুষঙ্গিক সরঞ্জাম (Accessory)',
    'adoption_type_component' => 'উপাদান (Component)',
    'adoption_type_license' => 'লাইসেন্স (License)',
    'adoption_label_governance_availability' => 'গভার্নেন্স ও প্রাপ্যতা',
    'adoption_gov_shared_standard' => 'যৌথ সরকারি মান',
    'adoption_gov_available_globally' => 'সকল সংস্থার জন্য গ্লোবালি উপলব্ধ।',
    'adoption_gov_org_private' => 'প্রাতিষ্ঠানিক মান (প্রাইভেট)',
    'adoption_gov_assign_org' => 'একচেটিয়াভাবে একটি নির্দিষ্ট সংস্থার জন্য বরাদ্দ করুন।',
    'adoption_label_select_company' => '-- কোম্পানি নির্বাচন করুন --',
    'adoption_notice_secure_scope' => 'এই ক্যাটাগরিটি আপনার প্রতিষ্ঠানের অপারেশনাল স্কোপের মধ্যে নিরাপদে তৈরি করা হবে।',
    'adoption_btn_create_adopt' => 'তৈরি করুন এবং গ্রহণ করুন (Create & Adopt)',
    'adoption_mapped_category' => 'ম্যাপকৃত ক্যাটাগরি',
    'adoption_used_by_your' => 'আপনার :scopeNoun দ্বারা ব্যবহৃত হচ্ছে',
    'adoption_governance_label' => 'গভার্নেন্স:',
    'adoption_native_snipeit_category' => 'নেটিভ Snipe-IT ক্যাটাগরি',
    'adoption_btn_use_category' => 'ক্যাটাগরি ব্যবহার করুন',
    'adoption_btn_stop_using' => 'ব্যবহার বন্ধ করুন',
    'adoption_js_confirm_remove' => 'আপনি কি নিশ্চিত যে আপনি এই ক্যাটাগরিটি সরাতে চান?',
    'adoption_js_provisioning_failed' => 'প্রোভিশন ব্যর্থ হয়েছে: ',

    // ==========================================
    // Controllers - Exception messages
    // ==========================================
    'ctrl_exception_no_operational_context' => 'কোনো সক্রিয় অপারেশনাল প্রেক্ষাপট পাওয়া যায়নি।',
    'ctrl_exception_unauthorized_governance' => 'অননুমোদিত অ্যাক্সেস। গ্লোবাল ক্যাটাগরি গভার্নেন্সের জন্য সুপার-অ্যাডমিন প্রিভিলেজ প্রয়োজন।',
    'ctrl_exception_no_active_context' => 'কোনো সক্রিয় অপারেশনাল প্রেক্ষাপট পাওয়া যায়নি।',
    'ctrl_exception_no_active_context_session' => 'আপনার সেশনের জন্য কোনো সক্রিয় অপারেশনাল প্রেক্ষাপট পাওয়া যায়নি। অনুগ্রহ করে শীর্ষ বার থেকে একটি কার্যালয় নির্বাচন করুন।',
    'ctrl_exception_category_not_found' => 'আপনার অপারেশনাল ক্যাটালগে ক্যাটাগরিটি পাওয়া যায়নি।',
    'ctrl_exception_select_target_company' => 'অনুগ্রহ করে একটি টার্গেট কোম্পানি নির্বাচন করুন।',
    'ctrl_analysis_failed' => 'বিশ্লেষণ ব্যর্থ হয়েছে: ',
    'ctrl_update_failed' => 'আপডেট ব্যর্থ হয়েছে: ',

    // ==========================================
    // Services - Exception messages
    // ==========================================
    'svc_invalid_adoption_scope' => 'অগ্রহণযোগ্য অ্যাডপশন স্কোপের ধরন।',
    'svc_governance_violation' => 'গভার্নেন্স লঙ্ঘন: এই ক্যাটাগরির ব্যবহার বন্ধ করা সম্ভব নয়। আপনার কার্যালয়/প্রতিষ্ঠানের বর্তমানে এর সাথে যুক্ত সক্রিয় আইটেম রয়েছে।',
    'svc_precompiled_nodes_missing' => 'প্রাক-সংকলিত nodes.csv ফাইলটি পাওয়া যায়নি: ',
    'svc_tree_csv_missing_headers' => "ট্রি CSV-তে প্রয়োজনীয় কোনো একটি হেডার নিখোঁজ আছে: 'Key', 'Code', অথবা 'Title'.",

    // ==========================================
    // CatalogDatasetLocator
    // ==========================================
    'svc_directory_not_resolved' => 'টার্গেট ডিরেক্টরি [src/database/data] ডিস্কে সমাধান করা যায়নি।',

    // ==========================================
    // ImportHistory model
    // ==========================================
    'history_model_seconds_suffix' => 'সেকেন্ড',
];