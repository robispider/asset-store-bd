@extends('layouts/default')

@section('title', __('requestlabels::requests.rules_title'))

{{--
    Request rules (board 12, F7): rules apply to every office, so the page says so; plain choices instead
    of AUTO_APPROVE/…; the limits as labelled numbers with a live example; "Not set" for categories without
    a rule; who changed each rule (D4); item exceptions found by name. Every save is a national change and
    goes through the review and confirmation step. Expects the data from GovApprovalController::policiesIndex.
--}}
@php
    $r = 'requestlabels::requests.';
    $sentence = function ($policy) use ($r) {
        $base = __($r.match ($policy->policy_name) { 'AUTO_APPROVE' => 'rule_sentence_auto', 'PRIMARY_AND_FINAL' => 'rule_sentence_two', default => 'rule_sentence_one' });
        if ($policy->policy_name === 'PRIMARY_AND_FINAL') {
            return $base;
        }
        $qty = $policy->threshold_qty;
        $value = $policy->threshold_value !== null ? number_format((float) $policy->threshold_value) : null;
        $limit = match (true) {
            $qty && $value => __($r.'rule_limit_both', ['qty' => $qty, 'value' => $value]),
            (bool) $qty => __($r.'rule_limit_qty', ['qty' => $qty]),
            (bool) $value => __($r.'rule_limit_value', ['value' => $value]),
            default => null,
        };

        return trim($base.' '.$limit);
    };
    $setBy = fn ($policy) => $policy->updated_by
        ? __($r.'rule_set_by', ['date' => $presenter->date($policy->updated_at), 'name' => $presenter->name($policy->updated_by)])
        : __($r.'rule_set_on', ['date' => $presenter->date($policy->updated_at)]);
    $examples = ['auto' => __($r.'ex_auto'), 'one' => __($r.'ex_one'), 'two' => __($r.'ex_two'),
        'qty' => __($r.'ex_qty', ['qty' => '__Q__']), 'value' => __($r.'ex_value', ['value' => '__V__'])];
    $editing = old('category_id') ? (int) old('category_id') : null;
@endphp

@section('content')
<div class="cr" data-cr-rules>
    <x-gs::page-header :title="__($r.'rules_title')" :subtitle="__($r.'rules_subtitle')" />

    <x-gs::alert tone="info" :title="__($r.'rules_banner_title')" data-cr-national>
        {{ __($r.'rules_banner_body') }}
    </x-gs::alert>

    @include('govstore::partials.errors')

    <x-gs::box :title="__($r.'rules_categories')" icon="fas fa-tags">
        <ul class="cr-rules" aria-label="{{ __($r.'rules_categories_caption') }}">
            @foreach ($categories as $category)
                @php
                    $policy = $policies[$category->id] ?? null;
                    $current = old('category_id') == $category->id ? old('policy_name') : ($policy?->policy_name ?? 'PRIMARY_ONLY');
                    $formId = 'cr-rule-'.$category->id;
                @endphp
                <li class="cr-rule" data-category-id="{{ $category->id }}" data-policy="{{ $policy?->policy_name ?? 'unset' }}">
                    <div class="cr-rule__head">
                        <div>
                            <strong class="cr-rule__name">{{ $category->name }}</strong>
                            <span class="cr-rule__sentence" data-cr-rule-sentence>{{ $policy ? $sentence($policy) : __($r.'rule_not_set') }}</span>
                            @if ($policy)<span class="cr-meta">{{ $setBy($policy) }}</span>@endif
                        </div>
                    </div>
                    <details class="cr-rule__edit" @if ($editing === (int) $category->id) open @endif>
                        <summary class="gs-btn gs-btn--light gs-btn--outline gs-btn--sm" aria-label="{{ __($r.($policy ? 'rule_change_label' : 'rule_set_label'), ['name' => $category->name]) }}">
                            {{ __($r.($policy ? 'rule_change' : 'rule_set')) }}
                        </summary>
                        <form method="POST" action="{{ route('gov.requests.admin.policies.store') }}" id="{{ $formId }}" class="cr-stack cr-rule__form" data-cr-rule-form
                            data-examples='@json($examples)'>
                            @csrf
                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                            <fieldset class="cr-fieldset">
                                <legend class="cr-label">{{ __($r.'rule_who') }}</legend>
                                <div class="gs-choices">
                                    @foreach (['AUTO_APPROVE' => 'auto', 'PRIMARY_ONLY' => 'one', 'PRIMARY_AND_FINAL' => 'two'] as $value => $key)
                                        <x-gs::choice-card name="policy_name" :value="$value" :id="$formId.'-'.$key" :label="__($r.'policy_'.$key)" :description="__($r.'policy_'.$key.'_desc')" :checked="$current === $value" />
                                    @endforeach
                                </div>
                            </fieldset>
                            <x-gs::alert tone="warning" data-cr-when="policy_name=AUTO_APPROVE">{{ __($r.'policy_auto_warning') }}</x-gs::alert>
                            <div class="cr-limits" data-cr-unless="policy_name=PRIMARY_AND_FINAL">
                                <x-gs::form-row :for="$formId.'-qty'" :label="__($r.'limit_qty_label')" :error="old('category_id') == $category->id ? $errors->first('threshold_qty') : null">
                                    <input type="number" id="{{ $formId }}-qty" name="threshold_qty" class="form-control cr-short" min="1" max="10000" inputmode="numeric"
                                        value="{{ old('category_id') == $category->id ? old('threshold_qty') : $policy?->threshold_qty }}" aria-describedby="{{ $formId }}-limits-help">
                                </x-gs::form-row>
                                <x-gs::form-row :for="$formId.'-value'" :label="__($r.'limit_value_label')" :error="old('category_id') == $category->id ? $errors->first('threshold_value') : null">
                                    <input type="number" id="{{ $formId }}-value" name="threshold_value" class="form-control cr-short" min="0.01" step="0.01" inputmode="decimal"
                                        value="{{ old('category_id') == $category->id ? old('threshold_value') : $policy?->threshold_value }}" aria-describedby="{{ $formId }}-limits-help">
                                </x-gs::form-row>
                                <p class="gs-form-row__help cr-flush" id="{{ $formId }}-limits-help">{{ __($r.'limit_help') }}</p>
                            </div>
                            <div class="cr-example">
                                <strong>{{ __($r.'example_title') }}</strong>
                                <ul class="cr-plain" data-cr-example aria-live="polite"></ul>
                            </div>
                            <div class="cr-toolbar">
                                <x-gs::button type="submit" icon="fa-floppy-disk">{{ __($r.'rule_save') }}</x-gs::button>
                                @if ($policy)
                                    <x-gs::button type="submit" tone="light" outline form="{{ $formId }}-remove" aria-label="{{ __($r.'rule_remove_label', ['name' => $category->name]) }}">{{ __($r.'rule_remove') }}</x-gs::button>
                                @endif
                                <span class="cr-meta">{{ __($r.'rule_save_note') }}</span>
                            </div>
                        </form>
                        @if ($policy)
                            <form method="POST" action="{{ route('gov.requests.admin.policies.destroy', $policy->id) }}" id="{{ $formId }}-remove" class="cr-hidden-form">@csrf</form>
                        @endif
                    </details>
                </li>
            @endforeach
        </ul>
    </x-gs::box>

    <x-gs::box :title="__($r.'exceptions_title')" icon="fas fa-star">
        <p class="cr-flush">{{ __($r.'exceptions_body') }}</p>
        @if ($exceptions->isEmpty())
            <p class="cr-muted cr-flush">{{ __($r.'exceptions_none') }}</p>
        @else
            <x-gs::table :caption="__($r.'exceptions_caption')" class="cr-cards-sm">
                <thead><tr><th scope="col">{{ __($r.'col_item') }}</th><th scope="col">{{ __($r.'about_rule') }}</th><th scope="col"><span class="gs-sr-only">{{ __($r.'exception_remove') }}</span></th></tr></thead>
                <tbody>
                    @foreach ($exceptions as $exception)
                        @php $itemName = $presenter->itemName($exception->target_type, (int) $exception->target_id); @endphp
                        <tr>
                            <td data-label="{{ __($r.'col_item') }}"><strong>{{ $itemName }}</strong> <span class="cr-meta">{{ $presenter->typeLabel($exception->target_type) }}</span></td>
                            <td data-label="{{ __($r.'about_rule') }}">{{ $sentence($exception) }} <span class="cr-meta">{{ $setBy($exception) }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('gov.requests.admin.policies.destroy', $exception->id) }}" class="cr-inline-form">
                                    @csrf
                                    <x-gs::button type="submit" tone="light" outline size="sm" icon="fa-trash-can" aria-label="{{ __($r.'exception_remove_label', ['name' => $itemName]) }}">{{ __($r.'exception_remove') }}</x-gs::button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-gs::table>
        @endif

        <details class="cr-more" @if (old('target_type')) open @endif>
            <summary>{{ __($r.'exception_add') }}</summary>
            <form method="POST" action="{{ route('gov.requests.admin.policies.store') }}" class="cr-stack" id="cr-exception" data-cr-rule-form data-examples='@json($examples)'>
                @csrf
                <div class="cr-inline cr-inline--wrap">
                    <div class="cr-filter">
                        <label for="cr-exception-type" class="cr-label">{{ __($r.'exception_kind') }}</label>
                        <select id="cr-exception-type" name="target_type" class="form-control" data-cr-item-type>
                            @foreach (['consumable', 'accessory', 'asset_model'] as $type)
                                <option value="{{ $type }}" @selected(old('target_type') === $type)>{{ __($r.'type_'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cr-filter cr-filter--grow">
                        <label for="cr-exception-q" class="cr-label">{{ __($r.'exception_find') }}</label>
                        <input type="search" id="cr-exception-q" class="form-control" autocomplete="off" placeholder="{{ __($r.'exception_find_placeholder') }}"
                            data-cr-item-search="{{ route('gov.requests.admin.policies.items') }}" data-none="{{ __($r.'exception_none_found') }}">
                    </div>
                </div>
                <fieldset class="cr-fieldset" id="cr-field-target-id">
                    <legend class="cr-label">{{ __($r.'exception_pick') }}</legend>
                    <ul class="cr-alt__results" data-cr-item-results aria-live="polite"><li class="cr-meta">{{ __($r.'exception_pick_none') }}</li></ul>
                    <noscript><label class="cr-label" for="cr-exception-id">{{ __($r.'exception_pick') }} (ID)</label><input type="number" id="cr-exception-id" name="target_id" class="form-control cr-short" min="1"></noscript>
                    @error('target_id')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <fieldset class="cr-fieldset">
                    <legend class="cr-label">{{ __($r.'rule_who') }}</legend>
                    <div class="gs-choices">
                        @foreach (['AUTO_APPROVE' => 'auto', 'PRIMARY_ONLY' => 'one', 'PRIMARY_AND_FINAL' => 'two'] as $value => $key)
                            <x-gs::choice-card name="policy_name" :value="$value" :id="'cr-exception-'.$key" :label="__($r.'policy_'.$key)" :description="__($r.'policy_'.$key.'_desc')" :checked="old('policy_name', 'PRIMARY_AND_FINAL') === $value" />
                        @endforeach
                    </div>
                </fieldset>
                <x-gs::alert tone="warning" data-cr-when="policy_name=AUTO_APPROVE">{{ __($r.'policy_auto_warning') }}</x-gs::alert>
                <div class="cr-limits" data-cr-unless="policy_name=PRIMARY_AND_FINAL">
                    <x-gs::form-row for="cr-exception-qty" :label="__($r.'limit_qty_label')">
                        <input type="number" id="cr-exception-qty" name="threshold_qty" class="form-control cr-short" min="1" max="10000" value="{{ old('target_type') ? old('threshold_qty') : '' }}">
                    </x-gs::form-row>
                    <x-gs::form-row for="cr-exception-value" :label="__($r.'limit_value_label')">
                        <input type="number" id="cr-exception-value" name="threshold_value" class="form-control cr-short" min="0.01" step="0.01" value="{{ old('target_type') ? old('threshold_value') : '' }}">
                    </x-gs::form-row>
                </div>
                <div class="cr-example">
                    <strong>{{ __($r.'example_title') }}</strong>
                    <ul class="cr-plain" data-cr-example aria-live="polite"></ul>
                </div>
                <div class="cr-toolbar">
                    <x-gs::button type="submit" icon="fa-floppy-disk">{{ __($r.'rule_save') }}</x-gs::button>
                    <span class="cr-meta">{{ __($r.'rule_save_note') }}</span>
                </div>
            </form>
        </details>
    </x-gs::box>
</div>
@endsection
