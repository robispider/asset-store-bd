@extends('layouts/default')
@section('title', __('govonboard::onboard.my_title'))
@section('content')
<div class="box box-primary">
    <div class="box-header"><h1 class="box-title">{{ __('govonboard::onboard.my_title') }}</h1></div>
    <div class="box-body">
        @if($item)
            <p>{{ __('govonboard::onboard.status') }}: <strong>{{ __('govonboard::onboard.'.$item->status) }}</strong></p>
            @if($item->membership?->location)<p>{{ __('govonboard::onboard.office') }}: {{ $item->membership->location->name }}</p>@endif
        @else<p>{{ __('govonboard::onboard.no_record') }}</p>@endif
        <p>{{ __('govonboard::onboard.self_help') }}</p>
        @include('govonboard::queue.notices')
    </div>
</div>
@endsection
