@extends('layouts/default')
@section('title', __('govtracking::general.report_title'))
@section('content')
<div class="gs-tracking-monitor">
    <div class="hidden-print">@include('govtracking::monitor.tabs')<button type="button" class="btn btn-default" data-tracking-print>{{ __('govtracking::general.monitor.print') }}</button></div>
    <x-gs::document :title="$initiative->title">
        <h2>{{ __('govtracking::general.monitor.delivered') }}: {{ $monitor['delivered']===null ? '—' : number_format($monitor['delivered']*100,1).'%' }}</h2>
        <p>{{ __('govtracking::general.monitor.programme_required') }}</p>
        <x-gs::table><thead><tr>@foreach(['item','allocated','received','delivered','economic_code'] as $heading)<th>{{ __('govtracking::general.monitor.'.$heading) }}</th>@endforeach</tr></thead><tbody>
        @forelse($monitor['codes'] as $code)@if($code['status']==='ACTIVE')@foreach($code['targets'] as $target)<tr><td>{{ $target['name'] }} <small>{{ $code['code'] }}</small></td><td>{{ $target['planned'] }}</td><td>{{ $target['received'] }}</td><td>{{ $target['fulfilment']===null ? '—' : number_format($target['fulfilment']*100,1).'%' }}</td><td>{{ $target['economic_code'] ?: '—' }}</td></tr>@endforeach @endif @empty<tr><td colspan="5">{{ __('govtracking::general.monitor.no_active') }}</td></tr>@endforelse
        </tbody></x-gs::table>
        <h2>{{ __('govtracking::general.monitor.receipts') }}</h2>
        @if(!$monitor['value_complete'])<p>{{ __('govtracking::general.monitor.incomplete_receipts') }}</p>@endif
        @include('govtracking::monitor.receipts',['receipts'=>$monitor['receipts']])
    </x-gs::document>
</div>
@endsection
