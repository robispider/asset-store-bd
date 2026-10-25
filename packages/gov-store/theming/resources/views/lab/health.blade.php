@extends('layouts/default')

@section('title')
    {{ __('gs-theme::appearance.lab_title') }} · {{ __('gs-theme::appearance.lab.health_report') }}
    @parent
@stop

@section('content')
@php
    $t = fn (string $key, array $replace = []) => __('gs-theme::appearance.lab.'.$key, $replace);
    $modeLabel = ['light' => __('gs-theme::appearance.mode_light'), 'dark' => __('gs-theme::appearance.mode_dark')];
    $ratio = fn (?float $r) => $r === null ? '—' : number_format($r, 2).' : 1';
    // Side panel data, translated here so the script only copies text.
    $details = [];
    foreach ($report['details'] as $id => $d) {
        $details[$id] = [
            'theme' => $all[$d['theme']]->label(),
            'themeKey' => $d['theme'],
            'mode' => $modeLabel[$d['mode']],
            'modeKey' => $d['mode'],
            'name' => $t('checks.'.$d['slug'].'.name'),
            'why' => $t('checks.'.$d['slug'].'.why'),
            'pair' => $d['fg'].' / '.$d['bg'],
            'pass' => $d['pass'],
            'verdict' => $d['pass'] ? $t('passes') : $t('fails'),
            'now' => $t('now', ['ratio' => $ratio($d['ratio']), 'min' => number_format($d['min'], 1).' : 1']),
            'fg' => $d['fgValue'],
            'bg' => $d['bgValue'],
            'fix' => $d['fix'] ? [
                'title' => $t('suggested', ['ratio' => $ratio($d['fix']['ratio'])]),
                'text' => $t('fix_text', ['token' => $d['fix']['token'], 'value' => $d['fix']['value']]),
                'fg' => $d['fix']['token'] === $d['fg'] ? $d['fix']['value'] : $d['fgValue'],
                'bg' => $d['fix']['token'] === $d['bg'] ? $d['fix']['value'] : $d['bgValue'],
                'file' => $d['fix']['file'],
                'json' => $d['fix']['json'],
            ] : null,
            'workspace' => route('gs-theme.lab.focus', ['theme' => $d['theme'], 'mode' => $d['mode'], 'section' => 'colour']),
        ];
    }
    // Component attributes cannot hold "=>" (Blade's tag parser stops there), so captions are built here.
    $dirty = count($all) - $report['clean'];
    $stats = ['clean' => trans_choice('gs-theme::appearance.lab.stat_clean_note', $dirty, ['count' => $dirty])];
    foreach (['light', 'dark'] as $m) {
        $stats[$m] = [
            'contrastLabel' => $t('stat_contrast').' · '.$modeLabel[$m],
            'contrastValue' => $report['modes'][$m]['pass'].' / '.$report['modes'][$m]['total'],
            'contrastNote' => $t('stat_contrast_note', ['count' => $report['modes'][$m]['total'] - $report['modes'][$m]['pass']]),
            'otherLabel' => $t('stat_other').' · '.$modeLabel[$m],
            'otherValue' => array_sum(array_map('count', $report['modes'][$m]['other'])),
        ];
    }
@endphp

<div class="gs-lab gs-lab-health-page" data-gs-lab="health" data-mode="{{ $mode }}" data-show="{{ $show }}" data-gs-lab-selected="{{ $selected }}">
    @include('gs-theme::lab.partials.header', ['current' => 'health', 'intro' => $t('health_intro')])

    <div class="gs-lab-stats">
        <x-gs::kpi :label="$t('stat_clean')" :value="$report['clean'].' / '.count($all)" icon="fa-swatchbook" :caption="$stats['clean']" />
        @foreach (['light', 'dark'] as $m)
            <x-gs::kpi data-gs-lab-mode-panel="{{ $m }}" :label="$stats[$m]['contrastLabel']" :value="$stats[$m]['contrastValue']" icon="fa-circle-half-stroke" :caption="$stats[$m]['contrastNote']" />
            <x-gs::kpi data-gs-lab-mode-panel="{{ $m }}" :label="$stats[$m]['otherLabel']" :value="$stats[$m]['otherValue']" icon="fa-list-check" :caption="$t('stat_other_note')" />
        @endforeach
    </div>

    <div class="gs-lab-toolbar" role="toolbar" aria-label="{{ $t('report_options') }}">
        <div class="gs-lab-toolbar__group">
            <span class="gs-lab-toolbar__label" id="gs-lab-hmode-label">{{ __('gs-theme::appearance.mode') }}</span>
            <div class="gs-lab-seg" role="group" aria-labelledby="gs-lab-hmode-label">
                @foreach ($modeLabel as $v => $label)
                    <button type="button" class="gs-lab-seg__btn" data-gs-lab-set="mode" data-value="{{ $v }}" aria-pressed="{{ $mode === $v ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="gs-lab-toolbar__group">
            <span class="gs-lab-toolbar__label" id="gs-lab-show-label">{{ $t('show') }}</span>
            <div class="gs-lab-seg" role="group" aria-labelledby="gs-lab-show-label">
                @foreach (['all' => $t('show_all'), 'problems' => $t('show_problems')] as $v => $label)
                    <button type="button" class="gs-lab-seg__btn" data-gs-lab-set="show" data-value="{{ $v }}" aria-pressed="{{ $show === $v ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <span class="gs-lab-legend gs-lab-toolbar__end">
            <span><span class="gs-lab-legend__key gs-lab-legend__key--pass" aria-hidden="true"></span>{{ $t('legend_pass') }}</span>
            <span><span class="gs-lab-legend__key gs-lab-legend__key--fail" aria-hidden="true"></span>{{ $t('legend_fail') }}</span>
        </span>
    </div>

    <div class="gs-lab-health-page__layout">
        <div class="gs-lab-health-page__main">
            @foreach (['light', 'dark'] as $m)
                @php
                    $data = $report['modes'][$m];
                @endphp
                <div class="gs-lab-card gs-lab-card--flush" data-gs-lab-mode-panel="{{ $m }}">
                    <div class="gs-lab-card__head gs-lab-card__head--rule">
                        <div>
                            <h2 class="gs-lab-card__title">{{ $t('matrix_title', ['mode' => $modeLabel[$m]]) }}</h2>
                            <p class="gs-lab-small gs-muted">{{ $t('matrix_help') }}</p>
                        </div>
                    </div>
                    <div class="gs-lab-scroll">
                        <table class="gs-lab-hmatrix">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('check') }}</th>
                                    @foreach ($all as $key => $theme)
                                        <th scope="col">
                                            <span class="gs-lab-hmatrix__theme">{{ $theme->label() }}</span>
                                            <span @class(['gs-lab-small', 'gs-lab-text-ok' => ! $data['perTheme'][$key], 'gs-lab-text-fail' => $data['perTheme'][$key]])>
                                                {{ $data['perTheme'][$key] ? $t('failing', ['count' => $data['perTheme'][$key]]) : $t('all_pass') }}
                                            </span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['rows'] as $row)
                                    <tr data-gs-lab-failing="{{ $row['failing'] }}">
                                        <th scope="row">
                                            <span class="gs-lab-hmatrix__check">{{ $t('checks.'.$row['slug'].'.name') }}</span>
                                            <code class="gs-lab-code-inline">{{ $row['fg'] }} / {{ $row['bg'] }}</code>
                                        </th>
                                        @foreach ($all as $key => $theme)
                                            @php
                                                $cell = $row['cells'][$key] ?? null;
                                                $id = $m.'|'.$key.'|'.$row['slug'];
                                            @endphp
                                            <td>
                                                @if ($cell)
                                                    <button type="button" @class(['gs-lab-cell', 'gs-lab-cell--pass' => $cell['pass'], 'gs-lab-cell--fail' => ! $cell['pass']])
                                                        data-gs-lab-cell="{{ $id }}" aria-pressed="{{ $selected === $id ? 'true' : 'false' }}"
                                                        aria-label="{{ $t('cell_label', ['theme' => $theme->label(), 'check' => $t('checks.'.$row['slug'].'.name'), 'ratio' => $ratio($cell['ratio']), 'verdict' => $cell['pass'] ? $t('passes') : $t('fails')]) }}">
                                                        <i class="fas {{ $cell['pass'] ? 'fa-check' : 'fa-xmark' }}" aria-hidden="true"></i>
                                                        {{ $cell['ratio'] === null ? '—' : number_format($cell['ratio'], 1) }}
                                                    </button>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="gs-lab-all-clear" data-gs-lab-all-clear hidden><i class="fas fa-circle-check" aria-hidden="true"></i> {{ $t('all_clear') }}</p>
                    </div>
                </div>

                <div class="gs-lab-card" data-gs-lab-mode-panel="{{ $m }}">
                    <h2 class="gs-lab-card__title">{{ $t('other_title', ['mode' => $modeLabel[$m]]) }}</h2>
                    @php
                        $others = array_filter($data['other']);
                    @endphp
                    @if ($others === [])
                        <p class="gs-lab-small gs-lab-text-ok"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ $t('other_none') }}</p>
                    @else
                        <div class="gs-lab-findings">
                            @foreach ($others as $key => $issues)
                                <div class="gs-lab-findings__theme">
                                    <h3 class="gs-lab-tune-group__title">{{ $all[$key]->label() }}</h3>
                                    <ul class="gs-lab-findings__list">
                                        @foreach ($issues as $issue)
                                            <li>
                                                <x-gs::badge :tone="$issue['level'] === 'error' ? 'danger' : 'warning'">{{ $t('levels.'.$issue['level']) }}</x-gs::badge>
                                                <span class="gs-lab-small">{{ $issue['message'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="gs-lab-health-page__side">
            <div class="gs-lab-card gs-lab-detail" data-gs-lab-detail aria-live="polite">
                <div class="gs-lab-card__head">
                    <div>
                        <div class="gs-lab-small gs-muted" data-gs-lab-d="context"></div>
                        <h2 class="gs-lab-about__title" data-gs-lab-d="name"></h2>
                    </div>
                    <span class="gs-tag gs-tag--pill" data-gs-lab-d="verdict"></span>
                </div>
                <p class="gs-lab-small" data-gs-lab-d="why"></p>
                <code class="gs-lab-code-inline" data-gs-lab-d="pair"></code>
                <div class="gs-lab-detail__block">
                    <span class="gs-lab-label" data-gs-lab-d="now"></span>
                    <div class="gs-lab-sample" data-gs-lab-sample="now"><span class="gs-lab-sample__big">Aa</span> {{ $t('sample') }}</div>
                    <span class="gs-lab-sample-values"><span class="gs-swatch" data-gs-lab-d-swatch="fg"></span><code data-gs-lab-d="fg"></code><span class="gs-swatch" data-gs-lab-d-swatch="bg"></span><code data-gs-lab-d="bg"></code></span>
                </div>
                <div class="gs-lab-detail__block" data-gs-lab-fix hidden>
                    <span class="gs-lab-label" data-gs-lab-d="fixTitle"></span>
                    <div class="gs-lab-sample" data-gs-lab-sample="fix"><span class="gs-lab-sample__big">Aa</span> {{ $t('sample') }}</div>
                    <p class="gs-lab-small" data-gs-lab-d="fixText"></p>
                    <pre class="gs-lab-json"><span data-gs-lab-d="fixFile"></span>
<span data-gs-lab-d="fixJson"></span></pre>
                    <x-gs::button tone="light" size="sm" icon="fa-copy" data-gs-lab-copy-fix data-gs-lab-copied="{{ $t('copied') }}">{{ $t('copy_fix') }}</x-gs::button>
                </div>
                <x-gs::button tone="dark" href="{{ route('gs-theme.lab') }}" data-gs-lab-d-href="workspace">{{ $t('see_in_workspace') }}</x-gs::button>
            </div>

            <div class="gs-lab-card">
                <span class="gs-lab-small gs-muted">{{ $t('ci') }}</span>
                <code class="gs-lab-code">php artisan gs-theme:validate --json</code>
            </div>
        </div>
    </div>
    <script type="application/json" id="gs-lab-health-data">@json($details)</script>
</div>
@stop

@push('js')
    @include('gs-theme::lab.partials.script')
@endpush
