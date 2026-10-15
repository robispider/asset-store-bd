@extends('layouts/default')

@section('title', __('requestlabels::requests.issue_title', ['number' => $serviceRequest->request_number]))

{{--
    Issue items (board 10, F6), in three steps on one page:
      1. Pick what to issue: quantities prefilled with what can be issued (− / + / "All"), or a scan field over
         the free serials with a picked count; an alternative search that states the price rule.
      2. Hand over: who took the items (D7), as two large choices, and a note.
      3. Check and issue: the list of what leaves the store, what pressing Issue records and under whose
         name, and the action in a bar that stays in view. The form carries the request's version, so a
         double press or a colleague's issue in between is refused rather than issued twice.
    "Close the rest" with a reason is kept apart, below. Refusals come back under the line with the input kept.
    Expects the data from GovFulfillmentController::show.
--}}
@php
    $r = 'requestlabels::requests.';
    $req = $serviceRequest;
    $requesterName = $presenter->name($req->requested_by);
    $approvedLines = $req->items->where('line_approval_status', 'approved');
    $open = $approvedLines->filter(fn ($l) => $l->issued_qty < $l->approved_qty);
    $closeOpen = $errors->has('reason') || $errors->has('reason_code');
    $today = now()->startOfDay();
    $due = $req->required_by_date?->copy()->startOfDay();
    $handedTo = old('handed_to', 'requester');
@endphp

@section('content')
<div class="cr cr-issue" data-cr-issue data-request-number="{{ $req->request_number }}" data-status-key="{{ $req->statusKey() }}">
    <x-gs::page-header :title="__($r.'issue_title', ['number' => $req->request_number])">
        <x-slot:meta>
            <div class="cr-toolbar">
                <x-gs::status-badge :status="$req->statusKey()" />
                <span class="cr-meta">{{ trans_choice($r.'issue_more_waiting', $waitingCount, ['count' => $waitingCount]) }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <x-gs::button :href="route('gov.requests.fulfillment.index')" tone="light" outline icon="fa-arrow-left">{{ __($r.'issue_back') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    @include('govstore::partials.errors')

    {{-- Who and why first: the person is the point of the handover. --}}
    <section class="cr-who" aria-labelledby="cr-who-name">
        <x-gs::avatar :name="$requesterName" size="lg" />
        <div class="cr-who__text">
            <span class="cr-eyebrow">{{ __($r.'issuing_to') }}</span>
            <h2 class="cr-who__name" id="cr-who-name">{{ $requesterName }}</h2>
            @if (filled($req->purpose))<p class="cr-who__purpose">{{ $req->purpose }}</p>@endif
        </div>
        <dl class="cr-who__facts">
            <div>
                <dt>{{ __($r.'about_where') }}</dt>
                <dd><i class="fas {{ $req->delivery_location_id ? 'fa-truck' : 'fa-store' }}" aria-hidden="true"></i> {{ $req->deliveryLocation?->name ?? __($r.'collect_from_store') }}</dd>
            </div>
            <div>
                <dt>{{ __($r.'about_needed') }}</dt>
                <dd>
                    @if (! $due)
                        <span class="cr-muted">{{ __($r.'due_none') }}</span>
                    @elseif ($due->lt($today))
                        <x-gs::badge tone="danger">{{ trans_choice($r.'due_overdue', (int) $due->diffInDays($today), ['count' => (int) $due->diffInDays($today)]) }}</x-gs::badge>
                    @elseif ($due->eq($today))
                        <x-gs::badge tone="warning">{{ __($r.'due_today') }}</x-gs::badge>
                    @else
                        {{ $presenter->date($due) }}
                    @endif
                </dd>
            </div>
            @if ($req->approved_at)
                <div>
                    <dt>{{ __($r.'m_approved') }}</dt>
                    <dd>{{ __($r.'meta_approved_by', ['date' => $presenter->date($req->approved_at), 'name' => $req->approved_by ? $presenter->name($req->approved_by) : __($r.'system')]) }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <div class="cr-layout">
        <div class="cr-layout__main cr-stack">
            <form method="POST" action="{{ route('gov.requests.fulfillment.process', $req->id) }}" id="cr-issue" class="cr-stack" data-cr-issue-form
                data-summary="{{ __($r.'issue_summary', ['count' => '__N__', 'name' => $requesterName]) }}" data-summary-none="{{ __($r.'issue_summary_none') }}"
                data-submitting="{{ __($r.'issue_submitting') }}">
                @csrf
                <input type="hidden" name="version" value="{{ $version }}">

                {{-- 1 · Pick what to issue --}}
                <section class="cr-step" aria-labelledby="cr-step-pick">
                    <h2 class="cr-step__title" id="cr-step-pick">
                        <span class="cr-step__n" aria-hidden="true">1</span>
                        {{ __($r.'step_pick') }}
                        <span class="cr-step__count">{{ trans_choice($r.'issue_lines', max(1, $open->count()), ['count' => $open->count()]) }}</span>
                    </h2>

                    @foreach ($approvedLines as $line)
                        @php
                            $info = $lines[$line->id];
                            $name = $presenter->lineName($line);
                            $remaining = $info['remaining'];
                            $isAsset = $info['type'] === 'asset_model';
                            $fieldId = 'cr-field-issue-'.$line->id;
                            $issueError = $errors->first('issue.'.$line->id);
                            $altError = $errors->first('substitutions.'.$line->id);
                            $chosenAlt = old('substitutions.'.$line->id);
                            $short = $remaining > 0 && $info['free'] < $remaining;
                        @endphp
                        <section @class(['cr-line', 'cr-line--done' => $remaining === 0, 'cr-line--error' => $issueError || $altError])
                            data-line-id="{{ $line->id }}" data-cr-issue-line data-type="{{ $isAsset ? 'asset' : 'bulk' }}" data-name="{{ $name }}" aria-labelledby="{{ $fieldId }}-name">
                            <div class="cr-line__head">
                                <span class="cr-line__icon" aria-hidden="true"><i class="fas {{ $isAsset ? 'fa-laptop' : 'fa-box' }}"></i></span>
                                <div class="cr-line__title">
                                    <h3 class="cr-line__name" id="{{ $fieldId }}-name">{{ $name }}</h3>
                                    <span class="cr-meta">{{ $presenter->typeLabel($info['type']) }}</span>
                                    <span class="cr-meta cr-line__alt" data-cr-alt-chosen="{{ $line->id }}" @if (! $chosenAlt) hidden @endif></span>
                                </div>
                                @if ($remaining > 0)
                                    <span @class(['cr-pill', 'cr-pill--ok' => ! $short, 'cr-pill--few' => $short && $info['free'] > 0, 'cr-pill--none' => $info['free'] === 0])>
                                        <i class="fas fa-warehouse" aria-hidden="true"></i> {{ __($r.'free_pill', ['count' => $info['free']]) }}
                                    </span>
                                @endif
                            </div>

                            <x-gs::progress size="sm" :value="$line->issued_qty" :max="max(1, $line->approved_qty)" :show-value="false" :tone="$remaining === 0 ? 'success' : 'primary'"
                                :label="__($r.'issued_progress', ['issued' => $line->issued_qty, 'approved' => $line->approved_qty])" />

                            @if ($remaining === 0)
                                <p class="cr-ok cr-flush"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __($r.'fully_issued') }}</p>
                            @elseif ($isAsset)
                                <div class="cr-stack" data-cr-serials="{{ $line->id }}" data-remaining="{{ $remaining }}">
                                    <x-gs::form-row :for="$fieldId" :label="__($r.'scan_label')" :help="__($r.'scan_help')" :error="$issueError">
                                        <div class="cr-scan">
                                            <i class="fas fa-barcode cr-scan__icon" aria-hidden="true"></i>
                                            <input type="text" id="{{ $fieldId }}" class="form-control cr-mono" autocomplete="off" spellcheck="false" data-cr-scan="{{ $line->id }}"
                                                data-no-match="{{ __($r.'scan_no_match') }}" aria-describedby="{{ $fieldId }}-help" @if ($issueError) aria-invalid="true" @endif>
                                        </div>
                                    </x-gs::form-row>
                                    <fieldset class="cr-fieldset">
                                        <legend class="cr-label cr-serials__legend">
                                            <span>{{ __($r.'free_list') }}</span>
                                            <span class="cr-pill" data-cr-picked="{{ $line->id }}" data-template="{{ __($r.'picked_count', ['picked' => '__P__', 'n' => $remaining]) }}">{{ __($r.'picked_count', ['picked' => count((array) old('issue.'.$line->id, [])), 'n' => $remaining]) }}</span>
                                        </legend>
                                        @if (($availableAssets[$line->id] ?? collect())->isEmpty())
                                            <p class="cr-warn cr-flush"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($r.'free_none') }}</p>
                                        @else
                                            <ul class="cr-serials">
                                                @foreach ($availableAssets[$line->id] as $asset)
                                                    <li data-tag="{{ mb_strtolower($asset->asset_tag.' '.$asset->serial) }}">
                                                        <label class="cr-serial">
                                                            <input type="checkbox" name="issue[{{ $line->id }}][]" value="{{ $asset->id }}" data-label="{{ $asset->asset_tag }}" @checked(in_array((string) $asset->id, (array) old('issue.'.$line->id, []), true))>
                                                            <span><strong class="cr-mono">{{ $asset->asset_tag }}</strong>
                                                                <span class="cr-meta">{{ __($r.'serial_meta', ['serial' => $asset->serial ?: __($r.'serial_none'), 'place' => $asset->location?->name ?? '']) }}</span></span>
                                                        </label>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </fieldset>
                                </div>
                            @else
                                <x-gs::form-row :for="$fieldId" :label="__($r.'issue_now')" :error="$issueError">
                                    <div class="cr-inline cr-inline--wrap cr-qty">
                                        <div class="cr-stepper">
                                            <button type="button" class="cr-stepper__btn" data-cr-qty-step="-1" data-cr-jsonly aria-controls="{{ $fieldId }}" aria-label="{{ __($r.'qty_less', ['item' => $name]) }}"><span aria-hidden="true">−</span></button>
                                            <input type="number" id="{{ $fieldId }}" name="issue[{{ $line->id }}]" class="cr-stepper__input" min="0" max="{{ $remaining }}"
                                                value="{{ old('issue.'.$line->id, $info['prefill']) }}" inputmode="numeric" data-cr-bulk="{{ $line->id }}"
                                                aria-label="{{ __($r.'issue_now_label', ['item' => $name]) }}" @if ($issueError) aria-invalid="true" @endif>
                                            <button type="button" class="cr-stepper__btn" data-cr-qty-step="1" data-cr-jsonly aria-controls="{{ $fieldId }}" aria-label="{{ __($r.'qty_more', ['item' => $name]) }}"><span aria-hidden="true">+</span></button>
                                        </div>
                                        <span class="cr-meta">{{ __($r.'of_n', ['n' => $remaining]) }}</span>
                                        @if (min($remaining, $info['free']) > 0)
                                            <x-gs::button size="sm" tone="light" outline data-cr-jsonly data-cr-qty-fill="{{ min($remaining, $info['free']) }}" aria-controls="{{ $fieldId }}"
                                                aria-label="{{ __($r.'issue_all_label', ['n' => min($remaining, $info['free']), 'item' => $name]) }}">{{ __($r.'issue_all', ['n' => min($remaining, $info['free'])]) }}</x-gs::button>
                                        @endif
                                    </div>
                                </x-gs::form-row>
                                @if ($short)
                                    <p class="cr-warn cr-flush"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($r.'free_short', ['free' => $info['free'], 'rest' => $remaining - $info['free']]) }}</p>
                                @endif
                            @endif

                            @if ($remaining > 0 && $line->issued_qty === 0 && ! $isAsset)
                                <details class="cr-alt" data-cr-alt="{{ $line->id }}" @if ($altError || $chosenAlt) open @endif>
                                    <summary aria-label="{{ __($r.'give_alt_label', ['item' => $name]) }}">{{ __($r.'give_alt') }}</summary>
                                    <div class="cr-alt__panel cr-stack">
                                        <strong>{{ __($r.'alt_title', ['item' => $name]) }}</strong>
                                        <p class="cr-flush cr-meta">{{ $info['price'] !== null ? __($r.'alt_rule', ['price' => number_format((float) $info['price'], 2)]) : __($r.'alt_rule_noprice') }}</p>
                                        @if ($info['price'] !== null)
                                            <label for="cr-alt-q-{{ $line->id }}" class="cr-label">{{ __($r.'alt_search') }}</label>
                                            <input type="search" id="cr-alt-q-{{ $line->id }}" class="form-control" autocomplete="off" placeholder="{{ __($r.'alt_search_placeholder') }}"
                                                data-cr-alt-search="{{ route('gov.requests.catalog.search', ['type' => $info['type'], 'for_line' => $line->id]) }}" data-line="{{ $line->id }}"
                                                data-none="{{ __($r.'alt_none') }}">
                                            <fieldset class="cr-fieldset">
                                                <legend class="gs-sr-only">{{ __($r.'alt_title', ['item' => $name]) }}</legend>
                                                <ul class="cr-alt__results" data-cr-alt-results="{{ $line->id }}" aria-live="polite"></ul>
                                                <label class="cr-radio"><input type="radio" name="substitutions[{{ $line->id }}]" value="" @checked(! $chosenAlt)> {{ __($r.'alt_undo') }}</label>
                                                @if ($chosenAlt)
                                                    <label class="cr-radio"><input type="radio" name="substitutions[{{ $line->id }}]" value="{{ $chosenAlt }}" checked> {{ __($r.'alt_chosen', ['item' => $presenter->itemName($info['type'], (int) $chosenAlt)]) }}</label>
                                                @endif
                                            </fieldset>
                                            @if ($altError)<p class="gs-form-row__error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $altError }}</p>@endif
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </section>
                    @endforeach
                </section>

                {{-- 2 · Hand over --}}
                <section class="cr-step" aria-labelledby="cr-step-handover">
                    <h2 class="cr-step__title" id="cr-step-handover"><span class="cr-step__n" aria-hidden="true">2</span> {{ __($r.'step_handover') }}</h2>
                    <div class="cr-panel">
                        <fieldset class="cr-fieldset">
                            <legend class="cr-label">{{ __($r.'handed_question') }}</legend>
                            <div class="gs-choices">
                                <x-gs::choice-card name="handed_to" value="requester" id="cr-handed-requester" icon="fa-user-check" :checked="$handedTo === 'requester'"
                                    :label="__($r.'handed_requester', ['name' => $requesterName])" :description="__($r.'handed_requester_desc')" />
                                <x-gs::choice-card name="handed_to" value="other" id="cr-handed-other" icon="fa-people-arrows" :checked="$handedTo === 'other'"
                                    :label="__($r.'handed_other')" :description="__($r.'handed_other_desc')" />
                            </div>
                        </fieldset>
                        <div data-cr-when="handed_to=other">
                            <x-gs::form-row for="cr-field-handed-to-name" :label="__($r.'handed_other_name')" :help="__($r.'handed_other_name_help')" required :error="$errors->first('handed_to_name')">
                                <input type="text" id="cr-field-handed-to-name" name="handed_to_name" class="form-control" value="{{ old('handed_to_name') }}" maxlength="255"
                                    autocomplete="off" aria-describedby="cr-field-handed-to-name-help">
                            </x-gs::form-row>
                        </div>
                        <x-gs::form-row for="cr-field-notes" :label="__($r.'note_label')" :help="__($r.'issue_note_help', ['name' => $requesterName])" :error="$errors->first('notes')">
                            <textarea id="cr-field-notes" name="notes" class="form-control" rows="2" maxlength="2000" aria-describedby="cr-field-notes-help">{{ old('notes') }}</textarea>
                        </x-gs::form-row>
                    </div>
                </section>

                {{-- 3 · Check and issue --}}
                <section class="cr-step" aria-labelledby="cr-step-confirm">
                    <h2 class="cr-step__title" id="cr-step-confirm"><span class="cr-step__n" aria-hidden="true">3</span> {{ __($r.'step_confirm') }}</h2>
                    <div class="cr-panel cr-receipt">
                        <div class="cr-receipt__list" data-cr-jsonly>
                            <span class="cr-eyebrow">{{ __($r.'receipt_title') }}</span>
                            <ul class="cr-plain" data-cr-issue-list data-template="{{ __($r.'item_qty', ['item' => '__ITEM__', 'qty' => '__QTY__']) }}"></ul>
                        </div>
                        <ul class="cr-trust cr-receipt__effects">
                            <li><i class="fas fa-user-shield" aria-hidden="true"></i> <span>{{ __($r.'issue_effect_you', ['name' => $issuer]) }}</span></li>
                            <li><i class="fas fa-warehouse" aria-hidden="true"></i> <span>{{ __($r.'issue_effect_stock') }}</span></li>
                            <li><i class="fas fa-book" aria-hidden="true"></i> <span>{{ __($r.'issue_effect_register') }}</span></li>
                            <li><i class="fas fa-bell" aria-hidden="true"></i> <span>{{ __($r.'issue_effect_receipt', ['name' => $requesterName]) }}</span></li>
                        </ul>
                    </div>
                </section>

                <div class="cr-issuebar">
                    <p class="cr-flush cr-issuebar__summary" role="status" data-cr-issue-summary></p>
                    <x-gs::button type="submit" icon="fa-dolly" data-cr-issue-submit>{{ __($r.'issue_submit') }}</x-gs::button>
                </div>
            </form>

            @if ($open->isNotEmpty())
                <div class="cr-panel cr-danger">
                    <x-gs::action-row id="close-rest-row" :title="__($r.'cant_issue')" :description="__($r.'cant_issue_body', ['name' => $requesterName])" />
                    <details class="cr-confirm cr-confirm--block" id="close-rest" @if ($closeOpen) open @endif>
                        <summary class="gs-btn gs-btn--danger gs-btn--outline">{{ __($r.'close_rest_open') }}</summary>
                        <form method="POST" action="{{ route('gov.requests.fulfillment.close', $req->id) }}" class="cr-confirm__panel cr-stack">
                            @csrf
                            <strong>{{ __($r.'close_title', ['number' => $req->request_number]) }}</strong>
                            <p class="cr-flush">{{ __($r.'close_body', ['items' => $open->map(fn ($l) => __($r.'item_qty', ['item' => $presenter->lineName($l), 'qty' => $l->approved_qty - $l->issued_qty]))->implode(', '), 'name' => $requesterName]) }}</p>
                            <fieldset class="cr-fieldset" id="cr-field-reason-code">
                                <legend class="cr-label">{{ __($r.'close_why') }}</legend>
                                <div class="cr-radios">
                                    @foreach (['out_of_stock', 'discontinued', 'not_needed', 'other'] as $code)
                                        <label class="cr-radio"><input type="radio" name="reason_code" value="{{ $code }}" @checked(old('reason_code', 'out_of_stock') === $code)> {{ __($r.'close_reason_'.$code) }}</label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <x-gs::form-row for="cr-field-reason" :label="__($r.'close_explain', ['name' => $requesterName])" required
                                :help="__($r.'close_explain_help', ['name' => $requesterName])" :error="$errors->first('reason')">
                                <textarea id="cr-field-reason" name="reason" class="form-control" rows="2" minlength="5" maxlength="2000" required aria-describedby="cr-field-reason-help">{{ old('reason') }}</textarea>
                            </x-gs::form-row>
                            <div class="cr-toolbar">
                                <x-gs::button type="submit" tone="danger">{{ __($r.'close_submit') }}</x-gs::button>
                                <x-gs::button tone="light" outline data-cr-close-details>{{ __($r.'close_keep') }}</x-gs::button>
                            </div>
                        </form>
                    </details>
                </div>
            @endif
        </div>

        <aside class="cr-layout__aside cr-stack">
            @include('govstore::partials.history')
        </aside>
    </div>
</div>
@endsection
