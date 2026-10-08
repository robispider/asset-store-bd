@extends('layouts/default')

@section('title', __('organization_labels::orglabel.onboard_title'))

@section('content')
<div class="govorg-theme">


<div class="row">
    <div class="col-md-7">
        <div class="box onboarding-box org-inline-77cb079a">
            <div class="box-header with-border org-inline-baa07011">
                <h3 class="box-title org-inline-7a0ebc26">
                    <i class="fas fa-plug"></i> {{ __('organization_labels::orglabel.onboard_workspace_title') }}
                </h3>
            </div>
            
            <form action="{{ route('gov.org.provisioning.onboard.store') }}" method="POST">
                @csrf
                <div class="box-body org-inline-b9c79929">
                    
                    <!-- SECTION 1: IDENTITY -->
                    <div class="form-section-header">
                        <i class="fas fa-id-card"></i> <span>{{ __('organization_labels::orglabel.onboard_section_mapped_office') }}</span>
                    </div>
                    <div class="form-group org-inline-19f5c02e">
                        <label for="existing_location_id">{{ __('organization_labels::orglabel.onboard_field_select_location_label') }} <span class="text-danger">*</span></label>
                        
                        @if(isset($preselectedLocation) && $preselectedLocation)
                            <!-- Lock selector if pre-selected from row click -->
                            <input type="hidden" name="existing_location_id" value="{{ $preselectedLocation->id }}">
                            <input type="text" class="form-control input-lg" value="{{ $preselectedLocation->name }}" readonly class="org-inline-bf6a37fe">
                        @else
                            <select class="form-control select2 org-inline-442a70a1" name="existing_location_id" id="existing_location_id" required>
                                <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_choose_unprovisioned') }}</option>
                                @foreach($unprovisionedLocations as $unmapped)
                                    <option value="{{ $unmapped->id }}">{{ $unmapped->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <p class="help-block">{{ __('organization_labels::orglabel.onboard_help_unprovisioned') }}</p>
                    </div>
                    <div class="form-group org-inline-19f5c02e">
                        <label for="office_type">{{ __('organization_labels::orglabel.office_type_label') }}</label>
                        <select name="office_type" id="office_type" class="form-control" required>
                            @foreach(['default', 'hospital', 'school', 'ict_office'] as $type)
                                <option value="{{ $type }}" {{ old('office_type', 'default') === $type ? 'selected' : '' }}>{{ __('organization_labels::orglabel.office_type_' . $type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- SECTION 2: GEOGRAPHY -->
                    <div class="form-section-header org-inline-f5897d74">
                        <i class="fas fa-map-marked-alt"></i> <span>{{ __('organization_labels::orglabel.onboard_section_geography') }}</span>
                    </div>
                    <div class="form-group org-inline-19f5c02e">
                        <label for="geoAreaSelector">{{ __('organization_labels::orglabel.onboard_field_geo_area_label') }} <span class="text-danger">*</span></label>
                        <select class="form-control org-inline-442a70a1" name="geo_area_id" id="geoAreaSelector" required>
                            <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_search_geo') }}</option>
                        </select>
                    </div>

                    <!-- SECTION 3: ADMINISTRATION & MAPPING -->
                    <div class="form-section-header org-inline-67ef8815">
                        <i class="fas fa-sitemap"></i> <span>{{ __('organization_labels::orglabel.onboard_section_hierarchy') }}</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group org-inline-19f5c02e">
                                <label for="company_id">{{ __('organization_labels::orglabel.onboard_field_ministry_label') }}</label>
                                <select class="form-control select2 org-inline-442a70a1" name="company_id" id="company_id">
                                    <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_standalone') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group org-inline-19f5c02e">
                                <label for="office_admin_id">{{ __('organization_labels::orglabel.onboard_field_admin_label') }}</label>
                                <select class="form-control select2 org-inline-442a70a1" name="office_admin_id" id="office_admin_id">
                                    <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_leave_unassigned') }}</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
                
                <div class="box-footer org-inline-57c164f8">
                    <a class="btn btn-default pull-left org-inline-b28ac730" href="{{ route('gov.org.provisioning.index') }}">
                        <i class="fas fa-arrow-left"></i> {{ __('organization_labels::orglabel.onboard_button_return_registry') }}
                    </a>
                    <button class="btn btn-success pull-right org-inline-4140e91d" type="submit">
                        <i class="fas fa-check-shield"></i> {{ __('organization_labels::orglabel.onboard_button_onboard_map') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: Advisory Details -->
    <div class="col-md-5">
        <div class="box onboarding-box org-inline-8c4782bd">
            <div class="box-header with-border org-inline-baa07011">
                <h3 class="box-title org-inline-0fcefbc5"><i class="fas fa-info-circle text-muted"></i> {{ __('organization_labels::orglabel.onboard_guidelines_title') }}</h3>
            </div>
            <div class="box-body org-inline-912f7f21">
                <p>{{ __('organization_labels::orglabel.onboard_guidelines_desc') }}</p>
                <ul>
                    <li>{{ __('organization_labels::orglabel.onboard_guidelines_point1') }}</li>
                    <li>{{ __('organization_labels::orglabel.onboard_guidelines_point2') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    $('#geoAreaSelector').select2({
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("gov.geo.search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    restrict_hid: '{{ $restrictToHid }}'
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: "Type to search Division, District, Upazila, or Union..."
    });
});
</script>
@endsection
