@extends('layouts/default')

@section('title', __('organization_labels::orglabel.config_title'))

@section('content')

{{-- Readiness Checklist and Config Layout --}}
<style>
    .checklist-item { display: flex; align-items: center; padding: 12px 15px; border-bottom: 1px solid #f4f4f4; }
    .checklist-item:last-child { border-bottom: none; }
    .checklist-icon { font-size: 18px; margin-right: 15px; }
    .checklist-text { font-size: 14px; font-weight: bold; flex-grow: 1; }
</style>

<div class="row">
    <!-- LEFT COLUMN: Dynamic Operational Readiness Checklist -->
    <div class="col-md-5">
        
        <!-- Checklist status panel -->
        <div class="box box-solid {{ $readiness['is_operational'] ? 'box-success' : 'box-warning' }}">
            <div class="box-header with-border">
                <h3 class="box-title" style="color: white !important;">
                    <i class="fas {{ $readiness['is_operational'] ? 'fa-check-double' : 'fa-clipboard-list' }}"></i> 
                    Office Status: {{ strtoupper($profile->lifecycle_status) }}
                </h3>
            </div>
            <div class="box-body no-padding" style="background-color: white;">
                
                <div class="checklist-item">
                    <span class="checklist-icon {{ $readiness['checklist']['has_office_admin'] ? 'text-success' : 'text-gray' }}">
                        <i class="fas {{ $readiness['checklist']['has_office_admin'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                    </span>
                    <span class="checklist-text">{{ __('organization_labels::orglabel.config_checklist_designate_admin') }}</span>
                    <span class="label {{ $readiness['checklist']['has_office_admin'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_office_admin'] ? __('organization_labels::orglabel.config_checklist_configured') : __('organization_labels::orglabel.config_checklist_missing') }}
                    </span>
                </div>

                <div class="checklist-item">
                    <span class="checklist-icon {{ $readiness['checklist']['has_primary_approver'] ? 'text-success' : 'text-gray' }}">
                        <i class="fas {{ $readiness['checklist']['has_primary_approver'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                    </span>
                    <span class="checklist-text">{{ __('organization_labels::orglabel.config_checklist_assign_approver') }}</span>
                    <span class="label {{ $readiness['checklist']['has_primary_approver'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_primary_approver'] ? __('organization_labels::orglabel.config_checklist_configured') : __('organization_labels::orglabel.config_checklist_missing') }}
                    </span>
                </div>

                <div class="checklist-item">
                    <span class="checklist-icon {{ $readiness['checklist']['has_storekeeper'] ? 'text-success' : 'text-gray' }}">
                        <i class="fas {{ $readiness['checklist']['has_storekeeper'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                    </span>
                    <span class="checklist-text">{{ __('organization_labels::orglabel.config_checklist_assign_storekeeper') }}</span>
                    <span class="label {{ $readiness['checklist']['has_storekeeper'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_storekeeper'] ? __('organization_labels::orglabel.config_checklist_configured') : __('organization_labels::orglabel.config_checklist_missing') }}
                    </span>
                </div>

                <div class="checklist-item">
                    <span class="checklist-icon {{ $readiness['checklist']['has_users'] ? 'text-success' : 'text-gray' }}">
                        <i class="fas {{ $readiness['checklist']['has_users'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                    </span>
                    <span class="checklist-text">{{ __('organization_labels::orglabel.config_checklist_mapped_employees') }}</span>
                    <span class="badge {{ $readiness['checklist']['has_users'] ? 'bg-green' : 'bg-gray' }}">
                        {{ $readiness['users_count'] }} {{ __('organization_labels::orglabel.config_checklist_user_count_suffix') }}
                    </span>
                </div>

            </div>
            @if($readiness['is_operational'])
                <div class="box-footer text-center" style="background-color: #dff0d8; border-top: 1px solid #d6e9c6;">
                    <strong class="text-success"><i class="fas fa-check-double"></i> {{ __('organization_labels::orglabel.config_operational_verified') }}</strong>
                </div>
            @else
                <div class="box-footer text-center" style="background-color: #fcf8e3; border-top: 1px solid #faebcc;">
                    <strong class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('organization_labels::orglabel.config_pending_instruction') }}</strong>
                </div>
            @endif
        </div>

        <!-- Geographic Mapped Reference Details -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-info-circle"></i> {{ __('organization_labels::orglabel.config_profile_title') }}</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table">
                    <tr>
                        <td style="border-top: none;"><strong>{{ __('organization_labels::orglabel.config_field_physical_office') }}</strong></td>
                        <td style="border-top: none;">{{ $location->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>{{ __('organization_labels::orglabel.config_field_ministry_division') }}</strong></td>
                        <td>{{ $location->company->name ?? __('organization_labels::orglabel.config_field_standalone') }}</td>
                    </tr>
                    <tr>
                        <td><strong>{{ __('organization_labels::orglabel.config_field_territory_tag') }}</strong></td>
                        <td>
                            <span class="label bg-blue" style="font-size: 11px;">
                                <i class="fas fa-map-marker-alt"></i> {{ $profile->geoArea->en_name ?? __('organization_labels::orglabel.config_field_unspecified') }} ({{ ucfirst($profile->geoArea->geo_type ?? 'N/A') }})
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Role Assignment Form and Local User List -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sliders-h"></i> {{ __('organization_labels::orglabel.config_roles_title') }}</h3>
            </div>
            <form action="{{ route('gov.org.config.save') }}" method="POST">
                @csrf
                <div class="box-body">
                    
                    <div class="form-group">
                        <label for="primary_approver_id">{{ __('organization_labels::orglabel.config_role_primary_approver') }} <span class="text-danger">*</span></label>
                        <select name="primary_approver_id" id="primary_approver_id" class="form-control select2" required style="width: 100%;">
                            <option value="">{{ __('organization_labels::orglabel.config_role_select_employee') }}</option>
                            @foreach($localStaff as $user)
                                <option value="{{ $user->id }}" {{ $roles && $roles->primary_approver_id == $user->id ? 'selected' : '' }}>
                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                </option>
                            @endforeach
                        </select>
                        <p class="help-block">{{ __('organization_labels::orglabel.config_help_primary_approver') }}</p>
                    </div>

                    <div class="form-group">
                        <label for="final_approver_id">{{ __('organization_labels::orglabel.config_role_final_approver') }}</label>
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

                    <div class="form-group">
                        <label for="storekeeper_id">{{ __('organization_labels::orglabel.config_role_storekeeper') }} <span class="text-danger">*</span></label>
                        <select name="storekeeper_id" id="storekeeper_id" class="form-control select2" required style="width: 100%;">
                            <option value="">{{ __('organization_labels::orglabel.config_role_select_employee') }}</option>
                            @foreach($localStaff as $user)
                                <option value="{{ $user->id }}" {{ $roles && $roles->storekeeper_id == $user->id ? 'selected' : '' }}>
                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                </option>
                            @endforeach
                        </select>
                        <p class="help-block">{{ __('organization_labels::orglabel.config_help_storekeeper') }}</p>
                    </div>

                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right"><i class="fas fa-save"></i> {{ __('organization_labels::orglabel.config_save_button') }}</button>
                </div>
            </form>
        </div>

        <!-- MAPPED EMPLOYEES LIST -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-users"></i> {{ __('organization_labels::orglabel.config_employees_title') }}</h3>
            </div>
            <div class="box-body table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('organization_labels::orglabel.config_employee_name') }}</th>
                            <th>{{ __('organization_labels::orglabel.config_employee_username') }}</th>
                            <th>{{ __('organization_labels::orglabel.config_employee_jobtitle') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($localStaff as $user)
                            <tr>
                                <td><strong>{{ $user->present()->fullName }}</strong></td>
                                <td>{{ $user->username }}</td>
                                <td>{{ $user->jobtitle ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">{{ __('organization_labels::orglabel.config_no_employees') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection