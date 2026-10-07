@extends('committee::layout')
@section('committee-content')
<div class="cm-toolbar cm-no-print"><h1>{{ __('committee::committee.print') }}</h1><button type="button" class="btn btn-default cm-print"><i class="fa fa-print" aria-hidden="true"></i> {{ __('committee::committee.print') }}</button></div>
@if($c->status === 'DRAFT')<p class="alert alert-warning">{{ __('committee::committee.ux.draft_print') }}</p>@endif
<p>{{ __('committee::committee.as_of') }}: {{ $display::date($date->toDateString()) }}</p>
@include('committee::partials/paper')
<section class="box"><h2>{{ __('committee::committee.orders') }}</h2>@foreach($orders as $order)<p>{{ __('committee::committee.options.'.$order['kind']) }} · {{ $order['memo_no'] }} · {{ $display::date($order['issued_on']) }} · {{ $order['authority'] }}</p>@endforeach</section>
@stop
