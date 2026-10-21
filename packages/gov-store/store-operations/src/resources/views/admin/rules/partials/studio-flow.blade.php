{{-- View B: one lane per rule, read left to right; the last level that sets a rule wins. --}}
@php
    $onlyChanged = request()->boolean('changed');
    $laneRules = collect($rules)->filter(function ($rule) use ($onlyChanged) {
        if (! $onlyChanged) {
            return true;
        }
        $values = collect($rule['cells'])->filter(fn ($cell) => in_array($cell['state'], ['on', 'off'], true))->pluck('value');

        return $values->count() > 1 && $values->unique()->count() > 1;
    });
    $overridden = collect($rules)->filter(fn ($rule) => $rule['result'] === 'off'
        && collect($rule['replaced'])->contains(fn ($key) => $rule['cells'][$key]['state'] === 'on'));
@endphp
<section class="rst-panel" aria-labelledby="rst-flow-title">
    <div class="rst-ph">
        <h2 id="rst-flow-title">{{ __('storeops::studio.flow_title') }}</h2>
        <div class="rst-filter" role="group" aria-label="{{ __('storeops::studio.filter_rules') }}">
            <a class="rst-gs-plain" href="{{ $studioUrl() }}" @if (! $onlyChanged) aria-current="true" @endif>{{ __('storeops::studio.all_rules', ['count' => count($rules)]) }}</a>
            <a class="rst-gs-plain" href="{{ $studioUrl(['changed' => 1]) }}" @if ($onlyChanged) aria-current="true" @endif>{{ __('storeops::studio.only_overridden') }}</a>
        </div>
    </div>
    <p class="rst-muted rst-small rst-context">{{ __('storeops::studio.flow_hint') }}</p>
    <div class="rst-lanes-scroll">
        <div class="rst-lanes" role="table" aria-label="{{ __('storeops::studio.flow_title') }}">
            <div class="rst-lane rst-lane--head" role="row">
                <div class="rst-lane__rule rst-small rst-muted" role="columnheader">{{ __('storeops::studio.rule') }}</div>
                @foreach ($layers as $index => $layer)
                    <div @class(['rst-lane__h', 'rst-lane__h--here' => $layer['key'] === $node['layer']]) role="columnheader">
                        <span class="rst-chip__lv">{{ $index + 1 }}. {{ $layerName($layer['key']) }}</span>
                        <span class="rst-chip__pol">{{ $layer['key'] === 'DEFAULT' ? __('storeops::studio.package_default') : ($layer['profile']['name'] ?? ($layer['chosen'] ? __('storeops::studio.no_own_policy') : __('storeops::studio.not_chosen'))) }}</span>
                        <span class="rst-chip__n">{{ $layer['key'] === 'DEFAULT' ? __('storeops::studio.installed_packages') : ($layer['target'] ?? '—') }}</span>
                    </div>
                @endforeach
                <div class="rst-lane__res rst-lane__res--head" role="columnheader">{{ __('storeops::studio.storekeeper_gets') }}</div>
            </div>
            @forelse ($laneRules as $code => $rule)
                <div class="rst-lane rst-lane--body" role="row">
                    <div class="rst-lane__rule" role="rowheader"><span class="rst-row__name">{{ $rule['name'] }}</span><span class="rst-row__desc">{{ $rule['desc'] }}</span></div>
                    @foreach ($rule['cells'] as $key => $cell)
                        <div @class(['rst-lane__cell', 'rst-lane__cell--here' => $key === $node['layer'],
                            'rst-lane__cell--carry-'.($cell['state'] === 'pass' ? $cell['carry'] : $cell['state']) => $cell['state'] !== 'empty'])
                            role="cell" title="{{ $layerName($key) }}: {{ $cell['value'] ?? ($cell['state'] === 'pass' ? __('storeops::studio.passes_on') : __('storeops::studio.not_set_yet')) }}">
                            @if (in_array($cell['state'], ['on', 'off'], true))
                                <span class="rst-pill rst-pill--{{ $cell['role'] === 'decider' ? $cell['state'] : 'replaced' }}">{{ $cell['value'] }}</span>
                            @elseif ($cell['state'] === 'empty')
                                <span class="rst-lane__nil">{{ __('storeops::studio.not_set_yet') }}</span>
                            @else
                                <span class="gs-sr-only">{{ __('storeops::studio.passes_on') }}</span>
                            @endif
                        </div>
                    @endforeach
                    <div class="rst-lane__res" role="cell">
                        <span class="rst-res--{{ $rule['result'] }}">{{ $rule['value'] }}</span>
                        <span class="rst-row__note">{{ $rule['decider'] ? __('storeops::studio.from_source', ['source' => $sourceOf($byKey[$rule['decider']])]) : __('storeops::studio.nobody') }}</span>
                    </div>
                </div>
            @empty
                <p class="rst-context rst-muted">{{ __('storeops::studio.nothing_overridden') }}</p>
            @endforelse
        </div>
    </div>
    <div class="rst-legend">
        <span><span class="rst-pill rst-pill--on">{{ __('storeops::studio.required') }}</span> {{ __('storeops::studio.legend_sets') }}</span>
        <span><span class="rst-pill rst-pill--off">{{ __('storeops::studio.off') }}</span> {{ __('storeops::studio.legend_off') }}</span>
        <span><span class="rst-swatch rst-swatch--on"></span><span class="rst-swatch rst-swatch--off"></span> {{ __('storeops::studio.legend_flows') }}</span>
        <span><span class="rst-pill rst-pill--replaced">{{ __('storeops::studio.required') }}</span> {{ __('storeops::studio.legend_replaced') }}</span>
    </div>
</section>

@foreach ($overridden as $code => $rule)
    <x-gs::alert tone="warning" :title="__('storeops::studio.override_alert_title', ['rule' => $rule['name']])">
        {{ __('storeops::studio.override_alert', ['source' => $sourceOf($byKey[$rule['decider']]), 'rule' => $rule['name'], 'parent' => $sourceOf($byKey[$rule['replaced'][0]])]) }}
    </x-gs::alert>
@endforeach
