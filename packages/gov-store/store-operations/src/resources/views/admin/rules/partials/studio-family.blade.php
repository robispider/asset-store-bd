{{-- View C: every place a policy can sit, each card showing only what it changes from its parent. --}}
@php
    $chosenOffice = $result['location']?->id;
    $chosenCompany = $result['company']?->id;
    $chosenCategory = $result['category']?->id;
    $chosenModel = $result['model']?->id;
    $kind = array_key_exists((string) request('kind'), $productTree) ? request('kind') : ($result['category']?->category_type ?? array_key_first($productTree));
    $layerKeys = ['global' => 'GLOBAL', 'company' => 'COMPANY', 'location' => 'LOCATION', 'category' => 'CATEGORY', 'model' => 'MODEL'];
    $crumbs = collect($layers)->filter(fn ($layer) => $layer['key'] !== 'DEFAULT' && $layer['chosen'])->pluck('target');
@endphp
<div class="rst-layout">
    <div class="rst-main">
        <section class="rst-panel" aria-labelledby="rst-org-title">
            <div class="rst-ph"><h2 id="rst-org-title">1 · {{ __('storeops::studio.org_section') }}</h2><span class="rst-muted rst-small">{{ __('storeops::studio.click_office') }}</span></div>
            <div class="rst-oc-scroll">
                <div class="rst-oc">
                    <ul>
                        <li>
                            @include('storeops::admin.rules.partials.studio-card', ['n' => $orgTree['global'], 'level' => $layerName('GLOBAL'),
                                'href' => $studioUrl(['company' => null, 'location' => null, 'node' => 'global']), 'current' => ! $chosenCompany && ! $chosenOffice, 'path' => true])
                            @if ($orgTree['companies'] || $orgTree['unassigned_offices'])
                                <ul>
                                    @foreach ($orgTree['companies'] as $company)
                                        <li>
                                            @include('storeops::admin.rules.partials.studio-card', ['n' => $company, 'level' => $layerName('COMPANY'),
                                                'href' => $studioUrl(['company' => $company['id'], 'location' => null, 'node' => 'company:'.$company['id']]),
                                                'current' => $chosenCompany === $company['id'] && ! $chosenOffice, 'path' => $chosenCompany === $company['id']])
                                            @if ($company['children'])
                                                @include('storeops::admin.rules.partials.studio-office-stack', ['offices' => $company['children']])
                                            @endif
                                        </li>
                                    @endforeach
                                    @if ($orgTree['unassigned_offices'])
                                        <li>
                                            <span class="rst-group-lbl">{{ __('storeops::studio.offices_without_ministry') }}</span>
                                            @include('storeops::admin.rules.partials.studio-office-stack', ['offices' => $orgTree['unassigned_offices']])
                                        </li>
                                    @endif
                                </ul>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
            @if ($orgTree['hidden_companies'])
                <p class="rst-tree-more">{{ trans_choice('storeops::studio.hidden_companies', $orgTree['hidden_companies'], ['count' => number_format($orgTree['hidden_companies'])]) }}</p>
            @endif
        </section>

        <p class="rst-join">{{ __('storeops::studio.then_products') }}</p>

        <section class="rst-panel" aria-labelledby="rst-products-title">
            <div class="rst-ph"><h2 id="rst-products-title">2 · {{ __('storeops::studio.product_section') }}</h2><span class="rst-muted rst-small">{{ __('storeops::studio.click_category') }}</span></div>
            <nav class="rst-kinds" aria-label="{{ __('storeops::studio.product_kinds') }}">
                <div class="rst-filter">
                    @foreach ($productTree as $key => $nodes)
                        <a class="rst-gs-plain" href="{{ $studioUrl(['kind' => $key]) }}" @if ($key === $kind) aria-current="true" @endif>{{ __('storeops::studio.kinds.'.$key) }} · {{ count($nodes) }}</a>
                    @endforeach
                </div>
            </nav>
            <ul class="rst-minis">
                @foreach ($productTree[$kind] ?? [] as $category)
                    <li class="rst-mini">
                        @include('storeops::admin.rules.partials.studio-card', ['n' => $category, 'level' => $layerName('CATEGORY'),
                            'href' => $studioUrl(['category' => $category['id'], 'model' => null, 'node' => 'category:'.$category['id']]),
                            'current' => $chosenCategory === $category['id'] && ! $chosenModel, 'path' => $chosenCategory === $category['id']])
                        @if ($category['children'])
                            <ul class="rst-mini__kids">
                                @foreach ($category['children'] as $model)
                                    <li>
                                        @include('storeops::admin.rules.partials.studio-card', ['n' => $model, 'level' => $layerName('MODEL'),
                                            'href' => $studioUrl(['model' => $model['id'], 'category' => null, 'node' => 'model:'.$model['id']]),
                                            'current' => $chosenModel === $model['id'], 'path' => $chosenModel === $model['id']])
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($category['model_count'] > count($category['children']))
                            <span class="rst-mini__more">{{ trans_choice('storeops::studio.models_follow', $category['model_count'] - count($category['children']), ['count' => $category['model_count'] - count($category['children'])]) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="rst-legend">
                <span><span class="rst-diff rst-diff--add"><span class="rst-diff__k">+</span></span> {{ __('storeops::studio.legend_add') }}</span>
                <span><span class="rst-diff rst-diff--change"><span class="rst-diff__k">~</span></span> {{ __('storeops::studio.legend_change') }}</span>
                <span><span class="rst-diff rst-diff--off"><span class="rst-diff__k">✕</span></span> {{ __('storeops::studio.legend_off') }}</span>
                <span>{{ __('storeops::studio.legend_dashed') }}</span>
                <span>{{ __('storeops::studio.legend_path') }}</span>
            </div>
        </section>
    </div>

    <aside class="rst-side rst-panel" aria-labelledby="rst-gets-title" aria-live="polite">
        <div class="rst-ph"><h2 id="rst-gets-title">{{ __('storeops::studio.storekeeper_gets') }}</h2></div>
        <p class="rst-crumbs">
            @foreach ($crumbs as $crumb)
                @if (! $loop->first)<span aria-hidden="true">›</span>@endif
                <strong>{{ $crumb }}</strong>
            @endforeach
        </p>
        <ul class="rst-eff">
            @foreach ($rules as $rule)
                <li>
                    <span class="rst-row__name">{{ $rule['name'] }}</span>
                    <span class="rst-st rst-st--{{ $rule['result'] }}">{{ $rule['value'] }}</span>
                    <span class="rst-eff__src">
                        @if ($rule['decider'])
                            {{ __('storeops::studio.set_by', ['source' => $sourceOf($byKey[$rule['decider']])]) }}@if ($rule['replaced']) — {{ __('storeops::studio.replaced_source', ['source' => $sourceOf($byKey[end($rule['replaced'])])]) }}@endif
                        @else
                            {{ __('storeops::studio.nobody') }}
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
        @include('storeops::admin.rules.partials.studio-attached')
    </aside>
</div>
