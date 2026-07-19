@extends('layouts/default')

@section('title', __('organization_labels::orglabel.create_title'))

@section('content')

{{-- Professional Government Workspace Styling --}}
<style>
    .onboarding-box {
        border-radius: 6px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #d2d6de;
        background: #fff;
    }
    .form-section-header {
        font-size: 15px;
        font-weight: bold;
        color: var(--main-theme-color, #3c8dbc);
        border-bottom: 2px solid #f4f4f4;
        padding-bottom: 8px;
        margin-top: 30px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-section-header:first-of-type {
        margin-top: 10px;
    }
    .form-section-header i {
        font-size: 17px;
    }
    .advisory-box {
        background: #fafafa;
        border-left: 4px solid var(--main-theme-color, #3c8dbc);
        padding: 15px;
        border-radius: 0 4px 4px 0;
        margin-bottom: 20px;
        border-top: 1px solid #eee;
        border-right: 1px solid #eee;
        border-bottom: 1px solid #eee;
    }
    .duplicate-alert-callout {
        background: #fffcf5;
        border-left: 4px solid #f39c12;
        padding: 15px;
        border-radius: 0 4px 4px 0;
        margin-bottom: 25px;
        border-top: 1px solid #faebcc;
        border-right: 1px solid #faebcc;
        border-bottom: 1px solid #faebcc;
    }
    .list-group-custom .list-group-item {
        background: transparent;
        border-left: none;
        border-right: none;
        padding: 10px 0;
        border-bottom: 1px dashed #ddd;
    }
    .list-group-custom .list-group-item:last-child {
        border-bottom: none;
    }
</style>

<div class="row">
    <!-- LEFT COLUMN: Step-Guided Provisioning Form -->
    <div class="col-md-7">
        <div class="box onboarding-box" style="border-top: 3px solid var(--main-theme-color, #3c8dbc);">
            <div class="box-header with-border" style="padding: 15px 20px;">
                <h3 class="box-title" style="font-weight: bold; font-size: 16px;">
                    <i class="fas fa-plus-circle"></i> {{ __('organization_labels::orglabel.create_workspace_title') }}
                </h3>
            </div>
            
            <form action="{{ route('gov.org.provisioning.store') }}" method="POST">
                @csrf
                <div class="box-body" style="padding: 20px 25px;">
                    
                    <!-- SECTION 1: IDENTITY -->
                    <div class="form-section-header">
                        <i class="fas fa-id-card"></i> <span>{{ __('organization_labels::orglabel.create_section_identity') }}</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="name">{{ __('organization_labels::orglabel.create_field_office_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control input-lg" placeholder="{{ __('organization_labels::orglabel.create_placeholder_office_name') }}" required value="{{ old('name') }}">
                    </div>


                    <!-- SECTION 2: GEOGRAPHY -->
                    <div class="form-section-header" style="margin-top: 25px;">
                        <i class="fas fa-map-marked-alt"></i> <span>{{ __('organization_labels::orglabel.create_section_geography') }}</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="geoAreaSelector">{{ __('organization_labels::orglabel.create_field_geo_area') }} <span class="text-danger">*</span></label>
                        <select name="geo_area_id" id="geoAreaSelector" class="form-control" required style="width: 100%;">
                            <option value="">{{ __('organization_labels::orglabel.create_placeholder_geo_area') }}</option>
                        </select>
                        <p class="help-block" style="margin-top: 6px;"><i class="fas fa-info-circle"></i> {{ __('organization_labels::orglabel.create_help_geo_area') }}</p>
                    </div>


                    <!-- SECTION 3: ADMINISTRATION & MAPPING -->
                    <div class="form-section-header" style="margin-top: 35px;">
                        <i class="fas fa-sitemap"></i> <span>{{ __('organization_labels::orglabel.create_section_hierarchy') }}</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="company_id">{{ __('organization_labels::orglabel.create_field_ministry') }}</label>
                                <select name="company_id" id="company_id" class="form-control select2" style="width: 100%;">
                                    <option value="">{{ __('organization_labels::orglabel.create_placeholder_standalone') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="parent_id">{{ __('organization_labels::orglabel.create_field_parent_office') }}</label>
                                <select name="parent_id" id="parent_id" class="form-control select2" style="width: 100%;">
                                    <option value="">{{ __('organization_labels::orglabel.create_placeholder_no_parent') }}</option>
                                    @foreach($offices as $parentLoc)
                                        <option value="{{ $parentLoc->id }}">{{ $parentLoc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px; margin-bottom: 10px;">
                        <label for="office_admin_id">{{ __('organization_labels::orglabel.create_field_delegate_admin') }}</label>
                        <select name="office_admin_id" id="office_admin_id" class="form-control select2" style="width: 100%;">
                            <option value="">{{ __('organization_labels::orglabel.create_placeholder_leave_unassigned') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                        <p class="help-block" style="margin-top: 6px;">{{ __('organization_labels::orglabel.create_help_delegate_admin') }}</p>
                    </div>

                </div>
                
                <div class="box-footer" style="padding: 15px 25px; background-color: #fafafa; border-top: 1px solid #f4f4f4;">
                    <a href="{{ route('gov.org.provisioning.index') }}" class="btn btn-default pull-left" style="padding: 8px 15px;">
                        <i class="fas fa-arrow-left"></i> {{ __('organization_labels::orglabel.create_button_return_registry') }}
                    </a>
                    <button type="submit" class="btn btn-primary pull-right" style="padding: 8px 25px; font-weight: bold;">
                        <i class="fas fa-building"></i> {{ __('organization_labels::orglabel.create_button_save_provision') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: Guidelines & Smart Warnings -->
    <div class="col-md-5">
        
        <!-- Live Duplicate Checker Callout Widget -->
        <div class="duplicate-alert-callout" id="duplicateWidget" style="display: none;">
            <h4 style="font-weight: bold; margin-top: 0; color: #c0392b !important;">
                <i class="fas fa-exclamation-triangle text-warning"></i> {{ __('organization_labels::orglabel.create_duplicate_warning_title') }}
            </h4>
            <p class="text-muted" style="font-size: 13px; line-height: 1.5; margin-bottom: 15px;">
                {{ __('organization_labels::orglabel.create_duplicate_warning_desc') }}
            </p>
            <ul id="duplicateList" class="list-group list-group-custom" style="margin-bottom: 10px;"></ul>
            <p class="text-muted" style="font-size: 11px; margin-bottom: 0; font-style: italic;">
                {{ __('organization_labels::orglabel.create_duplicate_note') }}
            </p>
        </div>

        <!-- Onboarding Advisory Panel -->
        <div class="box onboarding-box" style="border-top: 3px solid #d2d6de;">
            <div class="box-header with-border" style="padding: 15px 20px;">
                <h3 class="box-title" style="font-weight: bold; font-size: 15px;"><i class="fas fa-info-circle text-muted"></i> {{ __('organization_labels::orglabel.create_guidelines_title') }}</h3>
            </div>
            <div class="box-body" style="padding: 20px 25px;">
                <div class="advisory-box">
                    <p style="margin-bottom: 0; font-size: 13px; font-weight: bold; color: #333;">{{ __('organization_labels::orglabel.create_advisory_spatial_title') }}</p>
                    <p class="text-muted" style="font-size: 12.5px; line-height: 1.6; margin-top: 5px; margin-bottom: 0;">
                        {{ __('organization_labels::orglabel.create_advisory_spatial_desc') }}
                    </p>
                </div>

                <ul style="padding-left: 20px; line-height: 1.8; color: #555; font-size: 13px;">
                    <li><strong>{{ __('organization_labels::orglabel.create_step1_label') }}</strong> {{ __('organization_labels::orglabel.create_step1_desc') }}</li>
                    <li><strong>{{ __('organization_labels::orglabel.create_step2_label') }}</strong> {{ __('organization_labels::orglabel.create_step2_desc') }}</li>
                    <li><strong>{{ __('organization_labels::orglabel.create_step3_label') }}</strong> {{ __('organization_labels::orglabel.create_step3_desc') }}</li>
                </ul>
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