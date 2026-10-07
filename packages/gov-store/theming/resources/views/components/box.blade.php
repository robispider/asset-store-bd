@props(['title' => null, 'bn' => null, 'icon' => null, 'tone' => 'default', 'collapsible' => false, 'collapsed' => false, 'loading' => false])
@php($bodyId = 'gs-box-'.\Illuminate\Support\Str::random(8))
<section {{ $attributes->class(['gs-box', 'gs-box--'.$tone => $tone !== 'default', 'gs-box--loading' => $loading]) }}
    data-collapsed="{{ $collapsed ? 'true' : 'false' }}" @if ($loading) aria-busy="true" @endif>
    @if ($title || isset($tools) || $collapsible)
        <header class="gs-box__header">
            @if ($icon)<i class="{{ $icon }} gs-muted" aria-hidden="true"></i>@endif
            @if ($title)
                <h2 class="gs-box__title">{{ $title }} @if ($bn)<span class="gs-bn gs-muted" lang="bn">· {{ $bn }}</span>@endif</h2>
            @endif
            @isset($tools)<div class="gs-box__tools">{{ $tools }}</div>@endisset
            @if ($collapsible)
                <button type="button" class="gs-box__toggle" data-gs-collapse aria-controls="{{ $bodyId }}"
                    aria-expanded="{{ $collapsed ? 'false' : 'true' }}" aria-label="{{ __('gs-theme::appearance.kit.toggle') }}">
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
            @endif
        </header>
    @endif
    <div class="gs-box__body" id="{{ $bodyId }}">{{ $slot }}</div>
    @isset($footer)<footer class="gs-box__footer">{{ $footer }}</footer>@endisset
</section>
