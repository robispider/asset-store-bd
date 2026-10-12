@extends('layouts/default')

@section('title', __('office_membership::member.onboard_page_title'))

@section('content')
<div class="office-membership-theme">


<div class="row">
    <div class="col-md-7">
        <div class="box onboarding-box om-inline-670de20b">
            <div class="box-header with-border om-inline-baa07011">
                <h3 class="box-title om-inline-7a0ebc26">
                    <i class="fas fa-plug"></i> {{ __('office_membership::member.onboard_map_title') }}
                </h3>
            </div>
            
            <form action="{{ route('gov.org.provisioning.onboard.store') }}" method="POST">
                @csrf
                <div class="box-body om-inline-b9c79929">
                    
                    <!-- SECTION 1: IDENTITY -->
                    <div class="form-section-header">
                        <i class="fas fa-id-card"></i> <span>{{ __('office_membership::member.onboard_section_identity') }}</span>
                    </div>
                    <div class="form-group om-inline-19f5c02e">
                        <label for="existing_location_id">{{ __('office_membership::member.onboard_location_label') }} <span class="text-danger">*</span></label>
                        <select class="form-control select2 om-inline-442a70a1" name="existing_location_id" id="existing_location_id" required>
                            <option value="">{{ __('office_membership::member.onboard_location_placeholder') }}</option>
                            @foreach($unprovisionedLocations as $unmapped)
                                <option value="{{ $unmapped->id }}">{{ $unmapped->name }}</option>
                            @endforeach
                        </select>
                        <p class="help-block">{{ __('office_membership::member.onboard_location_hint') }}</p>
                    </div>

                    <!-- SECTION 2: GEOGRAPHY -->
                    <div class="form-section-header om-inline-f5897d74">
                        <i class="fas fa-map-marked-alt"></i> <span>{{ __('office_membership::member.onboard_section_geography') }}</span>
                    </div>
                    <div class="form-group om-inline-19f5c02e">
                        <label for="geoAreaSelector">{{ __('office_membership::member.onboard_geo_label') }} <span class="text-danger">*</span></label>
                        <select class="form-control om-inline-442a70a1" name="geo_area_id" id="geoAreaSelector" required>
                            <option value="">{{ __('office_membership::member.onboard_geo_placeholder') }}</option>
                        </select>
                    </div>

                    <!-- SECTION 3: ADMINISTRATION & MAPPING -->
                    <div class="form-section-header om-inline-67ef8815">
                        <i class="fas fa-sitemap"></i> <span>{{ __('office_membership::member.onboard_section_hierarchy') }}</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group om-inline-19f5c02e">
                                <label for="company_id">{{ __('office_membership::member.onboard_ministry_label') }}</label>
                                <select name="company_id" id="company_id" class="form-control select2 om-inline-442a70a1">
                                    <option value="">{{ __('office_membership::member.onboard_ministry_placeholder') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group om-inline-19f5c02e">
                                <label for="office_admin_id">{{ __('office_membership::member.onboard_admin_label') }}</label>
                                <select name="office_admin_id" id="office_admin_id" class="form-control select2 om-inline-442a70a1">
                                    <option value="">{{ __('office_membership::member.onboard_admin_placeholder') }}</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
                
                <div class="box-footer om-inline-81063683">
                    <a href="{{ route('gov.org.provisioning.index') }}" class="btn btn-default pull-left om-inline-b28ac730">
                        <i class="fas fa-arrow-left"></i> {{ __('office_membership::member.onboard_return_button') }}
                    </a>
                    <button type="submit" class="btn btn-success pull-right om-inline-4140e91d">
                        <i class="fas fa-check-shield"></i> {{ __('office_membership::member.onboard_submit_button') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: Advisory Details -->
    <div class="col-md-5">
        <div class="box onboarding-box om-inline-80472c54">
            <div class="box-header with-border om-inline-baa07011">
                <h3 class="box-title om-inline-0fcefbc5"><i class="fas fa-info-circle text-muted"></i> {{ __('office_membership::member.onboard_guidelines_title') }}</h3>
            </div>
            <div class="box-body om-inline-ca9b09af">
                <p>{{ __('office_membership::member.onboard_guidelines_text') }}</p>
                <ul>
                    <li>{{ __('office_membership::member.onboard_guidelines_point1') }}</li>
                    <li>{{ __('office_membership::member.onboard_guidelines_point2') }}</li>
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
        'placeholder': "{{ __('office_membership::member.onboard_search_geo_placeholder') }}"
    });
});
</script>
@endsection