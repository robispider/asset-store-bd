@extends('layouts/default')

@section('title')
    {{ __('gs-theme::appearance.title') }}
    @parent
@stop

@section('content')
@php($gs = app('gs.theme'))
<div class="gs-appearance">
    <x-gs::page-header :title="__('gs-theme::appearance.title')" :bn="trans('gs-theme::appearance.title', [], 'bn-BD')" :subtitle="__('gs-theme::appearance.intro')">
        @if ($canAssign || $canViewLab)
            <x-slot:actions>
                @if ($canAssign)<a class="gs-btn" href="{{ route('gs-theme.assignments') }}"><i class="fas fa-swatchbook" aria-hidden="true"></i> {{ __('gs-theme::appearance.manage_defaults') }}</a>@endif
                @if ($canViewLab)<a class="gs-btn" href="{{ route('gs-theme.lab') }}"><i class="fas fa-flask" aria-hidden="true"></i> {{ __('gs-theme::appearance.open_lab') }}</a>@endif
            </x-slot:actions>
        @endif
    </x-gs::page-header>

    @if (session('success'))
        <x-gs::alert tone="success" dismissible>{{ session('success') }}</x-gs::alert>
    @endif
    @error('theme')
        <x-gs::alert tone="danger">{{ $message }}</x-gs::alert>
    @enderror

    <form method="POST" action="{{ route('gs-theme.appearance.update') }}" data-gs-theme-picker
        data-original-skin="{{ $current }}">
        @csrf
        @method('PUT')

        <x-gs::box :title="__('gs-theme::appearance.theme')" icon="fas fa-palette">
            @if ($enforced)
                <x-gs::alert tone="info">
                    {{ __('gs-theme::appearance.enforced_notice', [
                        'scope' => __('gs-theme::appearance.scope_names.'.$enforced['scope_type']),
                        'theme' => ($themes[$enforced['theme']] ?? null)?->label() ?? $enforced['theme'],
                    ]) }}
                </x-gs::alert>
            @elseif (! $canChoose)
                <x-gs::alert tone="info">{{ __('gs-theme::appearance.choice_disabled') }}</x-gs::alert>
            @else
                <p class="gs-muted">{{ __('gs-theme::appearance.live_preview_note') }}</p>
            @endif

            <fieldset class="gs-theme-cards" @disabled(! $canChoose)>
                <legend class="gs-sr-only">{{ __('gs-theme::appearance.theme') }}</legend>
                @foreach ($themes as $key => $theme)
                    @php($checked = $preference['theme'] ? $preference['theme'] === $key : $current === $key)
                    <label class="gs-theme-card" data-gs-theme-card data-key="{{ $key }}"
                        data-css="{{ $gs->themeAsset($key) }}" data-variants='@json($theme->variantAttributes())'>
                        <input type="radio" name="theme" value="{{ $key }}" class="gs-theme-card__input" @checked($checked)>
                        <span class="gs-theme-card__previews">
                            @foreach (['light', 'dark'] as $previewMode)
                                @if ($url = $gs->previewUrl($key, $previewMode))
                                    <img src="{{ $url }}" loading="lazy" width="300" height="188"
                                        alt="{{ __('gs-theme::appearance.preview_'.$previewMode, ['theme' => $theme->label()]) }}">
                                @endif
                            @endforeach
                        </span>
                        <span class="gs-theme-card__name">
                            {{ $theme->label() }}
                            @if ($current === $key)<span class="gs-theme-card__tag gs-theme-card__tag--current">{{ __('gs-theme::appearance.current') }}</span>@endif
                            @foreach ($tags[$key] ?? [] as $scope)
                                <span class="gs-theme-card__tag">{{ __('gs-theme::appearance.tag_'.$scope) }}</span>
                            @endforeach
                        </span>
                        <span class="gs-theme-card__desc">{{ $theme->description() }}</span>
                        <span class="gs-theme-card__swatches" aria-hidden="true">
                            @foreach ($theme->swatches() as $i => $swatch)
                                <span class="gs-swatch" data-swatch="{{ $i }}" data-color="{{ $swatch }}"></span>
                            @endforeach
                        </span>
                    </label>
                @endforeach
            </fieldset>

            @if ($canChoose)
                <p class="gs-muted">
                    <label class="gs-inline-check">
                        <input type="checkbox" name="reset" value="1" @checked(! $preference['theme'])>
                        {{ __('gs-theme::appearance.reset') }}
                    </label>
                    — {{ __('gs-theme::appearance.reset_help', ['theme' => ($themes[$inherited['key']] ?? null)?->label() ?? $inherited['key']]) }}
                </p>
                <p class="gs-muted"><small>{{ __('gs-theme::appearance.office_switch_note') }}</small></p>
            @endif
        </x-gs::box>

        <x-gs::box :title="__('gs-theme::appearance.mode')" icon="fas fa-circle-half-stroke">
            <div class="gs-segmented" role="radiogroup" aria-label="{{ __('gs-theme::appearance.mode') }}">
                @foreach (['light' => 'fa-sun', 'dark' => 'fa-moon', 'system' => 'fa-desktop'] as $value => $icon)
                    <label class="gs-segmented__option">
                        <input type="radio" name="mode" value="{{ $value }}" data-gs-mode-option @checked(($preference['mode'] ?? 'system') === $value)>
                        <span><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ __('gs-theme::appearance.mode_'.$value) }}</span>
                    </label>
                @endforeach
            </div>
            <p class="gs-muted">{{ __('gs-theme::appearance.mode_help') }}</p>
            <x-slot:footer>
                <button type="submit" class="gs-btn gs-btn--primary">{{ __('gs-theme::appearance.save') }}</button>
                <button type="button" class="gs-btn gs-btn--ghost" data-gs-picker-cancel>{{ __('gs-theme::appearance.cancel') }}</button>
            </x-slot:footer>
        </x-gs::box>
    </form>
</div>
@stop
