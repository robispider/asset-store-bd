<!DOCTYPE html>
@php
    $c = 'storeops::storeops.stock_card.';
    $r = 'storeops::storeops.stock_register.';
    $gsPrintAttributes = app()->bound('gs.theme') ? ' '.app('gs.theme')->htmlAttributes() : '';
    $prefix = ['consumable' => 'CON', 'accessory' => 'ACC', 'component' => 'CMP', 'assetmodel' => 'AST'][$type];
    $cardNumber = 'SC-'.$prefix.'-'.str_pad((string) $item->id, 4, '0', STR_PAD_LEFT);
    $period = $card['period'];
    $printer = $printedBy ? trim($printedBy->first_name.' '.$printedBy->last_name).($printedBy->jobtitle ? ' ('.$printedBy->jobtitle.')' : '') : '—';
    $isAsset = $type === 'assetmodel';
    $details = array_filter([
        __($c.'item') => $item->name,
        __($c.'item_code') => $facts['code'],
        __($r.'col_category') => $facts['category'],
        __($c.'unit_of_issue') => $facts['unit'],
        __($r.'reorder_level') => $facts['reorder'],
        __($c.'period') => $period->start->translatedFormat('d M Y').' – '.$period->end->translatedFormat('d M Y'),
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<html lang="{{ app()->getLocale() }}"{!! $gsPrintAttributes !!}>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $cardNumber }} · {{ $item->name }}</title>
    @if (view()->exists('gs-theme::head'))
        @include('gs-theme::head')
    @endif
</head>
<body class="storeops-printcard-body">
<div class="storeops-printcard-tools storeops-noprint">
    <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> {{ __($c.'print_now') }}</button>
    <a class="btn btn-default" href="{{ route('storeops.register.kardex', array_merge(['type' => $type, 'id' => $item->id], $period->query())) }}">{{ __($c.'back_to_card') }}</a>
</div>
<main class="storeops-printcard">
    <header class="storeops-printcard__head">
        <div>
            @if ($office['company'])<span class="storeops-printcard__muted">{{ $office['company'] }}</span>@endif
            <strong class="storeops-printcard__office">{{ $office['name'] }}</strong>
            @if ($office['address'])<span class="storeops-printcard__muted">{{ $office['address'] }}</span>@endif
        </div>
        <div class="storeops-printcard__title">
            <span class="storeops-printcard__name">{{ $isAsset ? __('storeops::storeops.asset_register.print_title') : __($c.'print_title') }}</span>
            <span lang="bn" class="storeops-printcard__bn">{{ $isAsset ? 'সম্পদ কার্ড' : 'স্টক কার্ড' }}</span>
            <span class="storeops-mono">{{ __($c.'card_number', ['number' => $cardNumber, 'fy' => $period->financialYearLabel()]) }}</span>
        </div>
    </header>

    <section class="storeops-printcard__facts" aria-label="{{ __($c.'item_details') }}">
        @foreach ($details as $label => $value)
            <div><span class="storeops-printcard__label">{{ $label }}</span><strong>{{ $value }}</strong></div>
        @endforeach
    </section>

    <table class="storeops-printcard__table">
        <thead>
            <tr>
                <th scope="col">{{ __($c.'col_date') }}</th>
                <th scope="col">{{ __($c.'document_no') }}</th>
                <th scope="col">{{ __($c.'col_particulars') }}</th>
                @if ($isAsset)<th scope="col">{{ __($c.'col_tags') }}</th>@endif
                <th scope="col" class="gs-num">{{ __($c.'col_received') }}</th>
                <th scope="col" class="gs-num">{{ __($c.'col_issued') }}</th>
                <th scope="col" class="gs-num">{{ __($c.'col_balance') }}</th>
                <th scope="col">{{ __($c.'col_posted_by') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="storeops-printcard__bf">
                <td>{{ $period->start->translatedFormat('d M Y') }}</td><td>—</td>
                <td colspan="{{ $isAsset ? 4 : 3 }}">{{ __($c.'brought_forward') }}</td>
                <td class="gs-num"><strong>{{ $card['bf'] }}</strong></td><td></td>
            </tr>
            @foreach ($card['period_entries'] as $entry)
                <tr>
                    <td>{{ $entry['at']->translatedFormat('d M Y') }}</td>
                    <td class="storeops-mono">{{ $entry['document_number'] ?? '—' }}</td>
                    <td>
                        {{ __($c.'kinds.'.$entry['kind']) }}@if ($entry['party']): {{ $entry['party'] }}@endif
                        @if ($entry['reference']) ({{ $entry['reference'] }})@endif
                    </td>
                    @if ($isAsset)<td class="storeops-mono">{{ implode(', ', $entry['tags']) }}</td>@endif
                    <td class="gs-num">{{ $entry['direction'] === 'IN' ? $entry['qty'] : '' }}</td>
                    <td class="gs-num">{{ $entry['direction'] === 'OUT' ? $entry['qty'] : '' }}</td>
                    <td class="gs-num">{{ $entry['balance'] }}</td>
                    <td>{{ $entry['poster']['name'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>{{ $period->end->translatedFormat('d M Y') }}</td><td></td>
                <td @if ($isAsset) colspan="2" @endif>{{ __($c.'period_totals') }}</td>
                <td class="gs-num">{{ $card['in'] }}</td>
                <td class="gs-num">{{ $card['out'] }}</td>
                <td class="gs-num">{{ $card['closing'] }}</td><td></td>
            </tr>
        </tfoot>
    </table>

    <section class="storeops-printcard__statement" aria-label="{{ __($c.'reconciliation') }}">
        <div>
            <strong>{{ __($c.'reconciliation') }}</strong>
            <span>{{ __($c.'reconciliation_line', ['bf' => $card['bf'], 'in' => $card['in'], 'out' => $card['out'], 'closing' => $card['closing']]) }}</span>
            <span>{{ $card['chain']['ok']
                ? trans_choice($c.'print_chain_ok', $card['chain']['entries'], ['count' => $card['chain']['entries'], 'time' => $card['checked_at']->translatedFormat('d M Y, H:i T')])
                : __($c.'chain_broken_text') }}</span>
        </div>
        <div class="storeops-printcard__code">
            <span class="storeops-printcard__label">{{ __($c.'fingerprint') }}</span>
            <strong class="storeops-mono">{{ $card['fingerprint'] }}</strong>
        </div>
    </section>

    <section class="storeops-printcard__signatures" aria-label="{{ __($c.'signatures') }}">
        @foreach (['prepared', 'checked', 'approved'] as $block)
            <div>
                <strong>{{ __($c.'sign.'.$block) }}</strong>
                <span>{{ __($c.'sign.'.$block.'_role') }}</span>
                <span class="storeops-printcard__muted">{{ __($c.'sign.'.$block.'_hint') }}</span>
            </div>
        @endforeach
    </section>

    <footer class="storeops-printcard__foot">
        {{ __($c.'printed_by', ['name' => $printer, 'time' => now()->translatedFormat('d M Y, H:i T'), 'code' => $card['fingerprint']]) }}
    </footer>
</main>
</body>
</html>
