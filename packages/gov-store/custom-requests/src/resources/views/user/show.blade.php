@extends('layouts/default')

@section('title', __('requestlabels::requests.show_title', ['number' => $serviceRequest->request_number, 'purpose' => $serviceRequest->purpose]))

{{--
    The request page (board 6, F1): one status, progress Sent → Approved → Issued → Received, what happens
    next and who, each line with asked/approved/issued and the approver's note, why withdrawing is not
    possible, the return dialog and a dated history. Only the requester sees it (GovRequestController::show).
--}}
@php
    $r = 'requestlabels::requests.';
    $req = $serviceRequest;
    $status = $req->statusKey();
    $sent = $req->submitted_at ?? $req->created_at;
    $returnable = $presenter->returnableLines($req);
    $canReturn = in_array($req->fulfillment_status, ['issued', 'closed'], true) && ! $req->return_requested_at && $returnable->isNotEmpty();
    $canWithdraw = in_array($req->approval_status, ['pending_primary', 'pending_final'], true) && ! $req->decided_by && ! $req->primary_decided_by;
    $decidedAt = $req->approved_at ?? ($req->decided_by ? $req->updated_at : null);
    $returnOpen = $errors->has('reason');
@endphp

@section('content')
@if(app(\GovStore\CustomRequests\Services\ApprovalRouting::class)->blocked($serviceRequest))
<div class="alert alert-warning" role="status">{{ __('requestlabels::requests.request_refused_approval_path') }}</div>
@endif
<div class="cr" data-cr-request data-request-number="{{ $req->request_number }}" data-status-key="{{ $status }}">
    <x-gs::page-header :title="__($r.'show_title', ['number' => $req->request_number, 'purpose' => $req->purpose])">
        <x-slot:meta>
            <div class="cr-toolbar">
                <x-gs::status-badge :status="$status" size="lg" />
                <span class="cr-meta">{{ __($r.'show_sent_by_you', ['date' => $presenter->dateTime($sent)]) }} · {{ $presenter->requestType($req->request_type) }}</span>
            </div>
        </x-slot:meta>
        @if ($req->tabKey() === 'finished')
            <x-slot:actions>
                <form method="POST" action="{{ route('gov.requests.again', $req->id) }}" class="cr-inline-form">
                    @csrf
                    <x-gs::button type="submit" tone="light" outline icon="fa-rotate-right">{{ __($r.'ask_again') }}</x-gs::button>
                </form>
            </x-slot:actions>
        @endif
    </x-gs::page-header>

    @include('govstore::partials.errors')

    <x-gs::stepper :steps="$presenter->steps($req)" :label="__($r.'show_progress')" />

    <div class="cr-layout">
        <div class="cr-layout__main cr-stack">
            <section class="cr-next" aria-labelledby="cr-next-title">
                <i class="fas fa-signs-post cr-next__icon" aria-hidden="true"></i>
                <div class="cr-stack">
                    <h2 class="cr-heading" id="cr-next-title">{{ __($r.'next_title') }}</h2>
                    <p class="cr-flush" data-cr-next-step>{{ $presenter->nextStep($req) }}</p>
                    @if ($status === 'request_issued')
                        <div class="cr-toolbar">
                            <form method="POST" action="{{ route('gov.requests.receive', $req->id) }}" class="cr-inline-form">
                                @csrf
                                <x-gs::button type="submit" icon="fa-check">{{ __($r.'receive_button') }}</x-gs::button>
                            </form>
                            <span class="cr-meta">{{ __($r.'receive_help', ['store' => $presenter->storeName($req->office_id)]) }}</span>
                        </div>
                    @endif
                </div>
            </section>

            <x-gs::box :title="__($r.'lines_title')" icon="fas fa-list" id="items">
                <x-gs::table :caption="__($r.'lines_caption')" class="cr-cards-sm">
                    <thead>
                        <tr>
                            <th scope="col">{{ __($r.'col_item') }}</th>
                            <th scope="col" class="gs-num">{{ __($r.'col_asked') }}</th>
                            <th scope="col" class="gs-num">{{ __($r.'col_approved') }}</th>
                            <th scope="col" class="gs-num">{{ __($r.'col_issued') }}</th>
                            <th scope="col">{{ __($r.'col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($req->items as $line)
                            @php
                                $decided = ! in_array($line->line_approval_status, ['pending'], true) || $req->approval_status === 'pending_final';
                                $note = $presenter->lineNote($line, $req);
                            @endphp
                            <tr data-line-id="{{ $line->id }}">
                                <td data-label="{{ __($r.'col_item') }}">
                                    <strong>{{ $presenter->itemName($line->requested_type, $line->requested_id) }}</strong>
                                    @if ($line->fulfilled_id)
                                        <span class="cr-meta">{{ __($r.'line_alternative', ['item' => $presenter->lineName($line)]) }}</span>
                                    @endif
                                    @if ($note)<q class="cr-quote">{{ $note }}</q>@endif
                                </td>
                                <td class="gs-num" data-label="{{ __($r.'col_asked') }}">{{ $line->requested_qty }}</td>
                                <td class="gs-num" data-label="{{ __($r.'col_approved') }}">{{ $line->line_approval_status === 'pending' && ! $req->primary_decided_by ? __($r.'dash') : $line->approved_qty }}</td>
                                <td class="gs-num" data-label="{{ __($r.'col_issued') }}">{{ $line->issued_qty > 0 ? $line->issued_qty : __($r.'dash') }}</td>
                                <td data-label="{{ __($r.'col_status') }}"><x-gs::badge :tone="$presenter->lineTone($line)" outline>{{ $presenter->lineLabel($line, $req) }}</x-gs::badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-gs::table>
            </x-gs::box>

            @if ($canReturn)
                <details class="cr-confirm cr-confirm--block" id="return" @if ($returnOpen) open @endif>
                    <summary class="gs-btn gs-btn--light gs-btn--outline">{{ __($r.'return_open') }}</summary>
                    <form method="POST" action="{{ route('gov.requests.return', $req->id) }}" class="cr-confirm__panel cr-stack">
                        @csrf
                        <strong>{{ __($r.'return_title') }}</strong>
                        <p class="cr-flush">{{ __($r.'return_body') }}</p>
                        <ul class="cr-plain">
                            @foreach ($returnable as $line)
                                <li>{{ __($r.'item_qty', ['item' => $presenter->lineName($line), 'qty' => $line->issued_qty]) }}</li>
                            @endforeach
                        </ul>
                        <p class="cr-meta cr-flush">{{ __($r.'return_serial_note') }}</p>
                        <x-gs::form-row for="cr-field-reason" :label="__($r.'return_reason')" required :help="__($r.'return_reason_help')" :error="$errors->first('reason')">
                            <textarea id="cr-field-reason" name="reason" class="form-control" rows="2" minlength="5" maxlength="2000" required aria-describedby="cr-field-reason-help">{{ old('reason') }}</textarea>
                        </x-gs::form-row>
                        <div><x-gs::button type="submit" icon="fa-arrow-rotate-left">{{ __($r.'return_submit') }}</x-gs::button></div>
                    </form>
                </details>
            @endif

            @include('govstore::partials.history')
        </div>

        <aside class="cr-layout__aside cr-stack">
            <x-gs::box :title="__($r.'about_title')" icon="fas fa-circle-info">
                <dl class="cr-facts">
                    <div><dt>{{ __($r.'about_for') }}</dt><dd>{{ $req->purpose }}</dd></div>
                    <div><dt>{{ __($r.'about_why') }}</dt><dd>{{ $req->justification }}</dd></div>
                    <div><dt>{{ __($r.'about_needed') }}</dt><dd>{{ $req->required_by_date ? $presenter->date($req->required_by_date) : __($r.'not_urgent') }}</dd></div>
                    <div><dt>{{ __($r.'about_where') }}</dt><dd>{{ $req->deliveryLocation?->name ?? __($r.'collect_from_store') }}</dd></div>
                    <div><dt>{{ __($r.'about_rule') }}</dt><dd>{{ ucfirst($presenter->ruleWords($req->resolved_policy ?? 'PRIMARY_ONLY')) }}
                        @foreach ($presenter->ruleReasons($req) as $reason)<span class="cr-meta">{{ $reason }}</span>@endforeach</dd></div>
                    @if ($req->cost_center)<div><dt>{{ __($r.'about_cost') }}</dt><dd>{{ $req->cost_center }}</dd></div>@endif
                    @if ($req->siblings->isNotEmpty())
                        <div><dt>{{ __($r.'about_sent_with') }}</dt><dd>
                            @foreach ($req->siblings as $sibling)
                                <a href="{{ route('gov.requests.user.show', $sibling->id) }}">{{ $sibling->request_number }}</a>@if (! $loop->last), @endif
                            @endforeach
                            <span class="cr-meta">({{ __($r.'about_sent_with_note') }})</span>
                        </dd></div>
                    @endif
                </dl>
            </x-gs::box>

            @if ($canWithdraw)
                <x-gs::action-row :title="__($r.'withdraw_confirm')" :description="__($r.'withdraw_body')" id="withdraw">
                    <x-slot:actions>
                        <form method="POST" action="{{ route('gov.requests.withdraw', $req->id) }}" class="cr-inline-form">
                            @csrf
                            <x-gs::button type="submit" tone="danger" outline>{{ __($r.'withdraw_confirm') }}</x-gs::button>
                        </form>
                    </x-slot:actions>
                </x-gs::action-row>
            @elseif (in_array($status, ['request_approved', 'request_partly_issued', 'request_issued', 'request_received', 'request_rejected', 'request_pending_final'], true))
                <x-gs::action-row :title="__($r.'withdraw_confirm')" id="withdraw"
                    :unavailable="$decidedAt ? __($r.'withdraw_unavailable', ['date' => $presenter->date($decidedAt)]) : __($r.'withdraw_unavailable_plain')">
                    <x-slot:actions>
                        <x-gs::button tone="light" outline disabled aria-describedby="withdraw-why">{{ __($r.'withdraw_confirm') }}</x-gs::button>
                    </x-slot:actions>
                </x-gs::action-row>
            @endif
        </aside>
    </div>
</div>
@endsection
