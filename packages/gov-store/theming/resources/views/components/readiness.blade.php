{{--
    Readiness checklist: "N of M done", a meter and one row per requirement. Every open item can carry
    the action that fixes it, so the checklist is also the to-do list.
    items: [['label' => …, 'value' => ?string, 'done' => bool, 'optional' => bool, 'action' => ?['label' => …, 'href' => …]]]
    `compact` drops the rows and keeps the meter (registry rows); `readonly` hides actions (staff pages).
--}}
@props(['items' => [], 'title' => null, 'caption' => null, 'compact' => false, 'readonly' => false])
@php
    $required = collect($items)->reject(fn ($item) => $item['optional'] ?? false);
    $total = $required->count();
    $done = $required->filter(fn ($item) => $item['done'] ?? false)->count();
    $complete = $total > 0 && $done === $total;
    $count = __('gs-theme::appearance.kit.readiness_count', ['done' => $done, 'total' => $total]);
@endphp
<section {{ $attributes->class(['gs-readiness', 'gs-readiness--complete' => $complete, 'gs-readiness--compact' => $compact]) }}>
    @unless ($compact)
        <div class="gs-readiness__head">
            <div class="gs-readiness__text">
                <strong class="gs-readiness__title">{{ $title ?? $count }}</strong>
                @if ($caption)<p class="gs-readiness__caption">{{ $caption }}</p>@endif
            </div>
            @if ($title)<span class="gs-readiness__count">{{ $count }}</span>@endif
        </div>
    @endunless
    <x-gs::progress :value="$done" :max="max(1, $total)" :tone="$complete ? 'success' : 'primary'" :label="$compact ? $count : null" :show-value="false" :size="$compact ? 'sm' : 'md'" />
    @unless ($compact)
        <ul class="gs-readiness__list">
            @foreach ($items as $item)
                @php($isDone = (bool) ($item['done'] ?? false))
                <li @class(['gs-readiness__item', 'gs-readiness__item--done' => $isDone, 'gs-readiness__item--optional' => $item['optional'] ?? false])>
                    <i class="fas {{ $isDone ? 'fa-circle-check' : 'fa-circle' }} gs-readiness__icon" aria-hidden="true"></i>
                    <span class="gs-readiness__label">
                        {{ $item['label'] }}@if (! empty($item['value'])): <strong>{{ $item['value'] }}</strong>@endif
                        <span class="gs-sr-only">({{ __('gs-theme::appearance.kit.'.($isDone ? 'readiness_done' : (($item['optional'] ?? false) ? 'optional' : 'readiness_todo'))) }})</span>
                    </span>
                    @if (! $isDone && ! $readonly && ! empty($item['action']))
                        <x-gs::button size="sm" :href="$item['action']['href']">{{ $item['action']['label'] }}</x-gs::button>
                    @elseif (! $isDone && ! ($item['optional'] ?? false))
                        <span class="gs-readiness__todo" aria-hidden="true">{{ __('gs-theme::appearance.kit.readiness_todo') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endunless
    {{ $slot }}
</section>
