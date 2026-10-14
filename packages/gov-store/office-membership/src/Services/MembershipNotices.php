<?php

namespace GovStore\OfficeMembership\Services;

use Carbon\Carbon;
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

    /**
     * One notice as a whole sentence in the current language (plan F10): the office, role and names go into
     * one translated string, never stitched from fragments. A note or decline reason follows in quotes.
     *
     * @return array{text: string, quote: ?string, at: string, when: string, icon: string}
     */
    public function present(object $notice): array
    {
        $details = json_decode((string) ($notice->details ?? ''), true) ?: [];
        $key = 'office_membership::member.notice_'.$notice->event_key;
        $replace = ['office' => $notice->location_name ?? __('office_membership::member.unknown_office'),
            'role' => isset($details['role']) ? __('office_membership::member.role_'.$details['role']) : '',
            'name' => $details['name'] ?? '', 'from' => $details['from'] ?? ''];
        $text = __($key, $replace);
        $created = $notice->created_at ? Carbon::parse($notice->created_at) : null;

        return [
            'text' => $text === $key ? __('office_membership::member.notice_other', $replace) : $text,
            'quote' => $details['reason'] ?? $details['note'] ?? null,
            'at' => $created?->diffForHumans() ?? '',
            'when' => $created?->toDayDateTimeString() ?? '',
            'icon' => match (true) {
                str_starts_with($notice->event_key, 'transfer_') => 'fa-right-left',
                str_contains($notice->event_key, 'override') => 'fa-shield-halved',
                in_array($notice->event_key, ['membership_rejected', 'membership_inactive', 'membership_released'], true) => 'fa-door-closed',
                default => 'fa-building',
            },
        ];
    }
}
