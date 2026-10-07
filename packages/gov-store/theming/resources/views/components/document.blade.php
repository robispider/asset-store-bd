{{-- Printable document. Tables inside are always ledger-style; prints always use light tokens. --}}
@props(['docNo' => null, 'date' => null, 'title' => null])
<article {{ $attributes->class('gs-document') }} data-gs-table="ledger">
    <header class="gs-document__head">
        <div class="gs-document__emblem">{{ $emblem ?? '' }}</div>
        <div class="gs-document__office">
            {{ $office ?? '' }}
            @if ($title)<h3>{{ $title }}</h3>@endif
        </div>
        <div class="gs-document__ref">
            @if ($docNo)<div><strong>{{ __('gs-theme::appearance.kit.doc_no') }}:</strong> {{ $docNo }}</div>@endif
            @if ($date)<div><strong>{{ __('gs-theme::appearance.kit.date') }}:</strong> {{ $date }}</div>@endif
        </div>
    </header>
    @isset($meta)<div class="gs-document__meta">{{ $meta }}</div>@endisset
    <div class="gs-document__body">{{ $slot }}</div>
    @isset($signatures)<div class="gs-document__signatures">{{ $signatures }}</div>@endisset
    @isset($footer)<footer class="gs-document__footer">{{ $footer }}</footer>@endisset
</article>
