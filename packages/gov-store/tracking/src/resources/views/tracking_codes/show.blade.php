@extends('layouts/default')
@section('title', $code['code'].' · '.$code['title'])
@section('content')
@php
    $authz=app(\GovStore\Tracking\Services\TrackingAuthorizationService::class);
    $digits=fn($value)=>\GovStore\Tracking\Services\TrackingMonitorMetrics::digits($value);
    $siblings=collect($monitor['codes'])->sortBy('code')->values();
    $position=$siblings->search(fn($c)=>$c['id']===$code['id']);
    $previous=$position>0 ? $siblings[$position-1] : null; $next=$siblings[$position+1] ?? null;
    $geoView=app()->bound(\GovStore\GeoView\Registry\GeoSourceRegistry::class);
    $head=$monitor['contacts']->firstWhere('designation','HEAD');
    $person=$head ? trim($head->first_name.' '.$head->last_name) : __('govtracking::general.monitor.operation_head');
    $forecast=$code['forecast'] ? ($code['fy_end'] && $code['forecast']->gt($code['fy_end']) ? __('govtracking::general.monitor.misses_year') : $code['forecast']->toDateString()) : __('govtracking::general.monitor.no_forecast');
    $steps=[['label'=>__('govtracking::general.monitor.created'),'date'=>$code['created']],['label'=>__('govtracking::general.monitor.activated'),'date'=>$code['activated']],['label'=>__('govtracking::general.monitor.first_receipt'),'date'=>collect($code['receipts'])->pluck('date')->filter()->sort()->first()],['label'=>__('govtracking::general.monitor.last_receipt'),'date'=>$code['last']],['label'=>__('govtracking::general.monitor.today'),'date'=>$monitor['today']->toDateString()],['label'=>__('govtracking::general.monitor.year_end'),'date'=>$code['fy_end']?->toDateString(),'detail'=>$code['fy_end'] ? __('govtracking::general.monitor.days_left',['days'=>max(0,(int)$monitor['today']->diffInDays($code['fy_end'],false))]) : null]];
@endphp
<div class="gs-tracking-monitor">
    <div class="gs-tracking-title-row"><div><div class="gs-tracking-code-heading"><h1 class="gs-tracking-code">{{ $code['code'] }}</h1><button type="button" class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" data-monitor-copy="{{ $code['code'] }}" data-copy-success="{{ __('govtracking::general.monitor.copied') }}" data-copy-failure="{{ __('govtracking::general.monitor.copy_failed') }}">{{ __('govtracking::general.monitor.copy') }}</button><span class="gs-sr-only" role="status" data-copy-result></span></div><p class="gs-tracking-subtitle">{{ $code['title'] }}</p></div>
    <div class="gs-tracking-actions">@if($previous)<a href="{{ route('gov.tracking.initiatives.tracking-codes.show',[$initiative->id,$previous['id']]) }}">← {{ $previous['code'] }}</a>@endif
        <label class="gs-sr-only" for="tracking-code-jump">{{ __('govtracking::general.monitor.jump') }}</label><select id="tracking-code-jump" data-monitor-jump>@foreach($siblings as $sibling)<option value="{{ route('gov.tracking.initiatives.tracking-codes.show',[$initiative->id,$sibling['id']]) }}" @selected($sibling['id']===$code['id'])>{{ $sibling['code'] }} · {{ __('govtracking::general.monitor.health.'.$sibling['health']) }}</option>@endforeach</select>
        @if($next)<a href="{{ route('gov.tracking.initiatives.tracking-codes.show',[$initiative->id,$next['id']]) }}">{{ $next['code'] }} →</a>@endif
    </div></div>
    <div class="gs-tracking-meta"><x-gs::status-badge :status="$code['health']" /><span>{{ __('govtracking::general.monitor.plans.'.$code['plan']) }}</span><span>{{ $code['fy'] }} · {{ $code['funding'] }}</span><span>{{ count($code['targets']) }} {{ __('govtracking::general.monitor.item_lines') }}</span></div>
    <div class="gs-tracking-actions"><a class="gs-btn gs-btn--secondary gs-btn--outline" href="{{ route('gov.tracking.tracking-codes.view-task',$code['id']) }}">{{ __('govtracking::general.monitor.code_sheet') }}</a>
    @if($code['order_pdf'])<a class="gs-btn gs-btn--secondary gs-btn--outline" href="{{ route('gov.tracking.tracking-codes.download',$code['id']) }}">{{ __('govtracking::general.monitor.order_pdf') }}</a>@endif
    @if($code['status']==='DRAFT' && $authz->allows($initiative,['HEAD','OFFICER']))<a class="gs-btn gs-btn--secondary gs-btn--outline" href="{{ route('gov.tracking.initiatives.tracking-codes.edit',[$initiative->id,$code['id']]) }}">{{ __('govtracking::general.monitor.edit') }}</a>@endif
    @if($code['status']==='DRAFT' && $authz->allows($initiative,['HEAD']))<form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.activate',[$initiative->id,$code['id']]) }}">@csrf<button class="gs-btn gs-btn--primary" data-tracking-confirm="{{ __('govtracking::general.ui.confirm_activate') }}">{{ __('govtracking::general.monitor.activate') }}</button></form>@endif
    </div>
    <x-gs::status-callout :status="$code['health']" :verdict="__('govtracking::general.monitor.rule.'.$code['health'])" :next="__('govtracking::general.monitor.contact_next',['person'=>$person])" :forecast="$forecast" />
    @if($code['approximate'])<p class="gs-tracking-notice">{{ __('govtracking::general.monitor.approximate_activation') }}</p>@endif
    <div class="gs-tracking-kpis">
        <div class="gs-tracking-kpi"><span>{{ __('govtracking::general.monitor.delivered') }}</span><x-gs::pace-bar :value="$code['status']==='ACTIVE' && $code['delivered']!==null ? $code['delivered']*100 : null" :expected="$code['status']==='ACTIVE' && $code['expected']!==null ? $code['expected']*100 : null" /></div>
        <x-gs::kpi :label="__('govtracking::general.monitor.received')" :value="$digits($code['received'])" :caption="$digits($code['grn_count']).' '.__('govtracking::general.monitor.receipts')" />
        <x-gs::kpi :label="__('govtracking::general.monitor.value')" :value="\GovStore\Tracking\Services\TrackingMonitorMetrics::money($code['value'])" :caption="__('govtracking::general.monitor.prices_per_item')" />
        <x-gs::kpi :label="__('govtracking::general.monitor.offices_started')" :value="$digits(collect($codeOffices)->where('received','>',0)->count().' / '.count($codeOffices))" />
    </div>
    @if($code['unknown_count'])<p class="gs-tracking-notice">{{ __('govtracking::general.monitor.incomplete_receipts') }}</p>@endif
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.receipts_over_time') }}</h2>
    @if($code['status']==='DRAFT')<p>{{ __('govtracking::general.monitor.draft_receipts') }}</p>@else
        <div class="gs-tracking-item-grid">@forelse($code['targets'] as $target)@php($chart=app(\GovStore\Tracking\Services\TrackingMonitorCharts::class)->target($code,$target,$monitor['today'])) @include('govtracking::monitor.chart')@empty<p>{{ __('govtracking::general.monitor.no_target') }}</p>@endforelse</div>
    @endif</section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.lifecycle') }}</h2><x-gs::lifecycle :steps="$steps" />
    <dl class="gs-monitor-inline-kv"><div><dt>{{ __('govtracking::general.monitor.overshoot') }}</dt><dd>{{ $initiative->allow_overshoot ? __('govtracking::general.monitor.warns') : __('govtracking::general.monitor.blocks') }}</dd></div><div><dt>{{ __('govtracking::general.monitor.documents_required') }}</dt><dd>{{ $initiative->require_documents ? __('govtracking::general.monitor.yes') : __('govtracking::general.monitor.no') }}</dd></div><div><dt>{{ __('govtracking::general.monitor.geography') }}</dt><dd>{{ $code['geography_label'] }}</dd></div><div><dt>{{ __('govtracking::general.monitor.who_receives') }}</dt><dd>{{ $code['participants_label'] }}</dd></div></dl>
    </section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.offices') }}</h2>@if($geoView)<x-geoview::map id="code-map" preset="tracking.programme" :params="['initiative' => $initiative->id, 'code' => $code['id']]" :options="['toolbar' => ['filters' => false]]" :label="__('govtracking::general.geo.map_label')" />@endif<div><x-gs::table><thead><tr><th>{{ __('govtracking::general.monitor.office') }}</th>@foreach($code['targets'] as $target)<th>{{ $target['name'] }}</th>@endforeach<th>{{ __('govtracking::general.monitor.status') }}</th><th>{{ __('govtracking::general.monitor.last_receipt') }}</th></tr></thead><tbody>
    @forelse($codeOffices as $office)<tr><td><a href="{{ route('gov.tracking.initiatives.tracking-codes.offices.show',[$initiative->id,$code['id'],$office['id']]) }}">{{ $office['name'] }}</a></td>@foreach($code['targets'] as $target)@php($cell=collect($office['items'])->firstWhere('category_id',$target['category_id']))<td class="{{ ($cell['allocated'] ?? 0)>0 && ($cell['received'] ?? 0)>$cell['allocated'] ? 'gs-tracking-over' : '' }}">{{ $cell['received'] ?? 0 }} / {{ ($cell['allocated'] ?? 0)>0 ? $cell['allocated'] : '—' }}</td>@endforeach<td><x-gs::status-badge :status="$office['state']" /></td><td>{{ $office['last'] ? \Carbon\CarbonImmutable::parse($office['last'])->toDateString() : '—' }}</td></tr>@empty<tr><td>{{ __('govtracking::general.monitor.no_offices') }}</td></tr>@endforelse
    </tbody></x-gs::table></div></section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.items_prices') }}</h2><div class="gs-tracking-item-grid">@foreach($code['targets'] as $target)<article class="gs-tracking-item"><h3>{{ $target['name'] }}</h3><p>{{ number_format($target['received']) }} / {{ number_format($target['planned']) }} · {{ $target['economic_code'] ?: __('govtracking::general.monitor.missing_economic') }}</p><x-gs::pace-bar :value="$target['fulfilment']===null ? null : $target['fulfilment']*100" /><x-gs::price-strip :prices="$target['prices']" :label="$target['name']" /></article>@endforeach</div></section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.receipts') }}</h2>@include('govtracking::monitor.receipts',['receipts'=>$code['status']==='DRAFT' ? [] : $code['receipts']])@include('govtracking::monitor.suppliers',['receipts'=>$code['status']==='DRAFT' ? [] : $code['receipts']])</section>
    <div class="gs-tracking-split"><section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.documents') }}</h2>@if($code['order_pdf'])<p><a href="{{ route('gov.tracking.tracking-codes.download',$code['id']) }}">{{ __('govtracking::general.monitor.order_pdf') }}</a></p>@endif
        @forelse($code['documents'] as $document)<p><a href="{{ route('gov.tracking.documents.download',[$initiative->id,$code['id'],$document->id]) }}">{{ $document->file_name }}</a></p>@empty<p>{{ __('govtracking::general.monitor.no_documents') }}</p>@endforelse</section>
    <section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.contacts') }}</h2>@include('govtracking::monitor.contacts')</section></div>
</div>
@endsection
