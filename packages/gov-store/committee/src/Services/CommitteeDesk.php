<?php

namespace GovStore\Committee\Services;

use Carbon\CarbonImmutable;
use GovStore\Committee\Contracts\PurposeRegistry;
use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Models\{Committee,LedgerEntry};
use GovStore\Committee\Scopes\CommitteeBoundaryScope;
use GovStore\Committee\Support\CommitteeDisplay as Display;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class CommitteeDesk
{
    public function build(): array
    {
        $context = app(TenantContext::class); $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $visible = app(CommitteeBoundaryScope::class)->apply(Committee::query())->get();
        $attention = []; $coverage = [];
        foreach ($visible->where('owner_location_id',$context->locationId) as $c) {
            if ($c->status === 'DRAFT') {
                $attention[] = ['status'=>'DRAFT','title'=>Display::text($c->name_bn,$c->name_en),'message'=>__('committee::committee.ux.finish_draft'),'url'=>route('committee.show',$c->id),'action'=>__('committee::committee.ux.continue_draft')];
                continue;
            }
            if (! in_array($c->status,['ACTIVE','SUSPENDED'])) { continue; }
            $report = app(CommitteeHealthService::class)->computeFor($c->id,$today);
            $issues = array_filter($report->issues,fn ($f) => $f['code'] !== 'TERM_EXPIRING');
            if ($issues) {
                $attention[] = ['status'=>$report->status->value,'title'=>Display::text($c->name_bn,$c->name_en),'message'=>implode(' · ',array_map(fn ($f) => __('committee::committee.issues.'.$f['code']),$issues)),
                    'url'=>route('committee.show',['committee'=>$c->id,'action'=>$c->status === 'SUSPENDED' ? 'resume' : 'replace']),'action'=>__('committee::committee.ux.'.($c->status === 'SUSPENDED' ? 'resume' : 'change_members'))];
            }
            if ($c->effective_to && $c->effective_to >= $today->toDateString() && $today->diffInDays(CarbonImmutable::parse($c->effective_to)) <= 30
                && ! DB::table('gov_committee_reminder_dismissals')->where(['user_id'=>auth()->id(),'committee_id'=>$c->id,'effective_to'=>$c->effective_to])->exists()) {
                $attention[] = ['status'=>'AT_RISK','title'=>Display::text($c->name_bn,$c->name_en),'message'=>__('committee::committee.ux.expiry_notice',['date'=>Display::date($c->effective_to)]),
                    'url'=>route('committee.show',['committee'=>$c->id,'action'=>'extend']),'action'=>__('committee::committee.ux.extend'),'dismiss'=>$c->id];
            }
        }
        if ($context->locationId) {
            foreach (app(PurposeRegistry::class)->all() as $purpose) {
                if (! in_array('office',$purpose->allowedScopeTypes)) { continue; }
                $resolution = app(CommitteeResolverService::class)->resolve($purpose->code,new ScopeRef('office',(string)$context->locationId),$today);
                $committee = $resolution->committee;
                $multiple=in_array($resolution->status,[\GovStore\Committee\Enums\ResolutionStatus::AMBIGUOUS,\GovStore\Committee\Enums\ResolutionStatus::CONFLICT]);
                $coverage[] = ['purpose'=>Display::text($purpose->labelBn,$purpose->labelEn),'committee'=>$committee,'status'=>$committee ? app(CommitteeHealthService::class)->computeFor($committee->id,$today)->status->value : ($multiple ? 'AT_RISK' : 'MISSING')];
                if (! $committee) {
                    $attention[] = ['status'=>$multiple ? 'AT_RISK' : 'MISSING','title'=>Display::text($purpose->labelBn,$purpose->labelEn),'message'=>__('committee::committee.ux.'.($multiple ? 'multiple_cover' : 'no_cover')),'url'=>route($multiple ? 'committee.registry' : 'committee.new'),'action'=>__('committee::committee.'.($multiple ? 'view' : 'ux.record_constitution'))];
                }
            }
        }
        $recent = LedgerEntry::whereIn('committee_id',$visible->pluck('id'))->orderByDesc('id')->limit(6)->get()->map(fn ($entry) => app(\GovStore\Committee\Http\Transformers\CommitteeTransformer::class)->history($entry))->all();
        return compact('attention','coverage','recent');
    }
}
