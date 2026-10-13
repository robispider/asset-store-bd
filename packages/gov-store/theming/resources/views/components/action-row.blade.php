{{--
    A settings or danger-zone row: what the action does, in plain words, beside its control. When the action
    is unavailable, `unavailable` says why and the `actions` slot should render a disabled control that
    points at it with aria-describedby="{{ id }}-why".
--}}
@props(['title', 'description' => null, 'unavailable' => null, 'id' => null])
@php($id ??= 'gs-action-'.\Illuminate\Support\Str::random(6))
<div {{ $attributes->class(['gs-action-row', 'gs-action-row--unavailable' => $unavailable]) }} id="{{ $id }}">
    <div class="gs-action-row__text">
        <strong class="gs-action-row__title">{{ $title }}</strong>
        @if ($description)<p class="gs-action-row__description">{{ $description }}</p>@endif
        @if ($unavailable)
            <p class="gs-action-row__why" id="{{ $id }}-why"><i class="fas fa-lock" aria-hidden="true"></i> {{ $unavailable }}</p>
        @endif
    </div>
    @isset($actions)<div class="gs-action-row__actions">{{ $actions }}</div>@endisset
</div>
