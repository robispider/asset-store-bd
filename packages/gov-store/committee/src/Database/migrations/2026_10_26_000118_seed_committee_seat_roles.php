<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Seat roles are reference data every committee draft needs; until now only CommitteeSeeder (run by the
 * experimentation scenarios) created them, so a fresh deployment offered an empty seat-role dropdown.
 * Existing roles keep their names, order and active flag; only missing codes are added.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();
        foreach ([
            ['chairperson', 'Chairperson', 'সভাপতি', true, false, true],
            ['convener', 'Convener', 'আহ্বায়ক', true, false, true],
            ['member_secretary', 'Member-Secretary', 'সদস্য সচিব', false, true, true],
            ['member', 'Member', 'সদস্য', false, false, true],
            ['technical_expert', 'Technical Expert', 'কারিগরি বিশেষজ্ঞ', false, false, true],
            ['co_opted', 'Co-opted Member', 'কো-অপ্ট সদস্য', false, false, true],
            ['observer', 'Observer', 'পর্যবেক্ষক', false, false, false],
        ] as $i => [$code, $en, $bn, $presiding, $secretary, $strength]) {
            DB::table('gov_committee_seat_roles')->insertOrIgnore([
                'code' => $code, 'name_en' => $en, 'name_bn' => $bn, 'is_presiding' => $presiding, 'is_secretary' => $secretary,
                'counts_toward_strength' => $strength, 'sort_order' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    // Seat rows reference these roles, so rolling back leaves the reference data in place.
    public function down(): void {}
};
