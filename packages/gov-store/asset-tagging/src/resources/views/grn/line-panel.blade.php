{{-- issue_govt_tags renderUI: the tag panel under one GRN asset line (additions only). --}}
@php
    $base = 'items['.$index.'][meta]['.\GovStore\AssetTagging\Services\LineTagResolver::LINE_ROW.']';
    $kind = $plan['kind'];
    $count = $plan['quantity'];
    $format = app(\GovStore\AssetTagging\Formats\FinanceDivisionV1Format::class);
    $native = app(\GovStore\AssetTagging\Formats\NativeSequenceV1Format::class);
    $facts = app(\GovStore\AssetTagging\Services\OfficeTagFacts::class);
    $lists = $facts->codeLists($context['company_id']);
    $officeDivisions = $context['office']['division_codes'] ?? [];
    $default = $plan['default'];
    $defaultItem = ($default['status'] ?? null) === 'ok' ? $default['item'] : null;
    $lineItem = $choices['gov_code'] ? \GovStore\Classification\Models\FinanceItem::find($choices['gov_code']) : null;
    $chosenStandard = $choices['tag_choice'] === 'standard';
    $showGovt = in_array($kind, ['govt', 'attention'], true) && $plan['gov_code'] && ($plan['attention'] ?? null) !== 'invalid_code';
    $segments = $showGovt
        ? $format->segments(['gov_code' => $plan['gov_code'], 'acquired_on' => $plan['acquired_on'], 'location_code' => $plan['location_code'], 'division_code' => $plan['division_code']], null)
        : $native->segments([], null);
    $ownerLabel = null;
    if ($defaultItem) {
        $ownerLabel = $default['scope'][0] === 'national'
            ? __('asset-tagging::tagging.panel.owner_national')
            : __('asset-tagging::tagging.panel.owner_company', ['company' => \Illuminate\Support\Facades\DB::table('companies')->where('id', $default['scope'][1])->value('name')]);
    }
    $selectUrl = route('gov.catalog.finance.selectlist');
@endphp
<div class="at-tagpanel" data-storeops-refresh @if (($plan['attention'] ?? null) === 'date') data-storeops-attention="references[0][reference_date]" @endif>
    <div class="at-tagpanel__head">
        <span class="at-tagpanel__title"><i class="fas fa-tag" aria-hidden="true"></i> {{ __('asset-tagging::tagging.panel.title') }}</span>
        @if ($kind === 'govt')
            <x-gs::badge tone="primary">{{ __('asset-tagging::tagging.kind.govt') }}</x-gs::badge>
        @elseif ($kind === 'standard')
            <x-gs::badge tone="secondary">{{ __('asset-tagging::tagging.kind.standard') }}</x-gs::badge>
        @endif
    </div>
    <input type="hidden" name="{{ $base }}[tag_choice]" value="{{ $choices['tag_choice'] }}">

    @if ($kind === 'paused')
        <div class="at-note at-note--warning" role="status">
            <i class="fas fa-pause-circle" aria-hidden="true"></i>
            <div class="at-note__body">{{ __('asset-tagging::tagging.panel.paused') }}</div>
        </div>
    @elseif (! $context['govt_source'])
        <div class="at-note at-note--info" role="status">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            <div class="at-note__body">{{ __('asset-tagging::tagging.panel.receiving_source') }}</div>
        </div>
    @else
        {{-- Finance Code: from the product type (read-only, owner named), this line's choice, or the no-code warning. --}}
        @if ($defaultItem && ! $choices['gov_code'])
            <div class="at-tagpanel__row">
                <span class="at-tagpanel__label">{{ __('asset-tagging::tagging.panel.finance_code') }}</span>
                <span><strong class="gs-mono">{{ $defaultItem->gov_code }}</strong> · {{ $defaultItem->item_name }}</span>
                <x-gs::badge tone="info" outline>{{ __('asset-tagging::tagging.panel.from_product_type') }}</x-gs::badge>
                <span class="at-owner"><i class="fas fa-user-shield" aria-hidden="true"></i> {{ $ownerLabel }}</span>
            </div>
        @elseif ($choices['gov_code'])
            <div class="at-tagpanel__row">
                <label class="at-tagpanel__label" for="at-code-{{ $index }}">{{ __('asset-tagging::tagging.panel.finance_code') }}</label>
                <div class="at-tagpanel__control">
                    <select id="at-code-{{ $index }}" name="{{ $base }}[gov_code]" class="form-control" data-storeops-select2="{{ $selectUrl }}"
                        data-category-id="{{ $plan['category_id'] }}" data-placeholder="{{ __('asset-tagging::tagging.fix.choose_line') }}" @disabled(! $editable)>
                        <option value="{{ $choices['gov_code'] }}" selected>{{ $lineItem ? $lineItem->label() : $choices['gov_code'] }}</option>
                    </select>
                </div>
                <x-gs::badge tone="info" outline>{{ __('asset-tagging::tagging.panel.from_line') }}</x-gs::badge>
            </div>
            @if (($plan['attention'] ?? null) === 'invalid_code')
                <div class="at-note at-note--danger" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <div class="at-note__body">{{ __('asset-tagging::tagging.panel.invalid_code') }}</div>
                </div>
            @endif
        @elseif (! $chosenStandard)
            <div class="at-note at-note--warning" role="status">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <div class="at-note__body">
                    <span>{{ __('asset-tagging::tagging.warn.no_finance', ['count' => $count]) }}</span>
                    @if ($editable)
                        <label class="gs-sr-only" for="at-code-{{ $index }}">{{ __('asset-tagging::tagging.fix.choose_line') }}</label>
                        <select id="at-code-{{ $index }}" name="{{ $base }}[gov_code]" class="form-control" data-storeops-select2="{{ $selectUrl }}"
                            data-category-id="{{ $plan['category_id'] }}" data-placeholder="{{ __('asset-tagging::tagging.fix.choose_line') }}">
                            <option value=""></option>
                        </select>
                        <div class="at-tagpanel__actions">
                            <button type="button" class="btn btn-default btn-sm" data-storeops-set="{{ $base }}[tag_choice]" data-value="standard">
                                {{ __('asset-tagging::tagging.fix.keep_standard') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($chosenStandard)
            <div class="at-note at-note--info" role="status">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <div class="at-note__body">
                    <span>{{ __('asset-tagging::tagging.panel.standard_chosen') }}</span>
                    @if ($editable)
                        <div class="at-tagpanel__actions">
                            <button type="button" class="btn btn-default btn-sm" data-storeops-set="{{ $base }}[tag_choice]" data-value="">
                                {{ __('asset-tagging::tagging.fix.use_govt') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if (($plan['attention'] ?? null) === 'date')
            <div class="at-note at-note--danger" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <div class="at-note__body">
                    <strong>{{ __('asset-tagging::tagging.attention.label') }}</strong>
                    <span>{{ __('asset-tagging::tagging.attention.date_line') }}</span>
                    @if ($editable)
                        <div class="at-tagpanel__actions">
                            <button type="button" class="btn btn-default btn-sm" data-storeops-focus="references[0][reference_date]">{{ __('asset-tagging::tagging.fix.go_to_date') }}</button>
                            <button type="button" class="btn btn-default btn-sm" data-storeops-set="{{ $base }}[tag_choice]" data-value="standard">{{ __('asset-tagging::tagging.fix.use_standard') }}</button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        @if (($plan['attention'] ?? null) === 'typed_tag')
            <div class="at-note at-note--danger" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <div class="at-note__body">{{ __('asset-tagging::tagging.attention.typed_tag_line') }}</div>
            </div>
        @endif

        @if (count($officeDivisions) > 1 && $showGovt)
            <div class="at-tagpanel__row">
                <label class="at-tagpanel__label" for="at-division-{{ $index }}">{{ __('asset-tagging::tagging.panel.division') }}</label>
                <div class="at-tagpanel__control">
                    <select id="at-division-{{ $index }}" name="{{ $base }}[division_code]" class="form-control input-sm" @disabled(! $editable)>
                        @foreach ($officeDivisions as $code)
                            <option value="{{ $code }}" @selected($plan['division_code'] === $code)>{{ $code }} · {{ $facts->name($lists['divisions'], $code) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
    @endif

    <div class="at-preview">
        <div class="at-preview__head">
            <span class="gs-muted">{{ __('asset-tagging::tagging.panel.preview') }}</span>
            <span>{{ $showGovt ? trans_choice('asset-tagging::tagging.panel.count_govt', $count, ['count' => $count]) : trans_choice('asset-tagging::tagging.panel.count_standard', $count, ['count' => $count]) }}</span>
        </div>
        <x-gs::code-segments :segments="$segments" :label="__('asset-tagging::tagging.panel.preview')" />
    </div>
</div>
