<?php

namespace GovStore\Committee\Services;

use Carbon\CarbonImmutable;
use GovStore\Committee\DTOs\HealthReport;
use GovStore\Committee\Enums\HealthStatus;
use GovStore\Committee\Models\{Committee,LedgerEntry};
use Illuminate\Support\Facades\DB;

class CommitteeHealthService
{
    public function __construct(private CompositionValidator $validator) {}
    public function computeFor(string $id, \DateTimeInterface $asOf): HealthReport
    {
        $date = $asOf->format('Y-m-d');
        $c = Committee::findOrFail($id);
        $issues = $this->validator->evaluate($c,$date);
        $state = LedgerEntry::where('committee_id',$id)->whereIn('event_type',['CommitteeSuspended','CommitteeResumed'])
            ->orderBy('id')->get()->filter(fn ($e) => ($e->payload['date'] ?? '9999') <= $date)->last();
        if ($state?->event_type === 'CommitteeSuspended') { $issues[] = ['code'=>'SUSPENDED','severity'=>'BLOCK']; }
        if ($date < $c->effective_from || ($c->effective_to && $date > $c->effective_to) || ($c->ended_on && $date > $c->ended_on)) { $issues[] = ['code'=>'OUTSIDE_TERM','severity'=>'BLOCK']; }
        if ($c->effective_to && $c->effective_to >= $date && CarbonImmutable::parse($date)->diffInDays(CarbonImmutable::parse($c->effective_to)) <= 30) { $issues[] = ['code'=>'TERM_EXPIRING','severity'=>'WARN']; }
        $severities = array_column($issues,'severity');
        return new HealthReport(in_array('BLOCK',$severities) ? HealthStatus::INOPERABLE : (in_array('WARN',$severities) ? HealthStatus::AT_RISK : HealthStatus::OPERABLE),$issues,$date);
    }
    public function refreshProjection(string $id): HealthReport
    {
        $health = $this->computeFor($id,CarbonImmutable::now('Asia/Dhaka'));
        $old = DB::table('gov_committee_health')->where('committee_id',$id)->value('status');
        DB::table('gov_committee_health')->updateOrInsert(['committee_id'=>$id],['status'=>$health->status->value,'issues'=>json_encode($health->issues),'computed_at'=>now()]);
        if ($old && $old !== $health->status->value) {
            $c = Committee::findOrFail($id);
            event(new \GovStore\Committee\Events\CommitteeHealthChanged($id,$c->lineage_id,auth()->id(),now()->toIso8601String(),['old'=>$old,'new'=>$health->status->value]));
        }
        return $health;
    }
    public function sweep(): int
    {
        $count = 0;
        foreach (Committee::whereIn('status',['ACTIVE','SUSPENDED'])->cursor() as $c) {
            $this->refreshProjection($c->id); $count++;
            if (! $c->effective_to) { continue; }
            $days = CarbonImmutable::now('Asia/Dhaka')->startOfDay()->diffInDays(CarbonImmutable::parse($c->effective_to),false);
            if ($days < 0 || $days > 30) { continue; }
            DB::transaction(function () use ($c,$days) {
                Committee::where('lineage_id',$c->lineage_id)->orderBy('version_no')->lockForUpdate()->firstOrFail();
                $locked = Committee::whereKey($c->id)->lockForUpdate()->firstOrFail();
                if ($locked->effective_to !== $c->effective_to) { return; }
                $previous = LedgerEntry::where('committee_id',$c->id)->where('event_type','CommitteeExpiringSoon')->get();
                foreach ([30,7] as $threshold) {
                    if ($days <= $threshold && ! $previous->contains(fn ($e) => ($e->payload['effective_to'] ?? null) === $c->effective_to && ($e->payload['threshold'] ?? null) === $threshold)) {
                        app(CommitteeLedger::class)->append($locked,'CommitteeExpiringSoon',['threshold'=>$threshold,'days_left'=>(int)$days,'effective_to'=>$c->effective_to]);
                    }
                }
            });
        }
        return $count;
    }
}
