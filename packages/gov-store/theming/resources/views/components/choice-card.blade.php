{{--
    A large, labelled radio (or checkbox) option with a description. The native input stays in the
    accessibility tree and keyboard order; the card is its label, so the whole card is the click target.
--}}
@props(['name', 'value', 'label', 'description' => null, 'icon' => null, 'checked' => false, 'type' => 'radio', 'id' => null])
@php($id ??= $name.'-'.\Illuminate\Support\Str::slug((string) $value))
<label {{ $attributes->class('gs-choice') }} for="{{ $id }}">
    <input class="gs-choice__input" type="{{ $type === 'checkbox' ? 'checkbox' : 'radio' }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($checked) @if ($description) aria-describedby="{{ $id }}-desc" @endif>
    @if ($icon)<i class="fas {{ $icon }} gs-choice__icon" aria-hidden="true"></i>@endif
    <span class="gs-choice__text">
        <span class="gs-choice__label">{{ $label }}</span>
        @if ($description)<span class="gs-choice__description" id="{{ $id }}-desc">{{ $description }}</span>@endif
    </span>
</label>
