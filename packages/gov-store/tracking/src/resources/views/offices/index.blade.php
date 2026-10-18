@extends('layouts/default')
@section('title', __('govtracking::general.monitor.offices'))
@section('content')
<div class="gs-tracking-monitor"><x-gs::page-header :title="__('govtracking::general.monitor.offices')" :subtitle="__('govtracking::general.monitor.programme_required')" />
    <x-gs::table data-monitor-sortable><thead><tr>@foreach(['office','programmes','codes','allocation_received','received','value','last_receipt','attention'] as $heading)<th><button type="button" data-monitor-sort="{{ $loop->index }}">{{ __('govtracking::general.monitor.'.$heading) }} ↕</button></th>@endforeach</tr></thead><tbody>
    @forelse($offices as $office)<tr><td><a href="{{ route('gov.tracking.offices.show',$office['id']) }}">{{ $office['name'] }}</a><small class="gs-tracking-block" lang="bn">{{ $office['name_bn'] }}</small></td><td>{{ $office['programme_count'] }}</td><td>{{ $office['code_count'] }}</td><td><x-gs::pace-bar :value="$office['share']===null ? null : $office['share']*100" /></td><td>{{ $office['received'] }}</td><td>{{ \GovStore\Tracking\Services\TrackingMonitorMetrics::money($office['value']) }}</td><td>{{ $office['last'] ? \Carbon\CarbonImmutable::parse($office['last'])->toDateString() : '—' }}</td><td>@if($office['no_area'])<span>{{ __('govtracking::general.monitor.no_area') }}</span>@endif @if($office['over'])<x-gs::status-badge status="over_allocation" />@elseif($office['allocated']>0 && $office['done']==0)<x-gs::status-badge status="waiting" />@endif</td></tr>@empty<tr><td colspan="8">{{ __('govtracking::general.monitor.no_offices') }}</td></tr>@endforelse
    </tbody></x-gs::table>
    @if($programmes->isNotEmpty())<section class="gs-tracking-panel"><h2>{{ __('govtracking::general.monitor.more_through_programmes') }}</h2>@foreach($programmes as $p)<p><a href="{{ route('gov.tracking.initiatives.show',$p['initiative']->id) }}">{{ $p['initiative']->title }}</a></p>@endforeach</section>@endif
</div>
@endsection
