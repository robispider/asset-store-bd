{{-- Organization section tabs. $active: offices · admins · directory. National sections only for super users. --}}
@php($national = auth()->user()?->isSuperUser())
<nav class="govorg-secnav" aria-label="{{ __('organization_labels::office.section_nav') }}">
    <a href="{{ route('gov.org.provisioning.index') }}" @if ($active === 'offices') aria-current="page" @endif>{{ __('organization_labels::office.section_offices') }}</a>
    @if ($national)
        <a href="{{ route('gov.org.jurisdictions.index') }}" @if ($active === 'admins') aria-current="page" @endif>{{ __('organization_labels::office.section_admins') }}</a>
        <a href="{{ route('gov.org.directory.index') }}" @if ($active === 'directory') aria-current="page" @endif>{{ __('organization_labels::office.section_directory') }}</a>
    @endif
</nav>
