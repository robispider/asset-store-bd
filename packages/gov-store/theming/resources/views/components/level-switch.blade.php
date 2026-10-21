@props(['options' => []])
<nav {{ $attributes->class('gs-level-switch') }} aria-label="{{ __('gs-theme::kit.level') }}">
    @foreach($options as $option)
        @if($option['locked'] ?? false)<span class="gs-level-switch__locked" title="{{ $option['reason'] }}"><i class="fas fa-lock" aria-hidden="true"></i> {{ $option['label'] }}<small>{{ $option['reason'] }}</small></span>
        @else<a class="gs-level-switch__option" href="{{ $option['href'] }}" @if($option['active'] ?? false) aria-current="page" @endif>{{ $option['label'] }}</a>@endif
    @endforeach
</nav>
