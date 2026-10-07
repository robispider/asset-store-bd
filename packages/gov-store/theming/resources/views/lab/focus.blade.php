@extends('layouts/default')

@section('title')
    {{ __('gs-theme::appearance.lab_title') }} · {{ $theme->label() }}
    @parent
@stop

@section('content')
@php
    $query = fn (array $change) => route('gs-theme.lab.focus', array_merge(request()->except(['gs_preview']), ['theme' => $theme->key], $change));
    $changed = array_diff_assoc($overrides, $theme->variants);
@endphp
<div class="gs-lab" data-gs-lab="focus">
    <x-gs::page-header :title="$theme->label()" :subtitle="$theme->description()">
        <x-slot:meta>
            <span>{{ __('gs-theme::appearance.lab_status') }}: {{ $theme->status() }}</span>
            <span>{{ __('gs-theme::appearance.lab_version') }}: {{ $theme->version() }}</span>
            <span>extends: {{ $theme->parent() ?? '—' }}</span>
        </x-slot:meta>
        <x-slot:actions>
            <a class="gs-btn" href="{{ route('gs-theme.lab') }}"><i class="fas fa-table-cells" aria-hidden="true"></i> {{ __('gs-theme::appearance.lab_matrix') }}</a>
        </x-slot:actions>
    </x-gs::page-header>

    <form method="GET" action="{{ route('gs-theme.lab.focus', $theme->key) }}" class="gs-lab-toolbar" aria-label="Theme Lab toolbar">
        <input type="hidden" name="gs_preview" value="{{ $theme->key }}">
        <div class="gs-lab-toolbar__group">
            <span class="gs-lab-toolbar__label">{{ __('gs-theme::appearance.theme') }}</span>
            @foreach ($all as $key => $other)
                <a class="gs-btn {{ $key === $theme->key ? 'gs-btn--primary' : '' }}" href="{{ route('gs-theme.lab.focus', ['theme' => $key, 'mode' => $mode]) }}"
                    @if ($key === $theme->key) aria-current="page" @endif>{{ $other->label() }}</a>
            @endforeach
        </div>
        <div class="gs-lab-toolbar__group">
            <span class="gs-lab-toolbar__label">{{ __('gs-theme::appearance.mode') }}</span>
            @foreach (['light', 'dark', 'system'] as $m)
                <a class="gs-btn {{ $mode === $m ? 'gs-btn--primary' : '' }}" href="{{ $query(['gs_mode' => $m, 'gs_preview' => $theme->key]) }}">{{ __('gs-theme::appearance.mode_'.$m) }}</a>
            @endforeach
        </div>
        <fieldset class="gs-lab-toolbar__group">
            <legend class="gs-lab-toolbar__label">{{ __('gs-theme::appearance.lab_variant_overrides') }}</legend>
            <input type="hidden" name="gs_mode" value="{{ $mode }}">
            @foreach ($options as $variant => $values)
                <label class="gs-lab-toolbar__select">
                    <span>{{ $variant }}</span>
                    <select name="gs_variant[{{ $variant }}]" class="form-control input-sm">
                        @foreach ($values as $value)
                            <option value="{{ $value }}" @selected(($variants[$variant] ?? null) === $value)>{{ $value }}{{ ($theme->variants[$variant] ?? null) === $value ? ' (theme)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <label class="gs-inline-check"><input type="checkbox" name="bn" value="1" @checked($bengali)> {{ __('gs-theme::appearance.lab_bengali') }}</label>
            <button type="submit" class="gs-btn">{{ __('gs-theme::appearance.lab_apply') }}</button>
        </fieldset>
        <label class="gs-inline-check"><input type="checkbox" data-gs-lab-tokens> {{ __('gs-theme::appearance.lab_show_tokens') }}</label>
    </form>

    @if ($changed)
        <x-gs::alert tone="info" :title="__('gs-theme::appearance.lab_paste_json')">
            <pre class="gs-lab-json">@foreach ($changed as $name => $value)"{{ $name }}": "{{ $value }}",
@endforeach</pre>
        </x-gs::alert>
    @endif

    <div class="gs-lab-focus" id="gs-panel-focus">
        @include('gs-theme::lab.partials.sections', [
            'panel' => 'gs-panel-focus',
            'mode' => $mode === 'dark' ? 'dark' : 'light',
            'tokens' => $theme->tokens($mode === 'dark' ? 'dark' : 'light', $fonts),
            'issues' => $validation[$theme->key] ?? [],
            'compact' => false,
        ])
    </div>
</div>
@stop

@push('js')
    <script src="{{ url(mix('js/dist/Chart.min.js')) }}" nonce="{{ csrf_token() }}"></script>
    @include('gs-theme::lab.partials.script')
@endpush
