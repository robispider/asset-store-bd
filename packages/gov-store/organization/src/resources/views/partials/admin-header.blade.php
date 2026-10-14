{{--
    Header for the Administrators section: page header, section nav, the two access tabs and the
    "Restore access" offer after a removal. $tab: ict · ministry. $ictCount, $ministryCount.
--}}
@php($bangla = str_starts_with(strtolower(app()->getLocale()), 'bn'))
<x-gs::page-header :title="__('organization_labels::office.admins_title')" :bn="$bangla ? null : __('organization_labels::office.admins_title', [], 'bn-BD')"
    :subtitle="__('organization_labels::office.admins_subtitle')" />

@include('govorg::partials.section-nav', ['active' => 'admins'])

<x-gs::status-tabs :label="__('organization_labels::office.section_admins')" :active="$tab" :tabs="[
    ['key' => 'ict', 'label' => __('organization_labels::office.section_ict'), 'count' => $ictCount, 'href' => route('gov.org.jurisdictions.index')],
    ['key' => 'ministry', 'label' => __('organization_labels::office.section_ministry'), 'count' => $ministryCount, 'href' => route('gov.org.company_admins.index')],
]" />

@if (session('gov_org_restore') && \Illuminate\Support\Facades\Route::has(session('gov_org_restore.route')))
    {{-- Undo is a new national change, so it goes through the same national review (plan D3). --}}
    <form method="POST" action="{{ route(session('gov_org_restore.route')) }}" class="govorg-toolbar">
        @csrf
        @foreach (session('gov_org_restore.fields', []) as $field => $value)
            <input type="hidden" name="{{ $field }}" value="{{ $value }}">
        @endforeach
        <x-gs::button type="submit" size="sm" tone="light" outline icon="fa-rotate-left">{{ __('organization_labels::office.restore_access') }}</x-gs::button>
    </form>
@endif
