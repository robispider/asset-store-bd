@extends('layouts/default')
@section('title', __('classification::collections.title'))
@section('content')
@php
    $visibleOthers = 6;
    $topSections = $sections->take(7);
    $largestSection = max(1, (int) ($sections->first() ?? 1));
    $featuredState = $featured ? $adoption[$featured->id] : ['adopted' => 0, 'remaining' => []];
    $remainingByCollection = array_map(function ($state) {
        return $state['remaining'];
    }, $adoption);
    $anyRemaining = collect($adoption)->contains(function ($state) {
        return count($state['remaining']) > 0;
    });
@endphp
<div class="classification-theme gs-discover">
    @include('gov-classification::discover.partials.nav', ['active' => 'collections'])

    <header class="gs-discover-hero">
        <div class="gs-discover-hero__text">
            <h1 class="gs-discover-title">{{ __('classification::discover.collections.heading') }}</h1>
            <p class="gs-discover-lede">{{ __('classification::collections.intro') }}</p>
        </div>
        <form class="gs-discover-search" role="search" method="GET" action="{{ route('gov.catalog.search') }}">
            <label for="discover-search" class="gs-discover-search__label">{{ __('classification::discover.collections.search_label') }}</label>
            <div class="gs-discover-search__field">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input id="discover-search" type="search" name="q" autocomplete="off" placeholder="{{ __('classification::discover.collections.search_placeholder') }}">
                <button type="submit" class="gs-discover-search__submit">{{ __('classification::discover.collections.search_submit') }}</button>
            </div>
        </form>
    </header>

    <ol class="gs-discover-steps" aria-label="{{ __('classification::discover.collections.steps_label') }}">
        @foreach (__('classification::discover.collections.steps') as $step)
            <li class="gs-discover-step">
                <span class="gs-discover-step__no" aria-hidden="true">{{ $loop->iteration }}</span>
                <div>
                    <p class="gs-discover-step__title">{{ $step['title'] }}</p>
                    <p class="gs-discover-step__body">{{ $step['body'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>

    @if ($featured)
        <section class="gs-discover-featured" aria-labelledby="featured-title">
            <div class="gs-discover-featured__main">
                <div class="gs-discover-featured__head">
                    <span class="gs-discover-featured__icon" aria-hidden="true"><i class="{{ $featured->icon ?: 'fas fa-layer-group' }}"></i></span>
                    <div class="gs-discover-featured__names">
                        <x-gs::badge tone="primary" pill>{{ __('classification::discover.collections.recommended') }}</x-gs::badge>
                        <h2 id="featured-title" class="gs-discover-featured__title">{{ $featured->display_name }}</h2>
                        @if (app()->getLocale() !== 'bn-BD' && $featured->name_bn)
                            <p class="gs-discover-featured__bn" lang="bn">{{ $featured->name_bn }}</p>
                        @endif
                    </div>
                </div>
                @if ($featured->display_description)
                    <p class="gs-discover-featured__desc">{{ $featured->display_description }}</p>
                @endif
                <dl class="gs-discover-stats">
                    <div class="gs-discover-stat"><dt>{{ __('classification::discover.collections.item_types') }}</dt><dd>{{ number_format($featured->nodes_count) }}</dd></div>
                    <div class="gs-discover-stat"><dt>{{ __('classification::discover.collections.groups') }}</dt><dd>{{ $sections->count() }}</dd></div>
                    <div class="gs-discover-stat"><dt>{{ __('classification::discover.collections.in_catalog') }}</dt><dd>{{ number_format($featuredState['adopted']) }}</dd></div>
                </dl>
                <x-gs::progress :value="$featuredState['adopted']" :max="$featured->nodes_count" tone="success"
                    :label="__('classification::collections.progress')"
                    :caption="__('classification::collections.adopted_count', ['adopted' => $featuredState['adopted'], 'total' => $featured->nodes_count])" />
                <div class="gs-discover-actions">
                    @if (count($featuredState['remaining']) === 0)
                        <x-gs::button tone="secondary" outline icon="fa-check-circle" disabled>{{ __('classification::collections.complete') }}</x-gs::button>
                    @elseif ($canAdopt)
                        <x-gs::button icon="fa-check-double" data-adopt-collection="{{ $featured->id }}">{{ __('classification::discover.collections.adopt_all', ['count' => count($featuredState['remaining'])]) }}</x-gs::button>
                    @endif
                    <x-gs::button tone="secondary" outline :href="route('gov.catalog.discover.collections.show', $featured->id)">{{ __('classification::discover.collections.preview_all') }}</x-gs::button>
                </div>
                @if (count($featuredState['remaining']) > 0)
                    <p class="gs-discover-muted">{{ $canAdopt ? __('classification::discover.collections.adopt_all_hint') : __('classification::discover.detail.no_permission') }}</p>
                @endif
            </div>
            @if ($topSections->isNotEmpty())
                <aside class="gs-discover-featured__inside" aria-labelledby="inside-title">
                    <h3 id="inside-title" class="gs-discover-featured__inside-title">{{ __('classification::discover.collections.inside') }}</h3>
                    <ul class="gs-discover-bars">
                        @foreach ($topSections as $section => $total)
                            <li>
                                <div class="gs-discover-bars__row"><span>{{ __('classification::collections.sections.'.$section) }}</span><span class="gs-discover-mono">{{ $total }}</span></div>
                                <div class="gs-discover-bars__track" aria-hidden="true"><span class="gs-discover-bars__bar" style="width: {{ round($total / $largestSection * 100) }}%"></span></div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($sections->count() > $topSections->count())
                        <a href="#discover-groups" class="gs-discover-link">{{ __('classification::discover.collections.more_groups', ['count' => $sections->count() - $topSections->count()]) }}</a>
                    @endif
                </aside>
            @endif
        </section>
    @endif

    @if ($others->isNotEmpty())
        <section class="gs-discover-section" aria-labelledby="all-collections-title">
            <div class="gs-discover-section__head">
                <h2 id="all-collections-title" class="gs-discover-section__title">{{ __('classification::discover.collections.all') }} <span class="gs-discover-muted">· {{ $collections->count() }}</span></h2>
            </div>
            <ul class="gs-discover-cards">
                @foreach ($others->take($visibleOthers) as $collection)
                    @include('gov-classification::discover.partials.collection-card', ['collection' => $collection, 'state' => $adoption[$collection->id]])
                @endforeach
            </ul>
            @if ($others->count() > $visibleOthers)
                <ul class="gs-discover-cards" id="discover-more-collections" hidden>
                    @foreach ($others->slice($visibleOthers) as $collection)
                        @include('gov-classification::discover.partials.collection-card', ['collection' => $collection, 'state' => $adoption[$collection->id]])
                    @endforeach
                </ul>
                <button type="button" class="gs-btn gs-btn--secondary gs-btn--outline gs-discover-more" aria-expanded="false" aria-controls="discover-more-collections"
                    data-more="{{ __('classification::discover.collections.show_more', ['count' => $others->count() - $visibleOthers]) }}"
                    data-less="{{ __('classification::discover.collections.show_less') }}">{{ __('classification::discover.collections.show_more', ['count' => $others->count() - $visibleOthers]) }}</button>
            @endif
        </section>
    @endif

    @if ($featured && $sections->isNotEmpty())
        <section class="gs-discover-section" id="discover-groups" aria-labelledby="groups-title">
            <div class="gs-discover-section__head">
                <h2 id="groups-title" class="gs-discover-section__title">{{ __('classification::discover.collections.by_group', ['name' => $featured->display_name]) }}</h2>
                <p class="gs-discover-muted">{{ __('classification::discover.collections.by_group_hint') }}</p>
            </div>
            <ul class="gs-discover-groups">
                @foreach ($sections as $section => $total)
                    <li class="gs-discover-group">
                        <span class="gs-discover-group__name">{{ __('classification::collections.sections.'.$section) }}</span>
                        <span class="gs-discover-group__count">{{ __('classification::discover.collections.group_count', ['count' => $total]) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($collections->isEmpty())
        <x-gs::empty-state icon="fa-layer-group" :title="__('classification::discover.collections.empty_title')">
            {{ __('classification::discover.collections.empty_body') }}
        </x-gs::empty-state>
    @endif

    <section class="gs-discover-callout" aria-labelledby="master-title">
        <div class="gs-discover-callout__text">
            <span class="gs-discover-callout__icon" aria-hidden="true"><i class="fas fa-globe"></i></span>
            <div>
                <h2 id="master-title" class="gs-discover-callout__title">{{ __('classification::discover.collections.master_title') }}</h2>
                <p class="gs-discover-muted">{{ __('classification::discover.collections.master_body', ['count' => number_format($masterCount)]) }}</p>
            </div>
        </div>
        <x-gs::button tone="secondary" outline :href="route('gov.catalog.discover.explorer')" icon="fa-compass">{{ __('classification::discover.collections.master_cta') }}</x-gs::button>
    </section>

    @if ($canAdopt && $anyRemaining)
        @include('gov-classification::adopt.partials.bulk-preview')
    @endif
</div>
@endsection

@section('moar_scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if ($canAdopt && $anyRemaining)
    var remainingByCollection = @json($remainingByCollection);
    document.querySelectorAll('[data-adopt-collection]').forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.dataset.adoptCollection;
            if ((remainingByCollection[id] || []).length > 0) triggerBulkAdoption(remainingByCollection[id], Number(id));
        });
    });
    @endif

    document.querySelectorAll('.gs-discover-more').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.getElementById(button.getAttribute('aria-controls'));
            var open = panel.hasAttribute('hidden');
            panel.hidden = !open;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.textContent = open ? button.dataset.less : button.dataset.more;
        });
    });
});
</script>
@endsection
