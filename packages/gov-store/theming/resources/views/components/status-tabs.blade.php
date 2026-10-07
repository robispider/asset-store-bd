@props(['tabs' => [], 'active' => null, 'label' => null])
<nav {{ $attributes->class('gs-status-tabs') }} aria-label="{{ $label ?? __('gs-theme::appearance.kit.status_tabs') }}">
    <ul class="gs-tabs">
        @foreach ($tabs as $tab)
            <li class="gs-tabs__item">
                <a class="gs-tabs__link" href="{{ $tab['href'] }}" @if (($tab['key'] ?? null) === $active) aria-current="page" @endif>
                    <span>{{ $tab['label'] }}</span>
                    @isset($tab['count'])
                        <span @class(['gs-tabs__count', 'gs-tabs__count--zero' => (int) $tab['count'] === 0])>{{ number_format((int) $tab['count']) }}</span>
                    @endisset
                </a>
            </li>
        @endforeach
    </ul>
</nav>
