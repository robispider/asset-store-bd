@extends('layouts/default')

@section('title', __('organization_labels::orglabel.hub_title_prefix') . ' ' . $location->name)

@section('content')

{{-- Custom Styling for Hub Panels --}}
<style>
    .hub-header {
        background: #fff;
        padding: 20px;
        border-radius: 4px;
        border: 1px solid #ddd;
        border-top: 3px solid var(--main-theme-color, #3c8dbc);
        margin-bottom: 25px;
    }
    .checklist-row { display: flex; align-items: center; padding: 10px 15px; border-bottom: 1px solid #f4f4f4; }
    .checklist-row:last-child { border-bottom: none; }
    .checklist-indicator { font-size: 16px; margin-right: 15px; }
    .checklist-label { font-size: 13px; font-weight: bold; flex-grow: 1; }
</style>

@php
    // Evaluate operational readiness checklist on the fly
    $hasAdmin = !is_null($profile->office_admin_id);
    $hasPrimary = $roles && !is_null($roles->primary_approver_id);
    $hasStorekeeper = $roles && !is_null($roles->storekeeper_id);
    $hasStaff = $localStaff->count() > 0;
    $isOperational = $hasAdmin && $hasPrimary && $hasStorekeeper && $hasStaff;
@endphp

<!-- HUB MASTER HEADER & STATUS BLOCK -->
<div class="row">
    <div class="col-md-12">
        <div class="hub-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h2 style="margin-top: 0; font-weight: bold; color: #333;">{{ $location->name }}</h2>
                    <p style="margin-bottom: 0; font-size: 14px; color: #777;">
                        <i class="fas fa-map-marker-alt"></i> 
                        {{ $profile->geoArea->en_name ?? 'Unmapped Territory' }} ({{ ucfirst($profile->geoArea->geo_type ?? 'N/A') }}) 
                        &bull; Ministry: {{ $location->company->name ?? 'Standalone Office' }}
                    </p>
                </div>
                <div>
                    @if($profile->lifecycle_status === 'operational')
                        <span class="label label-success" style="font-size: 14px; padding: 8px 15px;"><i class="fas fa-check-double"></i> {{ __('organization_labels::orglabel.hub_status_operational') }}</span>
                    @elseif($profile->lifecycle_status === 'configured')
                        <span class="label label-info" style="font-size: 14px; padding: 8px 15px;"><i class="fas fa-sliders-h"></i> {{ __('organization_labels::orglabel.hub_status_configured') }}</span>
                    @else
                        <span class="label label-warning" style="font-size: 14px; padding: 8px 15px;"><i class="fas fa-building"></i> {{ __('organization_labels::orglabel.hub_status_provisioned') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <!-- TAB BAR SELECTORS -->
            <ul class="nav nav-tabs">
                <li class="active"><a href="#tab_overview" data-toggle="tab"><i class="fas fa-id-card"></i> {{ __('organization_labels::orglabel.hub_tab_overview') }}</a></li>
                <li><a href="#tab_roles" data-toggle="tab"><i class="fas fa-user-shield"></i> {{ __('organization_labels::orglabel.hub_tab_roles') }}</a></li>
                <li><a href="#tab_employees" data-toggle="tab"><i class="fas fa-users"></i> {{ __('organization_labels::orglabel.hub_tab_employees') }} <span class="badge bg-blue">{{ $localStaff->count() }}</span></a></li>
                <li><a href="#tab_geography" data-toggle="tab"><i class="fas fa-map-marked-alt"></i> {{ __('organization_labels::orglabel.hub_tab_geography') }}</a></li>
                <li><a href="#tab_timeline" data-toggle="tab"><i class="fas fa-history"></i> {{ __('organization_labels::orglabel.hub_tab_timeline') }}</a></li>
            </ul>

            <div class="tab-content" style="background-color: white;">
                
                <!-- TAB A: GENERAL PROFILE EDITS -->
                <div class="tab-pane active" id="tab_overview">
                    <form action="{{ route('gov.org.hub.update', $location->id) }}" method="POST" style="max-width: 700px; padding: 15px 0;">
                        @csrf
                        <div class="form-group">
                            <label for="name">{{ __('organization_labels::orglabel.hub_field_office_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ $location->name }}" required>
                        </div>

                        <div class="form-group">
                            <label for="company_id">{{ __('organization_labels::orglabel.hub_field_ministry') }}</label>
                            <select name="company_id" id="company_id" class="form-control select2" style="width: 100%;">
                                <option value="">{{ __('organization_labels::orglabel.create_placeholder_standalone') }}</option>
                                @foreach($companies as $comp)
                                    <option value="{{ $comp->id }}" {{ $location->company_id == $comp->id ? 'selected' : '' }}>{{ $comp->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="parent_id">{{ __('organization_labels::orglabel.hub_field_parent_office') }}</label>
                            <select name="parent_id" id="parent_id" class="form-control select2" style="width: 100%;">
                                <option value="">{{ __('organization_labels::orglabel.create_placeholder_no_parent') }}</option>
                                @foreach($allOffices as $parent)
                                    <option value="{{ $parent->id }}" {{ $location->parent_id == $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="geoAreaSelector">{{ __('organization_labels::orglabel.hub_field_geo_area') }} <span class="text-danger">*</span></label>
                            <select name="geo_area_id" id="geoAreaSelector" class="form-control" required style="width: 100%;">
                                @if($profile->geoArea)
                                    <option value="{{ $profile->geo_area_id }}" selected>
                                        {{ $profile->geoArea->en_name }} ({{ $profile->geoArea->bn_name }}) - {{ ucfirst($profile->geoArea->geo_type) }}
                                    </option>
                                @endif
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="office_admin_id">{{ __('organization_labels::orglabel.hub_field_office_admin') }}</label>
                            <select name="office_admin_id" id="office_admin_id" class="form-control select2" style="width: 100%;">
                                <option value="">{{ __('organization_labels::orglabel.hub_placeholder_no_admin') }}</option>
                                @foreach($allUsers as $user)
                                    <option value="{{ $user->id }}" {{ $profile->office_admin_id == $user->id ? 'selected' : '' }}>
                                        {{ $user->present()->fullName }} ({{ $user->username }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div style="margin-top: 25px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('organization_labels::orglabel.hub_save_button') }}</button>
                        </div>
                    </form>
                </div>

                <!-- TAB B: WORKFLOW ROLES & LIVE CHECKLIST -->
                <div class="tab-pane" id="tab_roles">
                    <div class="row">
                        <!-- Left: Form selectors -->
                        <div class="col-md-7" style="border-right: 1px solid #f4f4f4; padding-right: 30px;">
                            <form action="{{ route('gov.org.hub.save-roles', $location->id) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label for="primary_approver_id">Primary Approver (Supervisor) <span class="text-danger">*</span></label>
                                    <select name="primary_approver_id" id="primary_approver_id" class="form-control select2" required style="width: 100%;">
                                        <option value="">{{ __('organization_labels::orglabel.jurisdictions_select_employee_placeholder') }}</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->primary_approver_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">{{ __('organization_labels::orglabel.config_help_primary_approver') }}</p>
                                </div>

                                <div class="form-group" style="margin-top: 20px;">
                                    <label for="final_approver_id">Final Approver (Optional)</label>
                                    <select name="final_approver_id" id="final_approver_id" class="form-control select2" style="width: 100%;">
                                        <option value="">{{ __('organization_labels::orglabel.config_role_none_single_level') }}</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->final_approver_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">{{ __('organization_labels::orglabel.config_help_final_approver') }}</p>
                                </div>

                                <div class="form-group" style="margin-top: 20px;">
                                    <label for="storekeeper_id">Storekeeper (Inventory Officer) <span class="text-danger">*</span></label>
                                    <select name="storekeeper_id" id="storekeeper_id" class="form-control select2" required style="width: 100%;">
                                        <option value="">{{ __('organization_labels::orglabel.jurisdictions_select_employee_placeholder') }}</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->storekeeper_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">{{ __('organization_labels::orglabel.config_help_storekeeper') }}</p>
                                </div>

                                <div style="margin-top: 25px; margin-bottom: 15px;">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('organization_labels::orglabel.config_save_button') }}</button>
                                </div>
                            </form>
                        </div>

                        <!-- Right: Checklist overview -->
                        <div class="col-md-5" style="padding-left: 30px;">
                            <div class="box box-solid {{ $isOperational ? 'box-success' : 'box-warning' }}" style="box-shadow: none; border: 1px solid #ddd;">
                                <div class="box-header with-border">
                                    <h4 class="box-title" style="font-weight: bold; font-size: 14px;"><i class="fas fa-tasks"></i> Operational Readiness Checklist</h4>
                                </div>
                                <div class="box-body no-padding" style="background-color: white;">
                                    
                                    <div class="checklist-row">
                                        <span class="checklist-indicator {{ $hasAdmin ? 'text-success' : 'text-gray' }}"><i class="fas {{ $hasAdmin ? 'fa-check-circle' : 'fa-circle' }}"></i></span>
                                        <span class="checklist-label">{{ __('organization_labels::orglabel.hub_checklist_admin_assigned') }}</span>
                                        <span class="label {{ $hasAdmin ? 'label-success' : 'label-default' }}">{{ $hasAdmin ? __('organization_labels::orglabel.hub_checklist_ready') : __('organization_labels::orglabel.hub_checklist_missing') }}</span>
                                    </div>

                                    <div class="checklist-row">
                                        <span class="checklist-indicator {{ $hasPrimary ? 'text-success' : 'text-gray' }}"><i class="fas {{ $hasPrimary ? 'fa-check-circle' : 'fa-circle' }}"></i></span>
                                        <span class="checklist-label">{{ __('organization_labels::orglabel.hub_checklist_primary_assigned') }}</span>
                                        <span class="label {{ $hasPrimary ? 'label-success' : 'label-default' }}">{{ $hasPrimary ? __('organization_labels::orglabel.hub_checklist_ready') : __('organization_labels::orglabel.hub_checklist_missing') }}</span>
                                    </div>

                                    <div class="checklist-row">
                                        <span class="checklist-indicator {{ $hasStorekeeper ? 'text-success' : 'text-gray' }}"><i class="fas {{ $hasStorekeeper ? 'fa-check-circle' : 'fa-circle' }}"></i></span>
                                        <span class="checklist-label">{{ __('organization_labels::orglabel.hub_checklist_storekeeper_assigned') }}</span>
                                        <span class="label {{ $hasStorekeeper ? 'label-success' : 'label-default' }}">{{ $hasStorekeeper ? __('organization_labels::orglabel.hub_checklist_ready') : __('organization_labels::orglabel.hub_checklist_missing') }}</span>
                                    </div>

                                    <div class="checklist-row">
                                        <span class="checklist-indicator {{ $hasStaff ? 'text-success' : 'text-gray' }}"><i class="fas {{ $hasStaff ? 'fa-check-circle' : 'fa-circle' }}"></i></span>
                                        <span class="checklist-label">{{ __('organization_labels::orglabel.hub_checklist_staff_count') }}</span>
                                        <span class="badge {{ $hasStaff ? 'bg-green' : 'bg-gray' }}">{{ $localStaff->count() }} {{ __('organization_labels::orglabel.hub_checklist_mapped') }}</span>
                                    </div>

                                </div>
                                <div class="box-footer" style="font-size: 12px; line-height: 1.5;">
                                    @if($isOperational)
                                        <span class="text-success"><i class="fas fa-check-circle"></i> {{ __('organization_labels::orglabel.hub_checklist_verified_passed') }}</span>
                                    @else
                                        <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('organization_labels::orglabel.hub_checklist_pending_unlock') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB C: LOCAL STAFF DIRECTORY -->
                <div class="tab-pane" id="tab_employees">
                    <div class="table-responsive" style="padding: 10px 0;">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('organization_labels::orglabel.hub_employee_name') }}</th>
                                    <th>{{ __('organization_labels::orglabel.hub_employee_username') }}</th>
                                    <th>{{ __('organization_labels::orglabel.hub_employee_email') }}</th>
                                    <th>{{ __('organization_labels::orglabel.hub_employee_jobtitle') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($localStaff as $user)
                                    <tr>
                                        <td><strong>{{ $user->present()->fullName }}</strong></td>
                                        <td>{{ $user->username }}</td>
                                        <td>{{ $user->email ?: '-' }}</td>
                                        <td>{{ $user->jobtitle ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted" style="padding: 30px;">
                                            {{ __('organization_labels::orglabel.hub_no_employees_message') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB D: SPATIAL INTEGRITY VERIFICATION -->
                <div class="tab-pane" id="tab_geography">
                    <div class="row" style="padding: 15px 0;">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <td style="width: 35%;"><strong>{{ __('organization_labels::orglabel.hub_geo_mapped_district') }}</strong></td>
                                    <td><strong>{{ $location->state ?: __('organization_labels::orglabel.hub_geo_unassigned') }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>{{ __('organization_labels::orglabel.hub_geo_mapped_upazila') }}</strong></td>
                                    <td><strong>{{ $location->city ?: __('organization_labels::orglabel.hub_geo_unassigned') }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>{{ __('organization_labels::orglabel.hub_geo_geographical_level') }}</strong></td>
                                    <td>{{ $profile->geoArea ? $profile->geoArea->GeoLevel : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ __('organization_labels::orglabel.hub_geo_hierarchy_path') }}</strong></td>
                                    <td><code>{{ $profile->geoArea ? $profile->geoArea->hid : 'N/A' }}</code></td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <div class="box box-solid box-default" style="box-shadow: none; border: 1px solid #ddd;">
                                <div class="box-header with-border">
                                    <h4 class="box-title" style="font-weight: bold; font-size: 14px;"><i class="fas fa-user-check"></i> {{ __('organization_labels::orglabel.hub_geo_admin_verification') }}</h4>
                                </div>
                                <div class="box-body">
                                    @if($profile->geo_area_verified_at)
                                        <div class="text-center" style="padding: 10px 0;">
                                            <span style="font-size: 35px; color: #00a65a;"><i class="fas fa-shield-alt"></i></span>
                                            <h4 style="font-weight: bold; margin-top: 10px; margin-bottom: 5px;">{{ __('organization_labels::orglabel.hub_geo_verified_title') }}</h4>
                                            <p class="text-muted" style="font-size: 12px; margin-bottom: 0;">
                                                {{ __('organization_labels::orglabel.hub_geo_signoff_label') }} <strong>{{ $profile->geo_area_verified_at->format('Y-m-d H:i') }}</strong> <br>
                                                {{ __('organization_labels::orglabel.hub_geo_audited_by') }} <strong>{{ $profile->verifier->display_name ?? __('organization_labels::orglabel.hub_geo_system_administrator') }}</strong>
                                            </p>
                                        </div>
                                    @else
                                        <p class="text-muted">{{ __('organization_labels::orglabel.hub_geo_not_verified') }}</p>
                                        
                                        <form action="{{ route('gov.org.hub.verify-geo', $location->id) }}" method="POST" style="margin-top: 15px;">
                                            @csrf
                                            <button type="submit" class="btn btn-success"><i class="fas fa-check-shield"></i> {{ __('organization_labels::orglabel.hub_geo_verify_button') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB E: SYSTEM AUDIT TIMELINE -->
                <div class="tab-pane" id="tab_timeline">
                    <div style="padding: 15px 0;">
                        <ul class="timeline">
                            @forelse($activityLogs as $log)
                                <li>
                                    @if($log->event_type === 'office_created')
                                        <i class="fa fa-plus bg-blue"></i>
                                    @elseif($log->event_type === 'admin_assigned')
                                        <i class="fa fa-user-tie bg-purple"></i>
                                    @elseif($log->event_type === 'roles_configured')
                                        <i class="fa fa-sliders-h bg-yellow-active"></i>
                                    @elseif($log->event_type === 'status_changed')
                                        <i class="fa fa-toggle-on bg-green-active"></i>
                                    @else
                                        <i class="fa fa-info bg-gray"></i>
                                    @endif

                                    <div class="timeline-item" style="box-shadow: none; border: 1px solid #eee; background-color: #fafafa; margin-left: 45px;">
                                        <span class="time"><i class="fa fa-clock"></i> {{ $log->created_at->format('Y-m-d H:i') }}</span>
                                        <h3 class="timeline-header" style="font-size: 13px; font-weight: bold; border-bottom: none; padding: 5px 10px;">
                                            {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                        </h3>
                                        <div class="timeline-body" style="padding: 5px 10px; font-size: 12px; color: #555;">
                                            Executed by: <strong>{{ $log->performer->display_name ?? 'System' }}</strong>
                                            @if(isset($log->details['message']))
                                                <p style="margin-top: 5px; font-style: italic;">"{{ $log->details['message'] }}"</p>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li>
                                    <i class="fa fa-info bg-gray"></i>
                                    <div class="timeline-item">
                                        <h3 class="timeline-header">{{ __('organization_labels::orglabel.hub_activity_empty') }}</h3>
                                    </div>
                                </li>
                            @endforelse
                            <li><i class="fa fa-clock bg-gray"></i></li>
                        </ul>
                    </div>
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
                    q: params.term
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: "Search Division, District, Upazila, or Union..."
    });
});
</script>
@endsection