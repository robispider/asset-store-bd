@extends('layouts/default')
@section('title', __('experiments::ui.dataset'))
@section('content')
<div class="box box-primary"><div class="box-header with-border"><h2 class="box-title">{{ $run->label }}</h2></div><div class="box-body">
    @foreach ($errors->all() as $error)<div class="alert alert-danger">{{ $error }}</div>@endforeach
    <p>{{ __('experiments::ui.status') }}: <strong id="experiment-status">{{ $run->status }}</strong> — {{ __('experiments::ui.phase') }}: <span id="experiment-phase">{{ $run->phase }}</span></p>
    @if ($run->error)<div class="alert alert-danger">{{ $run->error }}</div>@endif
    @if (!$run->wiped_at)<a class="btn btn-default" href="{{ route('gov.experiments.accounts', $run) }}">{{ __('experiments::ui.accounts') }}</a><p class="help-block">{{ __('experiments::ui.accounts_help') }}</p>@endif
    @if (!$run->wiped_at)<p class="help-block">{{ __('experiments::ui.office_inventory_help') }}</p>@endif
    @if (in_array($run->status, ['pending', 'running', 'wipe_pending', 'wiping']))
        <p class="help-block">{{ __('experiments::ui.recover_help') }}</p>
        <form method="POST" action="{{ route('gov.experiments.recover', $run) }}">@csrf<button class="btn btn-default">{{ __('experiments::ui.recover') }}</button></form>
    @endif
    @if ($run->wiped_at && $run->error)<form method="POST" action="{{ route('gov.experiments.cleanup', $run) }}">@csrf<button class="btn btn-default">{{ __('experiments::ui.cleanup') }}</button></form>@endif
    @if ($run->status === 'failed' && $run->phase !== 'wipe')<form style="display:inline" method="POST" action="{{ route('gov.experiments.resume', $run) }}">@csrf<button class="btn btn-primary">{{ __('experiments::ui.resume') }}</button></form>@endif
    @if (!in_array($run->status, ['pending', 'running', 'wipe_pending', 'wiping']))
        @if (!$run->wiped_at)<a class="btn btn-danger" href="{{ route('gov.experiments.preview', [$run, 'scope' => 'dataset']) }}">{{ __('experiments::ui.wipe_dataset') }}</a>@endif
        @if (config('govstore-experiments.database_reset_enabled'))<a class="btn btn-danger" href="{{ route('gov.experiments.preview', [$run, 'scope' => 'database']) }}">{{ __('experiments::ui.reset_database') }}</a>@endif
    @endif
</div></div>
@if (isset($run->report['verification']))
<div class="box"><div class="box-header"><h2 class="box-title">{{ __('experiments::ui.verification') }}</h2></div><div class="box-body">
    @foreach ($run->report['verification']['checks'] as $name => $passed)<p><i class="fa {{ $passed ? 'fa-check text-green' : 'fa-times text-red' }}"></i> {{ __('experiments::ui.check_'.$name) }}</p>@endforeach
    <details><summary>{{ __('experiments::ui.limitations') }}</summary><ul>@foreach ($run->report['verification']['limitations'] as $key)<li>{{ __('experiments::ui.'.$key) }}</li>@endforeach</ul></details>
</div></div>
@endif
@include('experiments::counts', ['counts' => $counts])
@endsection
@section('moar_scripts')
@if (in_array($run->status, ['pending', 'running', 'wipe_pending', 'wiping']))
<script>
(function () {
    var timer = setInterval(function () {
        fetch(@json(route('gov.experiments.status', $run)), {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (response) { if (!response.ok) throw new Error('status'); return response.json(); })
            .then(function (data) {
                document.getElementById('experiment-status').textContent = data.status;
                document.getElementById('experiment-phase').textContent = data.phase || '';
                if (['pending', 'running', 'wipe_pending', 'wiping'].indexOf(data.status) < 0) { clearInterval(timer); location.reload(); }
            }).catch(function () { clearInterval(timer); });
    }, 5000);
})();
</script>
@endif
@endsection
