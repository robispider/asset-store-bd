{{--
    Shared office form for "create a new office" and "set up an existing location" (plan D1).
    $mode: create · onboard. Every field keeps its value after a failed save (old()).
--}}
@php
    $oldGeo = old('geo_area_id') ? \GovStore\GeoAreas\Models\GeoArea::find((int) old('geo_area_id')) : null;
    $bangla = str_starts_with(strtolower(app()->getLocale()), 'bn');
    $types = ['default', 'hospital', 'school', 'ict_office'];
@endphp
<form action="{{ $mode === 'create' ? route('gov.org.provisioning.store') : route('gov.org.provisioning.onboard.store') }}" method="POST" class="govorg-stack" novalidate>
    @csrf
    @include('govorg::partials.errors')

    <section class="govorg-step" id="govorg-step-about" aria-labelledby="govorg-step-about-title">
        <h2 class="govorg-step__title" id="govorg-step-about-title">{{ __('organization_labels::office.about_title') }}</h2>
        @if ($mode === 'create')
            <x-gs::form-row for="name" :label="__('organization_labels::office.name')" required :help="__('organization_labels::office.name_hint')">
                <input type="text" class="form-control" name="name" id="name" value="{{ old('name', request('find')) }}" required maxlength="255" aria-describedby="name-help" @error('name') aria-invalid="true" @enderror>
            </x-gs::form-row>
        @elseif ($preselectedLocation)
            <x-gs::form-row for="existing_location_id" :label="__('organization_labels::office.existing_location')">
                <input type="hidden" name="existing_location_id" value="{{ $preselectedLocation->id }}">
                <p class="form-control-static govorg-flush"><strong>{{ $preselectedLocation->name }}</strong></p>
            </x-gs::form-row>
        @else
            <x-gs::form-row for="existing_location_id" :label="__('organization_labels::office.existing_location')" required>
                <select class="form-control select2" name="existing_location_id" id="existing_location_id" required style="width: 100%">
                    <option value="">{{ __('organization_labels::office.existing_pick') }}</option>
                    @foreach ($unprovisionedLocations as $unmapped)
                        <option value="{{ $unmapped->id }}" @selected((string) old('existing_location_id') === (string) $unmapped->id)>{{ $unmapped->name }}</option>
                    @endforeach
                </select>
            </x-gs::form-row>
        @endif
        <fieldset class="govorg-flush">
            <legend class="gs-form-row__label">{{ __('organization_labels::office.type_question') }}</legend>
            <div class="gs-choices">
                @foreach ($types as $type)
                    <x-gs::choice-card name="office_type" :value="$type" :id="'office_type_'.$type"
                        :label="__('organization_labels::orglabel.office_type_'.$type)"
                        :description="__('organization_labels::office.type_'.$type.'_desc')"
                        :checked="old('office_type', 'default') === $type" />
                @endforeach
            </div>
        </fieldset>
    </section>

    <section class="govorg-step" id="govorg-step-location" aria-labelledby="govorg-step-location-title">
        <h2 class="govorg-step__title" id="govorg-step-location-title">{{ __('organization_labels::office.location_title') }}</h2>
        <p class="govorg-step__hint">
            {{ __('organization_labels::office.location_hint') }}
            @if ($scopeArea) {{ __('organization_labels::office.location_scoped', ['area' => $bangla && $scopeArea->bn_name ? $scopeArea->bn_name : $scopeArea->en_name]) }} @endif
        </p>
        <x-gs::form-row for="geo_area_id" :label="__('organization_labels::office.area')" required>
            <select class="form-control" name="geo_area_id" id="geo_area_id" required style="width: 100%">
                @if ($oldGeo)
                    <option value="{{ $oldGeo->getKey() }}" selected>{{ $bangla && $oldGeo->bn_name ? $oldGeo->bn_name : $oldGeo->en_name }}</option>
                @endif
            </select>
        </x-gs::form-row>
        <div id="govorg-duplicates" hidden>
            <x-gs::alert tone="warning" :title="__('organization_labels::office.duplicate_title')">
                <p class="govorg-flush">{{ __('organization_labels::office.duplicate_body') }}</p>
                <ul class="govorg-flush" data-govorg-duplicate-list></ul>
            </x-gs::alert>
        </div>
    </section>

    <section class="govorg-step" id="govorg-step-reports" aria-labelledby="govorg-step-reports-title">
        <h2 class="govorg-step__title" id="govorg-step-reports-title">{{ __('organization_labels::office.reports_title') }}</h2>
        <p class="govorg-step__hint">{{ __('organization_labels::office.reports_hint') }}</p>
        <x-gs::form-row for="company_id" :label="__('organization_labels::office.ministry')">
            <select class="form-control select2" name="company_id" id="company_id" style="width: 100%">
                <option value="">{{ __('organization_labels::office.not_linked') }}</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </x-gs::form-row>
        <x-gs::form-row for="parent_id" :label="__('organization_labels::office.parent')" :help="__('organization_labels::office.parent_hint')">
            <select class="form-control select2" name="parent_id" id="parent_id" style="width: 100%" aria-describedby="parent_id-help">
                <option value="">{{ __('organization_labels::office.no_parent') }}</option>
                @foreach ($offices as $parentLoc)
                    <option value="{{ $parentLoc->id }}" @selected((string) old('parent_id') === (string) $parentLoc->id)>{{ $parentLoc->name }}</option>
                @endforeach
            </select>
        </x-gs::form-row>
    </section>

    <section class="govorg-step" id="govorg-step-create" aria-labelledby="govorg-step-create-title">
        <x-gs::alert tone="info" :title="__('organization_labels::office.next_title')">
            <ol class="govorg-next-list">
                <li>{{ __('organization_labels::office.next_1') }}</li>
                <li>{{ __('organization_labels::office.next_2') }}</li>
                <li>{{ __('organization_labels::office.next_3') }}</li>
            </ol>
        </x-gs::alert>
        <div class="govorg-toolbar govorg-toolbar--end">
            <x-gs::button tone="light" outline :href="route('gov.org.provisioning.index')">{{ __('organization_labels::office.cancel') }}</x-gs::button>
            <x-gs::button type="submit" icon="fa-building">{{ $mode === 'create' ? __('organization_labels::office.create_button') : __('organization_labels::office.onboard_button') }}</x-gs::button>
        </div>
    </section>
</form>
