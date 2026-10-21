@extends('layouts/default')
@section('title', $initiative->title.' · '.__('govtracking::general.monitor.manage'))
@section('content')
@php
    $m = 'govtracking::general.manage.';
    $codePrefix = null;
    if (preg_match('/^(.+-)[^-]+-[^-]+$/', $all_codes[0]['code'] ?? '', $parts)
        && collect($all_codes)->every(fn ($c) => str_starts_with($c['code'], $parts[1]))) $codePrefix = $parts[1];
    $short = fn (?string $code) => $code && $codePrefix && str_starts_with($code, $codePrefix) ? substr($code, strlen($codePrefix)) : $code;
    $num = fn ($n) => number_format((float) $n, fmod((float) $n, 1.0) ? 2 : 0);
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->isoFormat('D MMM YYYY') : null;
    $head = $team->get('HEAD', collect())->first();
    $headName = $head ? trim($head->first_name.' '.$head->last_name) : null;
    $activeReady = collect($readiness)->firstWhere('key', 'active')['done'] ?? false;
    $gaps = count($todos) + ($activeReady ? 0 : 1);
    $open = in_array($initiative->status, ['Planning', 'Active'], true);
    $next = ! $open ? __($m.'next_closed', ['status' => __('govtracking::general.monitor.initiative_status.'.$initiative->status)])
        : (! $headName ? __($m.'next_no_head')
        : ($can['head'] ? __($m.'next_head') : ($can['edit'] ? __($m.'next_officer', ['person' => $headName]) : __($m.'next_read', ['person' => $headName]))));
    $teamUrl = route('gov.tracking.initiatives.operation-unit.index', $initiative->id);
    $readinessItems = collect($readiness)->map(fn ($r) => ['label' => __($m.'ready.'.$r['key']), 'value' => $r['value'] ?? null, 'done' => $r['done'],
        'action' => ! $r['done'] && $can['head'] && in_array($r['key'], ['head', 'officer'], true) ? ['label' => __($m.'assign'), 'href' => $teamUrl] : null])->all();
    $manageUrl = fn (array $change = [], string $anchor = '') => route('gov.tracking.initiatives.manage', array_filter(array_merge(['initiative' => $initiative->id,
        'tab' => $filters['tab'], 'q' => $filters['q'], 'activity' => $filters['activity']], $change), fn ($v) => $v !== null && $v !== '' && $v !== 'all')).$anchor;
    $ruleItems = [
        ['label' => __($m.'rule.documents'), 'value' => __($m.($rules['documents'] ? 'required' : 'not_required'))],
        ['label' => __($m.'rule.overshoot'), 'value' => __($m.($rules['overshoot'] ? 'allowed_warning' : 'blocked'))],
        ['label' => __($m.'rule.metadata'), 'value' => __($m.($rules['metadata'] ? 'required' : 'not_required'))],
    ];
    $missingText = fn (array $c) => collect($c['missing'])->map(fn ($k) => __($m.'missing.'.$k))->implode(', ');
    $shownActivity = array_slice($activity['items'], 0, 10);
    $moreActivity = array_slice($activity['items'], 10);
@endphp
<div class="gs-tracking-monitor gs-tracking-manage">
    <x-gs::page-header :title="$initiative->title" :subtitle="$initiative->purpose">
        @if($can['edit'] || $can['settings'])
            <x-slot:actions>
                @if($can['edit'])<a class="gs-btn gs-btn--primary" href="{{ route('gov.tracking.initiatives.tracking-codes.create', $initiative->id) }}"><i class="fas fa-plus" aria-hidden="true"></i> {{ __($m.'new_code') }}</a>@endif
                @if($can['settings'])<a class="gs-btn gs-btn--secondary gs-btn--outline" href="{{ route('gov.tracking.initiatives.edit', $initiative->id) }}"><i class="fas fa-gear" aria-hidden="true"></i> {{ __($m.'settings') }}</a>@endif
            </x-slot:actions>
        @endif
    </x-gs::page-header>
    <div class="gs-tracking-meta"><x-gs::status-badge :status="strtolower($initiative->status)" :label="__('govtracking::general.monitor.initiative_status.'.$initiative->status)" /><span>{{ $initiative->primary_funding }} · {{ $owner }}</span>@if($fiscal_years)<span>{{ implode(' · ', $fiscal_years) }}</span>@endif<span>{{ count($all_codes) }} {{ __('govtracking::general.monitor.codes') }}</span></div>

    <div class="gs-tracking-manage__tabsrow">
        @include('govtracking::monitor.tabs')
        <p class="gs-tracking-manage__snap">
            @if($snapshot['delivered'] !== null)<span>{{ __($m.'snap_delivered', ['value' => number_format($snapshot['delivered'] * 100, 1).'%']) }}</span>@endif
            @if($snapshot['planned'] > 0)<strong>{{ __($m.'snap_units', ['received' => $num($snapshot['received']), 'planned' => $num($snapshot['planned'])]) }}</strong>@endif
            <span>{{ trans_choice($m.'snap_receipts', $snapshot['receipts'], ['count' => $snapshot['receipts']]) }}@if($snapshot['last']) · {{ __($m.'snap_last', ['date' => $date($snapshot['last'])]) }}@endif</span>
            <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}">{{ __($m.'open_monitor') }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </p>
    </div>

    @if(isset($errors) && $errors->has('order_pdf'))<x-gs::alert tone="danger">{{ $errors->first('order_pdf') }}</x-gs::alert>@endif
    <div class="gs-tracking-manage__band">
        <div class="gs-tracking-panel gs-tracking-manage__todo-panel">
            <x-gs::status-callout :status="$gaps ? 'warning' : 'success'" :verdict="$gaps ? trans_choice($m.'callout_gaps', $gaps, ['count' => $gaps]) : __($m.'callout_ok')" :next="$next" />
            @if($todos)
                <ul class="gs-tracking-manage__todo">
                    @foreach($todos as $todo)
                        @php
                            $code = $todo['code'] ?? null;
                            $title = match ($todo['key']) {
                                'team' => __($m.'todo.team', ['role' => mb_strtolower(__('govtracking::general.monitor.designations.'.$todo['role']))]),
                                'orders' => __($m.'todo.orders', ['count' => $todo['count']]),
                                default => __($m.'todo.'.$todo['key'], ['code' => $short($code['code'])]),
                            };
                            $why = $todo['key'] === 'finish' ? __($m.'todo.finish_why', ['missing' => $missingText($code)]) : __($m.'todo.'.$todo['key'].'_why');
                        @endphp
                        <li class="gs-tracking-manage__todo-item" data-tone="{{ $todo['tone'] }}">
                            <i class="fas {{ $todo['icon'] }}" aria-hidden="true"></i>
                            <div><strong>{{ $title }}</strong><small>{{ $why }}</small></div>
                            @switch($todo['action'])
                                @case('team')<a class="gs-btn gs-btn--primary gs-btn--sm" href="{{ $teamUrl }}">{{ __($m.'assign') }}</a>@break
                                @case('setup')<a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ $manageUrl(['tab' => 'setup'], '#manage-codes') }}">{{ __($m.'show_codes') }}</a>@break
                                @case('attach')<a class="gs-btn gs-btn--warning gs-btn--sm" href="#order-{{ $code['id'] }}" data-manage-open="{{ $code['id'] }}">{{ __($m.'attach') }}</a>@break
                                @case('edit')<a class="gs-btn gs-btn--primary gs-btn--sm" href="{{ route('gov.tracking.initiatives.tracking-codes.edit', [$initiative->id, $code['id']]) }}">{{ __($m.'edit') }}</a>@break
                                @case('activate')
                                    <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.activate', [$initiative->id, $code['id']]) }}">@csrf<button type="submit" class="gs-btn gs-btn--success gs-btn--sm" data-tracking-confirm="{{ __($m.'confirm.activate', ['code' => $code['code']]) }}">{{ __($m.'activate') }}</button></form>
                                    @break
                                @default<span class="gs-tracking-manage__who"><i class="fas fa-lock" aria-hidden="true"></i> {{ __($m.'who.'.$todo['who']) }}</span>
                            @endswitch
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="gs-tracking-muted gs-tracking-manage__todo-none">{{ __($m.'todo_none') }}</p>
            @endif
        </div>
        <x-gs::readiness :items="$readinessItems" :title="__($m.'readiness')" :caption="__($m.'readiness_caption')" />
    </div>

    <div class="gs-tracking-manage__work">
        <div class="gs-tracking-manage__main">
            <div class="gs-tracking-panel gs-tracking-manage__codes" id="manage-codes">
                <div class="gs-tracking-manage__head">
                    <h2>{{ __($m.'codes') }}</h2>
                    <span class="gs-tracking-muted">{{ __($m.'codes_hint') }}</span>
                </div>
                <div class="gs-tracking-manage__filters">
                    <nav class="gs-tracking-manage__chips" aria-label="{{ __($m.'filter_label') }}">
                        @foreach(\GovStore\Tracking\Services\TrackingManageService::TABS as $tab)
                            <a href="{{ $manageUrl(['tab' => $tab], '#manage-codes') }}" @if($filters['tab'] === $tab) aria-current="true" @endif>{{ __($m.'tabs.'.$tab) }} <b>{{ $counts[$tab] }}</b></a>
                        @endforeach
                    </nav>
                    <form method="GET" action="{{ route('gov.tracking.initiatives.manage', $initiative->id) }}#manage-codes" class="gs-tracking-manage__search" role="search">
                        @if($filters['tab'] !== 'all')<input type="hidden" name="tab" value="{{ $filters['tab'] }}">@endif
                        @if($filters['activity'] !== 'all')<input type="hidden" name="activity" value="{{ $filters['activity'] }}">@endif
                        <label class="gs-sr-only" for="manage-q">{{ __($m.'search') }}</label>
                        <input type="search" id="manage-q" name="q" value="{{ $filters['q'] }}" maxlength="150" placeholder="{{ __($m.'search') }}">
                        <button type="submit" class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm">{{ __($m.'search_button') }}</button>
                    </form>
                </div>
                @if(! $codes)
                    <x-gs::empty-state :icon="$all_codes ? 'fa-filter' : 'fa-barcode'" :title="$all_codes ? __($m.'no_match') : __($m.'no_codes')">
                        @unless($all_codes){{ __($m.'no_codes_hint') }}@endunless
                        <x-slot:actions>
                            @if($all_codes)<a class="gs-btn gs-btn--secondary gs-btn--outline" href="{{ $manageUrl(['tab' => null, 'q' => null], '#manage-codes') }}">{{ __($m.'clear') }}</a>
                            @elseif($can['edit'])<a class="gs-btn gs-btn--primary" href="{{ route('gov.tracking.initiatives.tracking-codes.create', $initiative->id) }}">{{ __($m.'new_code') }}</a>@endif
                        </x-slot:actions>
                    </x-gs::empty-state>
                @else
                    <x-gs::table class="gs-tracking-manage-codes" data-monitor-sortable>
                        <thead><tr>
                            <th scope="col"><span class="gs-sr-only">{{ __($m.'th.details') }}</span></th>
                            <th scope="col">{{ __($m.'th.code') }}</th>
                            <th scope="col">{{ __($m.'th.plan') }}</th>
                            <th scope="col">{{ __($m.'th.checks') }}</th>
                            <th scope="col">{{ __($m.'th.status') }}</th>
                            <th scope="col" class="gs-tracking-manage__actions-head">{{ __($m.'th.actions') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($codes as $c)
                            @php
                                $planLabel = in_array($c['plan'], ['1_BLANKET', '2_CATEGORY', '3_MATRIX'], true) ? __('govtracking::general.monitor.plans.'.$c['plan']) : __($m.'plan_unknown');
                                $badge = ['DRAFT' => 'draft', 'ACTIVE' => 'success', 'ARCHIVED' => 'archived'][$c['status']] ?? 'info';
                                $canAttach = $can['edit'] && ($c['status'] === 'DRAFT' || ($c['status'] === 'ACTIVE' && ! $c['order_pdf']));
                                $activateWhy = ! $can['head'] ? __($m.'lock_head') : (! $c['ready'] ? __($m.'lock_checks', ['missing' => $missingText($c)]) : null);
                            @endphp
                            <tr id="code-{{ $c['id'] }}" @class(['gs-tracking-manage__row--archived' => $c['status'] === 'ARCHIVED'])>
                                <td class="gs-tracking-codes__toggle"><button type="button" class="gs-tracking-expand" data-monitor-toggle hidden aria-expanded="true" aria-controls="manage-detail-{{ $c['id'] }}" aria-label="{{ __($m.'collapse', ['code' => $c['code']]) }}" data-expand-label="{{ __($m.'expand', ['code' => $c['code']]) }}" data-collapse-label="{{ __($m.'collapse', ['code' => $c['code']]) }}"><span data-monitor-toggle-icon aria-hidden="true">−</span></button></td>
                                <td>
                                    <div class="gs-tracking-manage__id">
                                        <a class="gs-tracking-code" href="{{ route('gov.tracking.initiatives.tracking-codes.show', [$initiative->id, $c['id']]) }}" title="{{ $c['code'] }}" aria-label="{{ $c['code'] }}">@if($codePrefix)<x-gs::code-segments size="sm" :segments="[['value' => $codePrefix, 'tone' => 'muted'], ['value' => $short($c['code']), 'tone' => 'primary']]" />@else{{ $c['code'] }}@endif</a>
                                        <strong>{{ $c['title'] }}</strong>
                                        <small>@if($c['plan'] === '1_BLANKET'){{ $planLabel }}@else{{ trans_choice($m.'products', count($c['targets']), ['count' => count($c['targets'])]) }}@if($c['planned'] > 0) · {{ __($m.'units_planned', ['count' => $num($c['planned'])]) }}@endif @endif</small>
                                    </div>
                                </td>
                                <td class="gs-tracking-manage__plan">{{ $planLabel }}<small>{{ $c['geography_label'] }} · {{ $c['participants_label'] }}</small><small>{{ __($m.'fy', ['year' => $c['fy']]) }}</small></td>
                                <td>
                                    @if($c['status'] === 'ARCHIVED')<span class="gs-tracking-muted">—</span>
                                    @else
                                        <ul class="gs-tracking-manage__checks">
                                            @foreach($c['checks'] as $check => $ok)
                                                <li data-ok="{{ $ok ? 'true' : 'false' }}"><i class="fas {{ $ok ? 'fa-check' : 'fa-circle-exclamation' }}" aria-hidden="true"></i> {{ __($m.'check.'.$check) }}<span class="gs-sr-only"> ({{ __($m.($ok ? 'check_ok' : 'check_missing')) }})</span></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="gs-tracking-manage__status">
                                    <x-gs::status-badge :status="$badge" :label="__($m.'status.'.$c['status'])" />
                                    @if($c['status'] === 'ACTIVE')
                                        @if($c['activated'])<small>{{ __($m.'since', ['date' => $date($c['activated'])]) }}</small>@endif
                                        @if($c['plan'] !== '1_BLANKET' && $c['planned'] > 0)<small>{{ __($m.'received_of', ['received' => $num($c['received']), 'planned' => $num($c['planned'])]) }}</small>@endif
                                    @elseif($c['status'] === 'DRAFT')
                                        @if($c['created'])<small>{{ __($m.'created_on', ['date' => $date($c['created'])]) }}</small>@endif
                                    @elseif($c['archived_at'])
                                        <small>{{ __($m.'archived_on', ['date' => $date($c['archived_at'])]) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="gs-tracking-manage__actions">
                                        @if($c['status'] === 'DRAFT' && $can['head'] && $c['ready'])
                                            <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.activate', [$initiative->id, $c['id']]) }}">@csrf<button type="submit" class="gs-btn gs-btn--success gs-btn--sm" data-tracking-confirm="{{ __($m.'confirm.activate', ['code' => $c['code']]) }}"><i class="fas fa-circle-play" aria-hidden="true"></i> {{ __($m.'activate') }}</button></form>
                                        @elseif($c['status'] === 'DRAFT' && $can['edit'])
                                            <a class="gs-btn gs-btn--primary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.tracking-codes.edit', [$initiative->id, $c['id']]) }}"><i class="fas fa-pen" aria-hidden="true"></i> {{ __($m.'edit') }}</a>
                                        @elseif($c['status'] === 'ACTIVE' && in_array('order', $c['missing'], true) && $can['edit'])
                                            <a class="gs-btn gs-btn--warning gs-btn--sm" href="#order-{{ $c['id'] }}" data-manage-open="{{ $c['id'] }}"><i class="fas fa-file-arrow-up" aria-hidden="true"></i> {{ __($m.'attach') }}</a>
                                        @else
                                            <a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.tracking-codes.show', [$initiative->id, $c['id']]) }}">{{ __($m.'open') }}<span class="gs-sr-only"> {{ $c['code'] }}</span></a>
                                        @endif
                                        <details class="gs-tracking-more">
                                            <summary class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" aria-label="{{ __($m.'more', ['code' => $c['code']]) }}"><i class="fas fa-ellipsis" aria-hidden="true"></i></summary>
                                            <div class="gs-tracking-more__menu">
                                                <a href="{{ route('gov.tracking.initiatives.tracking-codes.show', [$initiative->id, $c['id']]) }}"><i class="fas fa-up-right-from-square" aria-hidden="true"></i> {{ __($m.'action.open') }}</a>
                                                @if($c['status'] !== 'DRAFT')<a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}#tracking-products-{{ $c['id'] }}"><i class="fas fa-chart-line" aria-hidden="true"></i> {{ __($m.'action.monitor') }}</a>@endif
                                                @if($c['order_pdf'])<a href="{{ route('gov.tracking.tracking-codes.download', $c['id']) }}"><i class="fas fa-file-pdf" aria-hidden="true"></i> {{ __($m.'action.download') }}</a>@endif
                                                @if($c['status'] === 'DRAFT' && $can['edit'])
                                                    <a href="{{ route('gov.tracking.initiatives.tracking-codes.edit', [$initiative->id, $c['id']]) }}"><i class="fas fa-pen" aria-hidden="true"></i> {{ __($m.'action.edit') }}</a>
                                                    @if($activateWhy)
                                                        <span class="gs-tracking-more__locked"><i class="fas fa-circle-play" aria-hidden="true"></i> {{ __($m.'action.activate') }}<small><i class="fas fa-lock" aria-hidden="true"></i> {{ $activateWhy }}</small></span>
                                                    @else
                                                        <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.activate', [$initiative->id, $c['id']]) }}">@csrf<button type="submit" data-tracking-confirm="{{ __($m.'confirm.activate', ['code' => $c['code']]) }}"><i class="fas fa-circle-play" aria-hidden="true"></i> {{ __($m.'action.activate') }}</button></form>
                                                    @endif
                                                @endif
                                                @if($canAttach)<a href="#order-{{ $c['id'] }}" data-manage-open="{{ $c['id'] }}"><i class="fas fa-file-arrow-up" aria-hidden="true"></i> {{ __($m.'action.'.($c['order_pdf'] ? 'replace' : 'attach')) }}</a>@endif
                                                @if($c['status'] === 'DRAFT' && $can['edit'])
                                                    <hr>
                                                    <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.destroy', [$initiative->id, $c['id']]) }}">@csrf @method('DELETE')<button type="submit" class="gs-tracking-more__danger" data-tracking-confirm="{{ __($m.'confirm.delete', ['code' => $c['code']]) }}"><i class="fas fa-trash" aria-hidden="true"></i> {{ __($m.'action.delete') }}</button></form>
                                                @endif
                                                @if($c['status'] === 'ACTIVE' && $can['edit'])
                                                    <hr>
                                                    @if($can['head'])
                                                        <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.archive', [$initiative->id, $c['id']]) }}">@csrf<button type="submit" class="gs-tracking-more__danger" data-tracking-confirm="{{ __($m.'confirm.archive', ['code' => $c['code']]) }}"><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __($m.'action.archive') }}</button></form>
                                                    @else
                                                        <span class="gs-tracking-more__locked"><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __($m.'action.archive') }}<small><i class="fas fa-lock" aria-hidden="true"></i> {{ __($m.'lock_head') }}</small></span>
                                                    @endif
                                                @endif
                                                @unless($can['edit'])<span class="gs-tracking-more__locked"><i class="fas fa-eye" aria-hidden="true"></i> {{ __($m.'read_only') }}</span>@endunless
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                            <tr class="gs-tracking-code-detail" data-monitor-detail id="manage-detail-{{ $c['id'] }}"><td colspan="6">@include('govtracking::initiatives.manage.code-detail', ['c' => $c, 'planLabel' => $planLabel, 'canAttach' => $canAttach, 'activateWhy' => $activateWhy])</td></tr>
                        @endforeach
                        </tbody>
                    </x-gs::table>
                @endif
            </div>

            <div class="gs-tracking-panel gs-tracking-manage__activity" id="manage-activity">
                <div class="gs-tracking-manage__head">
                    <h2>{{ __($m.'activity') }}</h2>
                    <nav class="gs-tracking-manage__chips" aria-label="{{ __($m.'activity') }}">
                        @foreach(\GovStore\Tracking\Services\TrackingManageService::ACTIVITY as $kind)
                            <a href="{{ $manageUrl(['activity' => $kind], '#manage-activity') }}" @if($filters['activity'] === $kind) aria-current="true" @endif>{{ __($m.'activity_tabs.'.$kind) }} <b>{{ $activity['counts'][$kind] }}</b></a>
                        @endforeach
                    </nav>
                </div>
                @if($activity['items'])
                    <ol class="gs-tracking-manage__events">@foreach($shownActivity as $event)@include('govtracking::initiatives.manage.event', ['event' => $event])@endforeach</ol>
                    @if($moreActivity)
                        <details class="gs-tracking-manage__more-events">
                            <summary>{{ __($m.'activity_more', ['count' => count($moreActivity)]) }}</summary>
                            <ol class="gs-tracking-manage__events">@foreach($moreActivity as $event)@include('govtracking::initiatives.manage.event', ['event' => $event])@endforeach</ol>
                        </details>
                    @endif
                @else
                    <p class="gs-tracking-muted">{{ __($m.'activity_none') }}</p>
                @endif
            </div>
        </div>

        <div class="gs-tracking-manage__rail">
            <div class="gs-tracking-panel">
                <div class="gs-tracking-manage__head">
                    <h2>{{ __($m.'team') }}</h2>
                    @if($can['head'])<a class="gs-tracking-manage__link" href="{{ $teamUrl }}">{{ __($m.'team_manage') }}</a>@endif
                </div>
                <div class="gs-roles">
                    @foreach(['HEAD' => true, 'OFFICER' => true, 'SUPPORT' => false, 'MONITOR' => false] as $designation => $required)
                        @forelse($team->get($designation, collect()) as $person)
                            <x-gs::role-slot :role="__('govtracking::general.monitor.designations.'.$designation)" :person="trim($person->first_name.' '.$person->last_name)" :action-label="$can['head'] ? __($m.'change') : null" :action-href="$can['head'] ? $teamUrl : null" />
                        @empty
                            <x-gs::role-slot :role="__('govtracking::general.monitor.designations.'.$designation)" :description="__($m.'role_help.'.$designation)" :required="$required" :optional="! $required" :action-label="$can['head'] ? __($m.'assign') : null" :action-href="$can['head'] ? $teamUrl : null" />
                        @endforelse
                    @endforeach
                </div>
            </div>
            <div class="gs-tracking-panel">
                <div class="gs-tracking-manage__head">
                    <h2>{{ __($m.'rules') }}</h2>
                    @if($can['settings'])<a class="gs-tracking-manage__link" href="{{ route('gov.tracking.initiatives.edit', $initiative->id) }}">{{ __($m.'edit_rules') }}</a>@endif
                </div>
                <x-gs::key-value :items="$ruleItems" :columns="1" />
                <p class="gs-tracking-muted gs-tracking-manage__note">{{ __($m.'rules_why') }}</p>
            </div>
            <div class="gs-tracking-panel gs-tracking-manage__tools">
                <h2>{{ __($m.'tools') }}</h2>
                @if($can['retro'])
                    <x-gs::action-row :title="__($m.'tool.retro')" :description="__($m.'tool.retro_why')" id="manage-tool-retro">
                        <x-slot:actions><a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.retrospective.index', $initiative->id) }}">{{ __($m.'open') }}</a></x-slot:actions>
                    </x-gs::action-row>
                @endif
                <x-gs::action-row :title="__($m.'tool.report')" :description="__($m.'tool.report_why')" id="manage-tool-report">
                    <x-slot:actions><a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.report', $initiative->id) }}">{{ __($m.'open') }}</a></x-slot:actions>
                </x-gs::action-row>
                @if($can['settings'])
                    <x-gs::action-row :title="__($m.'tool.properties')" :description="__($m.'tool.properties_why')" id="manage-tool-properties">
                        <x-slot:actions><a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.edit', $initiative->id) }}">{{ __($m.'open') }}</a></x-slot:actions>
                    </x-gs::action-row>
                @endif
            </div>
        </div>
    </div>
</div>
@stop
