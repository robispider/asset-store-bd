@extends('layouts/default')
@section('title', __('storeops::storeops.store_documents_hub'))

@section('content')
@php
    $h = 'storeops::storeops.hub.';
    $hub = \GovStore\StoreOperations\Services\DocumentHub::class;
    $link = fn (array $changes = []) => route('storeops.hub', $hub::query($filters, $changes));
    $tabs = [];
    foreach ($hub::FILTERS as $key) {
        $tabs[] = ['key' => $key, 'label' => __($h.'tabs.'.$key), 'href' => $link(['filter' => $key]), 'count' => $counts[$key]];
    }
    $typeIcons = ['receipt' => 'fa-arrow-right-to-bracket', 'issue' => 'fa-arrow-right-from-bracket', 'transfer' => 'fa-right-left',
        'adjustment' => 'fa-sliders', 'opening' => 'fa-book-open'];
    $startType = $filters['start'] ?? 'receipt';
    $canStart = $abilities['draft'] && $office['id'];
    $kpis = [
        ['key' => 'drafts', 'value' => $counts['drafts'], 'icon' => 'fa-pen', 'tone' => 'primary', 'href' => $link(['filter' => 'drafts'])],
        ['key' => 'ready', 'value' => $counts['ready'], 'icon' => 'fa-clipboard-check', 'tone' => 'warning', 'href' => $link(['filter' => 'ready'])],
        ['key' => 'posted', 'value' => $counts['posted_month'], 'icon' => 'fa-lock', 'tone' => 'success', 'href' => $link(['filter' => 'posted'])],
    ];
@endphp
<div class="storeops-theme storeops-hub">
    @include('storeops::register.partials.context-bar')

    <x-gs::page-header :title="__($h.'title')" bn="স্টোর দলিল" :subtitle="__($h.'subtitle')">
        <x-slot:actions>
            <x-gs::button tone="secondary" outline icon="fa-table-list" :href="route('storeops.register.index')">{{ __($h.'stock_register') }}</x-gs::button>
            @if ($canStart)
                <x-gs::button icon="fa-plus" :href="$link(['start' => 'receipt'])" data-storeops-open="storeops-start">{{ __($h.'start') }}</x-gs::button>
            @endif
        </x-slot:actions>
    </x-gs::page-header>

    @if (! $office['id'])
        <x-gs::empty-state icon="fa-building" :title="__($h.'no_office')" />
    @else
        @if ($notices->isNotEmpty())
            <x-gs::alert tone="info" :title="__($h.'updates_title')" dismissible>
                <ul class="storeops-hub-notices">
                    @foreach ($notices as $notice)
                        <li>
                            {{ __($h.'events.'.$notice['event'], ['actor' => $notice['actor'], 'number' => $notice['number']]) }}
                            <span class="gs-muted">· {{ $notice['at'] }}</span>
                            <a href="{{ $link(['doc' => $notice['id']]) }}">{{ __($h.'view_document') }}<span class="gs-sr-only"> {{ $notice['number'] }}</span></a>
                        </li>
                    @endforeach
                </ul>
            </x-gs::alert>
        @endif

        @unless ($opened)
            <x-gs::alert tone="warning" :title="__($h.'paused_title')">{{ __($h.'paused_text') }}</x-gs::alert>
        @endunless

        <section class="storeops-hub-attention" aria-labelledby="storeops-hub-attention">
            <h2 id="storeops-hub-attention" class="storeops-hub-h2">{{ __($h.'attention_title') }}</h2>
            <div class="gs-kpis">
                @foreach ($kpis as $kpi)
                    <x-gs::kpi :label="__($h.'kpi.'.$kpi['key'])" :value="$kpi['value']" :icon="$kpi['icon']" :tone="$kpi['tone']"
                        :caption="__($h.'kpi.'.$kpi['key'].'_caption')" :href="$kpi['href']"
                        :class="$filters['filter'] === $kpi['key'] ? 'storeops-kpi--active' : ''" :aria-current="$filters['filter'] === $kpi['key'] ? 'true' : 'false'" />
                @endforeach
            </div>
        </section>

        <section class="storeops-hub-flow" aria-labelledby="storeops-hub-flow">
            <h2 id="storeops-hub-flow" class="storeops-hub-flow__title">{{ __($h.'flow_title') }}</h2>
            <ol class="storeops-hub-flow__steps">
                @foreach (['draft', 'ready', 'posted'] as $i => $state)
                    <li>
                        <x-gs::status-badge :status="$state" :label="($i + 1).' · '.__($h.'statuses.'.$state)" />
                        <span class="gs-muted">{{ __($h.'flow.'.$state) }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="gs-muted storeops-hub-flow__note">{{ __($h.'flow.cancelled') }}</p>
        </section>

        <x-gs::box :title="__($h.'documents_title')" icon="fas fa-folder-open" class="storeops-hub-documents">
            <x-gs::status-tabs :tabs="$tabs" :active="$filters['filter']" :label="__($h.'tabs_label')" />

            <form method="get" action="{{ route('storeops.hub') }}" class="storeops-toolbar storeops-hub-toolbar" role="search">
                @if ($filters['filter'] !== 'all')<input type="hidden" name="filter" value="{{ $filters['filter'] }}">@endif
                @if ($filters['type'] !== 'all')<input type="hidden" name="type" value="{{ $filters['type'] }}">@endif
                <div class="storeops-field storeops-field--grow">
                    <label for="storeops-hub-q" class="storeops-label">{{ __($h.'search') }}</label>
                    <input id="storeops-hub-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control storeops-control" placeholder="{{ __($h.'search_hint') }}">
                </div>
                <x-gs::button type="submit" icon="fa-magnifying-glass" class="storeops-control">{{ __($h.'search_button') }}</x-gs::button>
            </form>

            <nav class="storeops-hub-types" aria-label="{{ __($h.'type_filter') }}">
                @foreach (['all', ...$hub::TYPES] as $type)
                    <x-gs::button :tone="$filters['type'] === $type ? 'primary' : 'secondary'" :outline="$filters['type'] !== $type" pill size="sm"
                        :icon="$typeIcons[$type] ?? null" :href="$link(['type' => $type])" :aria-current="$filters['type'] === $type ? 'true' : 'false'">{{ __($h.'types_plural.'.$type) }}</x-gs::button>
                @endforeach
            </nav>

            <p class="gs-muted storeops-count" aria-live="polite">{{ __($h.'showing', ['shown' => $documents->total(), 'total' => $counts['all']]) }}</p>

            @if ($documents->isEmpty())
                <x-gs::empty-state icon="fa-magnifying-glass" :title="__($filters['q'] !== '' || $filters['type'] !== 'all' ? $h.'no_match' : $h.'empty.'.$filters['filter'])">
                    {{ __($filters['q'] !== '' || $filters['type'] !== 'all' ? $h.'no_match_hint' : $h.'empty_hint') }}
                    @if ($filters['q'] !== '' || $filters['type'] !== 'all' || $filters['filter'] !== 'all')
                        <x-slot:actions><x-gs::button tone="secondary" outline :href="route('storeops.hub')">{{ __($h.'clear_filters') }}</x-gs::button></x-slot:actions>
                    @endif
                </x-gs::empty-state>
            @else
                <x-gs::table class="storeops-hub-table" :caption="__($h.'table_caption')">
                    <thead>
                        <tr>
                            <th scope="col">{{ __($h.'col_document') }}</th>
                            <th scope="col">{{ __($h.'col_status') }}</th>
                            <th scope="col">{{ __($h.'col_party') }}</th>
                            <th scope="col">{{ __($h.'col_reference') }}</th>
                            <th scope="col">{{ __($h.'col_handler') }}</th>
                            <th scope="col">{{ __($h.'col_activity') }}</th>
                            <th scope="col" class="gs-num"><span class="gs-sr-only">{{ __($h.'col_action') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $row)
                            <tr @class(['storeops-hub-row--selected' => $panel && $panel['id'] === $row['id']])>
                                <td>
                                    <a class="storeops-mono storeops-hub-number" href="{{ $link(['doc' => $row['id']]) }}">{{ $row['number'] }}</a>
                                    <span class="storeops-hub-kind gs-muted">
                                        <i class="fas {{ $typeIcons[$row['type']] ?? 'fa-file' }}" aria-hidden="true"></i>
                                        {{ $row['type_label'] }} · {{ trans_choice($h.'lines', $row['lines'], ['count' => $row['lines']]) }}
                                    </span>
                                </td>
                                <td><x-gs::status-badge :status="$row['status']" :label="$row['status_label']" /></td>
                                <td>{{ $row['party'] ?? '—' }}</td>
                                <td>{{ $row['reference'] ?? '—' }}</td>
                                <td>
                                    <span class="storeops-hub-person">
                                        <x-gs::avatar :name="$row['handler']" size="sm" />
                                        <span>{{ $row['handler'] }}@if ($row['mine']) <span class="gs-muted">{{ __($h.'you') }}</span>@endif</span>
                                    </span>
                                </td>
                                <td class="storeops-hub-when">{{ $row['activity'] }}</td>
                                <td class="gs-num">
                                    @if ($row['action'] === 'view')
                                        <x-gs::button tone="secondary" outline size="sm" :href="$link(['doc' => $row['id']])">
                                            {{ __($h.'actions.view') }}<span class="gs-sr-only"> {{ $row['number'] }}</span></x-gs::button>
                                    @else
                                        <x-gs::button size="sm" :href="$row['workspace']">
                                            {{ __($h.'actions.'.$row['action']) }}<span class="gs-sr-only"> {{ $row['number'] }}</span></x-gs::button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-gs::table>
                {{ $documents->links() }}
            @endif

            <x-slot:footer>
                <span class="storeops-hub-audit"><i class="fas fa-shield-halved" aria-hidden="true"></i> {{ __($h.'audit_note') }}</span>
            </x-slot:footer>
        </x-gs::box>
    @endif

    @if ($panel)
        <dialog class="storeops-dialog storeops-drawer" id="storeops-panel" open aria-labelledby="storeops-panel-title" data-close-href="{{ $link() }}">
            <div class="storeops-drawer__inner">
                <header class="storeops-drawer__head">
                    <div>
                        <span class="gs-muted">{{ $panel['type_label'] }}</span>
                        <h2 id="storeops-panel-title" class="storeops-mono storeops-drawer__title">{{ $panel['number'] }}</h2>
                        <x-gs::status-badge :status="$panel['status']" :label="$panel['status_label']" />
                    </div>
                    <a class="storeops-dialog__close" href="{{ $link() }}" data-storeops-close aria-label="{{ __($h.'close_details') }}"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                </header>

                <div class="storeops-drawer__body">
                    <x-gs::stepper :steps="$panel['steps']" :label="__($h.'progress')" />
                    <x-gs::key-value :items="$panel['facts']" :columns="1" />

                    @if ($panel['checklist'])
                        <x-gs::readiness :items="$panel['checklist']" :title="__($h.'before_post')" :caption="__($h.'before_post_hint')" readonly />
                    @endif

                    @if ($panel['status'] === 'posted')
                        <x-gs::alert tone="success" :title="__($h.'locked_title')">{{ __($h.'locked_text') }}</x-gs::alert>
                    @elseif ($panel['status'] === 'cancelled')
                        <x-gs::alert tone="info">{{ __($h.'cancelled_text') }}</x-gs::alert>
                    @endif

                    @if ($panel['managed_elsewhere'] && $abilities['draft'])
                        <form method="post" action="{{ $panel['takeover'] }}" class="storeops-hub-takeover">
                            @csrf
                            <h3 class="storeops-h4">{{ __($h.'takeover_title', ['name' => $panel['handler']]) }}</h3>
                            <p class="gs-muted">{{ __($h.'takeover_text') }}</p>
                            <label for="storeops-takeover-reason" class="storeops-label">{{ __('storeops::storeops.takeover_reason') }}</label>
                            <textarea id="storeops-takeover-reason" name="reason" rows="3" minlength="5" maxlength="500" required
                                class="form-control" aria-describedby="storeops-takeover-help"></textarea>
                            <p id="storeops-takeover-help" class="gs-muted storeops-hub-help">{{ __($h.'reason_help') }}</p>
                            <x-gov-action ability="storeops.documents.draft" type="submit" class="gs-btn gs-btn--primary">{{ __($h.'takeover_button') }}</x-gov-action>
                        </form>
                    @endif

                    <section aria-labelledby="storeops-panel-timeline">
                        <h3 id="storeops-panel-timeline" class="storeops-h4">{{ __($h.'timeline_title') }}</h3>
                        <x-gs::timeline :items="$panel['timeline']" />
                    </section>
                </div>

                <footer class="storeops-drawer__foot">
                    @if ($panel['mine'])
                        <div class="storeops-actions">
                            <x-gs::button icon="fa-pen-to-square" :href="$panel['workspace']">{{ __($h.'continue_workspace') }}</x-gs::button>
                        </div>
                        <details class="storeops-details storeops-hub-void">
                            <summary>{{ __($h.'void_open') }}</summary>
                            <form method="post" action="{{ $panel['void'] }}">
                                @csrf
                                <p>{{ __($h.'void_text', ['number' => $panel['number']]) }}</p>
                                <label for="storeops-void-reason" class="storeops-label">{{ __($h.'void_reason') }}</label>
                                <textarea id="storeops-void-reason" name="reason" rows="2" minlength="5" maxlength="500" required class="form-control"
                                    aria-describedby="storeops-void-help"></textarea>
                                <p id="storeops-void-help" class="gs-muted storeops-hub-help">{{ __($h.'reason_help') }}</p>
                                <x-gov-action ability="storeops.documents.draft" type="submit" class="gs-btn gs-btn--danger">{{ __($h.'void_button') }}</x-gov-action>
                            </form>
                        </details>
                    @elseif ($panel['status'] === 'ready')
                        <div class="storeops-actions">
                            <x-gs::button icon="fa-clipboard-check" :href="$panel['workspace']">{{ __($h.'verify_post') }}</x-gs::button>
                        </div>
                        <p class="gs-muted storeops-hub-help">
                            {{ ! $abilities['post'] ? __($h.'post_needs_authority') : (! $opened ? __($h.'paused_short') : __($h.'post_hint')) }}
                        </p>
                    @elseif ($panel['status'] === 'posted')
                        <div class="storeops-actions">
                            <x-gs::button tone="secondary" outline :href="$panel['workspace']">{{ __($h.'view_document') }}</x-gs::button>
                            <x-gs::button tone="secondary" outline icon="fa-print" :href="$panel['print']">{{ __($h.'print') }}</x-gs::button>
                            @if ($canStart && $panel['type'] !== 'opening')
                                <x-gs::button tone="secondary" outline icon="fa-sliders" :href="$link(['start' => 'adjustment'])" data-storeops-open="storeops-start">{{ __($h.'correct') }}</x-gs::button>
                            @endif
                        </div>
                    @else
                        <div class="storeops-actions">
                            <x-gs::button tone="secondary" outline :href="$panel['workspace']">{{ __($h.'view_document') }}</x-gs::button>
                        </div>
                    @endif
                </footer>
            </div>
        </dialog>
    @endif

    @if ($canStart)
        <dialog class="storeops-dialog storeops-start" id="storeops-start" @if ($filters['start']) open @endif aria-labelledby="storeops-start-title" data-close-href="{{ $link() }}">
            <form method="post" action="{{ route('storeops.documents.initialize') }}" class="storeops-start__form">
                @csrf
                <header class="storeops-drawer__head">
                    <div>
                        <h2 id="storeops-start-title" class="storeops-start__title">{{ __($h.'start_title') }}</h2>
                        <p class="gs-muted">{{ __($h.'start_subtitle') }}</p>
                    </div>
                    <a class="storeops-dialog__close" href="{{ $link() }}" data-storeops-close aria-label="{{ __($h.'close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                </header>
                <fieldset class="storeops-start__choices">
                    <legend class="gs-sr-only">{{ __($h.'start_title') }}</legend>
                    @foreach ($hub::TYPES as $type)
                        <x-gs::choice-card name="document_type" :value="$type" :id="'storeops-start-'.$type" :icon="$typeIcons[$type]"
                            :label="__($h.'start_types.'.$type)" :description="__($h.'start_types.'.$type.'_desc')" :checked="$startType === $type" />
                    @endforeach
                </fieldset>
                @foreach ($hub::TYPES as $type)
                    <div class="storeops-start__needs" data-for="{{ $type }}">
                        <h3 class="storeops-h4">{{ __($h.'needs_title', ['type' => mb_strtolower(__($h.'types.'.$type))]) }}</h3>
                        <ul>
                            @foreach (__($h.'needs.'.$type) as $need)
                                <li><i class="fas fa-check" aria-hidden="true"></i> {{ $need }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <footer class="storeops-drawer__foot storeops-start__foot">
                    <p class="gs-muted storeops-hub-help">{{ __($h.'start_note') }}</p>
                    <div class="storeops-actions">
                        <a class="gs-btn gs-btn--secondary gs-btn--outline" href="{{ $link() }}" data-storeops-close>{{ __($h.'cancel') }}</a>
                        <x-gov-action ability="storeops.documents.draft" type="submit" class="gs-btn gs-btn--primary">{{ __($h.'create_draft') }}</x-gov-action>
                    </div>
                </footer>
            </form>
        </dialog>
    @endif
</div>
@endsection

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    // Progressive enhancement: the panel and the start dialog are server-rendered links, opened as modal dialogs here.
    (function () {
        var modal = function (dialog) {
            if (!dialog || typeof dialog.showModal !== 'function') { return; }
            if (dialog.open) { dialog.close(); }
            dialog.showModal();
        };
        document.querySelectorAll('[data-storeops-open]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                var dialog = document.getElementById(trigger.getAttribute('data-storeops-open'));
                if (!dialog || typeof dialog.showModal !== 'function') { return; }
                event.preventDefault();
                var type = new URL(trigger.href, window.location.href).searchParams.get('start');
                var choice = type && document.getElementById('storeops-start-' + type);
                if (choice) { choice.checked = true; }
                document.querySelectorAll('dialog.storeops-dialog[open]').forEach(function (open) { open.close(); });
                modal(dialog);
            });
        });
        document.querySelectorAll('dialog.storeops-dialog').forEach(function (dialog) {
            dialog.addEventListener('close', function () {
                var href = dialog.getAttribute('data-close-href');
                if (href && window.location.href !== href && !document.querySelector('dialog.storeops-dialog[open]')) {
                    window.history.replaceState(null, '', href);
                }
            });
            dialog.addEventListener('click', function (event) { if (event.target === dialog) { dialog.close(); } });
            dialog.querySelectorAll('[data-storeops-close]').forEach(function (close) {
                close.addEventListener('click', function (event) { event.preventDefault(); dialog.close(); });
            });
        });
        document.querySelectorAll('dialog.storeops-dialog[open]').forEach(modal);
    })();
</script>
@endsection
