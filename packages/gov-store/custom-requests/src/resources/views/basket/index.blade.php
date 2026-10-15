@extends('layouts/default')

@section('title', __('requestlabels::requests.draft_title'))

{{--
    Checkout: 1 Items · 2 Details · 3 Review and send. Each step checks itself before the next; errors
    are listed at the top of their step with links to the fields. Review shows the request as the
    approver will read it, a one-sentence declaration, and Send. Without JavaScript all three steps show
    in order and the form still works. Security: the review snapshot and a one-time token travel with
    the form (BasketService::submitBasket, BasketController::submit). Expects BasketController::index.
--}}
@php
    $r = 'requestlabels::requests.';
    $lines = $draft['lines'];
    $count = count($draft['groups']);
    $kind = old('request_type', 'other');
    $moreKinds = ['project', 'office_setup', 'repair', 'emergency'];
    $needed = old('needed', old('required_by_date') ? 'date' : 'none');
    $detailError = collect(\GovStore\CustomRequests\Http\Controllers\BasketController::DETAIL_FIELDS)->contains(fn ($field) => $errors->has($field));
    $startStep = $errors->hasAny(['reviewed', 'declared', 'request']) ? 3 : ($detailError ? 2 : 1);
    $changes = session('cr_changes', []);
    $steps = [1 => 'step_items', 2 => 'step_details', 3 => 'step_review'];
@endphp

@section('content')
<div class="cr cr-checkout" data-cr-checkout data-cr-start-step="{{ $startStep }}">
    <x-gs::page-header :title="__($r.'draft_title')" :subtitle="__($r.'checkout_subtitle', ['date' => $presenter->date($draft['basket']?->expires_at)])">
        <x-slot:actions>
            <x-gs::button :href="route('gov.requests.catalog')" tone="light" outline icon="fa-plus">{{ __($r.'draft_add_more') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    @if ($lines->isEmpty())
        @if ($errors->has('items'))
            <x-gs::alert tone="warning">{{ $errors->first('items') }}</x-gs::alert>
        @endif
        <x-gs::empty-state icon="fa-file-pen" :title="__($r.'draft_empty_title')">
            {{ __($r.'draft_empty_body') }}
            <x-slot:actions><x-gs::button :href="route('gov.requests.catalog')" icon="fa-store">{{ __($r.'draft_browse') }}</x-gs::button></x-slot:actions>
        </x-gs::empty-state>
    @else
        <ol class="cr-steps" data-cr-steps aria-label="{{ __($r.'steps_label') }}">
            @foreach ($steps as $n => $label)
                <li class="cr-steps__step" data-cr-step-marker="{{ $n }}">
                    <a href="#cr-step-{{ $n }}" class="cr-steps__link" data-cr-goto="{{ $n }}">
                        <span class="cr-steps__n" aria-hidden="true">{{ $n }}</span>
                        <span class="cr-steps__label">{{ __($r.$label) }}</span>
                    </a>
                </li>
            @endforeach
        </ol>

        <div class="cr-checkout__layout">
            <div class="cr-checkout__main">
                {{-- 1 · Items --}}
                <section class="cr-panel" id="cr-step-1" data-cr-panel="1" aria-labelledby="cr-step-1-title" tabindex="-1">
                    <h2 class="cr-panel__title" id="cr-step-1-title">{{ __($r.'step_items_title') }}</h2>
                    <div data-cr-fragment="lines">@include('govstore::basket.partials.lines')</div>
                    <div class="cr-panel__nav" data-cr-js hidden>
                        <x-gs::button :href="route('gov.requests.catalog')" tone="light" outline icon="fa-plus">{{ __($r.'draft_add_more') }}</x-gs::button>
                        <x-gs::button data-cr-next="2" icon="fa-arrow-right">{{ __($r.'step_to_details') }}</x-gs::button>
                    </div>
                </section>

                <form method="POST" action="{{ route('gov.requests.basket.submit') }}" id="cr-send" novalidate data-cr-send>
                    @csrf
                    <input type="hidden" name="submit_token" value="{{ $submitToken }}">
                    <input type="hidden" name="reviewed" value="{{ json_encode($draft['snapshot']) }}" data-cr-reviewed>

                    {{-- 2 · Details --}}
                    <section class="cr-panel" id="cr-step-2" data-cr-panel="2" aria-labelledby="cr-step-2-title" tabindex="-1">
                        <h2 class="cr-panel__title" id="cr-step-2-title">{{ __($r.'step_details_title') }}</h2>
                        <x-gs::alert tone="danger" :title="__($r.'errors_title')" data-cr-step-errors="2" :hidden="! $detailError">
                            <ul class="cr-flush cr-plain" data-cr-error-list>
                                @foreach (\GovStore\CustomRequests\Http\Controllers\BasketController::DETAIL_FIELDS as $field)
                                    @if ($errors->has($field))
                                        <li><a href="#cr-field-{{ str_replace('_', '-', $field) }}">{{ $errors->first($field) }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </x-gs::alert>

                        <fieldset class="cr-fieldset" id="cr-field-request-type">
                            <legend class="cr-label">{{ __($r.'kind_label') }}</legend>
                            <div class="gs-choices cr-kindcards">
                                @foreach (['other', 'replacement', 'new_employee'] as $value)
                                    <x-gs::choice-card name="request_type" :value="$value" :label="__($r.'request_type_'.$value)"
                                        :description="__($r.'request_type_desc_'.$value)" :checked="$kind === $value" />
                                @endforeach
                            </div>
                            <details class="cr-more" @if (in_array($kind, $moreKinds, true)) open @endif>
                                <summary>{{ __($r.'kind_more') }} <span class="cr-meta cr-inline-meta">· {{ __($r.'request_type_desc_more') }}</span></summary>
                                <div class="gs-choices cr-kindcards">
                                    @foreach ($moreKinds as $value)
                                        <x-gs::choice-card name="request_type" :value="$value" :label="__($r.'request_type_'.$value)" :checked="$kind === $value" />
                                    @endforeach
                                </div>
                            </details>
                            @error('request_type')<p class="gs-form-row__error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>@enderror
                        </fieldset>

                        <x-gs::form-row for="cr-field-purpose" :label="__($r.'purpose_label')" required :help="__($r.'purpose_help')" :error="$errors->first('purpose')">
                            <input type="text" id="cr-field-purpose" name="purpose" class="form-control" value="{{ old('purpose') }}" maxlength="255" required
                                aria-describedby="cr-field-purpose-help" data-cr-review-source="purpose" data-cr-message="{{ __($r.'purpose_missing') }}" @error('purpose') aria-invalid="true" @enderror>
                        </x-gs::form-row>

                        <div class="gs-form-row {{ $errors->has('justification') ? 'gs-form-row--error' : '' }}" data-cr-reason data-needs="{{ $draft['needsReason'] ? '1' : '0' }}">
                            <label class="gs-form-row__label cr-reason-label" for="cr-field-justification">
                                <span data-cr-reason-on @if (! $draft['needsReason']) hidden @endif>{{ __($r.'justification_label') }} <span class="gs-form-row__required" aria-hidden="true">*</span></span>
                                <span data-cr-reason-off @if ($draft['needsReason']) hidden @endif>{{ __($r.'store_note_label') }} <span class="cr-meta cr-inline-meta">{{ __($r.'optional') }}</span></span>
                                <span class="cr-reader" data-cr-reader @if (! $draft['reader']) hidden @endif><i class="fas fa-eye" aria-hidden="true"></i> <span data-cr-reader-name>{{ __($r.'read_by', ['name' => $draft['reader'] ?? '']) }}</span></span>
                            </label>
                            <div class="gs-form-row__control">
                                <textarea id="cr-field-justification" name="justification" class="form-control" rows="3" maxlength="10000" @if ($draft['needsReason']) required @endif
                                    aria-describedby="cr-field-justification-help" data-cr-review-source="why" data-cr-message="{{ __($r.'reason_missing') }}"
                                    data-read-by="{{ __($r.'read_by', ['name' => '__NAME__']) }}" @error('justification') aria-invalid="true" @enderror>{{ old('justification') }}</textarea>
                                <p class="gs-form-row__help" id="cr-field-justification-help">
                                    <span data-cr-reason-on @if (! $draft['needsReason']) hidden @endif>{{ __($r.'justification_help') }}</span>
                                    <span data-cr-reason-off @if ($draft['needsReason']) hidden @endif>{{ __($r.'note_help') }}</span>
                                </p>
                                @error('justification')<p class="gs-form-row__error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="cr-row2">
                            <fieldset class="cr-fieldset" id="cr-field-needed">
                                <legend class="cr-label">{{ __($r.'when_label') }}</legend>
                                <div class="cr-segmented">
                                    @foreach (['none' => 'when_none', 'week' => 'when_week', 'date' => 'when_date'] as $value => $label)
                                        <label class="cr-segmented__option">
                                            <input type="radio" name="needed" value="{{ $value }}" @checked($needed === $value) data-cr-review-source="needed">
                                            <span>{{ __($r.$label) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div data-cr-when="needed=date" class="cr-when-date">
                                    <label for="cr-field-required-by-date" class="gs-sr-only">{{ __($r.'needed_by_label') }}</label>
                                    <input type="date" id="cr-field-required-by-date" name="required_by_date" class="form-control cr-date" value="{{ old('required_by_date') }}"
                                        min="{{ now()->toDateString() }}" data-cr-review-source="date" data-cr-message="{{ __($r.'needed_date_required') }}" @error('required_by_date') aria-invalid="true" @enderror>
                                </div>
                                @error('required_by_date')<p class="gs-form-row__error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>@enderror
                            </fieldset>

                            <fieldset class="cr-fieldset" id="cr-field-delivery-location-id">
                                <legend class="cr-label">{{ __($r.'deliver_label') }}</legend>
                                <div class="cr-radios">
                                    <label class="cr-radio cr-radio--card"><input type="radio" name="delivery_location_id" value="" @checked(old('delivery_location_id', '') === '') data-cr-review-source="where"> <span>{{ __($r.'deliver_collect') }}</span></label>
                                    @foreach ($locations as $location)
                                        <label class="cr-radio cr-radio--card"><input type="radio" name="delivery_location_id" value="{{ $location->id }}" @checked((string) old('delivery_location_id') === (string) $location->id) data-cr-review-source="where"> <span>{{ __($r.'deliver_to_place', ['place' => $location->name]) }}</span></label>
                                    @endforeach
                                </div>
                                @error('delivery_location_id')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                            </fieldset>
                        </div>

                        <details class="cr-more" @if (old('cost_center') || $errors->has('cost_center')) open @endif>
                            <summary>{{ __($r.'more_details') }}</summary>
                            <x-gs::form-row for="cr-field-cost-center" :label="__($r.'cost_centre_label')" :help="__($r.'cost_centre_help')" :error="$errors->first('cost_center')">
                                <input type="text" id="cr-field-cost-center" name="cost_center" class="form-control cr-short" value="{{ old('cost_center') }}" maxlength="50" aria-describedby="cr-field-cost-center-help" data-cr-review-source="cost">
                            </x-gs::form-row>
                        </details>

                        <div class="cr-panel__nav" data-cr-js hidden>
                            <x-gs::button tone="light" outline icon="fa-arrow-left" data-cr-next="1">{{ __($r.'step_items') }}</x-gs::button>
                            <x-gs::button icon="fa-arrow-right" data-cr-next="3">{{ __($r.'draft_continue') }}</x-gs::button>
                        </div>
                    </section>

                    {{-- 3 · Review and send --}}
                    <section class="cr-panel" id="cr-step-3" data-cr-panel="3" aria-labelledby="cr-step-3-title" tabindex="-1">
                        <h2 class="cr-panel__title" id="cr-step-3-title">{{ __($r.'step_review_title') }}</h2>
                        @if ($errors->has('reviewed'))
                            <x-gs::alert tone="warning" :title="$errors->first('reviewed')" id="cr-changed">
                                @if ($changes)
                                    <ul class="cr-flush">
                                        @foreach ($changes as $change)<li>{{ $change }}</li>@endforeach
                                    </ul>
                                @endif
                                <p class="cr-flush">{{ __($r.'changed_check') }}</p>
                            </x-gs::alert>
                        @endif
                        @if ($errors->has('request'))
                            <x-gs::alert tone="danger">{{ $errors->first('request') }}</x-gs::alert>
                        @endif

                        <article class="cr-doc" aria-label="{{ __($r.'doc_label') }}">
                            <header class="cr-doc__head">
                                <div>
                                    <span class="cr-eyebrow">{{ __($r.'doc_eyebrow') }}</span>
                                    <h3 class="cr-doc__title" data-cr-review="purpose" data-empty="{{ __($r.'doc_no_purpose') }}">{{ old('purpose') ?: __($r.'doc_no_purpose') }}</h3>
                                </div>
                                <div class="cr-doc__number">
                                    <span class="cr-meta">{{ __($r.'doc_number') }}</span>
                                    <span class="cr-mono">{{ __($r.'doc_number_later') }}</span>
                                </div>
                            </header>
                            <dl class="cr-doc__kv">
                                <div><dt>{{ __($r.'doc_by') }}</dt><dd>{{ $presenter->name($draft['requester']) }}</dd></div>
                                <div><dt>{{ __($r.'doc_office') }}</dt><dd>{{ $draft['officeName'] }}</dd></div>
                                <div><dt>{{ __($r.'review_kind') }}</dt><dd data-cr-review="kind">{{ __($r.'request_type_'.$kind) }}</dd></div>
                                <div><dt>{{ __($r.'review_needed') }}</dt><dd data-cr-review="needed" data-none="{{ __($r.'when_none') }}" data-week="{{ __($r.'when_week_until', ['date' => $presenter->date(now()->addWeek())]) }}">{{ $needed === 'week' ? __($r.'when_week_until', ['date' => $presenter->date(now()->addWeek())]) : (old('required_by_date') ?: __($r.'when_none')) }}</dd></div>
                                <div><dt>{{ __($r.'review_where') }}</dt><dd data-cr-review="where">{{ __($r.'collect_from_store') }}</dd></div>
                                <div data-cr-review-wrap="cost" @if (! old('cost_center')) hidden @endif><dt>{{ __($r.'cost_centre_label') }}</dt><dd class="cr-mono" data-cr-review="cost">{{ old('cost_center') }}</dd></div>
                            </dl>
                            <div class="cr-doc__why" data-cr-review-wrap="why" @if (! old('justification')) hidden @endif>
                                <span class="cr-eyebrow" data-cr-reason-on @if (! $draft['needsReason']) hidden @endif>{{ __($r.'doc_why') }}</span>
                                <span class="cr-eyebrow" data-cr-reason-off @if ($draft['needsReason']) hidden @endif>{{ __($r.'store_note_label') }}</span>
                                <p class="cr-doc__text" data-cr-review="why">{{ old('justification') }}</p>
                            </div>
                            <div data-cr-fragment="groups">@include('govstore::basket.partials.groups')</div>
                        </article>

                        <div>
                            <h3 class="cr-heading">{{ __($r.'next_title') }}</h3>
                            <ul class="cr-effects">
                                <li data-cr-reason-on @if (! $draft['needsReason']) hidden @endif><i class="fas fa-bell" aria-hidden="true"></i><span>{{ __($r.'next_list_approvers', ['days' => (int) config('govstore-requests.escalation_weekdays', 3)]) }}</span></li>
                                <li><i class="fas fa-box" aria-hidden="true"></i><span>{{ __($r.'next_list_set_aside') }}</span></li>
                                <li><i class="fas fa-hand-holding" aria-hidden="true"></i><span>{{ __($r.'next_list_store') }}</span></li>
                            </ul>
                        </div>

                        <label class="cr-declare {{ $errors->has('declared') ? 'cr-declare--error' : '' }}" for="cr-field-declared" id="cr-field-declared-wrap">
                            <input type="checkbox" id="cr-field-declared" name="declared" value="1" required data-cr-message="{{ __($r.'declare_required') }}" @error('declared') aria-invalid="true" @enderror>
                            <span>
                                {{ __($r.'declare_text', ['office' => $draft['officeName']]) }}
                                <span class="cr-meta"><i class="fas fa-shield-halved" aria-hidden="true"></i> {{ __($r.'declare_recorded') }}</span>
                            </span>
                        </label>
                        @error('declared')<p class="gs-form-row__error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>@enderror

                        <div class="cr-panel__nav">
                            <x-gs::button tone="light" outline icon="fa-arrow-left" data-cr-next="2" data-cr-js hidden>{{ __($r.'step_details') }}</x-gs::button>
                            <x-gs::button type="submit" icon="fa-paper-plane" data-cr-submit data-sending="{{ __($r.'sending') }}"><span data-cr-send-label>{{ trans_choice($r.'draft_send', max(1, $count), ['count' => $count]) }}</span></x-gs::button>
                        </div>
                    </section>
                </form>
            </div>

            <aside class="cr-checkout__aside" data-cr-fragment="summary">
                @include('govstore::basket.partials.summary')
            </aside>
        </div>
    @endif
</div>
@endsection
