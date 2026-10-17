{{-- View A: where policies are attached (tree) + what applies for the chosen office and item. --}}
@php
    $chosenOffice = $result['location']?->id;
    $chosenCompany = $result['company']?->id;
    $chosenCategory = $result['category']?->id;
    $chosenModel = $result['model']?->id;
@endphp
<div class="rst-layout">
    <aside class="rst-side rst-panel" aria-label="{{ __('storeops::studio.where_attached') }}">
        <div class="rst-ph"><h2>{{ __('storeops::studio.where_attached') }}</h2></div>
        <div class="rst-search">
            <label class="gs-sr-only" for="rst-tree-filter">{{ __('storeops::studio.filter_tree') }}</label>
            <input id="rst-tree-filter" type="search" placeholder="{{ __('storeops::studio.filter_tree') }}" data-rst-filter="rst-tree">
        </div>
        <div class="rst-scrollbox" id="rst-tree">
            <div class="rst-tree-sec">{{ __('storeops::studio.org_section') }}</div>
            <ul class="rst-tree">
                <li>
                    @include('storeops::admin.rules.partials.studio-node', ['n' => $orgTree['global'], 'icon' => 'fa-globe',
                        'href' => $studioUrl(['company' => null, 'location' => null, 'node' => 'global']), 'current' => $node['type'] === 'global'])
                    <ul>
                        @foreach ($orgTree['companies'] as $company)
                            <li>
                                @include('storeops::admin.rules.partials.studio-node', ['n' => $company, 'icon' => 'fa-landmark',
                                    'href' => $studioUrl(['company' => $company['id'], 'location' => null, 'node' => 'company:'.$company['id']]),
                                    'current' => $chosenCompany === $company['id'] && ! $chosenOffice])
                                @if ($company['children'])
                                    <ul>
                                        @foreach ($company['children'] as $office)
                                            <li>
                                                @include('storeops::admin.rules.partials.studio-node', ['n' => $office, 'icon' => 'fa-building',
                                                    'href' => $studioUrl(['location' => $office['id'], 'company' => null, 'node' => 'location:'.$office['id']]),
                                                    'current' => $chosenOffice === $office['id']])
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                        @foreach ($orgTree['unassigned_offices'] as $office)
                            <li>
                                @include('storeops::admin.rules.partials.studio-node', ['n' => $office, 'icon' => 'fa-building',
                                    'href' => $studioUrl(['location' => $office['id'], 'company' => null, 'node' => 'location:'.$office['id']]),
                                    'current' => $chosenOffice === $office['id']])
                            </li>
                        @endforeach
                    </ul>
                    @if ($orgTree['hidden_companies'])
                        <p class="rst-tree-more">{{ trans_choice('storeops::studio.hidden_companies', $orgTree['hidden_companies'], ['count' => number_format($orgTree['hidden_companies'])]) }}</p>
                    @endif
                </li>
            </ul>

            <div class="rst-tree-sec">{{ __('storeops::studio.product_section') }}</div>
            <div class="rst-tree">
                @foreach ($productTree as $kind => $categoryNodes)
                    <details @if (collect($categoryNodes)->contains('id', $chosenCategory)) open @endif>
                        <summary>{{ __('storeops::studio.kinds.'.$kind) }} · {{ count($categoryNodes) }}</summary>
                        <ul>
                            @foreach ($categoryNodes as $category)
                                <li>
                                    @include('storeops::admin.rules.partials.studio-node', ['n' => $category, 'icon' => 'fa-cubes',
                                        'href' => $studioUrl(['category' => $category['id'], 'model' => null, 'node' => 'category:'.$category['id']]),
                                        'current' => $chosenCategory === $category['id'] && ! $chosenModel])
                                    @if ($category['children'])
                                        <ul>
                                            @foreach ($category['children'] as $model)
                                                <li>
                                                    @include('storeops::admin.rules.partials.studio-node', ['n' => $model, 'icon' => 'fa-tag',
                                                        'href' => $studioUrl(['model' => $model['id'], 'category' => null, 'node' => 'model:'.$model['id']]),
                                                        'current' => $chosenModel === $model['id']])
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if ($category['model_count'] > count($category['children']))
                                        <p class="rst-tree-more">{{ trans_choice('storeops::studio.models_follow', $category['model_count'] - count($category['children']), ['count' => $category['model_count'] - count($category['children'])]) }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </div>
    </aside>

    <div class="rst-main">
        <section class="rst-panel" aria-labelledby="rst-applies-title">
            <div class="rst-ph">
                <h2 id="rst-applies-title">{{ __('storeops::studio.applies_title', ['item' => $result['model']?->name ?? $result['category']?->name ?? __('storeops::studio.any_item'), 'office' => $result['location']?->name ?? $result['company']?->name ?? __('storeops::studio.any_office')]) }}</h2>
            </div>
            <ol class="rst-ribbon" aria-label="{{ __('storeops::studio.order_label') }}">
                @foreach ($layers as $index => $layer)
                    @php
                        $count = count($layer['rules']);
                        $empty = ! $layer['profile'] && ! $count;
                        $here = $layer['key'] === $node['layer'];
                    @endphp
                    <li @class(['rst-chip', 'rst-chip--none' => $empty, 'rst-chip--here' => $here])>
                        <span class="rst-chip__lv"><i class="fas {{ $layerIcons[$layer['key']] }}" aria-hidden="true"></i> {{ $index + 1 }} · {{ $layerName($layer['key']) }}</span>
                        <span class="rst-chip__pol">{{ $layer['key'] === 'DEFAULT' ? __('storeops::studio.package_default') : ($layer['profile']['name'] ?? ($layer['chosen'] ? __('storeops::studio.no_own_policy') : __('storeops::studio.not_chosen'))) }}</span>
                        <span class="rst-chip__n">{{ $layer['key'] === 'DEFAULT' ? '' : ($layer['target'] ? $layer['target'].' · ' : '') }}{{ $count ? trans_choice('storeops::studio.sets_n', $count, ['count' => $count]) : __('storeops::studio.passes_all') }}</span>
                    </li>
                    <li class="rst-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></li>
                @endforeach
                <li class="rst-chip rst-chip--result">
                    <span class="rst-chip__lv">= {{ __('storeops::studio.result') }}</span>
                    <span class="rst-chip__pol">{{ __('storeops::studio.storekeeper_gets') }}</span>
                </li>
            </ol>
            @include('storeops::admin.rules.partials.studio-attached')
        </section>

        <section class="rst-panel" aria-labelledby="rst-rules-title">
            <div class="rst-ph">
                <h2 id="rst-rules-title">{{ __('storeops::studio.what_applies') }}</h2>
                <span class="rst-muted rst-small">{{ __('storeops::studio.select_rule_hint') }}</span>
            </div>
            <div class="rst-rows">
                <div class="rst-rhead" aria-hidden="true">
                    <span>{{ __('storeops::studio.rule') }}</span><span>{{ __('storeops::studio.result') }}</span><span>{{ __('storeops::studio.decided_by') }}</span><span>{{ __('storeops::studio.flow_header') }}</span>
                </div>
                @foreach ($rules as $code => $rule)
                    @php
                        $decider = $rule['decider'] ? $byKey[$rule['decider']] : null;
                        $overrides = $rule['result'] === 'off' && collect($rule['replaced'])->contains(fn ($key) => $rule['cells'][$key]['state'] === 'on');
                    @endphp
                    <a class="rst-row rst-gs-plain" href="{{ $studioUrl(['rule' => $code]) }}#rst-why" data-rst-rule="{{ $code }}" @if ($code === $selectedRule) aria-current="true" @endif>
                        <span><span class="rst-row__name">{{ $rule['name'] }}</span><span class="rst-row__desc">{{ $rule['desc'] }}</span></span>
                        <span><span class="rst-st rst-st--{{ $rule['result'] }}">{{ $rule['value'] }}</span></span>
                        <span>
                            <span class="rst-row__by">{{ $decider ? $sourceOf($decider) : __('storeops::studio.nobody') }}</span>
                            @if ($overrides)
                                <span class="rst-row__note rst-row__note--warn">{{ __('storeops::studio.overrides', ['source' => $sourceOf($byKey[$rule['replaced'][0]])]) }}</span>
                            @elseif ($rule['replaced'])
                                <span class="rst-row__note">{{ __('storeops::studio.replaces', ['source' => $sourceOf($byKey[end($rule['replaced'])])]) }}</span>
                            @elseif ($decider)
                                <span class="rst-row__note">{{ $layerName($decider['key']) }}</span>
                            @endif
                        </span>
                        <span class="rst-trail" aria-label="{{ __('storeops::studio.flow_header') }}">
                            @foreach ($rule['cells'] as $key => $cell)
                                @if (! $loop->first)<span class="rst-link"></span>@endif
                                <span @class(['rst-dot', 'rst-dot--'.$cell['state'], 'rst-dot--'.$cell['role'] => $cell['role']]) title="{{ $layerName($key) }}: {{ $cell['value'] ?? __('storeops::studio.says_nothing') }}">{{ $cell['state'] === 'on' ? '✓' : ($cell['state'] === 'off' ? '✕' : '') }}</span>
                            @endforeach
                        </span>
                    </a>
                @endforeach
            </div>
            <div class="rst-legend">
                <span><span class="rst-dot rst-dot--on">✓</span> {{ __('storeops::studio.legend_on') }}</span>
                <span><span class="rst-dot rst-dot--off">✕</span> {{ __('storeops::studio.legend_off') }}</span>
                <span><span class="rst-dot rst-dot--pass"></span> {{ __('storeops::studio.legend_pass') }}</span>
                <span><span class="rst-dot rst-dot--on rst-dot--decider">✓</span> {{ __('storeops::studio.legend_decider') }}</span>
                <span><span class="rst-dot rst-dot--on rst-dot--replaced">✓</span> {{ __('storeops::studio.legend_replaced') }}</span>
            </div>
        </section>

        <div id="rst-why">
            @foreach ($rules as $code => $rule)
                <section class="rst-panel rst-why" data-rst-why="{{ $code }}" @if ($code !== $selectedRule) hidden @endif aria-live="polite">
                    <h3>{{ __('storeops::studio.why_title', ['rule' => $rule['name']]) }}</h3>
                    <p class="rst-muted">{{ __('storeops::studio.why_hint') }}</p>
                    <ol class="rst-steps">
                        @foreach ($layers as $layer)
                            @php
                                $cell = $rule['cells'][$layer['key']];
                                $set = in_array($cell['state'], ['on', 'off'], true);
                            @endphp
                            <li class="rst-step">
                                <span @class(['rst-step__dot', 'rst-step__dot--'.$cell['state'] => $set])>{{ $cell['state'] === 'on' ? '✓' : ($cell['state'] === 'off' ? '✕' : '') }}</span>
                                <span>
                                    <span class="rst-step__lv">{{ $layerName($layer['key']) }} · {{ $sourceOf($layer) }}</span>
                                    <span class="rst-step__who">{{ $layer['key'] === 'DEFAULT' ? __('storeops::studio.installed_packages') : ($layer['target'] ?? __('storeops::studio.not_chosen')) }}</span>
                                </span>
                                @if ($set)
                                    <span @class(['rst-step__said--replaced' => $cell['role'] === 'replaced'])>{{ $cell['value'] }} — {{ $cell['role'] === 'decider' ? __('storeops::studio.wins') : __('storeops::studio.replaced_below') }}</span>
                                @elseif (! $layer['chosen'])
                                    <span class="rst-step__said--pass">{{ __('storeops::studio.not_in_context') }}</span>
                                @elseif (! $layer['profile'] && $layer['key'] !== 'DEFAULT')
                                    <span class="rst-step__said--pass">{{ __('storeops::studio.no_policy_passes') }}</span>
                                @else
                                    <span class="rst-step__said--pass">{{ __('storeops::studio.silent_passes') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                    <p class="rst-verdict">
                        <strong>{{ __('storeops::studio.result') }}:</strong>
                        @if ($rule['decider'])
                            {{ __('storeops::studio.verdict', ['rule' => $rule['name'], 'value' => $rule['value'], 'source' => $sourceOf($byKey[$rule['decider']])]) }}
                        @else
                            {{ __('storeops::studio.verdict_none', ['rule' => $rule['name']]) }}
                        @endif
                    </p>
                </section>
            @endforeach
        </div>
    </div>
</div>
