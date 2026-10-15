{{-- Scope is always visible: whose ledger, which office, and what this person may do here. --}}
@php
    $r = 'storeops::storeops.stock_register.';
    $viewer = ($abilities['post'] ?? false) ? 'post' : (($abilities['draft'] ?? false) ? 'draft' : 'view');
@endphp
<div class="storeops-context storeops-noprint">
    <span class="storeops-chip">
        <i class="fas fa-building" aria-hidden="true"></i>
        {{ __($r.'working_office') }}: <strong>{{ $office['name'] ?? __($r.'no_office_short') }}</strong>
    </span>
    @if ($office['opened_at'])
        <span class="storeops-chip">
            <i class="fas fa-book" aria-hidden="true"></i>
            {{ __($r.'ledger_opened', ['date' => \Carbon\Carbon::parse($office['opened_at'])->translatedFormat('d M Y')]) }}
        </span>
    @endif
    <span class="storeops-chip storeops-chip--lock">
        <i class="fas fa-user-shield" aria-hidden="true"></i> {{ __($r.'viewer_'.$viewer) }}
    </span>
</div>
