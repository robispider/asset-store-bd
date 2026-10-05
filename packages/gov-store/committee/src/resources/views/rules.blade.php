@extends('committee::layout')
@section('committee-content')
<header class="cm-header"><h1>{{ __('committee::committee.ux.rules') }}</h1><p class="cm-muted">{{ __('committee::committee.ux.rules_intro') }}</p></header>
<div class="cm-actions"><a class="btn btn-default" href="{{ route('committee.types') }}">{{ __('committee::committee.ux.type_rules') }}</a><a class="btn btn-default" href="{{ route('committee.purposes') }}">{{ __('committee::committee.ux.jobs_rules') }}</a></div>
@include('committee::catalog')
@stop
