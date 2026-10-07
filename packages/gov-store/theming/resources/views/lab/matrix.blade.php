@extends('layouts/default')

@section('title')
    {{ __('gs-theme::appearance.lab_title') }}
    @parent
@stop

@section('content')
@php($gs = app('gs.theme'))
{{-- Every theme's CSS on this page only; panels are independent because token selectors are not html-qualified. --}}
@foreach ($themes as $key => $theme)
    @if ($css = $gs->themeAsset($key))<link rel="stylesheet" href="{{ $css }}" data-gs-lab-theme="{{ $key }}">@endif
@endforeach

<div class="gs-lab" data-gs-lab="matrix">
    <x-gs::page-header :title="__('gs-theme::appearance.lab_title')" :subtitle="__('gs-theme::appearance.lab_intro')">
        <x-slot:actions>
            <label class="gs-inline-check"><input type="checkbox" data-gs-lab-tokens> {{ __('gs-theme::appearance.lab_show_tokens') }}</label>
        </x-slot:actions>
    </x-gs::page-header>

    <form method="GET" action="{{ route('gs-theme.lab') }}" class="gs-lab-filter">
        <fieldset>
            <legend>{{ __('gs-theme::appearance.lab_filter') }}</legend>
            @foreach ($all as $key => $theme)
                <label class="gs-inline-check">
                    <input type="checkbox" name="themes[]" value="{{ $key }}" @checked(isset($themes[$key]))>
                    {{ $theme->label() }} <span class="gs-muted">({{ $theme->status() }}, v{{ $theme->version() }})</span>
                </label>
            @endforeach
            <button type="submit" class="gs-btn">{{ __('gs-theme::appearance.lab_apply') }}</button>
        </fieldset>
    </form>

    @foreach ($themes as $key => $theme)
        <section class="gs-lab-theme" aria-labelledby="gs-lab-{{ $key }}">
            <header class="gs-lab-theme__header">
                <h2 id="gs-lab-{{ $key }}">{{ $theme->label() }}</h2>
                <span class="gs-lab-chip">{{ __('gs-theme::appearance.lab_status') }}: {{ $theme->status() }}</span>
                <span class="gs-lab-chip">{{ __('gs-theme::appearance.lab_version') }}: {{ $theme->version() }}</span>
                @php($errorCount = count(array_filter($validation[$key] ?? [], fn ($i) => $i['level'] === 'error')))
                <span class="gs-lab-chip gs-lab-chip--{{ $errorCount ? 'fail' : 'pass' }}">{{ $errorCount ? $errorCount.' error(s)' : 'valid' }}</span>
                <a class="gs-btn" href="{{ route('gs-theme.lab.focus', ['theme' => $key, 'mode' => 'light']) }}">{{ __('gs-theme::appearance.lab_focus') }} · {{ __('gs-theme::appearance.mode_light') }}</a>
                <a class="gs-btn" href="{{ route('gs-theme.lab.focus', ['theme' => $key, 'mode' => 'dark']) }}">{{ __('gs-theme::appearance.lab_focus') }} · {{ __('gs-theme::appearance.mode_dark') }}</a>
            </header>
            <div class="gs-lab-matrix">
                @foreach (['light', 'dark'] as $mode)
                    @php($panel = 'gs-panel-'.$key.'-'.$mode)
                    <div class="gs-lab-panel" id="{{ $panel }}" data-skin="{{ $key }}" data-theme="{{ $mode }}"
                        @foreach ($theme->variantAttributes() as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach>
                        <div class="gs-lab-panel__label">{{ $theme->label() }} · {{ __('gs-theme::appearance.mode_'.$mode) }}</div>
                        @include('gs-theme::lab.partials.mini-shell')
                        <p class="gs-lab-note"><i class="fas fa-circle-info" aria-hidden="true"></i> {{ __('gs-theme::appearance.lab_approximation') }}</p>
                        @include('gs-theme::lab.partials.sections', [
                            'tokens' => $theme->tokens($mode, $fonts),
                            'issues' => $validation[$key] ?? [],
                            'bengali' => false,
                            'compact' => true,
                        ])
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@stop

@push('js')
    <script src="{{ url(mix('js/dist/Chart.min.js')) }}" nonce="{{ csrf_token() }}"></script>
    @include('gs-theme::lab.partials.script')
@endpush
