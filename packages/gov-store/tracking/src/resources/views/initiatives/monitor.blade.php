@extends('layouts/default')
@section('title', $initiative->title)
@section('content')
@php
    $authz=app(\GovStore\Tracking\Services\TrackingAuthorizationService::class);
    $digits=fn($value)=>\GovStore\Tracking\Services\TrackingMonitorMetrics::digits($value);
    $charts=app(\GovStore\Tracking\Services\TrackingMonitorCharts::class)->programme($monitor);
    $tiles=app(\GovStore\Tracking\Services\TrackingTileMap::class)->tiles($monitor['offices'],$initiative->id);
    $healthCounts=collect($monitor['codes'])->where('status','ACTIVE')->countBy('health');
    $firstAttention=$monitor['attention'][0] ?? null;
    $valueSpark=[]; $runningValue=0;
    if($monitor['value_complete']) foreach(collect($monitor['receipts'])->filter(fn($r)=>$r['date'])->groupBy(fn($r)=>substr($r['date'],0,7))->sortKeys() as $group) { $runningValue+=$group->sum('value'); $valueSpark[]=$runningValue; }
    $head=$monitor['contacts']->firstWhere('designation','HEAD');
    $contact=$head ? trim($head->first_name.' '.$head->last_name) : __('govtracking::general.monitor.operation_head');
    $codePrefix=null;
    if(preg_match('/^(.+-)[^-]+-[^-]+$/',$monitor['codes'][0]['code'] ?? '',$parts)
        && collect($monitor['codes'])->every(fn($c)=>str_starts_with($c['code'],$parts[1]))) $codePrefix=$parts[1];
@endphp
<div class="gs-tracking-monitor">
    <x-gs::page-header :title="$initiative->title" :subtitle="$initiative->purpose" />
    @if(collect($monitor['codes'])->where('status','ACTIVE')->contains('approximate',true))<p class="gs-tracking-muted">{{ __('govtracking::general.monitor.approximate_activation') }}</p>@endif
    <div class="gs-tracking-meta"><x-gs::status-badge :status="strtolower($initiative->status)" :label="__('govtracking::general.monitor.initiative_status.'.$initiative->status)" /><span>{{ $initiative->primary_funding }} · {{ $monitor['owner'] }}</span><span>{{ collect($monitor['codes'])->pluck('fy')->unique()->implode(' · ') }}</span><span>{{ count($monitor['codes']) }} {{ __('govtracking::general.monitor.codes') }}</span></div>
    <div class="gs-tracking-avatars">@foreach($monitor['contacts'] as $person)<span @if($person->designation==='HEAD') data-head @endif title="{{ trim($person->first_name.' '.$person->last_name).' · '.__('govtracking::general.monitor.designations.'.$person->designation) }}">{{ mb_substr($person->first_name,0,1) }}</span>@endforeach</div>
    @include('govtracking::monitor.tabs')
    <x-gs::status-callout :status="$firstAttention ? 'warning' : 'success'" :verdict="$firstAttention ? $firstAttention['title'] : __('govtracking::general.monitor.no_attention')" :next="__('govtracking::general.monitor.contact_next',['person'=>$contact])" />
    <form method="GET" class="gs-tracking-filters" role="search">
        <label>{{ __('govtracking::general.monitor.show') }}<select name="status">@foreach(['all','ACTIVE','DRAFT'] as $status)<option value="{{ $status }}" @selected(($monitor['filters']['status'] ?? 'all')===$status)>{{ __('govtracking::general.monitor.filter_status.'.$status) }}</option>@endforeach</select></label>
        <label>{{ __('govtracking::general.monitor.plan') }}<select name="plan">@foreach(['all','3_MATRIX','2_CATEGORY','1_BLANKET'] as $plan)<option value="{{ $plan }}" @selected(($monitor['filters']['plan'] ?? 'all')===$plan)>{{ __('govtracking::general.monitor.plans.'.$plan) }}</option>@endforeach</select></label>
        <label class="gs-tracking-filters__search">{{ __('govtracking::general.monitor.search') }}<input type="search" name="q" value="{{ $monitor['filters']['q'] ?? '' }}" maxlength="150" placeholder="{{ __('govtracking::general.monitor.search_hint') }}"></label>
        <button class="gs-btn gs-btn--primary" type="submit">{{ __('govtracking::general.monitor.apply') }}</button>
        <a href="{{ route('gov.tracking.initiatives.show',$initiative->id) }}">{{ __('govtracking::general.monitor.reset') }}</a>
    </form>
    <div class="gs-tracking-kpis">
        <div class="gs-tracking-kpi"><span>{{ __('govtracking::general.monitor.delivered') }}</span><x-gs::pace-bar :value="$monitor['delivered']===null ? null : $monitor['delivered']*100" :expected="$monitor['expected']===null ? null : $monitor['expected']*100" /></div>
        <x-gs::kpi :spark="$valueSpark" :label="__('govtracking::general.monitor.value')" :value="\GovStore\Tracking\Services\TrackingMonitorMetrics::money($monitor['value'])" :caption="$digits($monitor['grn_count']).' '.__('govtracking::general.monitor.receipts').' · '.$digits($monitor['supplier_count']).' '.__('govtracking::general.monitor.suppliers')" />
        <x-gs::kpi :label="__('govtracking::general.monitor.offices_reached')" :value="$digits($monitor['allocated_offices']>0 ? $monitor['reached'].' / '.$monitor['allocated_offices'] : $monitor['reached'])" :caption="__('govtracking::general.monitor.waiting_count',['count'=>$digits($monitor['waiting_offices'])])" />
        <x-gs::kpi :label="__('govtracking::general.monitor.codes')" :value="$digits($monitor['active_count'].' · '.$monitor['draft_count'])" :caption="__('govtracking::general.monitor.active_draft')" />
    </div>
    @if(!$monitor['value_complete'])<p class="gs-tracking-notice">{{ __('govtracking::general.monitor.incomplete_receipts') }} {{ __('govtracking::general.monitor.known_value') }}: {{ \GovStore\Tracking\Services\TrackingMonitorMetrics::money($monitor['known_value']) }}</p>@endif
    <div class="gs-tracking-health-mix">@foreach($healthCounts as $health=>$count)<span><x-gs::status-badge :status="$health" /> {{ $count }}</span>@endforeach</div>
    <div class="gs-tracking-split">
        <section><h2>{{ __('govtracking::general.monitor.attention') }}</h2><x-gs::attention-list :items="$monitor['attention']" /></section>
        <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.delivery_year') }}</h2>@forelse($charts as $chart)@include('govtracking::monitor.chart')@empty<p>{{ __('govtracking::general.monitor.no_active') }}</p>@endforelse</section>
    </div>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.codes') }}</h2>
        <x-gs::table class="gs-tracking-codes" data-monitor-sortable><thead><tr><th scope="col"><span class="gs-sr-only">{{ __('govtracking::general.monitor.product_breakdown') }}</span></th>@foreach(['code','status','delivered','received','value','offices','last_receipt','trend'] as $heading)<th scope="col" @class(['gs-num'=>in_array($heading,['received','value','offices'])])><button type="button" data-monitor-sort="{{ $loop->index+1 }}" @if(in_array($heading,['delivered','received','value','offices','trend'])) data-monitor-sort-type="number" @endif>{{ __('govtracking::general.monitor.'.$heading) }} ↕</button></th>@endforeach</tr></thead><tbody>
        @forelse($monitor['codes'] as $c)<tr data-monitor-code="{{ $c['id'] }}">
            <td class="gs-tracking-codes__toggle"><button type="button" class="gs-tracking-expand" data-monitor-toggle hidden aria-expanded="true" aria-controls="tracking-products-{{ $c['id'] }}" aria-label="{{ __('govtracking::general.monitor.collapse_products',['code'=>$c['code']]) }}" data-expand-label="{{ __('govtracking::general.monitor.expand_products',['code'=>$c['code']]) }}" data-collapse-label="{{ __('govtracking::general.monitor.collapse_products',['code'=>$c['code']]) }}"><span data-monitor-toggle-icon aria-hidden="true">−</span></button></td>
            <td class="gs-tracking-codes__identity" data-sort="{{ $c['code'] }}"><a class="gs-tracking-code" aria-label="{{ $c['code'] }}" title="{{ $c['code'] }}" href="{{ route('gov.tracking.initiatives.tracking-codes.show',[$initiative->id,$c['id']]) }}">@if($codePrefix)<x-gs::code-segments size="sm" :segments="[['value'=>$codePrefix,'tone'=>'muted'],['value'=>substr($c['code'],strlen($codePrefix)),'tone'=>'primary']]" />@else{{ $c['code'] }}@endif</a><strong class="gs-tracking-codes__title" title="{{ $c['title'] }}">{{ $c['title'] }}</strong><small class="gs-tracking-block gs-tracking-muted">{{ __('govtracking::general.monitor.plans.'.$c['plan']) }} · {{ trans_choice('govtracking::general.monitor.product_count',count($c['targets'])) }}</small></td>
            <td><x-gs::status-badge :status="$c['health']" :title="__('govtracking::general.monitor.rule.'.$c['health'])" /></td>
            <td data-sort="{{ $c['delivered'] ?? -1 }}"><x-gs::pace-bar compact :value="$c['status']==='ACTIVE' && $c['delivered']!==null ? $c['delivered']*100 : null" :expected="$c['status']==='ACTIVE' && $c['expected']!==null ? $c['expected']*100 : null" /></td>
            <td class="gs-num gs-tracking-codes__quantity" data-sort="{{ $c['received'] }}">{{ number_format($c['received']) }} / {{ $c['planned']>0 ? number_format($c['planned']) : '—' }}</td>
            <td class="gs-num" data-sort="{{ $c['status']==='DRAFT' ? -1 : ($c['value'] ?? -1) }}">{{ $c['status']==='DRAFT' ? '—' : \GovStore\Tracking\Services\TrackingMonitorMetrics::money($c['value']) }}</td>
            <td class="gs-num">{{ count(app(\GovStore\Tracking\Services\TrackingMonitorService::class)->codeOfficeIds($c)) }}</td><td data-sort="{{ $c['last'] ?? '' }}">{{ $c['last'] ? \Carbon\CarbonImmutable::parse($c['last'])->toDateString() : '—' }}@if($c['last'])<small class="gs-tracking-block">{{ \Carbon\CarbonImmutable::parse($c['last'])->diffForHumans() }}</small>@endif</td><td data-sort="{{ $c['grn_count'] }}">@include('govtracking::monitor.spark',['code'=>$c])</td>
        </tr>
        <tr class="gs-tracking-code-detail" data-monitor-detail id="tracking-products-{{ $c['id'] }}"><td colspan="9">@include('govtracking::monitor.code-products',['code'=>$c])</td></tr>
        @empty<tr><td colspan="9">{{ __('govtracking::general.monitor.no_codes') }}</td></tr>@endforelse
        </tbody></x-gs::table>
    </section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.where') }}</h2><p class="gs-tracking-muted">{{ __('govtracking::general.monitor.schematic') }}</p>
        <div class="gs-tracking-split"><x-gs::tile-map :tiles="$tiles" /><div>@foreach($monitor['offices'] as $office)<a class="gs-tracking-office-rank" href="{{ route('gov.tracking.initiatives.offices.show',[$initiative->id,$office['id']]) }}"><strong>{{ $office['name'] }}</strong><span>{{ number_format($office['received']) }} {{ __('govtracking::general.monitor.received') }}</span><span>{{ $office['share']===null ? __('govtracking::general.monitor.no_target') : number_format($office['share']*100,1).'%' }}</span></a>@endforeach</div></div>
    </section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.what') }}</h2>@include('govtracking::monitor.items',['items'=>$monitor['items']]) @include('govtracking::monitor.suppliers',['receipts'=>$monitor['receipts']])</section>
    <div class="gs-tracking-split"><section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.activity') }}</h2><ol class="gs-tracking-activity">@forelse($monitor['activity'] as $event)<li><time>{{ $event->occurred_at }}</time><span>{{ __('govtracking::general.monitor.events.'.$event->event_type) }}</span><small>{{ (json_decode($event->metadata ?? '{}',true)['tracking_code'] ?? '') }}</small></li>@empty<li>{{ __('govtracking::general.monitor.no_activity') }}</li>@endforelse</ol></section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.contacts') }}</h2>@include('govtracking::monitor.contacts')</section></div>
</div>
@endsection
