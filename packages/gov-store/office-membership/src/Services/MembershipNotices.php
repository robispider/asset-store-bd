<?php

namespace GovStore\OfficeMembership\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipNotices
{
    /** Transactional outbox: committed notices survive mail outages and disappear on rollback. */
    public function record(string $event, int $office, array $users, array $details = []): void
    {
        $key = (string) Str::uuid();
        foreach (array_unique(array_filter($users)) as $user) {
            DB::table('gov_membership_notices')->insert(['event_key' => $event, 'location_id' => $office, 'user_id' => $user,
                'event_id' => $key, 'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function forUser(int $user)
    {
        return DB::table('gov_membership_notices as notices')->leftJoin('locations as office', 'office.id', '=', 'notices.location_id')
            ->where('notices.user_id', $user)->orderByDesc('notices.id')->limit(20)->get(['notices.*', 'office.name as location_name']);
    }
}
