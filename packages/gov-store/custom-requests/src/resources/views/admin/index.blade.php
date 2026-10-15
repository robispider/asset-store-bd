@extends('layouts/default')

@section('title', __('requestlabels::requests.approvals_title'))

{{--
    Approvals (board 7, F5): Waiting for you · Overdue · Decided by you, from the same queries as the menu
    badge. Each waiting request shows its stage, its age or escalation date and any stock shortfall.
    Expects the data from GovApprovalController::index.
--}}
@php
    $r = 'requestlabels::requests.';
    $days = (int) config('govstore-requests.escalation_weekdays', 3);
@endphp

@section('content')
<div class="cr" data-cr-approvals>
    <x-gs::page-header :title="__($r.'approvals_title')" :subtitle="__($r.'approvals_subtitle', ['office' => $office])" />

    @include('govstore::partials.errors')

    @if ($counts['overdue'] > 0 && $tab !== 'overdue')
        <x-gs::alert tone="warning">
            {{ trans_choice($r.'overdue_banner', $counts['overdue'], ['count' => $counts['overdue']]) }}
            <a href="{{ route('gov.requests.admin.index', ['tab' => 'overdue']) }}">{{ __($r.'overdue_show') }}</a>
        </x-gs::alert>
    @endif

    @php
        $tabs = [];
        foreach (['waiting', 'overdue', 'decided'] as $key) {
            $tabs[] = ['key' => $key, 'label' => __($r.'tab_'.$key), 'count' => $counts[$key], 'href' => route('gov.requests.admin.index', ['tab' => $key])];
        }
    @endphp
    <x-gs::status-tabs :label="__($r.'tabs_label')" :active="$tab" :tabs="$tabs" />

    @if ($tab === 'decided')
        @if ($processedRequests->isEmpty())
            <x-gs::empty-state icon="fa-clipboard-check" :title="__($r.'approvals_empty_decided')" />
        @else
            <x-gs::box>
                <x-gs::table :caption="__($r.'decided_caption')" class="cr-cards-sm">
                    <thead>
                        <tr>
                            <th scope="col">{{ __($r.'col_request') }}</th>
                            <th scope="col">{{ __($r.'col_from') }}</th>
                            <th scope="col">{{ __($r.'col_decision') }}</th>
                            <th scope="col">{{ __($r.'col_now') }}</th>
                            <th scope="col">{{ __($r.'col_decided') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($processedRequests as $req)
                            @php
                                $mine = $req->events->whereIn('event_type', ['pending_final', 'approved', 'partially_approved', 'rejected'])
                                    ->where('user_id', auth()->id())->sortByDesc('id')->first();
                                $decisions = collect($mine?->details['decisions'] ?? []);
                                $approvedLines = $decisions->where('status', 'approved');
                                $lines = $req->items->keyBy('id');
                                $decision = match (true) {
                                    ! $mine => null,
                                    $mine->event_type === 'rejected' || $approvedLines->isEmpty() => __($r.'decision_declined'),
                                    $approvedLines->count() < $decisions->count() => __($r.'decision_some', ['approved' => $approvedLines->count(), 'total' => $decisions->count()]),
                                    $approvedLines->contains(fn ($d, $id) => isset($lines[$id]) && (int) $d['qty'] < $lines[$id]->requested_qty) => __($r.'decision_fewer'),
                                    $mine->event_type === 'pending_final' => __($r.'decision_first'),
                                    default => __($r.'decision_as_asked'),
                                };
                            @endphp
                            <tr data-request-number="{{ $req->request_number }}" data-status-key="{{ $req->statusKey() }}">
                                <td data-label="{{ __($r.'col_request') }}"><a href="{{ route('gov.requests.admin.show', $req->id) }}" aria-label="{{ __($r.'view_request_label', ['number' => $req->request_number]) }}">{{ $req->request_number }}</a></td>
                                <td data-label="{{ __($r.'col_from') }}">{{ $presenter->name($req->requested_by) }}</td>
                                <td data-label="{{ __($r.'col_decision') }}">{{ $decision ?? __($r.'dash') }}</td>
                                <td data-label="{{ __($r.'col_now') }}"><x-gs::status-badge :status="$req->statusKey()" /></td>
                                <td data-label="{{ __($r.'col_decided') }}">{{ $presenter->dateTime($mine?->created_at ?? $req->updated_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-gs::table>
                <div class="cr-pages">{{ $processedRequests->links() }}</div>
            </x-gs::box>
        @endif
    @else
        @if ($pendingRequests->isEmpty())
            <x-gs::empty-state icon="fa-clipboard-check" :title="__($r.($tab === 'overdue' ? 'approvals_empty_overdue' : 'approvals_empty_waiting'))">
                @if ($tab === 'waiting'){{ __($r.'approvals_empty_waiting_body') }}@endif
            </x-gs::empty-state>
        @else
            <ul class="cr-requests" aria-label="{{ __($r.'approvals_list_label') }}">
                @foreach ($pendingRequests as $req)
                    @php
                        $sent = $req->submitted_at ?? $req->created_at;
                        $escalated = $req->events->where('event_type', 'escalated')->sortByDesc('id')->first();
                        $waitingDays = $sent->diffInWeekdays(now());
                        $stage = $req->approval_status === 'pending_final' ? 'stage_final' : ($req->resolved_policy === 'PRIMARY_AND_FINAL' ? 'stage_first' : 'stage_only');
                    @endphp
                    <li>
                        <article class="gs-card cr-request" data-request-number="{{ $req->request_number }}" data-status-key="{{ $req->statusKey() }}">
                            <div class="gs-card__body cr-stack">
                                <div class="cr-request__head">
                                    <a class="cr-request__number" href="{{ route('gov.requests.admin.show', $req->id) }}">{{ $req->request_number }}</a>
                                    <x-gs::badge tone="primary" outline>{{ __($r.$stage) }}</x-gs::badge>
                                    <span class="cr-meta">
                                        @if ($escalated || $waitingDays >= $days)
                                            <span class="cr-overdue"><i class="fas fa-clock" aria-hidden="true"></i> {{ trans_choice($r.'age_overdue', $waitingDays, ['count' => $waitingDays]) }}</span>
                                            @if ($escalated) · {{ __($r.'age_escalated', ['date' => $presenter->date($escalated->created_at)]) }}@endif
                                        @else
                                            {{ $sent->isToday() ? __($r.'age_today', ['time' => $sent->format('H:i')]) : __($r.'age_sent', ['date' => $presenter->date($sent)]) }}
                                            · {{ __($r.'age_escalates', ['date' => $presenter->date($presenter->escalationDate($req))]) }}
                                        @endif
                                    </span>
                                </div>
                                <p class="cr-request__items cr-flush">{{ $presenter->itemsSummary($req) }}</p>
                                <p class="cr-meta cr-flush">
                                    {{ __($r.'row_from', ['name' => $presenter->name($req->requested_by), 'purpose' => \Illuminate\Support\Str::limit($req->purpose, 80)]) }}
                                    @if ($req->approval_status === 'pending_final' && $req->primary_decided_by)
                                        · {{ __($r.'final_after', ['name' => $presenter->name($req->primary_decided_by), 'date' => $presenter->date($presenter->lastEventAt($req, ['pending_final']))]) }}
                                    @endif
                                    @isset($stock[$req->id])
                                        · <span class="cr-warn"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ $stock[$req->id] }}</span>
                                    @endisset
                                </p>
                            </div>
                            <div class="gs-card__actions cr-toolbar">
                                <x-gs::button :href="route('gov.requests.admin.show', $req->id)" icon="fa-gavel" aria-label="{{ __($r.'decide_on_label', ['number' => $req->request_number]) }}">{{ __($r.'decide_on') }}</x-gs::button>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
            <p class="cr-meta cr-flush">{{ __($r.'approvals_footnote', ['days' => $days]) }}</p>
        @endif
    @endif
</div>
@endsection
