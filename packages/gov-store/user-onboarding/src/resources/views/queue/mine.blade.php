@extends('layouts/default')
@section('title', __('govonboard::onboard.my_title'))
@section('content')
<div class="govonboard-theme">
    <x-gs::box :title="__('govonboard::onboard.my_title')" icon="fas fa-user-check" tone="primary">
        @if($item)
            <p>{{ __('govonboard::onboard.status') }}: <x-gs::badge :tone="$item->status === 'COMPLETED' ? 'success' : ($item->status === 'CANCELLED' ? 'danger' : 'warning')">{{ __('govonboard::onboard.'.$item->status) }}</x-gs::badge></p>
            @if($item->membership?->location)<p>{{ __('govonboard::onboard.office') }}: {{ $item->membership->location->name }}</p>@endif
        @else<p>{{ __('govonboard::onboard.no_record') }}</p>@endif
        <p>{{ __('govonboard::onboard.self_help') }}</p>
        @include('govonboard::queue.notices')
    </x-gs::box>
</div>
@endsection
