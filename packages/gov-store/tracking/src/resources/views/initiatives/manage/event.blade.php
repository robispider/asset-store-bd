{{-- One Manage-tab activity entry: a grouped goods receipt or a lifecycle change. --}}
@php
    $by = $event['by'] ?? __($m.'not_logged');
    $codeLabel = $short($event['code'] ?? null);
    $setupLabel = \Illuminate\Support\Facades\Lang::has($m.'events.'.$event['event']) ? __($m.'events.'.$event['event']) : null;
    $otherLabel = \Illuminate\Support\Facades\Lang::has('govtracking::general.monitor.events.'.$event['event'])
        ? __('govtracking::general.monitor.events.'.$event['event']) : \Illuminate\Support\Str::headline(strtolower($event['event']));
@endphp
<li class="gs-tracking-manage__event" data-kind="{{ $event['type'] }}">
    <i class="fas {{ $event['event'] === 'GRN_RECEIVED' ? 'fa-truck-ramp-box' : ($event['type'] === 'setup' ? 'fa-pen-to-square' : 'fa-circle-info') }}" aria-hidden="true"></i>
    <div>
        @if($event['event'] === 'GRN_RECEIVED')
            <strong>{{ $by }}</strong> {{ __($m.'received', ['units' => $num($event['units'])]) }} <span>{{ $codeLabel }}@if($event['grn']) · {{ $event['grn'] }}@endif</span>
            @if($event['lines'] > 1)<small>{{ trans_choice($m.'lines', $event['lines'], ['count' => $event['lines']]) }}</small>@endif
        @elseif($event['event'] === 'PROGRAMME_CREATED')
            <strong>{{ $setupLabel }}</strong>
        @elseif($event['event'] === 'CODE_ACTIVATED')
            <strong>{{ $codeLabel }}</strong> {{ $setupLabel }}@if($event['by']) <span>· {{ $event['by'] }}</span>@endif
        @elseif($setupLabel)
            <strong>{{ $by }}</strong> {{ $setupLabel }} <span>{{ $codeLabel }}</span>@if(! empty($event['detail']))<small>{{ $event['detail'] }}</small>@endif
        @else
            <strong>{{ $by }}</strong> {{ $otherLabel }} <span>{{ $codeLabel }}</span>
        @endif
        @if($event['derived'])<span class="gs-tracking-manage__derived"><i class="fas fa-database" aria-hidden="true"></i> {{ __($m.'from_record') }}</span>@endif
    </div>
    <time datetime="{{ \Carbon\Carbon::parse($event['at'])->toIso8601String() }}">{{ \Carbon\Carbon::parse($event['at'])->isoFormat('D MMM YYYY, HH:mm') }}</time>
</li>
