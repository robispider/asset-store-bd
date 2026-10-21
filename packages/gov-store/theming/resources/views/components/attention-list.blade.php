@props(['items' => []])
<ul {{ $attributes->class('gs-attention') }}>
    @forelse($items as $item)
        <li class="gs-attention__item" data-tone="{{ in_array($item['severity'],['critical','serious']) ? 'danger' : ($item['severity'] === 'warning' ? 'warning' : 'info') }}">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <div><small>{{ __('gs-theme::kit.severity.'.$item['severity']) }}</small><strong>{{ $item['title'] }}</strong><p>{{ $item['detail'] }}</p></div>
            <a href="{{ $item['href'] }}">{{ __('gs-theme::kit.open') }} <span class="gs-sr-only">{{ $item['title'] }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </li>
    @empty<li>{{ __('gs-theme::kit.no_attention') }}</li>@endforelse
</ul>
