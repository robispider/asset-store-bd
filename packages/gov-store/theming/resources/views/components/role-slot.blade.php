{{--
    One responsibility and the person who holds it. Empty slots are drawn dashed and lead with the action
    that fills them; `required` slots say they are needed (to go live, to post, …) in words, not colour.
    The `actions` slot replaces the default button (e.g. a form with a person picker).
--}}
@props(['role', 'description' => null, 'person' => null, 'meta' => null, 'required' => false, 'optional' => false, 'actionLabel' => null, 'actionHref' => null])
<div {{ $attributes->class(['gs-role', 'gs-role--empty' => ! $person, 'gs-role--required' => ! $person && $required]) }}>
    <div class="gs-role__head">
        <strong class="gs-role__name">{{ $role }}</strong>
        @if (! $person && $required)
            <span class="gs-role__flag">{{ __('gs-theme::appearance.kit.needed') }}</span>
        @elseif ($optional)
            <span class="gs-role__flag gs-role__flag--muted">{{ __('gs-theme::appearance.kit.optional') }}</span>
        @endif
    </div>
    @if ($description)<p class="gs-role__description">{{ $description }}</p>@endif
    <div class="gs-role__body">
        @if ($person)
            <x-gs::avatar :name="$person" />
            <div class="gs-role__person">
                <span class="gs-role__person-name">{{ $person }}</span>
                @if ($meta)<span class="gs-role__person-meta">{{ $meta }}</span>@endif
            </div>
        @endif
        @isset($actions)
            <div class="gs-role__actions">{{ $actions }}</div>
        @elseif ($actionLabel && $actionHref)
            <div class="gs-role__actions">
                <x-gs::button :href="$actionHref" :tone="$person ? 'light' : 'primary'" :outline="(bool) $person" size="sm">{{ $actionLabel }}</x-gs::button>
            </div>
        @endisset
    </div>
</div>
