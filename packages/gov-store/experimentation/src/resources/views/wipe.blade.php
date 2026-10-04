@extends('layouts/default')
@section('title', __('experiments::ui.wipe'))
@section('content')
<div class="box box-danger"><div class="box-header with-border"><h2 class="box-title">{{ $preview['scope'] === 'database' ? __('experiments::ui.reset_database') : __('experiments::ui.wipe_dataset') }}</h2></div><div class="box-body">
    @foreach ($errors->all() as $error)<div class="alert alert-danger">{{ $error }}</div>@endforeach
    <p>{{ __($preview['scope'] === 'database' ? 'experiments::ui.database_wipe_help' : 'experiments::ui.dataset_wipe_help') }}</p>
    @if ($preview['blockers'])<div class="alert alert-danger">{{ __('experiments::ui.blocked') }}<ul>@foreach ($preview['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></div>
    @else<form method="POST" action="{{ route('gov.experiments.wipe', $run) }}">@csrf
        <input type="hidden" name="scope" value="{{ $preview['scope'] }}"><input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}">
        <div class="form-group"><label for="confirmation">{{ __('experiments::ui.confirm', ['text' => $preview['confirmation']]) }}</label><input class="form-control" id="confirmation" name="confirmation" autocomplete="off" required></div>
        <button class="btn btn-danger" type="submit">{{ __('experiments::ui.wipe') }}</button>
    </form>@endif
</div></div>
@include('experiments::counts', ['counts' => $preview['counts']])
@endsection
