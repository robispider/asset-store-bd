@extends('layouts/default')

@section('title')
    {{ __('gs-theme::appearance.lab_title') }} · {{ $theme->label() }}
    @parent
@stop

@section('content')
@php
    $gs = app('gs.theme');
    $t = fn (string $key, array $replace = []) => __('gs-theme::appearance.lab.'.$key, $replace);
    $value = fn (string $v) => __('gs-theme::appearance.lab.values.'.$v);
    $tones = ['published' => 'success', 'draft' => 'warning', 'deprecated' => 'danger'];
    $modeLabel = ['light' => __('gs-theme::appearance.mode_light'), 'dark' => __('gs-theme::appearance.mode_dark')];
    $pairs = [];
    foreach ($summary as $m => $s) {
        foreach ($s['rows'] as $row) {
            $pairs[$m][$row['fg']] ??= $row;
        }
    }
    $shellQuery = ['gs_preview' => $theme->key, 'gs_mode' => $state['mode'] === 'dark' ? 'dark' : 'light'];
    foreach ($overrides as $name => $v) {
        $shellQuery['gs_variant'][$name] = $v;
    }
    $changeLines = [];
    foreach (array_keys($overrides) as $i => $name) {
        $changeLines[] = '"'.$name.'": "'.$overrides[$name].'"'.($i < count($overrides) - 1 ? ',' : '');
    }
    $makeCommand = 'php artisan gs-theme:make my-theme --from='.$theme->key;
@endphp
@if ($css = $gs->themeAsset($theme->key))<link rel="stylesheet" href="{{ $css }}" data-gs-lab-theme="{{ $theme->key }}">@endif

<div class="gs-lab gs-lab-ws" data-gs-lab="workspace" data-section="{{ $state['section'] }}"
    data-theme-key="{{ $theme->key }}" data-bn="{{ $bengali ? '1' : '0' }}"
    data-gs-lab-state='@json($state)' data-gs-lab-defaults='@json($theme->variants)' data-gs-lab-overrides='@json((object) $overrides)'>
    @include('gs-theme::lab.partials.header', ['current' => 'workspace', 'intro' => $t('workspace_intro')])

    <x-gs::alert tone="info" class="gs-lab-notice">{{ $t('preview_only') }}</x-gs::alert>

    <div class="gs-lab-ws__layout">
        {{-- Themes --}}
        <div class="gs-lab-ws__rail">
            <nav class="gs-lab-card" aria-labelledby="gs-lab-themes-title">
                <div class="gs-lab-card__head">
                    <h2 class="gs-lab-card__title" id="gs-lab-themes-title">{{ $t('themes') }}</h2>
                    <span class="gs-lab-small gs-muted">{{ $t('theme_count', ['count' => count($all)]) }}</span>
                </div>
                <label class="gs-lab-label" for="gs-lab-find">{{ $t('find_theme') }}</label>
                <input id="gs-lab-find" type="search" class="form-control input-sm gs-lab-find" placeholder="{{ $t('find_placeholder') }}" autocomplete="off" data-gs-lab-find>
                <ul class="gs-lab-themes">
                    @foreach ($all as $key => $item)
                        @php
                            $findings = count($validation[$key] ?? []);
                        @endphp
                        <li data-gs-lab-theme-item data-search="{{ mb_strtolower($item->label().' '.$item->label('bn-BD').' '.$key) }}">
                            <a class="gs-lab-theme-link" href="{{ route('gs-theme.lab.focus', $key) }}" data-gs-lab-keep-state="view" @if ($key === $theme->key) aria-current="page" @endif>
                                <span class="gs-lab-theme-link__top">
                                    <span class="gs-lab-theme-link__name">{{ $item->label() }}</span>
                                    <x-gs::badge :tone="$tones[$item->status()] ?? 'light'" pill>{{ $t('statuses.'.$item->status()) }}</x-gs::badge>
                                </span>
                                <span class="gs-lab-swatches-mini" aria-hidden="true">
                                    @foreach ($item->swatches() as $color)<span class="gs-swatch gs-lab-swatch-mini" data-color="{{ $color }}"></span>@endforeach
                                </span>
                                <span @class(['gs-lab-verdict', 'gs-lab-verdict--ok' => ! $findings, 'gs-lab-verdict--warn' => $findings])>
                                    <i class="fas {{ $findings ? 'fa-triangle-exclamation' : 'fa-circle-check' }}" aria-hidden="true"></i>
                                    {{ $findings ? trans_choice('gs-theme::appearance.lab.to_review', $findings, ['count' => $findings]) : $t('all_pass') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="gs-lab-small gs-muted" data-gs-lab-no-match hidden>{{ $t('no_match') }}</p>
            </nav>

            <div class="gs-lab-card gs-lab-card--dashed">
                <h2 class="gs-lab-card__title">{{ $t('new_theme') }}</h2>
                <p class="gs-lab-small gs-muted">{{ $t('new_theme_help') }}</p>
                <code class="gs-lab-code">{{ $makeCommand }}</code>
                <x-gs::button tone="light" size="sm" icon="fa-copy" data-gs-lab-copy="{{ $makeCommand }}" data-gs-lab-copied="{{ $t('copied') }}">{{ $t('copy_command') }}</x-gs::button>
            </div>
        </div>

        {{-- Preview --}}
        <div class="gs-lab-ws__main">
            <div class="gs-lab-toolbar" role="toolbar" aria-label="{{ $t('preview_options') }}">
                <div class="gs-lab-toolbar__group">
                    <span class="gs-lab-toolbar__label" id="gs-lab-mode-label">{{ __('gs-theme::appearance.mode') }}</span>
                    <div class="gs-lab-seg" role="group" aria-labelledby="gs-lab-mode-label">
                        @foreach (['light' => $modeLabel['light'], 'dark' => $modeLabel['dark'], 'split' => $t('mode_split')] as $v => $label)
                            <button type="button" class="gs-lab-seg__btn" data-gs-lab-set="mode" data-value="{{ $v }}" aria-pressed="{{ $state['mode'] === $v ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="gs-lab-toolbar__group">
                    <span class="gs-lab-toolbar__label" id="gs-lab-lang-label">{{ $t('sample_text') }}</span>
                    <div class="gs-lab-seg" role="group" aria-labelledby="gs-lab-lang-label">
                        <a class="gs-lab-seg__btn" href="{{ request()->url() }}" data-gs-lab-keep-state="all" data-bn="0" @unless ($bengali) aria-current="true" @endunless>{{ $t('english') }}</a>
                        <a class="gs-lab-seg__btn" href="{{ request()->url() }}?bn=1" data-gs-lab-keep-state="all" data-bn="1" lang="bn" @if ($bengali) aria-current="true" @endif>{{ $t('bengali') }}</a>
                    </div>
                </div>
                <div class="gs-lab-toolbar__group" data-gs-lab-width-group @if ($state['mode'] === 'split') hidden @endif>
                    <span class="gs-lab-toolbar__label" id="gs-lab-width-label">{{ $t('width') }}</span>
                    <div class="gs-lab-seg" role="group" aria-labelledby="gs-lab-width-label">
                        @foreach (['desktop', 'tablet', 'phone'] as $v)
                            <button type="button" class="gs-lab-seg__btn" data-gs-lab-set="width" data-value="{{ $v }}" aria-pressed="{{ $state['width'] === $v ? 'true' : 'false' }}">{{ $t('widths.'.$v) }}</button>
                        @endforeach
                    </div>
                </div>
                <label class="gs-inline-check gs-lab-toolbar__end">
                    <input type="checkbox" data-gs-lab-tokens @checked($state['tokens'])> {{ __('gs-theme::appearance.lab_show_tokens') }}
                </label>
            </div>

            <div class="gs-lab-tabs" role="group" aria-label="{{ $t('sections') }}">
                @foreach (array_keys($tabs) as $tab)
                    <button type="button" class="gs-lab-tab" data-gs-lab-set="section" data-value="{{ $tab }}" aria-pressed="{{ $state['section'] === $tab ? 'true' : 'false' }}">{{ $t('tabs.'.$tab) }}</button>
                @endforeach
            </div>

            {{-- Colour tokens: both modes side by side; chips resolve inside their own theme × mode scope. --}}
            <div class="gs-lab-card gs-lab-card--flush gs-lab-colours">
                <div class="gs-lab-card__head gs-lab-card__head--rule">
                    <div>
                        <h2 class="gs-lab-card__title">{{ $t('colour_title', ['theme' => $theme->label()]) }}</h2>
                        <p class="gs-lab-small gs-muted">{{ $t('colour_help') }}</p>
                    </div>
                </div>
                <div class="gs-lab-scroll">
                    <table class="gs-lab-token-table">
                        <thead>
                            <tr><th scope="col">{{ $t('token') }}</th><th scope="col">{{ $modeLabel['light'] }}</th><th scope="col">{{ $modeLabel['dark'] }}</th></tr>
                        </thead>
                        @foreach ($contract as $group => $paths)
                            @continue(in_array($group, ['Typography', 'Shape', 'Density', 'Elevation'], true))
                            <tbody>
                                <tr class="gs-lab-token-table__group"><th colspan="3" scope="colgroup">{{ $group }}</th></tr>
                                @foreach ($paths as $path)
                                    @php
                                        $var = \GovStore\Theming\Build\Contract::cssVar($path);
                                    @endphp
                                    <tr>
                                        <th scope="row">
                                            <span class="gs-lab-token-table__path">{{ $path }}</span>
                                            <code class="gs-lab-code-inline">{{ $var }}</code>
                                        </th>
                                        @foreach (['light', 'dark'] as $m)
                                            <td>
                                                <span class="gs-lab-token-cell" data-skin="{{ $theme->key }}" data-theme="{{ $m }}">
                                                    <span class="gs-lab-token-cell__chip" data-gs-swatch-token="{{ substr($var, 5) }}"></span>
                                                    <code class="gs-lab-code-inline">{{ $tokenValues[$m][$path] ?? '—' }}</code>
                                                    @isset($pairs[$m][$path])
                                                        @php
                                                            $pair = $pairs[$m][$path];
                                                        @endphp
                                                        @if ($pair['ratio'] !== null)
                                                            <span @class(['gs-lab-ratio', 'gs-lab-ratio--pass' => $pair['pass'], 'gs-lab-ratio--fail' => ! $pair['pass']]) title="{{ $pair['fg'] }} / {{ $pair['bg'] }}">
                                                                {{ number_format($pair['ratio'], 1) }} : 1 · {{ $pair['pass'] ? __('gs-theme::appearance.lab_pass') : __('gs-theme::appearance.lab_fail') }}
                                                            </span>
                                                        @endif
                                                    @endisset
                                                </span>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </div>

            {{-- Live preview: one panel per mode; variants and visibility switch in the browser. --}}
            <div class="gs-lab-stage" role="region" aria-label="{{ $t('live_preview') }}" data-gs-lab-stage data-mode="{{ $state['mode'] }}" data-width="{{ $state['width'] }}">
                @foreach (['light', 'dark'] as $m)
                    <div class="gs-lab-stage__item" data-gs-lab-mode-panel="{{ $m }}">
                        <div class="gs-lab-stage__caption">
                            <span class="gs-lab-mode-dot gs-lab-mode-dot--{{ $m }}" aria-hidden="true"></span>
                            {{ $t('panel_caption', ['mode' => $modeLabel[$m], 'theme' => $theme->label()]) }}
                        </div>
                        <div class="gs-lab-panel gs-lab-panel--stage" id="gs-panel-{{ $m }}" data-skin="{{ $theme->key }}" data-theme="{{ $m }}" data-gs-lab-variants
                            @foreach ($theme->variantAttributes($overrides) as $attr => $v) {{ $attr }}="{{ $v }}" @endforeach>
                            <div data-lab-section="shell">@include('gs-theme::lab.partials.mini-shell')</div>
                            @include('gs-theme::lab.partials.sections', [
                                'panel' => 'gs-panel-'.$m,
                                'mode' => $m,
                                'tokens' => $tokenValues[$m],
                                'compact' => false,
                            ])
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- About, health and variants --}}
        <div class="gs-lab-ws__inspector">
            <div class="gs-lab-card">
                <div class="gs-lab-card__head">
                    <div>
                        <h2 class="gs-lab-about__title">{{ $theme->label() }}</h2>
                        <div class="gs-lab-small gs-muted gs-bn" lang="bn">{{ $theme->label('bn-BD') }}</div>
                    </div>
                    <x-gs::badge :tone="$tones[$theme->status()] ?? 'light'" pill>{{ $t('statuses.'.$theme->status()) }}</x-gs::badge>
                </div>
                <p class="gs-lab-small">{{ $theme->description() }}</p>
                <dl class="gs-lab-meta">
                    <dt>{{ $t('key') }}</dt><dd><code class="gs-lab-code-inline">{{ $theme->key }}</code></dd>
                    <dt>{{ $t('version') }}</dt><dd>{{ $theme->version() }}</dd>
                    <dt>{{ $t('extends') }}</dt><dd>{{ $theme->parent() ? ($all[$theme->parent()] ?? null)?->label() ?? $theme->parent() : '—' }}</dd>
                    <dt>{{ $t('fonts') }}</dt><dd>{{ implode(' · ', $fontNames) }}</dd>
                </dl>
                <x-gs::button tone="dark" icon="fa-arrow-up-right-from-square" :href="$shellUrl.'?'.http_build_query($shellQuery)" target="_blank" rel="noopener" data-gs-lab-shell="{{ $shellUrl }}">{{ $t('open_shell') }}</x-gs::button>
            </div>

            <div class="gs-lab-card">
                <div class="gs-lab-card__head">
                    <h2 class="gs-lab-card__title">{{ $t('health') }}</h2>
                    <a class="gs-lab-small" href="{{ route('gs-theme.lab.health', ['theme' => $theme->key]) }}">{{ $t('full_report') }} →</a>
                </div>
                @foreach ($summary as $m => $s)
                    @php
                        $ok = $s['failing'] === [] && $s['other'] === [];
                    @endphp
                    <div @class(['gs-lab-health', 'gs-lab-health--ok' => $ok, 'gs-lab-health--warn' => ! $ok])>
                        <div class="gs-lab-health__title">
                            <i class="fas {{ $ok ? 'fa-circle-check' : 'fa-triangle-exclamation' }}" aria-hidden="true"></i>
                            {{ $t('contrast_summary', ['mode' => $modeLabel[$m], 'pass' => $s['pass'], 'total' => $s['total']]) }}
                        </div>
                        @foreach (array_slice($s['failing'], 0, 3) as $row)
                            <div class="gs-lab-health__item">{{ $t('checks.'.$row['slug'].'.name') }} — {{ number_format($row['ratio'], 2) }} : 1</div>
                        @endforeach
                        @if ($s['other'])
                            <div class="gs-lab-health__item">{{ trans_choice('gs-theme::appearance.lab.other_findings', count($s['other']), ['count' => count($s['other'])]) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="gs-lab-card" aria-labelledby="gs-lab-tune-title" role="region">
                <div>
                    <h2 class="gs-lab-card__title" id="gs-lab-tune-title">{{ $t('tune') }}</h2>
                    <p class="gs-lab-small gs-muted">{{ $t('tune_help') }}</p>
                </div>

                <div class="gs-lab-changes" data-gs-lab-changes @if (! $overrides) hidden @endif>
                    <div class="gs-lab-changes__head">
                        <strong data-gs-lab-changes-title data-one="{{ trans_choice('gs-theme::appearance.lab.changes', 1) }}" data-many="{{ trans_choice('gs-theme::appearance.lab.changes', 2, ['count' => '__N__']) }}">{{ $overrides ? trans_choice('gs-theme::appearance.lab.changes', count($overrides), ['count' => count($overrides)]) : '' }}</strong>
                        <button type="button" class="gs-btn gs-btn--light gs-btn--sm" data-gs-lab-reset-all>{{ $t('reset_all') }}</button>
                    </div>
                    <span class="gs-lab-small">{{ $t('paste_into') }}</span>
                    <pre class="gs-lab-json" data-gs-lab-json>{{ implode("\n", $changeLines) }}</pre>
                    <x-gs::button tone="primary" size="sm" icon="fa-copy" data-gs-lab-copy-json data-gs-lab-copied="{{ $t('copied') }}">{{ $t('copy_json') }}</x-gs::button>
                </div>

                @foreach ($groups as $group => $names)
                    <div class="gs-lab-tune-group">
                        <h3 class="gs-lab-tune-group__title">{{ $t('variant_groups.'.$group) }}</h3>
                        @foreach ($names as $name)
                            @php
                                $current = $variants[$name] ?? null;
                                $default = $theme->variants[$name] ?? null;
                            @endphp
                            <div class="gs-lab-tune-row" role="group" aria-labelledby="gs-lab-v-{{ $name }}" data-gs-lab-variant="{{ $name }}">
                                <div class="gs-lab-tune-row__head">
                                    <span class="gs-lab-tune-row__label" id="gs-lab-v-{{ $name }}">{{ $t('variants.'.$name) }}</span>
                                    <span class="gs-lab-small gs-muted" data-gs-lab-default-note @if ($current !== $default) hidden @endif>{{ $t('theme_default') }}</span>
                                    <button type="button" class="gs-lab-link-btn" data-gs-lab-reset="{{ $name }}" @if ($current === $default) hidden @endif>{{ $t('back_to', ['value' => $value((string) $default)]) }}</button>
                                </div>
                                <div class="gs-lab-seg gs-lab-seg--wrap">
                                    @foreach ($options[$name] as $option)
                                        <button type="button" class="gs-lab-seg__btn" data-gs-lab-set-variant="{{ $name }}" data-value="{{ $option }}" aria-pressed="{{ $current === $option ? 'true' : 'false' }}">{{ $value($option) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@stop

@push('js')
    <script src="{{ url(mix('js/dist/Chart.min.js')) }}" nonce="{{ csrf_token() }}"></script>
    @include('gs-theme::lab.partials.script')
@endpush
