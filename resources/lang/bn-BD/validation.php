<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | such as the size rules. Feel free to tweak each of these messages.
    |
    */


    'accepted' => ':attribute ফিল্ডটি অবশ্যই গৃহীত হতে হবে।',
    'accepted_if' => ':other এর মান :value হলে :attribute ফিল্ডটি অবশ্যই গৃহীত হতে হবে।',
    'active_url' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ URL হতে হবে।',
    'after' => ':attribute ফিল্ডটি অবশ্যই :date তারিখের পরের হতে হবে।',
    'after_or_equal' => ':attribute ফিল্ডটি অবশ্যই :date তারিখের পরে বা সমান হতে হবে।',
    'alpha' => ':attribute ফিল্ডটিতে শুধুমাত্র অক্ষর থাকতে পারবে।',
    'alpha_dash' => ':attribute ফিল্ডটিতে শুধুমাত্র অক্ষর, সংখ্যা, ড্যাশ এবং আন্ডারস্কোর থাকতে পারবে।',
    'alpha_num' => ':attribute ফিল্ডটিতে শুধুমাত্র অক্ষর এবং সংখ্যা থাকতে পারবে।',
    'array' => ':attribute ফিল্ডটি অবশ্যই একটি অ্যারে (array) হতে হবে।',
    'ascii' => ':attribute ফিল্ডটিতে শুধুমাত্র সিঙ্গেল-বাইট আলফানিউমেরিক ক্যারেক্টার এবং সিম্বল থাকতে পারবে।',
    'before' => ':attribute ফিল্ডটি অবশ্যই :date তারিখের আগের হতে হবে।',
    'before_or_equal' => ':attribute ফিল্ডটি অবশ্যই :date তারিখের আগে বা সমান হতে হবে।',
    'between' => [
        'array' => ':attribute ফিল্ডটিতে :min এবং :max এর মধ্যে আইটেম থাকতে হবে।',
        'file' => ':attribute ফিল্ডটি অবশ্যই :min এবং :max কিলোবাইটের মধ্যে হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই :min এবং :max এর মধ্যে হতে হবে।',
        'string' => ':attribute ফিল্ডটি অবশ্যই :min এবং :max অক্ষরের মধ্যে হতে হবে।',
    ],
    'valid_regex' => 'রেগুলার এক্সপ্রেশনটি (regular expression) অবৈধ।',
    'boolean' => ':attribute ফিল্ডটি অবশ্যই সত্য (true) অথবা মিথ্যা (false) হতে হবে।',
    'can' => ':attribute ফিল্ডটিতে একটি অননুমোদিত মান রয়েছে।',
    'confirmed' => ':attribute ফিল্ডের নিশ্চিতকরণ মিলছে না।',
    'contains' => ':attribute ফিল্ডে একটি প্রয়োজনীয় মানের অভাব রয়েছে।',
    'current_password' => 'পাসওয়ার্ডটি ভুল।',
    'date' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ তারিখ হতে হবে।',
    'date_equals' => ':attribute ফিল্ডটি অবশ্যই :date তারিখের সমান হতে হবে।',
    'date_format' => ':attribute ফিল্ডটি অবশ্যই :format ফরম্যাটের সাথে মিলতে হবে।',
    'decimal' => ':attribute ফিল্ডটিতে :decimal দশমিক স্থান থাকতে হবে।',
    'declined' => ':attribute ফিল্ডটি অবশ্যই প্রত্যাখ্যাত হতে হবে।',
    'declined_if' => ':other এর মান :value হলে :attribute ফিল্ডটি অবশ্যই প্রত্যাখ্যাত হতে হবে।',
    'different' => ':attribute ফিল্ড এবং :other অবশ্যই ভিন্ন হতে হবে।',
    'digits' => ':attribute ফিল্ডটিতে অবশ্যই :digits টি ডিজিট থাকতে হবে।',
    'digits_between' => ':attribute ফিল্ডটিতে অবশ্যই :min এবং :max ডিজিটের মধ্যে হতে হবে।',
    'dimensions' => ':attribute ফিল্ডের ছবির ডাইমেনশন অবৈধ।',
    'distinct' => ':attribute ফিল্ডে একটি ডুপ্লিকেট মান রয়েছে।',
    'doesnt_end_with' => ':attribute ফিল্ডটি নিম্নলিখিতগুলোর কোনোটি দিয়ে শেষ হতে পারবে না: :values।',
    'doesnt_start_with' => ':attribute ফিল্ডটি নিম্নলিখিতগুলোর কোনোটি দিয়ে শুরু হতে পারবে না: :values।',
    'email' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ ইমেইল ঠিকানা হতে হবে।',
    'ends_with' => ':attribute ফিল্ডটি অবশ্যই নিম্নলিখিতগুলোর কোনো একটি দিয়ে শেষ হতে হবে: :values।',
    'enum' => 'নির্বাচিত :attribute অবৈধ।',
    'exists' => 'নির্বাচিত :attribute অবৈধ।',
    'extensions' => ':attribute ফিল্ডটির অবশ্যই নিম্নলিখিত এক্সটেনশনগুলোর মধ্যে একটি হতে হবে: :values।',
    'file' => ':attribute ফিল্ডটি অবশ্যই একটি ফাইল হতে হবে।',
    'filled' => ':attribute ফিল্ডটিতে অবশ্যই একটি মান থাকতে হবে।',
    'gt' => [
        'array' => ':attribute ফিল্ডটিতে :value এর বেশি আইটেম থাকতে হবে।',
        'file' => ':attribute ফিল্ডটি অবশ্যই :value কিলোবাইটের বেশি হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই :value এর বেশি হতে হবে।',
        'string' => ':attribute ফিল্ডটি অবশ্যই :value অক্ষরের বেশি হতে হবে।',
    ],
    'gte' => [
        'array' => ':attribute ফিল্ডটিতে :value বা তার বেশি আইটেম থাকতে হবে।',
        'file' => ':attribute ফিল্ডটি অবশ্যই :value কিলোবাইটের সমান বা বেশি হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই :value এর সমান বা বেশি হতে হবে।',
        'string' => ':attribute ফিল্ডটি অবশ্যই :value অক্ষরের সমান বা বেশি হতে হবে।',
    ],
    'hex_color' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ হেক্সাডেসিমেল রঙ হতে হবে।',
    'image' => ':attribute ফিল্ডটি অবশ্যই একটি ছবি হতে হবে।',
    'import_field_empty' => ':fieldname এর মান শূন্য (null) হতে পারবে না।',
    'in' => 'নির্বাচিত :attribute অবৈধ।',
    'in_array' => ':attribute ফিল্ডটি অবশ্যই :other এর মধ্যে থাকতে হবে।',
    'integer' => ':attribute ফিল্ডটি অবশ্যই একটি পূর্ণসংখ্যা (integer) হতে হবে।',
    'ip' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ IP ঠিকানা হতে হবে।',
    'ipv4' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ IPv4 ঠিকানা হতে হবে।',
    'ipv6' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ IPv6 ঠিকানা হতে হবে।',
    'json' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ JSON স্ট্রিং হতে হবে।',
    'list' => ':attribute ফিল্ডটি অবশ্যই একটি তালিকা (list) হতে হবে।',
    'lowercase' => ':attribute ফিল্ডটি অবশ্যই ছোট হাতের অক্ষরে (lowercase) হতে হবে।',
    'lt' => [
        'array' => ':attribute ফিল্ডটিতে :value এর কম আইটেম থাকতে হবে।',
        'file' => ':attribute ফিল্ডটি অবশ্যই :value কিলোবাইটের কম হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই :value এর কম হতে হবে।',
        'string' => ':attribute ফিল্ডটি অবশ্যই :value অক্ষরের কম হতে হবে।',
    ],
    'lte' => [
        'array' => ':attribute ফিল্ডটিতে :value এর বেশি আইটেম থাকতে পারবে না।',
        'file' => ':attribute ফিল্ডটি অবশ্যই :value কিলোবাইটের সমান বা কম হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই :value এর সমান বা কম হতে হবে।',
        'string' => ':attribute ফিল্ডটি অবশ্যই :value অক্ষরের সমান বা কম হতে হবে।',
    ],
    'mac_address' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ MAC ঠিকানা হতে হবে।',
    'max' => [
        'array' => ':attribute ফিল্ডটিতে :max এর বেশি আইটেম থাকতে পারবে না।',
        'file' => ':attribute ফিল্ডটি অবশ্যই :max কিলোবাইটের বেশি হতে পারবে না।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই :max এর বেশি হতে পারবে না।',
        'string' => ':attribute ফিল্ডটি অবশ্যই :max অক্ষরের বেশি হতে পারবে না।',
    ],
    'max_digits' => ':attribute ফিল্ডটিতে :max এর বেশি ডিজিট থাকতে পারবে না।',
    'mimes' => ':attribute ফিল্ডটি অবশ্যই এই ধরনের ফাইল হতে হবে: :values।',

    'mimetypes' => ':attribute ফিল্ডটি অবশ্যই এই ধরনের ফাইল হতে হবে: :values।',
    'min' => [
        'array' => ':attribute ফিল্ডটিতে কমপক্ষে :min টি আইটেম থাকতে হবে।',
        'file' => ':attribute ফিল্ডটি অবশ্যই কমপক্ষে :min কিলোবাইট হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই কমপক্ষে :min হতে হবে।',
        'string' => ':attribute ফিল্ডটি অবশ্যই কমপক্ষে :min অক্ষরের হতে হবে।',
    ],
    'min_digits' => ':attribute ফিল্ডটিতে কমপক্ষে :min টি ডিজিট থাকতে হবে।',
    'missing' => ':attribute ফিল্ডটি অনুপস্থিত হতে হবে।',
    'missing_if' => ':other এর মান :value হলে :attribute ফিল্ডটি অনুপস্থিত হতে হবে।',
    'missing_unless' => ':other এর মান :value না হলে :attribute ফিল্ডটি অনুপস্থিত হতে হবে।',
    'missing_with' => ':values উপস্থিত থাকলে :attribute ফিল্ডটি অনুপস্থিত হতে হবে।',
    'missing_with_all' => ':values উপস্থিত থাকলে :attribute ফিল্ডটি অনুপস্থিত হতে হবে।',
    'multiple_of' => ':attribute ফিল্ডটি অবশ্যই :value এর গুণিতক হতে হবে।',
    'not_in' => 'নির্বাচিত :attribute অবৈধ।',
    'not_regex' => ':attribute ফিল্ডের ফরম্যাটটি অবৈধ।',
    'numeric' => ':attribute ফিল্ডটি অবশ্যই একটি সংখ্যা হতে হবে।',
    'password' => [
        'letters' => ':attribute ফিল্ডটিতে কমপক্ষে একটি অক্ষর থাকতে হবে।',
        'mixed' => ':attribute ফিল্ডটিতে কমপক্ষে একটি বড় হাতের এবং একটি ছোট হাতের অক্ষর থাকতে হবে।',
        'numbers' => ':attribute ফিল্ডটিতে কমপক্ষে একটি সংখ্যা থাকতে হবে।',
        'symbols' => ':attribute ফিল্ডটিতে কমপক্ষে একটি প্রতীক (symbol) থাকতে হবে।',
        'uncompromised' => 'প্রদত্ত :attribute একটি ডেটা লিক-এ পাওয়া গেছে। অনুগ্রহ করে অন্য একটি :attribute নির্বাচন করুন।',
    ],
    'percent' => 'অবচয়ের ধরন শতকরা হলে, সর্বনিম্ন অবচয় ০ থেকে ১০০ এর মধ্যে হতে হবে।',

    'present' => ':attribute ফিল্ডটি উপস্থিত থাকতে হবে।',
    'present_if' => ':other এর মান :value হলে :attribute ফিল্ডটি উপস্থিত থাকতে হবে।',
    'present_unless' => ':other এর মান :value না হলে :attribute ফিল্ডটি উপস্থিত থাকতে হবে।',
    'present_with' => ':values উপস্থিত থাকলে :attribute ফিল্ডটি উপস্থিত থাকতে হবে।',
    'present_with_all' => ':values উপস্থিত থাকলে :attribute ফিল্ডটি উপস্থিত থাকতে হবে।',
    'prohibited' => ':attribute ফিল্ডটি নিষিদ্ধ।',
    'prohibited_if' => ':other এর মান :value হলে :attribute ফিল্ডটি নিষিদ্ধ।',
    'prohibited_unless' => ':other এর মান :values এর মধ্যে না হলে :attribute ফিল্ডটি নিষিদ্ধ।',
    'prohibits' => ':attribute ফিল্ডটি উপস্থিত থাকলে :other ফিল্ডটি থাকতে পারবে না।',
    'regex' => ':attribute ফিল্ডের ফরম্যাটটি অবৈধ।',
    'required' => ':attribute ফিল্ডটি আবশ্যক।',
    'required_array_keys' => ':attribute ফিল্ডটিতে অবশ্যই এই এন্ট্রিগুলো থাকতে হবে: :values।',
    'required_if' => ':other এর মান :value হলে :attribute ফিল্ডটি আবশ্যক।',
    'required_if_accepted' => ':other গৃহীত হলে :attribute ফিল্ডটি আবশ্যক।',
    'required_if_declined' => ':other প্রত্যাখ্যাত হলে :attribute ফিল্ডটি আবশ্যক।',
    'required_unless' => ':other এর মান :values এর মধ্যে না হলে :attribute ফিল্ডটি আবশ্যক।',
    'required_with' => ':values উপস্থিত থাকলে :attribute ফিল্ডটি আবশ্যক।',
    'required_with_all' => ':values উপস্থিত থাকলে :attribute ফিল্ডটি আবশ্যক।',
    'required_without' => ':values অনুপস্থিত থাকলে :attribute ফিল্ডটি আবশ্যক।',
    'required_without_all' => ':values এর কোনোটিই উপস্থিত না থাকলে :attribute ফিল্ডটি আবশ্যক।',
    'same' => ':attribute ফিল্ডটি অবশ্যই :other এর সাথে মিলতে হবে।',
    'size' => [
        'array' => ':attribute ফিল্ডটিতে অবশ্যই :size টি আইটেম থাকতে হবে।',
        'file' => ':attribute ফিল্ডটি অবশ্যই :size কিলোবাইট হতে হবে।',
        'numeric' => ':attribute ফিল্ডটি অবশ্যই :size হতে হবে।',
        'string' => ':attribute ফিল্ডটি অবশ্যই :size অক্ষরের হতে হবে।',
    ],
    'starts_with' => ':attribute ফিল্ডটি অবশ্যই নিম্নলিখিতগুলোর কোনো একটি দিয়ে শুরু হতে হবে: :values।',
    'string' => ':attribute অবশ্যই একটি স্ট্রিং (string) হতে হবে।',
    'two_column_unique_undeleted' => ':attribute অবশ্যই :table1 এবং :table2 জুড়ে অনন্য হতে হবে। ',
    'unique_undeleted' => ':attribute অবশ্যই অনন্য হতে হবে।',
    'non_circular' => ':attribute দ্বারা কোনো সার্কুলার রেফারেন্স তৈরি করা যাবে না।',
    'parent_must_be_top_level' => 'নির্বাচিত :attribute অবশ্যই একটি টপ-লেভেল আইটেম হতে হবে। শুধুমাত্র এক স্তরের নেস্টিং অনুমোদিত।',
    'must_have_no_children' => 'এই আইটেমের ইতিমধ্যে নিজস্ব অধীনস্থ আইটেম রয়েছে, তাই এটিকে কোনো প্যারেন্ট বরাদ্দ করা সম্ভব নয়।',
    'not_array' => ':attribute অ্যারে (array) হতে পারবে না।',
    'disallow_same_pwd_as_user_fields' => 'পাসওয়ার্ড ব্যবহারকারীর নামের সাথে একই হতে পারবে না।',
    'letters' => 'পাসওয়ার্ডে কমপক্ষে একটি অক্ষর থাকতে হবে।',
    'numbers' => 'পাসওয়ার্ডে কমপক্ষে একটি সংখ্যা থাকতে হবে।',
    'case_diff' => 'পাসওয়ার্ডে বড় এবং ছোট হাতের অক্ষরের মিশ্রণ থাকতে হবে।',
    'symbols' => 'পাসওয়ার্ডে প্রতীক (symbol) থাকতে হবে।',
    'timezone' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ টাইমজোন হতে হবে।',
    'unique' => ':attribute ইতিমধ্যে ব্যবহৃত হয়েছে।',
    'uploaded' => ':attribute আপলোড করতে ব্যর্থ হয়েছে।',
    'uppercase' => ':attribute ফিল্ডটি অবশ্যই বড় হাতের অক্ষরে (uppercase) হতে হবে।',
    'url' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ URL হতে হবে।',
    'external_url' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ এক্সটারনাল URL (http:// বা https://) হতে হবে যা কোনো প্রাইভেট বা লোকাল অ্যাড্রেস নির্দেশ করে না।',
    'ulid' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ ULID হতে হবে।',
    'uuid' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ UUID হতে হবে।',
    'valid_css_color' => ':attribute ফিল্ডটি অবশ্যই একটি বৈধ CSS রঙ (hex, rgb, rgba, hsl, বা hsla) হতে হবে।',
    'fmcs_location' => 'কার্যালয় ":location" এর মন্ত্রণালয়/বিভাগ/দপ্তর/সংস্থা হলো :location_company, যা নির্বাচিত মন্ত্রণালয়/বিভাগ/দপ্তর/সংস্থার সাথে মিলছে না।',
    'is_unique_across_company_and_location' => ':attribute অবশ্যই নির্বাচিত মন্ত্রণালয়/বিভাগ/দপ্তর/সংস্থা এবং কার্যালয়ের মধ্যে অনন্য হতে হবে।',
// ...existing code...
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

  
    'email_array' => 'এক বা একাধিক ইমেইল ঠিকানা অবৈধ।',
    'checkboxes' => ':attribute ফিল্ডে অবৈধ অপশন রয়েছে।',
    'radio_buttons' => ':attribute অবৈধ।',

    'custom' => [
        'alpha_space' => ':attribute ফিল্ডটিতে এমন একটি অক্ষর রয়েছে যা অনুমোদিত নয়।',

        'hashed_pass' => 'আপনার বর্তমান পাসওয়ার্ডটি ভুল',
        'dumbpwd' => 'এই পাসওয়ার্ডটি অত্যন্ত সাধারণ।',
        'statuslabel_type' => 'আপনাকে অবশ্যই একটি বৈধ স্ট্যাটাস লেবেল ধরন নির্বাচন করতে হবে',
        'custom_field_not_found' => 'এই ফিল্ডটি পাওয়া যায়নি, অনুগ্রহ করে আপনার কাস্টম ফিল্ডের নামগুলো পুনরায় পরীক্ষা করুন।',
        'custom_field_not_found_on_model' => 'এই ফিল্ডটি বিদ্যমান বলে মনে হচ্ছে, তবে এটি এই সম্পদ মডেলের ফিল্ডসেটে উপলব্ধ নেই।',

        'purchase_date.date_format' => ':attribute অবশ্যই YYYY-MM-DD ফরম্যাটে একটি বৈধ তারিখ হতে হবে',
        'last_audit_date.date_format' => ':attribute অবশ্যই YYYY-MM-DD hh:mm:ss ফরম্যাটে একটি বৈধ তারিখ হতে হবে',
        'expiration_date.date_format' => ':attribute অবশ্যই YYYY-MM-DD ফরম্যাটে একটি বৈধ তারিখ হতে হবে',
        'termination_date.date_format' => ':attribute অবশ্যই YYYY-MM-DD ফরম্যাটে একটি বৈধ তারিখ হতে হবে',
        'expected_checkin.date_format' => ':attribute অবশ্যই YYYY-MM-DD ফরম্যাটে একটি বৈধ তারিখ হতে হবে',
        'start_date.date_format' => ':attribute অবশ্যই YYYY-MM-DD ফরম্যাটে একটি বৈধ তারিখ হতে হবে',
        'end_date.date_format' => ':attribute অবশ্যই YYYY-MM-DD ফরম্যাটে একটি বৈধ তারিখ হতে হবে',
        'invalid_value_in_field' => 'এই ফিল্ডে একটি অবৈধ মান অন্তর্ভুক্ত করা হয়েছে',

        'ldap_username_field' => [
            'not_in' => '<code>sAMAccountName</code> (মিশ্র কেস) সম্ভবত কাজ করবে না। এর পরিবর্তে আপনার <code>samaccountname</code> (ছোট হাতের অক্ষর) ব্যবহার করা উচিত।',
        ],
        'ldap_auth_filter_query' => ['not_in' => '<code>uid=samaccountname</code> সম্ভবত একটি বৈধ অথ ফিল্টার নয়। আপনি সম্ভবত <code>uid=</code> ব্যবহার করতে চান '],
        'ldap_filter' => ['regex' => 'এই মানটি সম্ভবত বন্ধনী (parentheses) দ্বারা আবৃত থাকা উচিত নয়।'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

     'attributes' => [
        'serials.*' => 'সিরিয়াল নম্বর',
        'asset_tags.*' => 'সম্পদ ট্যাগ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generic Validation Messages - we use these in the jquery validation where we don't have
    | access to the :attribute
    |--------------------------------------------------------------------------
    */

    'generic' => [
        'invalid_value_in_field' => 'এই ফিল্ডে একটি অবৈধ মান অন্তর্ভুক্ত করা হয়েছে',
        'required' => 'এই ফিল্ডটি আবশ্যক',
        'email' => 'অনুগ্রহ করে একটি বৈধ ইমেইল ঠিকানা লিখুন',
    ],


];
