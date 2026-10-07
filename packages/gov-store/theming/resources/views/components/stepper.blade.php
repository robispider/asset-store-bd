@props(['steps' => [], 'orientation' => 'horizontal', 'label' => null])
<ol {{ $attributes->class(['gs-stepper', 'gs-stepper--vertical' => $orientation === 'vertical']) }} aria-label="{{ $label ?? __('gs-theme::appearance.kit.progress') }}">
    @foreach ($steps as $i => $step)
        @php($state = $step['state'] ?? 'upcoming')
        <li class="gs-step gs-step--{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
            <span class="gs-step__marker" aria-hidden="true">
                @if ($state === 'done')<i class="fas fa-check"></i>@elseif ($state === 'blocked')<i class="fas fa-exclamation"></i>@else{{ $i + 1 }}@endif
            </span>
            <span class="gs-step__label">{{ $step['label'] }}</span>
            <span class="gs-sr-only">({{ __('gs-theme::appearance.kit.step_'.$state) }})</span>
        </li>
    @endforeach
</ol>
