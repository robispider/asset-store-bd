@extends('layouts/default')

@section('title', __('organization_labels::orglabel.onboard_title'))

@section('content')
<style>
    .onboarding-box { border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #d2d6de; background: #fff; }
    .form-section-header { font-size: 15px; font-weight: bold; color: var(--main-theme-color, #3c8dbc); border-bottom: 2px solid #f4f4f4; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
    .form-section-header:first-of-type { margin-top: 10px; }
</style>

<div class="row">
    <div class="col-md-7">
        <div class="box onboarding-box" style="border-top: 3px solid var(--main-theme-color, #3c8dbc);">
            <div class="box-header with-border" style="padding: 15px 20px;">
                <h3 class="box-title" style="font-weight: bold; font-size: 16px;">
                    <i class="fas fa-plug"></i> {{ __('organization_labels::orglabel.onboard_workspace_title') }}
                </h3>
            </div>
            
            <form action="{{ route('gov.org.provisioning.onboard.store') }}" method="POST">
                @csrf
                <div class="box-body" style="padding: 20px 25px;">
                    
                    <!-- SECTION 1: IDENTITY -->
                    <div class="form-section-header">
                        <i class="fas fa-id-card"></i> <span>{{ __('organization_labels::orglabel.onboard_section_mapped_office') }}</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="existing_location_id">{{ __('organization_labels::orglabel.onboard_field_select_location_label') }} <span class="text-danger">*</span></label>
                        
                        @if(isset($preselectedLocation) && $preselectedLocation)
                            <!-- Lock selector if pre-selected from row click -->
                            <input type="hidden" name="existing_location_id" value="{{ $preselectedLocation->id }}">
                            <input type="text" class="form-control input-lg" value="{{ $preselectedLocation->name }}" readonly style="background-color: #fafafa; font-weight: bold; color: #333;">
                        @else
                            <select name="existing_location_id" id="existing_location_id" class="form-control select2" required style="width: 100%;">
                                <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_choose_unprovisioned') }}</option>
                                @foreach($unprovisionedLocations as $unmapped)
                                    <option value="{{ $unmapped->id }}">{{ $unmapped->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <p class="help-block">{{ __('organization_labels::orglabel.onboard_help_unprovisioned') }}</p>
                    </div>

                    <!-- SECTION 2: GEOGRAPHY -->
                    <div class="form-section-header" style="margin-top: 25px;">
                        <i class="fas fa-map-marked-alt"></i> <span>{{ __('organization_labels::orglabel.onboard_section_geography') }}</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="geoAreaSelector">{{ __('organization_labels::orglabel.onboard_field_geo_area_label') }} <span class="text-danger">*</span></label>
                        <select name="geo_area_id" id="geoAreaSelector" class="form-control" required style="width: 100%;">
                            <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_search_geo') }}</option>
                        </select>
                    </div>

                    <!-- SECTION 3: ADMINISTRATION & MAPPING -->
                    <div class="form-section-header" style="margin-top: 35px;">
                        <i class="fas fa-sitemap"></i> <span>{{ __('organization_labels::orglabel.onboard_section_hierarchy') }}</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="company_id">{{ __('organization_labels::orglabel.onboard_field_ministry_label') }}</label>
                                <select name="company_id" id="company_id" class="form-control select2" style="width: 100%;">
                                    <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_standalone') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="office_admin_id">{{ __('organization_labels::orglabel.onboard_field_admin_label') }}</label>
                                <select name="office_admin_id" id="office_admin_id" class="form-control select2" style="width: 100%;">
                                    <option value="">{{ __('organization_labels::orglabel.onboard_placeholder_leave_unassigned') }}</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
                
                <div class="box-footer" style="padding: 15px 25px; background-color: #fafafa; border-top: 1px solid #f4f4f4;">
                    <a href="{{ route('gov.org.provisioning.index') }}" class="btn btn-default pull-left" style="padding: 8px 15px;">
                        <i class="fas fa-arrow-left"></i> {{ __('organization_labels::orglabel.onboard_button_return_registry') }}
                    </a>
                    <button type="submit" class="btn btn-success pull-right" style="padding: 8px 25px; font-weight: bold;">
                        <i class="fas fa-check-shield"></i> {{ __('organization_labels::orglabel.onboard_button_onboard_map') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: Advisory Details -->
    <div class="col-md-5">
        <div class="box onboarding-box" style="border-top: 3px solid #d2d6de;">
            <div class="box-header with-border" style="padding: 15px 20px;">
                <h3 class="box-title" style="font-weight: bold; font-size: 15px;"><i class="fas fa-info-circle text-muted"></i> {{ __('organization_labels::orglabel.onboard_guidelines_title') }}</h3>
            </div>
            <div class="box-body" style="padding: 20px 25px; font-size: 13px; line-height: 1.6; color: #555;">
                <p>{{ __('organization_labels::orglabel.onboard_guidelines_desc') }}</p>
                <ul>
                    <li>{{ __('organization_labels::orglabel.onboard_guidelines_point1') }}</li>
                    <li>{{ __('organization_labels::orglabel.onboard_guidelines_point2') }}</li>
                </ul>
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