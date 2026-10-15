@extends('layouts/default')

@section('title', __('requestlabels::requests.decide_title', ['number' => $serviceRequest->request_number]))

{{--
    Decide (board 8, F5): per line the free stock, the requester's last issue of it and the rule; a
    three-way decision (approve as asked · approve fewer · decline) with an optional reason that the
    page prompts for (D5); why two approvers; a live summary; a stale decision explained in place.
    Expects the data from GovApprovalController::show.
--}}
@php
    $r = 'requestlabels::requests.';
    $req = $serviceRequest;
    $final = $req->approval_status === 'pending_final';
    $stage = $final ? 'stage_final' : ($req->resolved_policy === 'PRIMARY_AND_FINAL' ? 'stage_first' : 'stage_only');
    $requesterName = $presenter->name($req->requested_by);
    $requester = \App\Models\User::withTrashed()->find($req->requested_by);
    $sent = $req->submitted_at ?? $req->created_at;
    $escalated = $req->events->where('event_type', 'escalated')->sortByDesc('id')->first();
    $stale = $errors->has('decision');
@endphp

@section('content')
<div class="cr" data-cr-decide data-request-number="{{ $req->request_number }}" data-status-key="{{ $req->statusKey() }}">
    <x-gs::page-header :title="__($r.'decide_title', ['number' => $req->request_number])">
        <x-slot:meta>
            <div class="cr-toolbar">
                <x-gs::status-badge :status="$req->statusKey()" />
                @if (in_array($req->approval_status, ['pending_primary', 'pending_final'], true))
                    <x-gs::badge tone="primary" outline>{{ __($r.$stage) }}</x-gs::badge>
                @endif
                <span class="cr-meta">{{ __($r.'age_sent', ['date' => $presenter->dateTime($sent)]) }}
                    @if ($escalated) · {{ __($r.'age_escalated', ['date' => $presenter->date($escalated->created_at)]) }}@endif</span>
            </div>
        </x-slot:meta>
        @if ($waiting)
            <x-slot:actions>
                <x-gs::button :href="route('gov.requests.admin.show', $waiting->id)" tone="light" outline icon="fa-forward">{{ trans_choice($r.'next_waiting', $waitingCount, ['count' => $waitingCount]) }}</x-gs::button>
            </x-slot:actions>
        @endif
    </x-gs::page-header>

    @include('govstore::partials.errors')

    @if (! $canDecide && ! $stale)
        <x-gs::alert tone="info" :title="__($r.'view_only')">
            {{ __($r.$whyNot, ['role' => __($r.($final ? 'role_final' : 'role_primary'))]) }}
        </x-gs::alert>
    @endif

    <div class="cr-layout">
        <div class="cr-layout__main cr-stack">
            @if ($canDecide || $stale)
                <form method="POST" action="{{ route('gov.requests.admin.process', $req->id) }}" class="cr-stack" data-cr-decision-form>
                    @csrf
                    <h2 class="cr-heading">{{ __($r.'decide_lines') }}</h2>
                    <fieldset class="cr-stack cr-fieldset" @disabled($stale || ! $canDecide)>
                        @foreach ($req->items as $line)
                            @php
                                $name = $presenter->itemName($line->requested_type, $line->requested_id);
                                $cap = $final ? (int) $line->approved_qty : (int) $line->requested_qty;
                                $fact = $facts[$line->id] ?? ['free' => null, 'previous' => null, 'reason' => null];
                                $choice = old('items.'.$line->id.'.choice', 'as_asked');
                                $qtyError = $errors->first('items.'.$line->id.'.qty');
                                $fieldId = 'cr-field-items-'.$line->id.'-qty';
                            @endphp
                            @if ($final && $line->line_approval_status === 'rejected')
                                <div class="gs-card cr-decide-line cr-decide-line--done">
                                    <div class="gs-card__body"><strong>{{ $name }}</strong> <x-gs::badge tone="secondary" outline>{{ __($r.'line_rejected') }}</x-gs::badge></div>
                                </div>
                                @continue
                            @endif
                            <fieldset class="gs-card cr-decide-line" data-line-id="{{ $line->id }}" data-cr-decision="{{ $line->id }}" data-cap="{{ $cap }}">
                                <legend class="cr-decide-line__title"><strong>{{ $name }}</strong> · <span class="cr-muted">{{ __($r.'decide_asked', ['qty' => $line->requested_qty]) }}</span></legend>
                                <div class="gs-card__body cr-stack">
                                    <ul class="cr-facts-inline">
                                        <li>
                                            <i class="fas fa-warehouse" aria-hidden="true"></i>
                                            @if ($fact['free'] === null || $fact['free'] < 1)
                                                <span class="cr-warn">{{ __($r.'decide_free_none') }}</span>
                                            @elseif ($fact['free'] < $cap)
                                                <span class="cr-warn">{{ __($r.'decide_free', ['count' => $fact['free']]) }}</span>
                                            @else
                                                {{ __($r.'decide_free_after', ['count' => $fact['free'], 'left' => $fact['free'] - $cap]) }}
                                            @endif
                                        </li>
                                        @if ($fact['previous'])
                                            <li><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> {{ __($r.'decide_history', ['name' => $requesterName, 'qty' => $fact['previous']->issued_qty, 'date' => $presenter->date($fact['previous']->updated_at)]) }}</li>
                                        @endif
                                        @if ($fact['reason'])
                                            <li><i class="fas fa-scale-balanced" aria-hidden="true"></i> {{ __($r.'decide_rule', ['rule' => $fact['reason']]) }}</li>
                                        @endif
                                    </ul>
                                    <div class="cr-choices" role="radiogroup" aria-label="{{ __($r.'choice_label', ['item' => $name]) }}">
                                        @foreach (['as_asked' => __($r.'choice_as_asked', ['qty' => $cap]), 'fewer' => __($r.'choice_fewer'), 'decline' => __($r.'choice_decline')] as $value => $label)
                                            <label class="cr-choice cr-choice--{{ $value }}">
                                                <input type="radio" name="items[{{ $line->id }}][choice]" value="{{ $value }}" @checked($choice === $value)>
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="cr-decide-fewer" data-cr-when="items[{{ $line->id }}][choice]=fewer">
                                        <x-gs::form-row :for="$fieldId" :label="__($r.'how_many')" :error="$qtyError">
                                            <input type="number" id="{{ $fieldId }}" name="items[{{ $line->id }}][qty]" class="form-control cr-short" min="1" max="{{ max(1, $cap) }}"
                                                value="{{ old('items.'.$line->id.'.qty', max(1, min($cap - 1, (int) ($fact['free'] ?? $cap)))) }}" inputmode="numeric" @if ($qtyError) aria-invalid="true" @endif>
                                        </x-gs::form-row>
                                    </div>
                                    @if ($qtyError && $choice !== 'fewer')
                                        <p class="gs-form-row__error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $qtyError }}</p>
                                    @endif
                                    <details class="cr-reason" data-cr-reason @if (filled(old('items.'.$line->id.'.notes')) || $choice !== 'as_asked') open @endif>
                                        <summary>{{ __($r.'reason_label', ['name' => $requesterName]) }}</summary>
                                        <p class="cr-prompt cr-flush" data-cr-reason-prompt hidden>{{ __($r.'reason_prompt', ['name' => $requesterName]) }}</p>
                                        <label class="gs-sr-only" for="cr-field-items-{{ $line->id }}-notes">{{ __($r.'reason_label', ['name' => $requesterName]) }}</label>
                                        <textarea id="cr-field-items-{{ $line->id }}-notes" name="items[{{ $line->id }}][notes]" class="form-control" rows="2" maxlength="2000"
                                            aria-describedby="cr-field-items-{{ $line->id }}-notes-help">{{ old('items.'.$line->id.'.notes') }}</textarea>
                                        <p class="gs-form-row__help" id="cr-field-items-{{ $line->id }}-notes-help">{{ __($r.'reason_help', ['name' => $requesterName]) }}</p>
                                    </details>
                                </div>
                            </fieldset>
                        @endforeach
                    </fieldset>
                    <div class="cr-decide-footer">
                        <p class="cr-flush" role="status" data-cr-decision-summary
                            data-template="{{ __($r.'decide_summary', ['approved' => '__A__', 'total' => '__T__']) }}"
                            data-none="{{ __($r.'decide_summary_none') }}"></p>
                        <div class="cr-toolbar cr-toolbar--end">
                            <x-gs::button :href="route('gov.requests.admin.index')" tone="light" outline icon="fa-arrow-left">{{ __($r.'decide_back') }}</x-gs::button>
                            @unless ($stale)
                                <x-gs::button type="submit" icon="fa-check">{{ __($r.($stage === 'stage_first' ? 'decide_save_first' : 'decide_save')) }}</x-gs::button>
                            @endunless
                        </div>
                    </div>
                </form>
            @else
                <x-gs::box :title="__($r.'lines_title')" icon="fas fa-list">
                    <x-gs::table :caption="__($r.'lines_caption')" class="cr-cards-sm">
                        <thead><tr><th scope="col">{{ __($r.'col_item') }}</th><th scope="col" class="gs-num">{{ __($r.'col_asked') }}</th><th scope="col" class="gs-num">{{ __($r.'col_approved') }}</th><th scope="col">{{ __($r.'col_status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($req->items as $line)
                                <tr data-line-id="{{ $line->id }}">
                                    <td data-label="{{ __($r.'col_item') }}"><strong>{{ $presenter->itemName($line->requested_type, $line->requested_id) }}</strong>
                                        @if ($note = $presenter->lineNote($line, $req))<q class="cr-quote">{{ $note }}</q>@endif</td>
                                    <td class="gs-num" data-label="{{ __($r.'col_asked') }}">{{ $line->requested_qty }}</td>
                                    <td class="gs-num" data-label="{{ __($r.'col_approved') }}">{{ $line->line_approval_status === 'pending' && ! $req->primary_decided_by ? __($r.'dash') : $line->approved_qty }}</td>
                                    <td data-label="{{ __($r.'col_status') }}"><x-gs::badge :tone="$presenter->lineTone($line)" outline>{{ $presenter->lineLabel($line, $req) }}</x-gs::badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-gs::table>
                </x-gs::box>
            @endif

            @include('govstore::partials.history')
        </div>

        <aside class="cr-layout__aside cr-stack">
            <x-gs::box :title="__($r.'request_box')" icon="fas fa-file-lines">
                <div class="cr-person">
                    <x-gs::avatar :name="$requesterName" />
                    <div>
                        <strong>{{ $requesterName }}</strong>
                        @if ($requester?->jobtitle)<span class="cr-meta">{{ $requester->jobtitle }}</span>@endif
                    </div>
                </div>
                <dl class="cr-facts">
                    <div><dt>{{ __($r.'about_for') }}</dt><dd>{{ $req->purpose }}</dd></div>
                    <div><dt>{{ __($r.'about_why') }}</dt><dd>{{ $req->justification }}</dd></div>
                    <div><dt>{{ __($r.'about_needed') }}</dt><dd>{{ $req->required_by_date ? $presenter->date($req->required_by_date) : __($r.'not_urgent') }}</dd></div>
                    <div><dt>{{ __($r.'about_where') }}</dt><dd>{{ $req->deliveryLocation?->name ?? __($r.'collect_from_store') }}</dd></div>
                    <div><dt>{{ __($r.'about_kind') }}</dt><dd>{{ $presenter->requestType($req->request_type) }}</dd></div>
                </dl>
            </x-gs::box>

            @if ($req->resolved_policy === 'PRIMARY_AND_FINAL')
                <x-gs::box :title="__($r.'why_two_title')" icon="fas fa-user-group">
                    <ul class="cr-plain cr-stack">
                        @foreach ($presenter->ruleReasons($req) as $reason)<li>{{ $reason }}</li>@endforeach
                    </ul>
                    <p class="cr-flush">
                        @if ($final)
                            {{ __($r.'why_two_final', ['first' => $presenter->name($req->primary_decided_by)]) }}
                        @elseif ($finalApprover)
                            {{ __($r.'why_two_you_first', ['final' => $presenter->name($finalApprover)]) }}
                        @else
                            {{ __($r.'why_two_you_first_unassigned') }}
                        @endif
                    </p>
                </x-gs::box>
            @endif
        </aside>
    </div>
</div>
@endsection
