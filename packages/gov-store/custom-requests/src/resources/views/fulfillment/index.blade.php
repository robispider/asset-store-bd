@extends('layouts/default')

@section('title', __('requestlabels::requests.toissue_title'))

{{--
    To issue (board 9): a work queue. Ready · Partly issued · Returns to receive, needed-soonest first, then
    oldest. One row per request: who it is for, what, when it is needed (overdue rows are marked at the
    edge and in words), and the one action. Expects the data from GovFulfillmentController::index.
--}}
@php
    $r = 'requestlabels::requests.';
    $q = request('q');
    $today = now()->startOfDay();
    $tabs = [];
    foreach (['ready', 'partly', 'returns'] as $key) {
        $tabs[] = ['key' => $key, 'label' => __($r.'tab_'.$key), 'count' => $counts[$key], 'href' => route('gov.requests.fulfillment.index', ['tab' => $key])];
    }
@endphp

@section('content')
<div class="cr" data-cr-to-issue>
    <x-gs::page-header :title="__($r.'toissue_title')" :subtitle="__($r.'toissue_subtitle', ['office' => $office])">
        <x-slot:actions>
            <x-gs::button :href="route('gov.requests.fulfillment_register.index')" tone="light" outline icon="fa-book">{{ __($r.'register_title') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    @include('govstore::partials.errors')

    @if ($overdue > 0 && $tab !== 'returns')
        <x-gs::alert tone="warning" data-cr-overdue>{{ trans_choice($r.'toissue_attention', $overdue, ['count' => $overdue]) }}</x-gs::alert>
    @endif

    <div class="cr-queuebar">
        <x-gs::status-tabs :label="__($r.'tabs_label')" :active="$tab" :tabs="$tabs" />
        <form method="GET" action="{{ route('gov.requests.fulfillment.index') }}" class="cr-queuebar__search" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="cr-find" class="gs-sr-only">{{ __($r.'find_request') }}</label>
            <i class="fas fa-magnifying-glass cr-queuebar__icon" aria-hidden="true"></i>
            <input type="search" id="cr-find" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __($r.'find_placeholder') }}" maxlength="100">
            <x-gs::button type="submit" tone="light" outline>{{ __($r.'find_button') }}</x-gs::button>
        </form>
    </div>

    @if ($activeRequests->isEmpty())
        <x-gs::empty-state :icon="$tab === 'returns' ? 'fa-rotate-left' : 'fa-mug-hot'" :title="filled($q) ? __($r.'toissue_empty_search', ['q' => $q]) : __($r.'toissue_empty_'.$tab)" />
    @else
        <ul class="cr-queue" aria-label="{{ __($r.'toissue_list_label') }}">
            @foreach ($activeRequests as $req)
                @php
                    $requester = $presenter->name($req->requested_by);
                    $waitingDays = $req->approved_at ? (int) $req->approved_at->copy()->startOfDay()->diffInDays($today) : 0;
                    $approvedLines = $req->items->where('line_approval_status', 'approved');
                    $serial = $approvedLines->contains(fn ($l) => ($l->fulfilled_type ?: $l->requested_type) === 'asset_model' && $l->issued_qty < $l->approved_qty);
                    $due = $req->required_by_date?->copy()->startOfDay();
                    $urgency = $tab === 'returns' || ! $due ? 'none' : ($due->lt($today) ? 'overdue' : ($due->eq($today) ? 'today' : 'later'));
                    $return = $tab === 'returns' ? $req->events->where('event_type', 'return_requested')->sortByDesc('id')->first() : null;
                @endphp
                <li class="cr-queue__row cr-queue__row--{{ $urgency }}" data-request-number="{{ $req->request_number }}" data-status-key="{{ $req->statusKey() }}">
                    <div class="cr-queue__who">
                        <x-gs::avatar :name="$requester" size="md" />
                        <div class="cr-queue__id">
                            <strong class="cr-queue__name">{{ $requester }}</strong>
                            <span class="cr-queue__number">{{ $req->request_number }}</span>
                        </div>
                    </div>

                    <div class="cr-queue__what">
                        @if ($tab === 'returns')
                            <span class="cr-queue__items">{{ __($r.'return_items', ['items' => $presenter->itemsSummary($req, 3, 'issued_qty')]) }}</span>
                            @if ($return && filled($return->details['reason'] ?? null))
                                <q class="cr-queue__quote">{{ $return->details['reason'] }}</q>
                            @endif
                        @elseif ($tab === 'partly')
                            <span class="cr-queue__items">
                                {{ $approvedLines->map(fn ($l) => __($r.'partly_line', ['item' => $presenter->lineName($l), 'issued' => $l->issued_qty, 'approved' => $l->approved_qty]))->implode(' · ') }}
                            </span>
                            <x-gs::progress class="cr-queue__progress" size="sm" :value="$approvedLines->sum('issued_qty')" :max="max(1, $approvedLines->sum('approved_qty'))" :show-value="false"
                                :label="__($r.'issued_progress', ['issued' => $approvedLines->sum('issued_qty'), 'approved' => $approvedLines->sum('approved_qty')])" />
                        @else
                            <span class="cr-queue__items">{{ $presenter->itemsSummary($req, 3, 'approved_qty') }}</span>
                        @endif
                        @if ($tab !== 'returns')
                            <span class="cr-pills">
                                @if ($req->delivery_location_id)
                                    <span class="cr-pill"><i class="fas fa-truck" aria-hidden="true"></i> {{ __($r.'deliver_to', ['place' => $req->deliveryLocation?->name ?? '']) }}</span>
                                @else
                                    <span class="cr-pill"><i class="fas fa-store" aria-hidden="true"></i> {{ __($r.'collect_from_store') }}</span>
                                @endif
                                @if ($serial)<span class="cr-pill cr-pill--rule"><i class="fas fa-barcode" aria-hidden="true"></i> {{ __($r.'needs_serial') }}</span>@endif
                            </span>
                        @endif
                    </div>

                    <div class="cr-queue__when">
                        @if ($tab === 'returns')
                            @if ($return)<span class="cr-queue__due">{{ __($r.'return_asked', ['date' => $presenter->date($return->created_at)]) }}</span>@endif
                        @elseif ($urgency === 'overdue')
                            <x-gs::badge tone="danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ trans_choice($r.'due_overdue', (int) $due->diffInDays($today), ['count' => (int) $due->diffInDays($today)]) }}</x-gs::badge>
                        @elseif ($urgency === 'today')
                            <x-gs::badge tone="warning"><i class="fas fa-clock" aria-hidden="true"></i> {{ __($r.'due_today') }}</x-gs::badge>
                        @elseif ($urgency === 'later')
                            <span class="cr-queue__due"><i class="fas fa-calendar-day" aria-hidden="true"></i> {{ __($r.'needed_by', ['date' => $presenter->date($due)]) }}</span>
                        @else
                            <span class="cr-queue__due cr-muted">{{ __($r.'due_none') }}</span>
                        @endif
                        @if ($tab !== 'returns' && $req->approved_at)
                            <span class="cr-meta" title="{{ __($r.'meta_approved_by', ['date' => $presenter->date($req->approved_at), 'name' => $req->approved_by ? $presenter->name($req->approved_by) : __($r.'system')]) }}">
                                {{ trans_choice($r.'waiting_days', $waitingDays, ['count' => $waitingDays]) }}
                            </span>
                        @endif
                    </div>

                    <div class="cr-queue__act">
                        @if ($tab === 'returns')
                            <form method="POST" action="{{ route('gov.requests.fulfillment_register.return', $req->id) }}" class="cr-inline-form">
                                @csrf
                                <x-gs::button type="submit" icon="fa-file-import">{{ __($r.'prepare_return') }}</x-gs::button>
                            </form>
                            <x-gs::button :href="route('gov.requests.fulfillment_register.show', $req->id)" tone="light" outline
                                aria-label="{{ __($r.'view_request_label', ['number' => $req->request_number]) }}">{{ __($r.'view_request') }}</x-gs::button>
                        @else
                            <x-gs::button :href="route('gov.requests.fulfillment.show', $req->id)" icon="fa-dolly" aria-label="{{ __($r.'issue_button_label', ['number' => $req->request_number]) }}">
                                {{ __($r.($tab === 'partly' ? 'issue_rest' : 'issue_button')) }}
                            </x-gs::button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($tab !== 'returns')<p class="cr-meta cr-flush">{{ __($r.'toissue_sorted') }}</p>@endif
    @endif
</div>
@endsection
