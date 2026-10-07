{{--
    Loading indicator. type: ring · dots · pulse. tone: any kit tone, or `current` to follow the text colour.
    `label` is announced to screen readers; pass :label="false" when the surrounding control already says so.
--}}
@props(['type' => 'ring', 'tone' => 'primary', 'size' => 'md', 'label' => null])
<span {{ $attributes->class(['gs-spinner', 'gs-spinner--'.$type, 'gs-spinner--'.$size, 'gs-tone-'.$tone]) }}
    @if ($label !== false) role="status" @else aria-hidden="true" @endif>
    @if ($type === 'dots')<span></span><span></span><span></span>@endif
    @if ($label !== false)<span class="gs-sr-only">{{ $label ?? __('gs-theme::appearance.kit.loading') }}</span>@endif
</span>
