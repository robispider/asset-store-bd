@extends('committee::layout')
@section('committee-content')
<section class="box">
    <div class="cm-toolbar"><div><h1>{{ $display::text($c->name_bn,$c->name_en) }}</h1><p class="cm-muted">{{ $officeName }} · {{ $c->committee_number }} · {{ $display::digits($c->fiscal_year) }}</p></div>@include('committee::partials/status',['status'=>$c->status === 'ACTIVE' ? $health->status->value : $c->status])</div>
    <div class="cm-term"><div class="cm-toolbar"><span>{{ __('committee::committee.ux.since',['date'=>$display::date($c->effective_from)]) }}</span><strong>{{ $c->ended_on ? __('committee::committee.ux.ended_on',['date'=>$display::date($c->ended_on)]) : $display::date($c->effective_to) }}</strong></div>
    @if($c->effective_to && !$c->ended_on)@php($elapsed = max(0,min(100,(int)(\Carbon\CarbonImmutable::parse($c->effective_from)->diffInDays(now('Asia/Dhaka'),false) / max(1,\Carbon\CarbonImmutable::parse($c->effective_from)->diffInDays(\Carbon\CarbonImmutable::parse($c->effective_to))) * 100))))<progress class="cm-term-progress" max="100" value="{{ $elapsed }}" aria-label="{{ __('committee::committee.ux.term_progress') }}">{{ $elapsed }}%</progress>@endif</div>
    @include('committee::partials/order-summary')
    <div class="cm-actions">
    @if($own && $c->status === 'ACTIVE')
        <x-gov-action ability="committee.manage" href="{{ route('committee.show',['committee'=>$c->id,'action'=>'replace']) }}" class="btn btn-primary">{{ __('committee::committee.ux.change_members') }}</x-gov-action>
        <x-gov-action ability="committee.activate" href="{{ route('committee.show',['committee'=>$c->id,'action'=>'extend']) }}" class="btn btn-default">{{ __('committee::committee.ux.extend') }}</x-gov-action>
    @endif
    @if($own && in_array($c->status,['ACTIVE','EXPIRED']))<x-gov-action ability="committee.manage" href="{{ route('committee.reconstitute',$c->id) }}" class="btn btn-default">{{ __('committee::committee.ux.reconstitute') }}</x-gov-action>@endif
    <a class="btn btn-default" href="{{ route('committee.print',$c->id) }}"><i class="fa fa-print" aria-hidden="true"></i> {{ __('committee::committee.print') }}</a>
    @if($own && in_array($c->status,['ACTIVE','SUSPENDED']))<div class="dropdown"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true">{{ __('committee::committee.ux.more') }} <span class="caret"></span></button><ul class="dropdown-menu">
    @foreach(($c->status === 'ACTIVE' ? ['scope','suspend','dissolve','correct'] : ['resume','dissolve']) as $verb)<li><a href="{{ route('committee.show',['committee'=>$c->id,'action'=>$verb]) }}">{{ __('committee::committee.ux.'.$verb) }}</a></li>@endforeach
    </ul></div>@endif</div>
</section>
<div class="row"><div class="col-md-9"><h2>{{ __('committee::committee.ux.step_members') }} <small>· {{ __('committee::committee.ux.order_position') }}</small></h2><div class="row cm-people-grid">
    @forelse($view->seats as $seat)<div class="col-md-4">@include('committee::partials/person-card')</div>@empty<div class="col-md-12"><p class="cm-card cm-empty">{{ __('committee::committee.ux.no_members') }}</p></div>@endforelse
</div><section class="box"><h2>{{ __('committee::committee.orders') }}</h2>@forelse($orders as $order)<div class="cm-order-row"><div><strong>{{ __('committee::committee.options.'.$order['kind']) }}</strong><p class="cm-muted">{{ $order['memo_no'] }} · {{ $display::date($order['issued_on']) }} · {{ $order['authority'] }}</p></div>@if($order['file_url'])<a class="btn btn-default" href="{{ $order['file_url'] }}">{{ __('committee::committee.ux.view_order') }}</a>@endif</div>@empty<p>{{ __('committee::committee.empty') }}</p>@endforelse</section></div>
<aside class="col-md-3"><section class="box"><h2>{{ __('committee::committee.ux.offices_served') }}</h2><ul class="list-unstyled">@foreach($coverage as $scope)@if(!$scope->effective_to || $scope->effective_to >= $date->toDateString())<li>{{ $scope->scope_label_snapshot }}</li>@endif@endforeach</ul><p>{{ $c->terms_of_reference }}</p></section>
<section class="box"><h2>{{ __('committee::committee.ux.recent') }}</h2>@foreach($history->take(3) as $entry)<p><small>{{ $entry['date'] }}</small><br>{{ $entry['sentence'] }}</p>@endforeach<a class="btn btn-default" href="{{ route('committee.history',$c->id) }}">{{ __('committee::committee.ux.full_history') }}</a></section></aside></div>
@foreach($tabs as $tab)<a class="btn btn-default" href="{{ $tab['url'] }}">{{ $tab['label'] }}</a>@endforeach
@stop
