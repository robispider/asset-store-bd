@extends('layouts/default')

@section('title', __('organization_labels::orglabel.hub_title_prefix') . ' ' . $location->name)

@section('content')
<div class="govorg-theme">

{{-- Custom Styling for Hub Panels --}}


@php
    // Evaluate operational readiness checklist on the fly
    $hasAdmin = $readiness['checklist']['has_office_admin'];
    $hasPrimary = $readiness['checklist']['has_primary_approver'];
    $hasStorekeeper = $readiness['checklist']['has_storekeeper'];
    $hasStaff = $readiness['checklist']['has_users'];
    $isOperational = $readiness['is_operational'];
@endphp

<!-- HUB MASTER HEADER & STATUS BLOCK -->
<div class="row">
    <div class="col-md-12">
        <div class="hub-header">
            <div class="org-inline-67557c30">
                <div>
                    <h2 class="org-inline-0906d6e0">{{ $location->name }}</h2>
                    <p class="org-inline-be254991">
                        <i class="fas fa-map-marker-alt"></i> 
                        {{ $profile->geoArea->en_name ?? 'Unmapped Territory' }} ({{ ucfirst($profile->geoArea->geo_type ?? 'N/A') }}) 
                        &bull; Ministry: {{ $location->company->name ?? 'Standalone Office' }}
                    </p>
                </div>
                <div>
                    @if($profile->lifecycle_status === 'suspended')
                        <span class="label label-danger">{{ __('organization_labels::orglabel.lifecycle_suspended') }}</span>
                    @elseif($profile->lifecycle_status === 'operational')
                        <span class="label label-success org-inline-25c7f52f"><i class="fas fa-check-double"></i> {{ __('organization_labels::orglabel.hub_status_operational') }}</span>
                    @elseif($profile->lifecycle_status === 'configured')
                        <span class="label label-info org-inline-25c7f52f"><i class="fas fa-sliders-h"></i> {{ __('organization_labels::orglabel.hub_status_configured') }}</span>
                    @else
                        <span class="label label-warning org-inline-25c7f52f"><i class="fas fa-building"></i> {{ __('organization_labels::orglabel.hub_status_provisioned') }}</span>
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

            <div class="tab-content org-inline-2d2f5fdd">
                
                <!-- TAB A: GENERAL PROFILE EDITS -->
                <div class="tab-pane active" id="tab_overview">
                    <form action="{{ route('gov.org.hub.update', $location->id) }}" method="POST" class="org-inline-fa4154d1">
                        @csrf
                        <fieldset @if(!in_array($profile->lifecycle_status, ['provisioned', 'configured', 'operational'], true)) disabled @endif>
                        <div class="form-group">
                            <label for="name">{{ __('organization_labels::orglabel.hub_field_office_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ $location->name }}" required>
                        </div>

                        <div class="form-group">
                            <label for="company_id">{{ __('organization_labels::orglabel.hub_field_ministry') }}</label>
                            <input type="hidden" name="company_id" value="{{ $location->company_id }}">
                            <select class="form-control select2 org-inline-442a70a1" id="company_id" disabled>
                                <option value="">{{ __('organization_labels::orglabel.create_placeholder_standalone') }}</option>
                                @foreach($companies as $comp)
                                    <option value="{{ $comp->id }}" {{ $location->company_id == $comp->id ? 'selected' : '' }}>{{ $comp->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="parent_id">{{ __('organization_labels::orglabel.hub_field_parent_office') }}</label>
                            <input type="hidden" name="parent_id" value="{{ $location->parent_id }}">
                            <select class="form-control select2 org-inline-442a70a1" id="parent_id" disabled>
                                @if($location->parent)
                                    <option value="{{ $location->parent_id }}" selected>{{ $location->parent->name }}</option>
                                @endif
                                <option value="">{{ __('organization_labels::orglabel.create_placeholder_no_parent') }}</option>
                                @foreach($allOffices as $parent)
                                    <option value="{{ $parent->id }}" {{ $location->parent_id == $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="geoAreaSelector">{{ __('organization_labels::orglabel.hub_field_geo_area') }} <span class="text-danger">*</span></label>
                            <input type="hidden" name="geo_area_id" value="{{ $profile->geo_area_id }}">
                            <select class="form-control org-inline-442a70a1" id="geoAreaSelector" disabled>
                                @if($profile->geoArea)
                                    <option value="{{ $profile->geo_area_id }}" selected>
                                        {{ $profile->geoArea->en_name }} ({{ $profile->geoArea->bn_name }}) - {{ ucfirst($profile->geoArea->geo_type) }}
                                    </option>
                                @endif
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="office_admin_id">{{ __('organization_labels::orglabel.hub_field_office_admin') }}</label>
                            <select class="form-control select2 org-inline-442a70a1" name="office_admin_id" id="office_admin_id">
                                <option value="">{{ __('organization_labels::orglabel.hub_placeholder_no_admin') }}</option>
                                @foreach($allUsers as $user)
                                    <option value="{{ $user->id }}" {{ $profile->office_admin_id == $user->id ? 'selected' : '' }}>
                                        {{ $user->present()->fullName }} ({{ $user->username }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="org-inline-f5897d74">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('organization_labels::orglabel.hub_save_button') }}</button>
                        </div>
                        </fieldset>
                    </form>
                </div>

                <!-- TAB B: WORKFLOW ROLES & LIVE CHECKLIST -->
                <div class="tab-pane" id="tab_roles">
                    <div class="row">
                        <!-- Left: Form selectors -->
                        <div class="col-md-7 org-inline-c9875beb">
                            <form action="{{ route('gov.org.hub.save-roles', $location->id) }}" method="POST">
                                @csrf
                                <fieldset @if(!in_array($profile->lifecycle_status, ['provisioned', 'configured', 'operational'], true)) disabled @endif>
                                <div class="form-group">
                                    <label for="primary_approver_id">Primary Approver (Supervisor) <span class="text-danger">*</span></label>
                                    <select class="form-control select2 org-inline-442a70a1" name="primary_approver_id" id="primary_approver_id" required>
                                        <option value="">{{ __('organization_labels::orglabel.jurisdictions_select_employee_placeholder') }}</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->primary_approver_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">{{ __('organization_labels::orglabel.config_help_primary_approver') }}</p>
                                </div>

                                <div class="form-group org-inline-b6ec21fd">
                                    <label for="final_approver_id">Final Approver (Optional)</label>
                                    <select class="form-control select2 org-inline-442a70a1" name="final_approver_id" id="final_approver_id">
                                        <option value="">{{ __('organization_labels::orglabel.config_role_none_single_level') }}</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->final_approver_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">{{ __('organization_labels::orglabel.config_help_final_approver') }}</p>
                                </div>

                                <div class="form-group org-inline-b6ec21fd">
                                    <label for="storekeeper_id">Storekeeper (Inventory Officer) <span class="text-danger">*</span></label>
                                    <select class="form-control select2 org-inline-442a70a1" name="storekeeper_id" id="storekeeper_id" required>
                                        <option value="">{{ __('organization_labels::orglabel.jurisdictions_select_employee_placeholder') }}</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->storekeeper_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">{{ __('organization_labels::orglabel.config_help_storekeeper') }}</p>
                                </div>

                                <div class="org-inline-79ed16e9">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('organization_labels::orglabel.config_save_button') }}</button>
                                </div>
                                </fieldset>
                            </form>
                        </div>

                        <!-- Right: Checklist overview -->
                        <div class="col-md-5 org-inline-71d8f232">
                            <div class="box box-solid {{ $isOperational ? 'box-success' : 'box-warning' }} org-inline-1a49b75a">
                                <div class="box-header with-border">
                                    <h4 class="box-title org-inline-50e027a2"><i class="fas fa-tasks"></i> Operational Readiness Checklist</h4>
                                </div>
                                <div class="box-body no-padding org-inline-2d2f5fdd">
                                    
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
                                <div class="box-footer org-inline-73c238f0">
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
                    <div class="table-responsive org-inline-6d78c906">
                        <x-gs::table class="table table-striped table-hover">
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
                                        <td class="text-center text-muted org-inline-ed0f0aa0" colspan="4">
                                            {{ __('organization_labels::orglabel.hub_no_employees_message') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-gs::table>
                    </div>
                </div>

                <!-- TAB D: SPATIAL INTEGRITY VERIFICATION -->
                <div class="tab-pane" id="tab_geography">
                    <div class="row org-inline-c2cf9d67">
                        <div class="col-md-6">
                            <x-gs::table class="table table-bordered">
                                <tr>
                                    <td class="org-inline-3116a069"><strong>{{ __('organization_labels::orglabel.hub_geo_mapped_district') }}</strong></td>
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
                            </x-gs::table>
                        </div>

                        <div class="col-md-6">
                            <div class="box box-solid box-default org-inline-1a49b75a">
                                <div class="box-header with-border">
                                    <h4 class="box-title org-inline-50e027a2"><i class="fas fa-user-check"></i> {{ __('organization_labels::orglabel.hub_geo_admin_verification') }}</h4>
                                </div>
                                <div class="box-body">
                                    @if($profile->geo_area_verified_at)
                                        <div class="text-center org-inline-6d78c906">
                                            <span class="org-inline-078f470f"><i class="fas fa-shield-alt"></i></span>
                                            <h4 class="org-inline-6afce2c0">{{ __('organization_labels::orglabel.hub_geo_verified_title') }}</h4>
                                            <p class="text-muted org-inline-95214290">
                                                {{ __('organization_labels::orglabel.hub_geo_signoff_label') }} <strong>{{ $profile->geo_area_verified_at->format('Y-m-d H:i') }}</strong> <br>
                                                {{ __('organization_labels::orglabel.hub_geo_audited_by') }} <strong>{{ $profile->verifier->display_name ?? __('organization_labels::orglabel.hub_geo_system_administrator') }}</strong>
                                            </p>
                                        </div>
                                    @else
                                        <p class="text-muted">{{ __('organization_labels::orglabel.hub_geo_not_verified') }}</p>
                                        
                                        <form action="{{ route('gov.org.hub.verify-geo', $location->id) }}" method="POST" class="org-inline-e3bba13d">
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
                    <div class="org-inline-c2cf9d67">
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

                                    <div class="timeline-item org-inline-c031f1ca">
                                        <span class="time"><i class="fa fa-clock"></i> {{ $log->created_at->format('Y-m-d H:i') }}</span>
                                        <h3 class="timeline-header org-inline-ab0bd004">
                                            {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                        </h3>
                                        <div class="timeline-body org-inline-05284360">
                                            Executed by: <strong>{{ $log->performer->display_name ?? 'System' }}</strong>
                                            @if(isset($log->details['message']))
                                                <p class="org-inline-86509692">"{{ $log->details['message'] }}"</p>
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
@include('govorg::provisioning.lifecycle')
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    $('#lifecycle-geo').select2({
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
        placeholder: @json(__('organization_labels::orglabel.lifecycle_geo_search'))
    });
});
</script>
@endsection
