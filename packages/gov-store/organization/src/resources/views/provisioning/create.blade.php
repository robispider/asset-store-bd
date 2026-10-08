@extends('layouts/default')

@section('title', __('organization_labels::orglabel.create_title'))

@section('content')
<div class="govorg-theme">

{{-- Professional Government Workspace Styling --}}


<div class="row">
    <!-- LEFT COLUMN: Step-Guided Provisioning Form -->
    <div class="col-md-7">
        <div class="box onboarding-box org-inline-77cb079a">
            <div class="box-header with-border org-inline-baa07011">
                <h3 class="box-title org-inline-7a0ebc26">
                    <i class="fas fa-plus-circle"></i> {{ __('organization_labels::orglabel.create_workspace_title') }}
                </h3>
            </div>
            
            <form action="{{ route('gov.org.provisioning.store') }}" method="POST">
                @csrf
                <div class="box-body org-inline-b9c79929">
                    
                    <!-- SECTION 1: IDENTITY -->
                    <div class="form-section-header">
                        <i class="fas fa-id-card"></i> <span>{{ __('organization_labels::orglabel.create_section_identity') }}</span>
                    </div>
                    <div class="form-group org-inline-19f5c02e">
                        <label for="name">{{ __('organization_labels::orglabel.create_field_office_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control input-lg" placeholder="{{ __('organization_labels::orglabel.create_placeholder_office_name') }}" required value="{{ old('name') }}">
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
                        <i class="fas fa-map-marked-alt"></i> <span>{{ __('organization_labels::orglabel.create_section_geography') }}</span>
                    </div>
                    <div class="form-group org-inline-19f5c02e">
                        <label for="geoAreaSelector">{{ __('organization_labels::orglabel.create_field_geo_area') }} <span class="text-danger">*</span></label>
                        <select class="form-control org-inline-442a70a1" name="geo_area_id" id="geoAreaSelector" required>
                            <option value="">{{ __('organization_labels::orglabel.create_placeholder_geo_area') }}</option>
                        </select>
                        <p class="help-block org-inline-a63df2d2"><i class="fas fa-info-circle"></i> {{ __('organization_labels::orglabel.create_help_geo_area') }}</p>
                    </div>


                    <!-- SECTION 3: ADMINISTRATION & MAPPING -->
                    <div class="form-section-header org-inline-67ef8815">
                        <i class="fas fa-sitemap"></i> <span>{{ __('organization_labels::orglabel.create_section_hierarchy') }}</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group org-inline-19f5c02e">
                                <label for="company_id">{{ __('organization_labels::orglabel.create_field_ministry') }}</label>
                                <select class="form-control select2 org-inline-442a70a1" name="company_id" id="company_id">
                                    <option value="">{{ __('organization_labels::orglabel.create_placeholder_standalone') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group org-inline-19f5c02e">
                                <label for="parent_id">{{ __('organization_labels::orglabel.create_field_parent_office') }}</label>
                                <select class="form-control select2 org-inline-442a70a1" name="parent_id" id="parent_id">
                                    <option value="">{{ __('organization_labels::orglabel.create_placeholder_no_parent') }}</option>
                                    @foreach($offices as $parentLoc)
                                        <option value="{{ $parentLoc->id }}">{{ $parentLoc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group org-inline-2eefb8c4">
                        <label for="office_admin_id">{{ __('organization_labels::orglabel.create_field_delegate_admin') }}</label>
                        <select class="form-control select2 org-inline-442a70a1" id="office_admin_id" disabled>
                            <option value="">{{ __('organization_labels::orglabel.create_placeholder_leave_unassigned') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                        <p class="help-block org-inline-a63df2d2">{{ __('organization_labels::orglabel.starter_admin_membership') }}</p>
                    </div>

                </div>
                
                <div class="box-footer org-inline-57c164f8">
                    <a class="btn btn-default pull-left org-inline-b28ac730" href="{{ route('gov.org.provisioning.index') }}">
                        <i class="fas fa-arrow-left"></i> {{ __('organization_labels::orglabel.create_button_return_registry') }}
                    </a>
                    <button class="btn btn-primary pull-right org-inline-4140e91d" type="submit">
                        <i class="fas fa-building"></i> {{ __('organization_labels::orglabel.create_button_save_provision') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: Guidelines & Smart Warnings -->
    <div class="col-md-5">
        
        <!-- Live Duplicate Checker Callout Widget -->
        <div class="duplicate-alert-callout org-inline-7a2148b2" id="duplicateWidget">
            <h4 class="org-inline-3bcec61b">
                <i class="fas fa-exclamation-triangle text-warning"></i> {{ __('organization_labels::orglabel.create_duplicate_warning_title') }}
            </h4>
            <p class="text-muted org-inline-65de619e">
                {{ __('organization_labels::orglabel.create_duplicate_warning_desc') }}
            </p>
            <ul class="list-group list-group-custom org-inline-cd0f5d85" id="duplicateList"></ul>
            <p class="text-muted org-inline-086d17b8">
                {{ __('organization_labels::orglabel.create_duplicate_note') }}
            </p>
        </div>

        <!-- Onboarding Advisory Panel -->
        <div class="box onboarding-box org-inline-8c4782bd">
            <div class="box-header with-border org-inline-baa07011">
                <h3 class="box-title org-inline-0fcefbc5"><i class="fas fa-info-circle text-muted"></i> {{ __('organization_labels::orglabel.create_guidelines_title') }}</h3>
            </div>
            <div class="box-body org-inline-b9c79929">
                <div class="advisory-box">
                    <p class="org-inline-65a31ca6">{{ __('organization_labels::orglabel.create_advisory_spatial_title') }}</p>
                    <p class="text-muted org-inline-52aed2e2">
                        {{ __('organization_labels::orglabel.create_advisory_spatial_desc') }}
                    </p>
                </div>

                <ul class="org-inline-632dbc86">
                    <li><strong>{{ __('organization_labels::orglabel.create_step1_label') }}</strong> {{ __('organization_labels::orglabel.create_step1_desc') }}</li>
                    <li><strong>{{ __('organization_labels::orglabel.create_step2_label') }}</strong> {{ __('organization_labels::orglabel.create_step2_desc') }}</li>
                    <li><strong>{{ __('organization_labels::orglabel.create_step3_label') }}</strong> {{ __('organization_labels::orglabel.create_step3_desc') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(fontSelectionScript);

function fontSelectionScript() {
    // 1. Initialize Ajax Select2 searching over unrestricted geographic reference database
    $('#geoAreaSelector').select2({
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("gov.geo.search") }}', // Query the shared library API
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    restrict_hid: '{{ $restrictToHid }}' // Scopes search strictly within the ICT Officer's jurisdiction bounds
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: "{{ __('organization_labels::orglabel.create_placeholder_geo_area') }}"
    });

    // 2. Live Duplicate Awareness Checking
    function checkDuplicates() {
        var companyId = $('#company_id').val();
        var geoAreaId = $('#geoAreaSelector').val();

        if (companyId && geoAreaId) {
            $.ajax({
                url: '{{ route("gov.org.provisioning.check-duplicate") }}',
                data: { company_id: companyId, geo_area_id: geoAreaId },
                dataType: 'json',
                success: function(data) {
                    if (data.length > 0) {
                        var listHtml = '';
                        $.each(data, function(index, item) {
                            listHtml += '<li class="list-group-item"><strong>' + item.name + '</strong></li>';
                        });
                        $('#duplicateList').html(listHtml);
                        $('#duplicateWidget').slideDown('fast');
                    } else {
                        $('#duplicateWidget').slideUp('fast');
                    }
                }
            });
        } else {
            $('#duplicateWidget').slideUp('fast');
        }
    }

    $('#company_id').on('change', checkDuplicates);
    $('#geoAreaSelector').on('change', checkDuplicates);
}
</script>
@endsection
