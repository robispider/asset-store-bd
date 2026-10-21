@extends('layouts/default')
@section('title', __('govtracking::portfolio.title'))
@section('content')
@php
    $url=fn(array $changes=[])=>route('gov.tracking.initiatives.index',array_replace($filters,$changes));
    $money=fn($value)=>\GovStore\Tracking\Services\TrackingMonitorMetrics::money($value);
    $reviewCount=array_sum(array_intersect_key($summary['health'],array_flip(['attention','behind','no_target'])));
    $verdict=$reviewCount ? trans_choice('govtracking::portfolio.needs_review',$reviewCount) : __('govtracking::portfolio.'.($summary['operational'] ? 'all_on_pace' : 'no_operational'));
    $next=$priority ? $priority['title'].' · '.($priority['head']['name'] ?? __('govtracking::portfolio.no_head')).' · '.$priority['next'] : __('govtracking::portfolio.review_setup');
    $context=app(\GovStore\TenantScope\Contexts\TenantContext::class);
@endphp
<div class="gs-tracking-monitor gs-portfolio" data-portfolio-key="{{ auth()->id().'-'.$context->companyId.'-'.$context->locationId }}">
    <div class="gs-portfolio-heading">
        <p class="gs-portfolio-subtitle">{{ __('govtracking::portfolio.subtitle') }}</p>
        @if($can_create)<a class="btn btn-primary" href="{{ route('gov.tracking.initiatives.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('govtracking::portfolio.new_programme') }}</a>@endif
    </div>
    <nav class="gs-portfolio-nav gs-level-switch" aria-label="{{ __('govtracking::portfolio.navigation') }}">
        <a class="gs-level-switch__option" href="{{ route('gov.tracking.initiatives.index') }}" aria-current="page">{{ __('govtracking::general.monitor.programmes') }}</a>
        @can('tracking.offices.view')<a class="gs-level-switch__option" href="{{ route('gov.tracking.offices.index') }}">{{ __('govtracking::general.monitor.offices') }}</a>@endcan
        @can('tracking.settings.manage')<a class="gs-level-switch__option" href="{{ route('gov.tracking.funding-types.index') }}">{{ __('govtracking::portfolio.funding_types') }}</a>@endcan
    </nav>
    <x-gs::status-callout :status="$reviewCount ? 'warning' : ($summary['operational'] ? 'success' : 'info')" :verdict="$verdict" :next="$next" />
    <div class="gs-tracking-kpis gs-portfolio-kpis">
        <div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">{{ __('govtracking::portfolio.operational') }}</span><span class="gs-kpi__value">{{ $summary['operational'] }}</span><span class="gs-portfolio-health-key">@foreach($summary['health'] as $health=>$count)<span>{{ __('govtracking::portfolio.health.'.$health) }}: <b>{{ $count }}</b></span>@endforeach</span></span></div>
        <div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">{{ __('govtracking::general.monitor.value') }}</span><span class="gs-kpi__value">{{ $money($summary['value']) }}</span><span class="gs-kpi__caption">{{ trans_choice('govtracking::general.monitor.receipt_count_short',$summary['grn_count']) }} · {{ $summary['supplier_count'] }} {{ __('govtracking::general.monitor.suppliers') }}</span>@if(!$summary['value_complete'])<span class="gs-portfolio-incomplete">{{ __('govtracking::portfolio.incomplete') }} · {{ __('govtracking::general.monitor.known_value') }}: {{ $money($summary['known_value']) }}</span>@endif</span></div>
        <x-gs::kpi :label="__('govtracking::portfolio.offices')" :value="$summary['reached']" :caption="__('govtracking::general.monitor.waiting_count',['count'=>$summary['waiting']])" />
        <x-gs::kpi :label="__('govtracking::general.monitor.codes')" :value="$summary['active_count'].' · '.$summary['draft_count']" :caption="__('govtracking::general.monitor.active_draft')" />
    </div>
    <p class="gs-portfolio-scope">{{ __('govtracking::portfolio.summary_scope') }}</p>
    <nav class="gs-portfolio-tabs" aria-label="{{ __('govtracking::portfolio.status_filter') }}">
        @foreach(['all','Active','Planning','Closed','Archived'] as $status)<a href="{{ $url(['status'=>$status,'page'=>1]) }}" @if($filters['status']===$status) aria-current="page" @endif>{{ __('govtracking::portfolio.status.'.$status) }} <span>{{ $status==='all' ? $base_count : $counts[$status] }}</span></a>@endforeach
    </nav>
    <section class="gs-tracking-panel gs-portfolio-results" aria-label="{{ __('govtracking::portfolio.results') }}">
        <form class="gs-tracking-filters gs-portfolio-filters" method="GET" role="search">
            <input type="hidden" name="view" value="{{ $filters['view'] }}"><input type="hidden" name="status" value="{{ $filters['status'] }}">
            <label class="gs-tracking-filters__search">{{ __('govtracking::general.monitor.search') }}<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="{{ __('govtracking::portfolio.search_hint') }}"></label>
            <label>{{ __('govtracking::portfolio.funding') }}<select name="funding">@foreach(['all','ADP','REVENUE','OTHER'] as $funding)<option value="{{ $funding }}" @selected($filters['funding']===$funding)>{{ $funding==='all' ? __('govtracking::portfolio.all_funding') : $funding }}</option>@endforeach</select></label>
            <label>{{ __('govtracking::portfolio.sort') }}<select name="sort">@foreach(\GovStore\Tracking\Services\TrackingPortfolioService::SORTS as $sort)<option value="{{ $sort }}" @selected($filters['sort']===$sort)>{{ __('govtracking::portfolio.sorts.'.$sort) }}</option>@endforeach</select></label>
            <label>{{ __('govtracking::portfolio.direction') }}<select name="direction">@foreach(['asc','desc'] as $direction)<option value="{{ $direction }}" @selected($filters['direction']===$direction)>{{ __('govtracking::portfolio.'.$direction) }}</option>@endforeach</select></label>
            <label class="gs-portfolio-mine"><input type="checkbox" name="mine" value="1" @checked(!empty($filters['mine']))> {{ __('govtracking::portfolio.mine') }}</label>
            <button class="btn btn-primary" type="submit">{{ __('govtracking::general.monitor.apply') }}</button><a href="{{ route('gov.tracking.initiatives.index',['view'=>$filters['view']]) }}">{{ __('govtracking::general.monitor.reset') }}</a>
        </form>
        <div class="gs-portfolio-toolbar">
            <p role="status">{{ __('govtracking::portfolio.showing',['from'=>$programmes->firstItem() ?? 0,'to'=>$programmes->lastItem() ?? 0,'total'=>$programmes->total()]) }}</p>
            <nav class="gs-portfolio-view gs-level-switch" aria-label="{{ __('govtracking::portfolio.view') }}">@foreach(['table','cards'] as $view)<a class="gs-level-switch__option" href="{{ $url(['view'=>$view]) }}" @if($filters['view']===$view) aria-current="page" @endif><i class="fas {{ $view==='table' ? 'fa-table-list' : 'fa-grip' }}" aria-hidden="true"></i> {{ __('govtracking::portfolio.'.$view) }}</a>@endforeach</nav>
        </div>
        @if($programmes->count())
            @include('govtracking::portfolio.'.$filters['view'])
        @else
            <x-gs::empty-state icon="fa-briefcase" :title="__('govtracking::portfolio.'.($visible_count ? 'no_matches' : 'empty'))"><p>{{ __('govtracking::portfolio.empty_hint') }}</p><x-slot:actions><a class="btn btn-default" href="{{ route('gov.tracking.initiatives.index',['view'=>$filters['view']]) }}">{{ __('govtracking::general.monitor.reset') }}</a>@if($can_create)<a class="btn btn-primary" href="{{ route('gov.tracking.initiatives.create') }}">{{ __('govtracking::portfolio.new_programme') }}</a>@endif</x-slot:actions></x-gs::empty-state>
        @endif
        @if($programmes->hasPages())<nav class="gs-portfolio-pagination" aria-label="{{ __('govtracking::portfolio.pagination') }}">@if(!$programmes->onFirstPage())<a href="{{ $programmes->previousPageUrl() }}">← {{ __('govtracking::portfolio.previous') }}</a>@endif<span>{{ __('govtracking::portfolio.page_of',['page'=>$programmes->currentPage(),'pages'=>$programmes->lastPage()]) }}</span>@if($programmes->hasMorePages())<a href="{{ $programmes->nextPageUrl() }}">{{ __('govtracking::portfolio.next_page') }} →</a>@endif</nav>@endif
    </section>
</div>
@endsection
