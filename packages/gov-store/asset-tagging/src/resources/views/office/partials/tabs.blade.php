@php
    $settingsTabs = [];
    if ($tab === 'office' || ($canOffice ?? false)) {
        $settingsTabs[] = ['key' => 'office', 'label' => __('asset-tagging::tagging.office.tab'), 'href' => route('asset-tags.office.show')];
    }
    if ($tab === 'codes' || ($canCodes ?? false)) {
        $settingsTabs[] = ['key' => 'codes', 'label' => __('asset-tagging::tagging.codes.tab'), 'href' => route('asset-tags.codes.show')];
    }
@endphp
<x-gs::page-header :title="__('asset-tagging::tagging.nav.settings')" :subtitle="__('asset-tagging::tagging.office.intro')" />
@if (count($settingsTabs) > 1)
    <x-gs::status-tabs :tabs="$settingsTabs" :active="$tab" :label="__('asset-tagging::tagging.nav.settings')" />
@endif
@if (session('success'))
    <x-gs::alert tone="success" dismissible>{{ session('success') }}</x-gs::alert>
@endif
@if ($errors->any())
    <x-gs::alert tone="danger">{{ $errors->first() }}</x-gs::alert>
@endif
