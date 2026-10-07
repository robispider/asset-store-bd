<?php

namespace GovStore\CustomRequests\Console\Commands;

use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Support\RequestWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MaintainRequests extends Command
{
    protected $signature = 'gov-requests:maintain';

    protected $description = 'Expire draft baskets, escalate overdue requests and deliver committed notification mail';

    public function handle(): int
    {
        DraftBasket::where('expires_at', '<=', now())->where('status', 'draft')->chunkById(100, function ($baskets) {
            foreach ($baskets as $basket) {
                DB::transaction(function () use ($basket) {
                    // Same order as basket mutations; never purge a concurrent submit.
                    DB::table('users')->where('id', $basket->user_id)->lockForUpdate()->first();
                    $locked = DraftBasket::whereKey($basket->id)->where('expires_at', '<=', now())->lockForUpdate()->first();
                    if ($locked) {
                        $locked->items()->delete();
                        $locked->delete();
                    }
                });
            }
        });
        $cutoff = now()->subWeekdays(max(1, (int) config('govstore-requests.escalation_weekdays', 3)));
        Request::whereIn('approval_status', RequestWorkflow::PENDING)->where('updated_at', '<', $cutoff)->whereNotNull('office_id')
            ->chunkById(100, function ($requests) use ($cutoff) {
                foreach ($requests as $request) {
                    DB::transaction(function () use ($request, $cutoff) {
                        $locked = Request::whereKey($request->id)->lockForUpdate()->first();
                        if ($locked && in_array($locked->approval_status, RequestWorkflow::PENDING) && $locked->updated_at < $cutoff
                            && ! $locked->events()->where('event_type', 'escalated')->where('created_at', '>=', $locked->updated_at)->exists()) {
                            RequestEvent::create(['request_id' => $locked->id, 'user_id' => $locked->requested_by,
                                'event_type' => 'escalated', 'details' => ['stage' => $locked->approval_status]]);
                        }
                    });
                }
            });
        if (config('govstore-requests.mail_enabled', false)) {
            // Minimal email contains no protected request contents. Access is rechecked on opening the queue.
            DB::table('custom_request_notices')->whereNull('emailed_at')->orderBy('id')->chunkById(100, function ($notices) {
                foreach ($notices as $notice) {
                    $user = DB::table('users')->where('id', $notice->user_id)->whereNull('deleted_at')->first();
                    if (! $user || ! $user->email) {
                        continue;
                    }
                    Mail::raw(__('requestlabels::requests.notification_mail_body', ['url' => route('gov.requests.user.index')]),
                        fn ($message) => $message->to($user->email)->subject(__('requestlabels::requests.notification_mail_subject')));
                    DB::table('custom_request_notices')->where('id', $notice->id)->update(['emailed_at' => now()]);
                }
            });
        }

        return self::SUCCESS;
    }
}
