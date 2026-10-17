@extends('layouts/default')
@section('title', __('storeops::studio.title'))

@section('content')
@php
    // Links keep the chosen office and item; a change passes only the keys it alters (null drops one).
    $studioUrl = function (array $changes = []) use ($query) {
        return route('storeops.admin.rules.index', array_filter(array_merge($query, $changes), fn ($value) => $value !== null && $value !== ''));
    };
    $tabs = [];
    foreach (['tree', 'flow', 'family', 'policies'] as $key) {
        $tabs[] = ['key' => $key, 'label' => __('storeops::studio.views.'.$key), 'href' => $studioUrl(['view' => $key])];
    }
    $layerIcons = ['DEFAULT' => 'fa-puzzle-piece', 'GLOBAL' => 'fa-globe', 'COMPANY' => 'fa-landmark', 'LOCATION' => 'fa-building', 'CATEGORY' => 'fa-cubes', 'MODEL' => 'fa-tag'];
    $layerName = function (string $key) {
        return __('storeops::studio.layer.'.$key);
    };
    // Who speaks at a layer: the policy attached there, or the installed packages for defaults.
    $sourceOf = function (array $layer) {
        return $layer['key'] === 'DEFAULT' ? __('storeops::studio.package_default') : ($layer['profile']['name'] ?? __('storeops::studio.no_own_policy'));
    };
    $byKey = collect($layers)->keyBy('key');
@endphp
<div class="storeops-theme rst" data-rules-studio>
    <x-gs::page-header :title="__('storeops::studio.title')" :subtitle="__('storeops::studio.lede')">
        <x-slot:actions>
            <a class="gs-btn" href="{{ route('storeops.admin.rules.simulator') }}"><i class="fas fa-flask" aria-hidden="true"></i> {{ __('storeops::studio.try_scenario') }}</a>
            <a class="gs-btn gs-btn--primary" href="{{ $studioUrl(['view' => 'policies']) }}#new-policy"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('storeops::studio.new_policy') }}</a>
        </x-slot:actions>
    </x-gs::page-header>

    @if (session('success'))
        <x-gs::alert tone="success" dismissible>{{ session('success') }}</x-gs::alert>
    @endif
    @if (session('error') || $errors->any())
        <x-gs::alert tone="danger">{{ session('error') ?? $errors->first() }}</x-gs::alert>
    @endif

    <x-gs::status-tabs :tabs="$tabs" :active="$view" :label="__('storeops::studio.views_label')" />

    @if ($view !== 'policies')
        @include('storeops::admin.rules.partials.studio-context')
    @endif

    @include('storeops::admin.rules.partials.studio-'.$view)
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'index'])
@endsection
