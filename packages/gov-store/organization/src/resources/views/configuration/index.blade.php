@extends('layouts/default')

@section('title', 'My Office Management')

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
                    <span class="checklist-text">Designate Office Administrator</span>
                    <span class="label {{ $readiness['checklist']['has_office_admin'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_office_admin'] ? 'Configured' : 'Missing' }}
                    </span>
                </div>

                <div class="checklist-item">
                    <span class="checklist-icon {{ $readiness['checklist']['has_primary_approver'] ? 'text-success' : 'text-gray' }}">
                        <i class="fas {{ $readiness['checklist']['has_primary_approver'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                    </span>
                    <span class="checklist-text">Assign Primary Approver</span>
                    <span class="label {{ $readiness['checklist']['has_primary_approver'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_primary_approver'] ? 'Configured' : 'Missing' }}
                    </span>
                </div>

                <div class="checklist-item">
                    <span class="checklist-icon {{ $readiness['checklist']['has_storekeeper'] ? 'text-success' : 'text-gray' }}">
                        <i class="fas {{ $readiness['checklist']['has_storekeeper'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                    </span>
                    <span class="checklist-text">Assign Storekeeper</span>
                    <span class="label {{ $readiness['checklist']['has_storekeeper'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_storekeeper'] ? 'Configured' : 'Missing' }}
                    </span>
                </div>

                <div class="checklist-item">
                    <span class="checklist-icon {{ $readiness['checklist']['has_users'] ? 'text-success' : 'text-gray' }}">
                        <i class="fas {{ $readiness['checklist']['has_users'] ? 'fa-check-circle' : 'fa-circle' }}"></i>
                    </span>
                    <span class="checklist-text">Mapped Employees (Min: 1)</span>
                    <span class="badge {{ $readiness['checklist']['has_users'] ? 'bg-green' : 'bg-gray' }}">
                        {{ $readiness['users_count'] }} User(s)
                    </span>
                </div>

            </div>
            @if($readiness['is_operational'])
                <div class="box-footer text-center" style="background-color: #dff0d8; border-top: 1px solid #d6e9c6;">
                    <strong class="text-success"><i class="fas fa-check-double"></i> Verified. This office is active and ready to process requests.</strong>
                </div>
            @else
                <div class="box-footer text-center" style="background-color: #fcf8e3; border-top: 1px solid #faebcc;">
                    <strong class="text-warning"><i class="fas fa-exclamation-triangle"></i> Assign local staff roles below to activate your storefront.</strong>
                </div>
            @endif
        </div>

        <!-- Geographic Mapped Reference Details -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-info-circle"></i> Office Profile</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table">
                    <tr>
                        <td style="border-top: none;"><strong>Physical Office:</strong></td>
                        <td style="border-top: none;">{{ $location->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Ministry / Division:</strong></td>
                        <td>{{ $location->company->name ?? 'Standalone Office' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Territory Tag:</strong></td>
                        <td>
                            <span class="label bg-blue" style="font-size: 11px;">
                                <i class="fas fa-map-marker-alt"></i> {{ $profile->geoArea->en_name ?? 'Unspecified' }} ({{ ucfirst($profile->geoArea->geo_type ?? 'N/A') }})
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
                <h3 class="box-title"><i class="fas fa-sliders-h"></i> Assign Office Workflow Roles</h3>
            </div>
            <form action="{{ route('gov.org.config.save') }}" method="POST">
                @csrf
                <div class="box-body">
                    
                    <div class="form-group">
                        <label for="primary_approver_id">Primary Approver (Supervisor) <span class="text-danger">*</span></label>
                        <select name="primary_approver_id" id="primary_approver_id" class="form-control select2" required style="width: 100%;">
                            <option value="">-- Select Mapped Employee --</option>
                            @foreach($localStaff as $user)
                                <option value="{{ $user->id }}" {{ $roles && $roles->primary_approver_id == $user->id ? 'selected' : '' }}>
                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                </option>
                            @endforeach
                        </select>
                        <p class="help-block">Line manager responsible for checking and authorizing employee baskets first.</p>
                    </div>

                    <div class="form-group">
                        <label for="final_approver_id">Final Approver (Optional)</label>
                        <select name="final_approver_id" id="final_approver_id" class="form-control select2" style="width: 100%;">
                            <option value="">-- None (Single Level Approval Only) --</option>
                            @foreach($localStaff as $user)
                                <option value="{{ $user->id }}" {{ $roles && $roles->final_approver_id == $user->id ? 'selected' : '' }}>
                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                </option>
                            @endforeach
                        </select>
                        <p class="help-block">If specified, requests automatically move to this final director after primary sign-off.</p>
                    </div>

                    <div class="form-group">
                        <label for="storekeeper_id">Storekeeper (Inventory Officer) <span class="text-danger">*</span></label>
                        <select name="storekeeper_id" id="storekeeper_id" class="form-control select2" required style="width: 100%;">
                            <option value="">-- Select Mapped Employee --</option>
                            @foreach($localStaff as $user)
                                <option value="{{ $user->id }}" {{ $roles && $roles->storekeeper_id == $user->id ? 'selected' : '' }}>
                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                </option>
                            @endforeach
                        </select>
                        <p class="help-block">Fulfiller responsible for packing and registering physical checkout handovers.</p>
                    </div>

                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right"><i class="fas fa-save"></i> Save Office Assignments</button>
                </div>
            </form>
        </div>

        <!-- MAPPED EMPLOYEES LIST -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-users"></i> Assigned Office Employees</h3>
            </div>
            <div class="box-body table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Job Title</th>
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
                                <td colspan="3" class="text-center text-muted">No employees are assigned to this location yet. Map user profiles in Snipe-IT to this Location to satisfy the checklist.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection