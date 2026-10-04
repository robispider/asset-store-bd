<?php

namespace GovStore\Committee\Listeners;

use App\Models\{Actionlog,Location};
use GovStore\Committee\Events\CommitteeRecorded;

class WriteNativeAuditLog
{
    public function handle(CommitteeRecorded $event): void
    {
        $log = new Actionlog;
        // Native item IDs are integers; attach the summary to the owning office.
        // The committee's UUID and chained ledger remain its authoritative record.
        $log->item_type = Location::class;
        $log->item_id = $event->officeId;
        $log->company_id = $event->companyId;
        $log->created_by = $event->actorId;
        $log->action_type = 'update';
        $log->note = 'NIAR committee '.$event->number.': '.$event->eventType.' (ledger '.$event->ledgerId.')';
        $log->log_meta = json_encode(['committee_id'=>$event->committeeId,'ledger_id'=>$event->ledgerId]);
        try { $log->logaction('update'); }
        catch (\Throwable $exception) {
            // The authoritative ledger has already committed. A secondary audit
            // sink failure must not tell the caller the legal mutation rolled back.
            \Illuminate\Support\Facades\Log::error('Committee native audit sink failed',[
                'reference_id'=>(string)\Illuminate\Support\Str::uuid(),'ledger_id'=>$event->ledgerId,'exception'=>$exception,
            ]);
        }
    }
}
