@extends('layouts/default')

@section('title')
    {{ __('gs-theme::appearance.lab_title') }} · {{ __('gs-theme::appearance.lab.compare') }}
    @parent
@stop

@section('content')
@php
    $gs = app('gs.theme');
    $t = fn (string $key, array $replace = []) => __('gs-theme::appearance.lab.'.$key, $replace);
    $value = fn (string $v) => __('gs-theme::appearance.lab.values.'.$v);
    $tones = ['published' => 'success', 'draft' => 'warning', 'deprecated' => 'danger'];
    $modeLabel = ['light' => __('gs-theme::appearance.mode_light'), 'dark' => __('gs-theme::appearance.mode_dark')];
    $traits = ['header', 'sidebar', 'density', 'table', 'radius', 'status_style'];
    $assets = array_slice($fixtures->assets(), 0, 3);
    $assetColumns = [['key' => 'tag', 'label' => 'Tag'], ['key' => 'name', 'label' => 'Name']];
@endphp
{{-- Every theme's CSS on this page only; panels are independent because token selectors are not html-qualified. --}}
@foreach ($all as $key => $theme)
    @if ($css = $gs->themeAsset($key))<link rel="stylesheet" href="{{ $css }}" data-gs-lab-theme="{{ $key }}">@endif
@endforeach

<div class="gs-lab gs-lab-compare" data-gs-lab="compare" data-mode="{{ $mode }}" data-gs-lab-picked='@json($picked)'>
    @include('gs-theme::lab.partials.header', ['current' => 'compare', 'intro' => $t('compare_intro')])

    <div class="gs-lab-toolbar" role="toolbar" aria-label="{{ $t('compare_options') }}">
        <div class="gs-lab-toolbar__group">
            <span class="gs-lab-toolbar__label" id="gs-lab-pick-label">{{ $t('themes') }}</span>
            <div class="gs-lab-picks" role="group" aria-labelledby="gs-lab-pick-label">
                @foreach ($all as $key => $theme)
                    <button type="button" class="gs-lab-pick" data-gs-lab-pick="{{ $key }}" aria-pressed="{{ in_array($key, $picked, true) ? 'true' : 'false' }}">
                        <span class="gs-swatch gs-lab-pick__dot" data-color="{{ $theme->swatches()[0] ?? '' }}" aria-hidden="true"></span>
                        {{ $theme->label() }}
                    </button>
                @endforeach
            </div>
        </div>
        <div class="gs-lab-toolbar__group">
            <span class="gs-lab-toolbar__label" id="gs-lab-cmode-label">{{ __('gs-theme::appearance.mode') }}</span>
            <div class="gs-lab-seg" role="group" aria-labelledby="gs-lab-cmode-label">
                @foreach (['light' => $modeLabel['light'], 'dark' => $modeLabel['dark'], 'both' => $t('mode_both')] as $v => $label)
                    <button type="button" class="gs-lab-seg__btn" data-gs-lab-set="mode" data-value="{{ $v }}" aria-pressed="{{ $mode === $v ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <span class="gs-lab-small gs-muted gs-lab-toolbar__end" data-gs-lab-count data-template="{{ $t('shown_count', ['shown' => '__S__', 'total' => '__T__']) }}" aria-live="polite">{{ $t('shown_count', ['shown' => count($picked), 'total' => count($all)]) }}</span>
    </div>

    <div class="gs-lab-compare__grid">
        @foreach ($all as $key => $theme)
            @php
                $findings = count($validation[$key] ?? []);
            @endphp
            <article class="gs-lab-card gs-lab-compare-card" data-gs-lab-card="{{ $key }}" @unless (in_array($key, $picked, true)) hidden @endunless>
                <div class="gs-lab-card__head">
                    <div>
                        <h2 class="gs-lab-card__title gs-lab-card__title--lg">{{ $theme->label() }}</h2>
                        <p class="gs-lab-small gs-muted">{{ $theme->description() }}</p>
                    </div>
                    <x-gs::badge :tone="$tones[$theme->status()] ?? 'light'" pill>{{ $t('statuses.'.$theme->status()) }}</x-gs::badge>
                </div>
                @foreach (['light', 'dark'] as $m)
                    <figure class="gs-lab-mini-wrap" data-gs-lab-mode-panel="{{ $m }}">
                        <figcaption class="gs-lab-stage__caption"><span class="gs-lab-mode-dot gs-lab-mode-dot--{{ $m }}" aria-hidden="true"></span>{{ $modeLabel[$m] }}</figcaption>
                        <div class="gs-lab-panel gs-lab-mini" data-skin="{{ $key }}" data-theme="{{ $m }}" inert
                            @foreach ($theme->variantAttributes() as $attr => $v) {{ $attr }}="{{ $v }}" @endforeach>
                            <span class="gs-sr-only">{{ $t('preview_of', ['theme' => $theme->label(), 'mode' => $modeLabel[$m]]) }}</span>
                            @include('gs-theme::lab.partials.mini-shell')
                            <div class="gs-lab-mini__kit">
                                <div class="gs-lab-row">
                                    <x-gs::button tone="primary" size="sm">Check out</x-gs::button>
                                    <x-gs::button tone="light" size="sm">Export</x-gs::button>
                                    @foreach (['deployed', 'pending', 'undeployable'] as $status)
                                        <x-gs::status-badge :status="$status" />
                                    @endforeach
                                </div>
                                <x-gs::table :columns="$assetColumns" :rows="$assets" density="compact" />
                            </div>
                        </div>
                    </figure>
                @endforeach
                <ul class="gs-lab-traits">
                    @foreach ($traits as $name)
                        <li><x-gs::badge tone="light">{{ $t('variants.'.$name) }}: {{ $value((string) ($theme->variants[$name] ?? '')) }}</x-gs::badge></li>
                    @endforeach
                </ul>
                <div class="gs-lab-compare-card__foot">
                    <span @class(['gs-lab-verdict', 'gs-lab-verdict--ok' => ! $findings, 'gs-lab-verdict--warn' => $findings])>
                        <i class="fas {{ $findings ? 'fa-triangle-exclamation' : 'fa-circle-check' }}" aria-hidden="true"></i>
                        {{ $findings ? trans_choice('gs-theme::appearance.lab.to_review', $findings, ['count' => $findings]) : $t('all_pass_both') }}
                    </span>
                    <x-gs::button tone="light" size="sm" :href="route('gs-theme.lab.focus', $key)">{{ $t('open_workspace') }} →</x-gs::button>
                </div>
            </article>
        @endforeach
    </div>

    <div class="gs-lab-card gs-lab-card--flush">
        <div class="gs-lab-card__head gs-lab-card__head--rule">
            <div>
                <h2 class="gs-lab-card__title">{{ $t('glance') }}</h2>
                <p class="gs-lab-small gs-muted">{{ $t('glance_help') }}</p>
            </div>
        </div>
        <div class="gs-lab-scroll">
            <table class="gs-lab-glance" data-gs-lab-glance>
                <thead>
                    <tr>
                        <th scope="col">{{ $t('setting') }}</th>
                        @foreach ($all as $key => $theme)
                            <th scope="col" data-gs-lab-col="{{ $key }}" @unless (in_array($key, $picked, true)) hidden @endunless>{{ $theme->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (\GovStore\Theming\Build\ThemeValidator::VARIANTS as $name => $values)
                        <tr data-gs-lab-glance-row>
                            <th scope="row">{{ $t('variants.'.$name) }}</th>
                            @foreach ($all as $key => $theme)
                                <td data-gs-lab-col="{{ $key }}" data-value="{{ $theme->variants[$name] ?? '' }}" @unless (in_array($key, $picked, true)) hidden @endunless>{{ $value((string) ($theme->variants[$name] ?? '')) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop

@push('js')
    @include('gs-theme::lab.partials.script')
@endpush
