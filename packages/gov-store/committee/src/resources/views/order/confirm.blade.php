@extends('committee::layout')
@section('committee-content')
@include('committee::partials/stepper',['step'=>4])
@include('committee::partials/reconstitution-diff')
<header class="cm-header"><h1>{{ __('committee::committee.ux.compare_paper') }}</h1><p class="cm-muted">{{ __('committee::committee.ux.compare_help') }}</p></header>
<div class="row"><div class="col-md-8">@include('committee::partials/paper')</div>
<aside class="col-md-4">@include('committee::partials/rules-panel')<section class="box"><h2>{{ __('committee::committee.ux.after_confirm') }}</h2><p>{{ __('committee::committee.ux.activation_effect') }}</p>@if($c->supersedes_id)<p>{{ __('committee::committee.ux.old_term_ends') }}</p>@endif @include('committee::partials/order-summary')</section>
<form class="cm-command" method="post" action="{{ route('committee.command.activate',$c->id) }}">@csrf<input type="hidden" name="order_id" value="{{ $workingOrder['id'] ?? '' }}">@include('committee::acknowledgements')<p><label><input type="checkbox" name="matches_paper" value="1" required> {{ __('committee::committee.ux.matches_paper') }}</label></p><x-gov-action ability="committee.activate" type="submit" :locked="!$workingOrder || collect($health->issues)->contains('severity','BLOCK')" class="btn btn-success btn-block">{{ __('committee::committee.ux.make_official') }}</x-gov-action></form><a class="btn btn-default btn-block" href="{{ route('committee.show',['committee'=>$c->id,'step'=>3]) }}">{{ __('committee::committee.ux.back_fix') }}</a></aside></div>
@stop
