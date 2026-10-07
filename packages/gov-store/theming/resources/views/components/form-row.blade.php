@props(['label', 'for', 'bn' => null, 'required' => false, 'help' => null, 'error' => null])
@php($error ??= ($errors ?? null)?->first($for))
<div {{ $attributes->class(['gs-form-row', 'gs-form-row--error' => $error]) }}>
    <label class="gs-form-row__label" for="{{ $for }}">
        {{ $label }}
        @if ($bn)<span class="gs-bn gs-muted" lang="bn">({{ $bn }})</span>@endif
        @if ($required)<span class="gs-form-row__required" aria-hidden="true">*</span><span class="gs-sr-only">{{ __('gs-theme::appearance.kit.required') }}</span>@endif
    </label>
    <div class="gs-form-row__control">
        {{ $slot }}
        @if ($help)<p class="gs-form-row__help" id="{{ $for }}-help">{{ $help }}</p>@endif
        @if ($error)<p class="gs-form-row__error" id="{{ $for }}-error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $error }}</p>@endif
    </div>
</div>
