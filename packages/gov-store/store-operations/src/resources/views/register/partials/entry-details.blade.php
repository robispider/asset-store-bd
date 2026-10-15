{{-- One ledger entry in full: who drafted and who posted it, its document, and how to correct it. --}}
@php
    $c = 'storeops::storeops.stock_card.';
    $e = $card['selected'];
@endphp
<x-gs::box :title="__($c.'entry_details')" icon="fas fa-receipt" id="entry-details">
    @if (! $e)
        <p class="gs-muted">{{ __($c.'no_entry_selected') }}</p>
    @else
        <h3 class="storeops-h3 storeops-mono">{{ $e['document_number'] ?? '—' }}</h3>
        <p><x-gs::badge tone="secondary">{{ __($c.'kinds.'.$e['kind']) }}</x-gs::badge>
            <x-gs::status-badge status="posted" /></p>
        @php
            $details = [
                ['label' => __($c.'posted_on'), 'value' => $e['at']->translatedFormat('d M Y, H:i:s T')],
                ['label' => __($c.'quantity'), 'value' => ($e['direction'] === 'IN' ? '+' : '−').$e['qty'].($unit ? ' '.$unit : '')],
                ['label' => __($c.'col_balance'), 'value' => $e['before'].' → '.$e['balance']],
            ];
            if ($e['party']) {
                $details[] = ['label' => __($c.'parties.'.$e['party_key']), 'value' => $e['party']];
            }
            if ($e['reference']) {
                $details[] = ['label' => __($c.'reference'), 'value' => $e['reference']];
            }
            if ($e['corrects']) {
                $details[] = ['label' => __($c.'corrects_label'), 'value' => $e['corrects']];
            }
            if ($e['tags']) {
                $details[] = ['label' => __($c.'col_tags'), 'value' => implode(', ', $e['tags'])];
            }
            $person = fn ($p) => $p ? $p['name'].($p['role'] ? ', '.$p['role'] : '') : '—';
            $details[] = ['label' => __($c.'drafted_by'), 'value' => $person($e['drafter'])];
            $details[] = ['label' => __($c.'col_posted_by'), 'value' => $person($e['poster'])];
            if ($e['poster']) {
                $details[] = ['label' => __($c.'duties'), 'value' => $e['same_person'] ? __($c.'duties_same') : __($c.'duties_separate')];
            }
            $details[] = ['label' => __($c.'attachments'), 'value' => $e['attachments'] ? implode(', ', $e['attachments']) : __($c.'no_attachments')];
            $details[] = ['label' => __($c.'entry_id'), 'value' => $e['id']];
        @endphp
        <x-gs::key-value :items="$details" :columns="1" />

        @if ($e['history'])
            <h4 class="storeops-h4">{{ __($c.'history') }}</h4>
            <ol class="storeops-history">
                @foreach ($e['history'] as $step)
                    <li>
                        <strong>{{ __($c.'states.'.strtolower($step['state'])) }}</strong>
                        <span class="gs-muted">{{ $step['at'] ? \Carbon\Carbon::parse($step['at'])->translatedFormat('d M Y, H:i') : '' }}{{ $step['by'] ? ' · '.$step['by'] : '' }}</span>
                    </li>
                @endforeach
            </ol>
        @endif

        <p class="storeops-permanent"><i class="fas fa-lock" aria-hidden="true"></i>
            {{ __($c.'permanent', ['document' => $e['document_number'] ?? '—']) }}</p>
        <div class="storeops-actions storeops-noprint">
            @if ($e['document_number'] && $e['document_type'])
                <a class="btn btn-default" href="{{ route('storeops.documents.workspace', ['type' => $e['document_type'], 'id' => $e['document_id']]) }}">
                    <i class="fas fa-file-lines" aria-hidden="true"></i> {{ __($c.'open_document') }}</a>
            @endif
            @if ($abilities['draft'])
                <form method="post" action="{{ route('storeops.documents.initialize') }}" class="storeops-inline-form">
                    @csrf
                    <input type="hidden" name="document_type" value="adjustment">
                    <button type="submit" class="btn btn-default"><i class="fas fa-pen-to-square" aria-hidden="true"></i> {{ __($c.'correct_with_adjustment') }}</button>
                </form>
            @else
                <p class="gs-muted">{{ __($c.'view_only_note') }}</p>
            @endif
        </div>
    @endif
</x-gs::box>
