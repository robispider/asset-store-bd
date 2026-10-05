@extends('committee::layout')
@section('committee-content')
<header class="cm-header"><h1>{{ __('committee::committee.ux.desk') }}</h1><p class="cm-muted">{{ $officeName }} · {{ __('committee::committee.ux.today') }} · {{ $display::date($date->toDateString()) }}</p></header>
<div class="row">
    <div class="col-md-4"><a class="cm-card cm-start cm-start-primary" href="{{ route('committee.new') }}"><strong><i class="fa fa-file-text-o" aria-hidden="true"></i> {{ __('committee::committee.ux.new_order') }}</strong><p>{{ __('committee::committee.ux.new_order_help') }}</p></a></div>
    <div class="col-md-4"><a class="cm-card cm-start" href="{{ route('committee.transfers') }}"><strong><i class="fa fa-exchange" aria-hidden="true"></i> {{ __('committee::committee.ux.transferred') }}</strong><p>{{ __('committee::committee.ux.transfer_intro') }}</p></a></div>
    <div class="col-md-4"><form class="cm-card cm-start" action="{{ route('committee.registry') }}"><label for="cm-desk-find"><i class="fa fa-search" aria-hidden="true"></i> {{ __('committee::committee.ux.find') }}</label><input type="search" name="q" id="cm-desk-find" class="form-control" placeholder="{{ __('committee::committee.search_placeholder') }}"><button class="btn btn-default" type="submit">{{ __('committee::committee.search') }}</button></form></div>
</div>
<div class="row"><div class="col-md-8">
    <section aria-labelledby="cm-attention-title"><h2 id="cm-attention-title">{{ __('committee::committee.ux.attention') }} <small>· {{ $display::digits(count($desk['attention'])) }}</small></h2>
    @forelse($desk['attention'] as $item)
    <article class="cm-card cm-attention {{ $item['status'] === 'INOPERABLE' ? 'cm-block' : '' }}"><div>@include('committee::partials/status',['status'=>$item['status']])<h3>{{ $item['title'] }}</h3><p>{{ $item['message'] }}</p></div><div class="cm-actions"><a class="btn btn-primary" href="{{ $item['url'] }}">{{ $item['action'] }}</a>
        @if(isset($item['dismiss']))<form class="cm-command" method="post" action="{{ route('committee.reminder.dismiss',$item['dismiss']) }}">@csrf<x-gov-action ability="committee.manage" type="submit" class="btn btn-default">{{ __('committee::committee.ux.let_end') }}</x-gov-action></form>@endif
    </div></article>@empty<div class="cm-card cm-readiness"><i class="fa fa-check" aria-hidden="true"></i> {{ __('committee::committee.ux.all_clear') }}</div>@endforelse
    </section>
    <section class="box"><h2>{{ __('committee::committee.ux.coverage') }}</h2><p class="cm-muted">{{ __('committee::committee.ux.coverage_help') }}</p><div class="table-responsive"><table class="table table-hover"><thead><tr><th>{{ __('committee::committee.ux.job') }}</th><th>{{ __('committee::committee.ux.responsible_committee') }}</th><th>{{ __('committee::committee.status') }}</th></tr></thead><tbody>
    @forelse($desk['coverage'] as $row)<tr><td>{{ $row['purpose'] }}</td><td>@if($row['committee'])<a href="{{ route('committee.show',$row['committee']->id) }}">{{ $display::text($row['committee']->nameBn,$row['committee']->nameEn) }}</a><br><small>{{ $display::date($row['committee']->effectiveTo) }}</small>@else{{ __('committee::committee.ux.'.($row['status'] === 'MISSING' ? 'missing' : 'multiple_cover')) }}@endif</td><td>@include('committee::partials/status',['status'=>$row['status']])</td></tr>@empty<tr><td colspan="3">{{ __('committee::committee.ux.select_office') }}</td></tr>@endforelse
    </tbody></table></div></section>
</div><aside class="col-md-4"><section class="box"><h2>{{ __('committee::committee.ux.recent') }}</h2>
    @forelse($desk['recent'] as $entry)<div class="cm-order-row"><div><small>{{ $entry['date'] }} · {{ $entry['actor'] }}</small><p>{{ $entry['sentence'] }}</p></div></div>@empty<p class="cm-muted">{{ __('committee::committee.empty') }}</p>@endforelse
</section></aside></div>
@stop
