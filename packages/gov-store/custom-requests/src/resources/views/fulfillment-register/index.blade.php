@extends('layouts/default')

@section('title', __('requestlabels::requests.register_title'))

{{--
    Issue register (board 11, F13): filters, pages of 25, the items issued by name, document links and
    one status per request. Expects the data from FulfillmentRegisterController::index.
--}}
@php
    $r = 'requestlabels::requests.';
    $active = collect([$filters['q'] ?? null, ($show !== 'all') ? $show : null, $filters['from'] ?? null, $filters['to'] ?? null])->filter()->count();
@endphp

@section('content')
<div class="cr" data-cr-register>
    <x-gs::page-header :title="__($r.'register_title')" :subtitle="__($r.'register_subtitle')" />

    @include('govstore::partials.errors')

    <x-gs::filter-bar :action="route('gov.requests.fulfillment_register.index')" :reset-href="route('gov.requests.fulfillment_register.index')" :active="$active">
        <div class="cr-filter">
            <label for="cr-reg-q" class="cr-label">{{ __($r.'reg_search') }}</label>
            <input type="search" id="cr-reg-q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __($r.'find_placeholder') }}" maxlength="100">
        </div>
        <div class="cr-filter">
            <label for="cr-reg-show" class="cr-label">{{ __($r.'reg_show') }}</label>
            <select id="cr-reg-show" name="show" class="form-control">
                @foreach (['all', 'issued', 'closed', 'return'] as $option)
                    <option value="{{ $option }}" @selected($show === $option)>{{ __($r.'reg_show_'.$option) }}</option>
                @endforeach
            </select>
        </div>
        <div class="cr-filter">
            <label for="cr-reg-from" class="cr-label">{{ __($r.'reg_from') }}</label>
            <input type="date" id="cr-reg-from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
        </div>
        <div class="cr-filter">
            <label for="cr-reg-to" class="cr-label">{{ __($r.'reg_to') }}</label>
            <input type="date" id="cr-reg-to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
        </div>
    </x-gs::filter-bar>

    @if ($completedRequests->isEmpty())
        <x-gs::empty-state icon="fa-book" :title="$active ? __($r.'reg_empty') : __($r.'reg_empty_all')" />
    @else
        <x-gs::box>
            <p class="cr-meta cr-flush">{{ __($r.'reg_count', ['from' => $completedRequests->firstItem(), 'to' => $completedRequests->lastItem(), 'total' => $completedRequests->total()]) }}</p>
            <x-gs::table :caption="__($r.'reg_caption')" class="cr-cards-sm">
                <thead>
                    <tr>
                        <th scope="col">{{ __($r.'col_request') }}</th>
                        <th scope="col">{{ __($r.'col_for') }}</th>
                        <th scope="col">{{ __($r.'col_items_issued') }}</th>
                        <th scope="col">{{ __($r.'col_documents') }}</th>
                        <th scope="col">{{ __($r.'col_status') }}</th>
                        <th scope="col">{{ __($r.'col_last') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($completedRequests as $req)
                        @php $issued = $req->items->where('issued_qty', '>', 0); @endphp
                        <tr data-request-number="{{ $req->request_number }}" data-status-key="{{ $req->statusKey() }}">
                            <td data-label="{{ __($r.'col_request') }}"><a href="{{ route('gov.requests.fulfillment_register.show', $req->id) }}">{{ $req->request_number }}</a></td>
                            <td data-label="{{ __($r.'col_for') }}">{{ $presenter->name($req->requested_by) }}</td>
                            <td data-label="{{ __($r.'col_items_issued') }}">
                                {{ $issued->isEmpty() ? __($r.'dash') : $issued->map(fn ($l) => $l->issued_qty < $l->approved_qty
                                    ? __($r.'partly_line', ['item' => $presenter->lineName($l), 'issued' => $l->issued_qty, 'approved' => $l->approved_qty])
                                    : __($r.'item_qty', ['item' => $presenter->lineName($l), 'qty' => $l->issued_qty]))->implode(', ') }}
                            </td>
                            <td data-label="{{ __($r.'col_documents') }}">
                                @forelse ($documents->get($req->id, collect()) as $document)
                                    @if (isset($documentLinks[$document->issue_no]))<a href="{{ $documentLinks[$document->issue_no] }}" class="cr-mono">{{ $document->issue_no }}</a>@else<span class="cr-mono">{{ \Illuminate\Support\Str::limit($document->issue_no, 18) }}</span>@endif@if (! $loop->last), @endif
                                @empty
                                    {{ __($r.'dash') }}
                                @endforelse
                            </td>
                            <td data-label="{{ __($r.'col_status') }}"><x-gs::status-badge :status="$req->statusKey()" /></td>
                            <td data-label="{{ __($r.'col_last') }}">{{ $presenter->dateTime($presenter->lastEventAt($req, ['item_issued', 'issued', 'closed']) ?? $req->closed_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-gs::table>
            <div class="cr-pages">{{ $completedRequests->links() }}</div>
        </x-gs::box>
    @endif
</div>
@endsection
