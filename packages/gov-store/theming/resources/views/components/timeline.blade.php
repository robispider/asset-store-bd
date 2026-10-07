@props(['items' => []])
@if (count($items))
    <ol {{ $attributes->class('gs-timeline') }}>
        @foreach ($items as $item)
            <li class="gs-timeline__item">
                <div><strong>{{ $item['by'] }}</strong> {{ $item['action'] }} <span>{{ $item['target'] ?? '' }}</span></div>
                <time class="gs-timeline__meta">{{ $item['at'] }}</time>
            </li>
        @endforeach
    </ol>
@else
    <p {{ $attributes->class('gs-timeline__empty') }}>{{ __('gs-theme::appearance.kit.no_activity') }}</p>
@endif
