@extends('layouts/default')

@section('title', __('requestlabels::requests.sent_title_plain'))

{{--
    Sent: the end of the request flow. The request numbers, who has each request now, progress, and
    what to do next. Requests sent together from one draft are listed together.
    Expects $requests (one submission_batch, the requester's own) and $presenter.
--}}
@php
    $r = 'requestlabels::requests.';
    $first = $requests->first();
@endphp

@section('content')
<div class="cr cr-sent" data-cr-sent>
    <header class="cr-sent__hero">
        <span class="cr-sent__icon" aria-hidden="true"><i class="fas fa-check"></i></span>
        <div>
            <h1 class="cr-sent__title">{{ trans_choice($r.'sent_title', $requests->count(), ['count' => $requests->count()]) }}</h1>
            <p class="cr-muted cr-flush">“{{ $first->purpose }}” · {{ $presenter->dateTime($first->submitted_at ?? $first->created_at) }}</p>
        </div>
    </header>

    @foreach ($requests as $req)
        @php
            $status = $req->statusKey();
        @endphp
        <section class="cr-panel cr-sent__request" aria-labelledby="cr-sent-{{ $req->id }}" data-request-number="{{ $req->request_number }}" data-status-key="{{ $status }}">
            <div class="cr-sent__head">
                <h2 class="cr-sent__number" id="cr-sent-{{ $req->id }}"><a href="{{ route('gov.requests.user.show', $req->id) }}" class="cr-mono">{{ $req->request_number }}</a></h2>
                <x-gs::status-badge :status="$status" />
            </div>
            <p class="cr-flush">{{ $presenter->itemsSummary($req, 5) }}</p>
            <x-gs::stepper :steps="$presenter->steps($req)" :label="__($r.'show_progress')" />
            <p class="cr-sent__next cr-flush"><i class="fas fa-clock" aria-hidden="true"></i> <span>{{ $presenter->nextStep($req) }}</span></p>
        </section>
    @endforeach

    @if ($requests->count() > 1)
        <p class="cr-meta cr-flush">{{ __($r.'sent_together') }}</p>
    @endif

    <div class="cr-toolbar">
        <x-gs::button :href="route('gov.requests.user.index')" icon="fa-clipboard-list">{{ __($r.'sent_track') }}</x-gs::button>
        <x-gs::button :href="route('gov.requests.catalog')" tone="light" outline icon="fa-store">{{ __($r.'sent_more') }}</x-gs::button>
    </div>
</div>
@endsection
