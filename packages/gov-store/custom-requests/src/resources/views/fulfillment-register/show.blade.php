@extends('layouts/default')

@section('title', __('requestlabels::requests.record_title', ['number' => $serviceRequest->request_number]))

{{--
    Issue record (board 11): the storekeeper's return task in their words, the goods issue lines with item
    names (not class names), document links and a dated history with full names.
    Expects the data from FulfillmentRegisterController::show.
--}}
@php
    $r = 'requestlabels::requests.';
    $req = $serviceRequest;
    $requesterName = $presenter->name($req->requested_by);
    $return = $req->events->where('event_type', 'return_requested')->sortByDesc('id')->first();
    $lastIssued = $presenter->lastEventAt($req, ['item_issued', 'issued']) ?? $req->closed_at;
@endphp

@section('content')
<div class="cr" data-cr-record data-request-number="{{ $req->request_number }}" data-status-key="{{ $req->statusKey() }}">
    <x-gs::page-header :title="__($r.'record_title', ['number' => $req->request_number])">
        <x-slot:meta>
            <div class="cr-toolbar">
                <x-gs::status-badge :status="$req->statusKey()" />
                <span class="cr-meta">{{ __($r.'for_person', ['name' => $requesterName]) }}@if ($lastIssued) · {{ __($r.'record_issued', ['date' => $presenter->dateTime($lastIssued)]) }}@endif</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <x-gs::button tone="light" outline icon="fa-print" data-cr-print>{{ __($r.'print') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    @include('govstore::partials.errors')

    @if ($req->return_requested_at)
        <x-gs::alert tone="warning">
            @if ($req->return_document_id)
                <p class="cr-flush">{{ __($r.'return_task_drafted', ['number' => $req->returnDocument?->document_number ?? '']) }}</p>
                <x-gs::button :href="route('storeops.documents.workspace', ['type' => 'receipt', 'id' => $req->return_document_id])" size="sm" icon="fa-file-import">{{ __($r.'open_return_receipt') }}</x-gs::button>
            @else
                <p class="cr-flush">{{ __($r.'return_task', ['name' => $requesterName, 'reason' => $return->details['reason'] ?? '']) }}</p>
                @if ($canDraftReturn)
                    <form method="POST" action="{{ route('gov.requests.fulfillment_register.return', $req->id) }}" class="cr-inline-form">
                        @csrf
                        <x-gs::button type="submit" size="sm" icon="fa-file-import">{{ __($r.'prepare_return') }}</x-gs::button>
                    </form>
                @endif
            @endif
        </x-gs::alert>
    @endif

    <div class="cr-layout">
        <div class="cr-layout__main cr-stack">
            <x-gs::box :title="__($r.'lines_title')" icon="fas fa-list">
                <x-gs::table :caption="__($r.'lines_caption')" class="cr-cards-sm">
                    <thead><tr><th scope="col">{{ __($r.'col_item') }}</th><th scope="col" class="gs-num">{{ __($r.'col_asked') }}</th><th scope="col" class="gs-num">{{ __($r.'col_approved') }}</th><th scope="col" class="gs-num">{{ __($r.'col_issued') }}</th><th scope="col">{{ __($r.'col_status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($req->items as $line)
                            <tr data-line-id="{{ $line->id }}">
                                <td data-label="{{ __($r.'col_item') }}"><strong>{{ $presenter->lineName($line) }}</strong> <span class="cr-meta">{{ $presenter->typeLabel($line->fulfilled_type ?: $line->requested_type) }}</span></td>
                                <td class="gs-num" data-label="{{ __($r.'col_asked') }}">{{ $line->requested_qty }}</td>
                                <td class="gs-num" data-label="{{ __($r.'col_approved') }}">{{ $line->approved_qty }}</td>
                                <td class="gs-num" data-label="{{ __($r.'col_issued') }}">{{ $line->issued_qty }}</td>
                                <td data-label="{{ __($r.'col_status') }}"><x-gs::badge :tone="$presenter->lineTone($line)" outline>{{ $presenter->lineLabel($line, $req) }}</x-gs::badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-gs::table>
            </x-gs::box>

            <x-gs::box :title="__($r.'documents_title')" icon="fas fa-file-invoice">
                @if ($goodsIssues->isEmpty())
                    <p class="cr-muted cr-flush">{{ __($r.'documents_none') }}</p>
                @else
                    <x-gs::table :caption="__($r.'documents_caption')" class="cr-cards-sm">
                        <thead><tr><th scope="col">{{ __($r.'col_document') }}</th><th scope="col">{{ __($r.'col_item') }}</th><th scope="col" class="gs-num">{{ __($r.'col_quantity') }}</th><th scope="col">{{ __($r.'col_issued_by') }}</th></tr></thead>
                        <tbody>
                            @foreach ($goodsIssues as $issue)
                                @foreach ($issue->items as $issueItem)
                                    @php $key = \GovStore\CustomRequests\Http\Controllers\FulfillmentRegisterController::morphKey($issueItem->stockable_type); @endphp
                                    <tr>
                                        <td data-label="{{ __($r.'col_document') }}">
                                            @if (isset($documentLinks[$issue->issue_no]))<a href="{{ $documentLinks[$issue->issue_no] }}" class="cr-mono">{{ $issue->issue_no }}</a>@else<span class="cr-mono">{{ \Illuminate\Support\Str::limit($issue->issue_no, 18) }}</span>@endif
                                        </td>
                                        <td data-label="{{ __($r.'col_item') }}">{{ $presenter->itemName($key, (int) $issueItem->stockable_id) }} <span class="cr-meta">{{ $presenter->typeLabel($key) }}</span></td>
                                        <td class="gs-num" data-label="{{ __($r.'col_quantity') }}">{{ $issueItem->quantity }}</td>
                                        <td data-label="{{ __($r.'col_issued_by') }}">{{ $presenter->name($issue->created_by) }} <span class="cr-meta">{{ $presenter->dateTime($issue->created_at) }}</span></td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </x-gs::table>
                @endif
            </x-gs::box>
        </div>

        <aside class="cr-layout__aside cr-stack">
            <x-gs::box :title="__($r.'request_box')" icon="fas fa-file-lines">
                <dl class="cr-facts">
                    <div><dt>{{ __($r.'about_for') }}</dt><dd>{{ $req->purpose }}</dd></div>
                    <div><dt>{{ __($r.'about_where') }}</dt><dd>{{ $req->deliveryLocation?->name ?? __($r.'collect_from_store') }}</dd></div>
                </dl>
            </x-gs::box>
            @include('govstore::partials.history')
        </aside>
    </div>
</div>
@endsection
