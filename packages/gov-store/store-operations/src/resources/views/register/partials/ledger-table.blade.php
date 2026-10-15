{{--
    Ledger entries, newest last like the paper register. Brought-forward opens the first page and the
    period totals close the last, so every page of a printout adds up. $showTags adds the asset-tag column.
--}}
@php
    $c = 'storeops::storeops.stock_card.';
    $na = '—';
    $showTags = $showTags ?? false;
    $entries = $card['entries'];
    $columns = $showTags ? 9 : 8;
    $kindTones = ['receipt' => 'success', 'transfer_in' => 'success', 'issue' => 'info', 'adjustment' => 'warning',
        'transfer' => 'secondary', 'opening' => 'primary', 'other' => 'secondary'];
    $selectedId = $card['selected']['id'] ?? null;
@endphp
<x-gs::table class="storeops-ledger">
    <thead>
        <tr>
            <th scope="col">{{ __($c.'col_date') }}</th>
            <th scope="col">{{ __($c.'col_document') }}</th>
            <th scope="col">{{ __($c.'col_particulars') }}</th>
            <th scope="col">{{ __($c.'col_posted_by') }}</th>
            @if ($showTags)<th scope="col">{{ __($c.'col_tags') }}</th>@endif
            <th scope="col" class="gs-num">{{ __($c.'col_in') }}</th>
            <th scope="col" class="gs-num">{{ __($c.'col_out') }}</th>
            <th scope="col" class="gs-num">{{ __($c.'col_balance') }}</th>
            <th scope="col" class="storeops-noprint"><span class="gs-sr-only">{{ __($c.'col_details') }}</span></th>
        </tr>
    </thead>
    <tbody>
        @if ($entries->onFirstPage())
            <tr class="storeops-ledger__bf">
                <td>{{ $card['period']->start->translatedFormat('d M Y') }}</td>
                <td>{{ $na }}</td>
                <td colspan="{{ $showTags ? 3 : 2 }}">{{ __($c.'brought_forward') }}</td>
                <td class="gs-num">{{ $na }}</td>
                <td class="gs-num">{{ $na }}</td>
                <td class="gs-num"><strong>{{ $card['bf'] }}</strong></td>
                <td class="storeops-noprint"></td>
            </tr>
        @endif
        @forelse ($entries as $entry)
            <tr @class(['storeops-ledger__row--selected' => $entry['id'] === $selectedId]) id="entry-{{ $entry['id'] }}">
                <td>
                    {{ $entry['at']->translatedFormat('d M Y') }}<br>
                    <span class="gs-muted">{{ $entry['at']->format('H:i') }}</span>
                </td>
                <td>
                    @if ($entry['document_number'] && ! $entry['document_type'])
                        <span class="storeops-mono">{{ $entry['document_number'] }}</span><br>
                    @elseif ($entry['document_number'])
                        <a class="storeops-mono" href="{{ route('storeops.documents.workspace', ['type' => $entry['document_type'], 'id' => $entry['document_id']]) }}">{{ $entry['document_number'] }}</a><br>
                    @endif
                    <x-gs::badge :tone="$kindTones[$entry['kind']] ?? 'secondary'">{{ __($c.'kinds.'.$entry['kind']) }}</x-gs::badge>
                </td>
                <td>
                    @if ($entry['party'])
                        {{ __($c.'parties.'.$entry['party_key']) }}: <strong>{{ $entry['party'] }}</strong>
                    @elseif ($entry['kind'] === 'opening')
                        {{ __($c.'opening_particulars') }}
                    @endif
                    @if ($entry['reference'])<br><span class="gs-muted">{{ $entry['reference'] }}</span>@endif
                    @if ($entry['corrects'])<br><span class="gs-muted">{{ __($c.'corrects', ['document' => $entry['corrects']]) }}</span>@endif
                </td>
                <td>
                    {{ $entry['poster']['name'] ?? $na }}
                    @if ($entry['poster']['role'] ?? null)<br><span class="gs-muted">{{ $entry['poster']['role'] }}</span>@endif
                </td>
                @if ($showTags)<td class="storeops-mono">{{ implode(', ', $entry['tags']) ?: $na }}</td>@endif
                <td class="gs-num storeops-ledger-in">
                    @if ($entry['direction'] === 'IN')<i class="fas fa-arrow-down" aria-hidden="true"></i> +{{ $entry['qty'] }}@else{{ $na }}@endif
                </td>
                <td class="gs-num storeops-ledger-out">
                    @if ($entry['direction'] === 'OUT')<i class="fas fa-arrow-up" aria-hidden="true"></i> −{{ $entry['qty'] }}@else{{ $na }}@endif
                </td>
                <td class="gs-num">
                    <strong>{{ $entry['balance'] }}</strong>
                    @if ($entry['low'])
                        <br><span class="storeops-low"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($c.'below_reorder_short') }}</span>
                    @endif
                </td>
                <td class="storeops-noprint">
                    <a href="{{ request()->fullUrlWithQuery(['entry' => $entry['id']]) }}#entry-details" class="btn btn-default btn-sm"
                        @if ($entry['id'] === $selectedId) aria-current="true" @endif>
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <span class="gs-sr-only">{{ __($c.'details_of', ['document' => $entry['document_number'] ?? $na]) }}</span>
                    </a>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ $columns + 1 }}" class="gs-table__empty">{{ __($c.'no_entries') }}</td></tr>
        @endforelse
    </tbody>
    @if (! $entries->hasMorePages())
        <x-slot:totals>
            <tr class="storeops-ledger__total">
                <td>{{ $card['period']->end->translatedFormat('d M Y') }}</td>
                <td>{{ $na }}</td>
                <td colspan="{{ $showTags ? 3 : 2 }}">{{ __($c.'period_totals') }}</td>
                <td class="gs-num">+{{ $card['in'] }}</td>
                <td class="gs-num">−{{ $card['out'] }}</td>
                <td class="gs-num">{{ $card['closing'] }}</td>
                <td class="storeops-noprint"></td>
            </tr>
        </x-slot:totals>
    @endif
</x-gs::table>
{{ $entries->fragment('ledger')->links() }}
