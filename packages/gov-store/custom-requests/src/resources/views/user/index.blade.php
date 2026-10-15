@extends('layouts/default')

@section('title', __('requestlabels::requests.my_title'))

{{--
    My requests (board 5 and 5b, F1/F3/F8/F14): tabs In progress · Needs you · Finished, one status and the
    next step per request, receipt confirmed in the row, and dated updates that link to the request.
    Rows are cards, so phones never scroll sideways. Expects the data from GovRequestController::index.
--}}
@php $r = 'requestlabels::requests.'; @endphp

@section('content')
<div class="cr" data-cr-my-requests>
    <x-gs::page-header :title="__($r.'my_title')" :subtitle="__($r.'my_subtitle', ['office' => $office])">
        <x-slot:actions>
            <x-gs::button :href="route('gov.requests.catalog')" icon="fa-plus">{{ __($r.'nav_request_items') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    @include('govstore::partials.errors')

    @if ($counts['needs_you'] > 0 && $tab !== 'needs_you')
        <x-gs::alert tone="info">
            {{ trans_choice($r.'my_needs_banner', $counts['needs_you'], ['count' => $counts['needs_you']]) }}
            <a href="{{ route('gov.requests.user.index', ['tab' => 'needs_you']) }}">{{ __($r.'my_show_it') }}</a>
        </x-gs::alert>
    @endif

    @php
        $tabs = [];
        foreach (['active', 'needs_you', 'finished'] as $key) {
            $tabs[] = ['key' => $key, 'label' => __($r.'tab_'.$key), 'count' => $counts[$key], 'href' => route('gov.requests.user.index', ['tab' => $key])];
        }
    @endphp
    <x-gs::status-tabs :label="__($r.'tabs_label')" :active="$tab" :tabs="$tabs" />

    <div class="cr-layout">
        <section class="cr-layout__main" aria-label="{{ __($r.'my_list_label') }}">
            @forelse ($requests as $req)
                @php
                    $status = $req->statusKey();
                    $sent = $req->submitted_at ?? $req->created_at;
                    $canWithdraw = $req->office_id && in_array($req->approval_status, ['pending_primary', 'pending_final'], true) && ! $req->decided_by && ! $req->primary_decided_by;
                @endphp
                <article class="gs-card cr-request" id="request-{{ $req->id }}" data-request-number="{{ $req->request_number }}" data-status-key="{{ $status }}">
                    <div class="gs-card__body cr-stack">
                        <div class="cr-request__head">
                            <a class="cr-request__number" href="{{ route('gov.requests.user.show', $req->id) }}" aria-label="{{ __($r.'my_open', ['number' => $req->request_number]) }}">{{ $req->request_number }}</a>
                            <x-gs::status-badge :status="$status" />
                        </div>
                        <p class="cr-request__items cr-flush">{{ $presenter->itemsSummary($req) }} <span class="cr-muted" data-cr-purpose>· “{{ \Illuminate\Support\Str::limit($req->purpose, 90) }}”</span></p>
                        <p class="cr-flush">{{ $presenter->nextStep($req) }}</p>
                        <p class="cr-meta cr-flush">
                            {{ $sent->isToday() ? __($r.'meta_sent_today', ['time' => $sent->format('H:i')]) : __($r.'meta_sent', ['date' => $presenter->date($sent)]) }}
                            @if ($req->approved_at && $req->approved_by && $status === 'request_approved')
                                · {{ __($r.'meta_approved_by', ['date' => $presenter->date($req->approved_at), 'name' => $presenter->name($req->approved_by)]) }}
                            @endif
                        </p>
                    </div>
                    @if ($status === 'request_issued' || $canWithdraw || in_array($status, ['request_rejected', 'request_closed_short'], true))
                        <div class="gs-card__actions cr-toolbar">
                            @if ($status === 'request_issued')
                                <form method="POST" action="{{ route('gov.requests.receive', $req->id) }}" class="cr-inline-form">
                                    @csrf
                                    <x-gs::button type="submit" icon="fa-check" aria-label="{{ __($r.'receive_button_label', ['number' => $req->request_number]) }}">{{ __($r.'receive_button') }}</x-gs::button>
                                </form>
                            @endif
                            @if ($canWithdraw)
                                <details class="cr-confirm">
                                    <summary class="gs-btn gs-btn--light gs-btn--outline" aria-label="{{ __($r.'withdraw_label', ['number' => $req->request_number]) }}">{{ __($r.'withdraw') }}</summary>
                                    <div class="cr-confirm__panel cr-stack">
                                        <strong>{{ __($r.'withdraw_title', ['number' => $req->request_number]) }}</strong>
                                        <p class="cr-flush">{{ __($r.'withdraw_body') }}</p>
                                        <form method="POST" action="{{ route('gov.requests.withdraw', $req->id) }}" class="cr-inline-form">
                                            @csrf
                                            <x-gs::button type="submit" tone="danger">{{ __($r.'withdraw_confirm') }}</x-gs::button>
                                        </form>
                                    </div>
                                </details>
                            @endif
                            @if (in_array($status, ['request_rejected', 'request_closed_short'], true))
                                <form method="POST" action="{{ route('gov.requests.again', $req->id) }}" class="cr-inline-form">
                                    @csrf
                                    <x-gs::button type="submit" tone="light" outline icon="fa-rotate-right" aria-label="{{ __($r.'ask_again_label', ['number' => $req->request_number]) }}">{{ __($r.'ask_again') }}</x-gs::button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <x-gs::empty-state icon="fa-clipboard-list" :title="__($r.'my_empty_'.$tab)">
                    {{ __($r.'my_empty_body') }}
                    <x-slot:actions><x-gs::button :href="route('gov.requests.catalog')">{{ __($r.'nav_request_items') }}</x-gs::button></x-slot:actions>
                </x-gs::empty-state>
            @endforelse
        </section>

        <aside class="cr-layout__aside">
            <x-gs::box :title="__($r.'notices_title')" icon="fas fa-bell" id="updates">
                @if ($updates->isEmpty())
                    <p class="cr-muted cr-flush">{{ __($r.'notices_none') }}</p>
                @else
                    <ul class="cr-feed">
                        @foreach ($updates as $update)
                            <li @class(['cr-feed__item', 'cr-feed__item--new' => $update['unread']])>
                                <div>
                                    <a href="{{ $update['href'] }}">{{ $update['text'] }}</a>
                                    @if ($update['unread'])<x-gs::badge tone="primary" outline>{{ __($r.'notices_new') }}</x-gs::badge>@endif
                                    <time class="cr-meta" title="{{ $update['when'] }}">{{ $update['at'] }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-gs::box>
        </aside>
    </div>
</div>
@endsection
