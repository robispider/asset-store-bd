<?php

namespace GovStore\OfficeMembership\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DeliverMembershipMail extends Command
{
    protected $signature = 'govstore:membership-mail {--limit=100}';

    protected $description = 'Deliver optional membership outbox mail; committed in-app notices remain authoritative';

    public function handle(): int
    {
        if (! config('membership-notices.mail_enabled')) {
            $this->info('Membership mail is disabled.');

            return self::SUCCESS;
        }
        $ids = DB::table('gov_membership_notices')->whereNull('mailed_at')->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id) {
                    $notice = DB::table('gov_membership_notices')->where('id', $id)->whereNull('mailed_at')->lockForUpdate()->first();
                    if (! $notice) {
                        return;
                    }
                    $user = DB::table('users')->where('id', $notice->user_id)->whereNull('deleted_at')->first(['email']);
                    if ($user && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        // No invite codes, private evidence or national override reasons in mail.
                        Mail::raw(__('office_membership::member.mail_body'), fn ($m) => $m->to($user->email)->subject(__('office_membership::member.notices_title')));
                    }
                    DB::table('gov_membership_notices')->where('id', $id)->update(['mailed_at' => now()]);
                });
            } catch (\Throwable $e) {
                $reference = (string) Str::uuid();
                Log::error('Membership mail failed', ['reference_id' => $reference, 'notice_id' => $id, 'exception' => $e]);
                $this->error('Membership mail failed. Reference: '.$reference);

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
