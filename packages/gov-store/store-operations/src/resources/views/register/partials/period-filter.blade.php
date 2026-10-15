{{-- Period, entry type and search for the ledger table. Plain GET form: works without JavaScript. --}}
@php
    $c = 'storeops::storeops.stock_card.';
    $period = $card['period'];
    $fyStart = \GovStore\StoreOperations\DTOs\LedgerPeriod::financialYearStart(\Carbon\CarbonImmutable::now());
    $periods = [
        'fy' => __($c.'period_fy', ['fy' => $period->financialYearLabel(), 'date' => $fyStart->translatedFormat('d M Y')]),
        '90d' => __($c.'period_90d'),
        'month' => __($c.'period_month', ['month' => now()->translatedFormat('F Y')]),
        'custom' => __($c.'period_custom'),
    ];
    $kindQuery = array_filter(array_merge($period->query(), ['q' => $card['q'] ?: null]));
@endphp
<form method="get" class="storeops-toolbar storeops-noprint" role="search" aria-label="{{ __($c.'filter_label') }}">
    <div class="storeops-field">
        <label for="sc-period" class="storeops-label">{{ __($c.'period') }}</label>
        <select id="sc-period" name="period" class="form-control storeops-control">
            @foreach ($periods as $key => $label)
                <option value="{{ $key }}" @selected($period->key === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="storeops-field storeops-field--dates">
        <label for="sc-from" class="storeops-label">{{ __($c.'from') }}</label>
        <input id="sc-from" type="date" name="from" class="form-control storeops-control" value="{{ $period->key === 'custom' ? $period->start->toDateString() : '' }}">
    </div>
    <div class="storeops-field storeops-field--dates">
        <label for="sc-to" class="storeops-label">{{ __($c.'to') }}</label>
        <input id="sc-to" type="date" name="to" class="form-control storeops-control" value="{{ $period->key === 'custom' ? $period->end->toDateString() : '' }}">
    </div>
    <div class="storeops-field storeops-field--grow">
        <label for="sc-q" class="storeops-label">{{ __($c.'find_entry') }}</label>
        <input id="sc-q" type="search" name="q" class="form-control storeops-control" value="{{ $card['q'] }}" placeholder="{{ __($c.'find_entry_hint') }}">
    </div>
    @if ($card['kind'] !== 'all')<input type="hidden" name="kind" value="{{ $card['kind'] }}">@endif
    <button type="submit" class="btn btn-primary storeops-control">{{ __($c.'apply') }}</button>
</form>
<nav class="storeops-seg storeops-noprint" aria-label="{{ __($c.'entry_type') }}">
    @foreach (\GovStore\StoreOperations\Services\StockCardBuilder::KINDS as $kind)
        <a href="{{ request()->url().'?'.http_build_query(array_filter(array_merge($kindQuery, ['kind' => $kind === 'all' ? null : $kind]))) }}"
            @if ($card['kind'] === $kind) aria-current="true" @endif>{{ __($c.'kind_filters.'.$kind) }}</a>
    @endforeach
</nav>
<p class="gs-muted storeops-count">
    {{ __($c.'showing', ['shown' => $card['entries']->total(), 'total' => $card['period_count']]) }}
    @if ($card['filtered'])<br>{{ __($c.'filtered_note') }}@endif
</p>
