@extends('layouts/default')

@section('title', 'Office Hub: ' . $location->name)

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
                        <span class="label label-success" style="font-size: 14px; padding: 8px 15px;"><i class="fas fa-check-double"></i> OPERATIONAL</span>
                    @elseif($profile->lifecycle_status === 'configured')
                        <span class="label label-info" style="font-size: 14px; padding: 8px 15px;"><i class="fas fa-sliders-h"></i> CONFIGURED</span>
                    @else
                        <span class="label label-warning" style="font-size: 14px; padding: 8px 15px;"><i class="fas fa-building"></i> PROVISIONED (PENDING)</span>
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
                <li class="active"><a href="#tab_overview" data-toggle="tab"><i class="fas fa-id-card"></i> General Info</a></li>
                <li><a href="#tab_roles" data-toggle="tab"><i class="fas fa-user-shield"></i> Workflow Roles</a></li>
                <li><a href="#tab_employees" data-toggle="tab"><i class="fas fa-users"></i> Local Employees <span class="badge bg-blue">{{ $localStaff->count() }}</span></a></li>
                <li><a href="#tab_geography" data-toggle="tab"><i class="fas fa-map-marked-alt"></i> Spatial Integrity</a></li>
                <li><a href="#tab_timeline" data-toggle="tab"><i class="fas fa-history"></i> Activity Timeline</a></li>
            </ul>

            <div class="tab-content" style="background-color: white;">
                
                <!-- TAB A: GENERAL PROFILE EDITS -->
                <div class="tab-pane active" id="tab_overview">
                    <form action="{{ route('gov.org.hub.update', $location->id) }}" method="POST" style="max-width: 700px; padding: 15px 0;">
                        @csrf
                        <div class="form-group">
                            <label for="name">Office Building Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ $location->name }}" required>
                        </div>

                        <div class="form-group">
                            <label for="company_id">Ministry / Department Ownership (Optional)</label>
                            <select name="company_id" id="company_id" class="form-control select2" style="width: 100%;">
                                <option value="">-- Standalone Office (No Ministry) --</option>
                                @foreach($companies as $comp)
                                    <option value="{{ $comp->id }}" {{ $location->company_id == $comp->id ? 'selected' : '' }}>{{ $comp->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="parent_id">Parent Regional / District Office (Optional)</label>
                            <select name="parent_id" id="parent_id" class="form-control select2" style="width: 100%;">
                                <option value="">-- No Parent (Root Location) --</option>
                                @foreach($allOffices as $parent)
                                    <option value="{{ $parent->id }}" {{ $location->parent_id == $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="geoAreaSelector">Geographical Boundary Territory <span class="text-danger">*</span></label>
                            <select name="geo_area_id" id="geoAreaSelector" class="form-control" required style="width: 100%;">
                                @if($profile->geoArea)
                                    <option value="{{ $profile->geo_area_id }}" selected>
                                        {{ $profile->geoArea->en_name }} ({{ $profile->geoArea->bn_name }}) - {{ ucfirst($profile->geoArea->geo_type) }}
                                    </option>
                                @endif
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="office_admin_id">Designated Office Administrator</label>
                            <select name="office_admin_id" id="office_admin_id" class="form-control select2" style="width: 100%;">
                                <option value="">-- No Administrator Assigned --</option>
                                @foreach($allUsers as $user)
                                    <option value="{{ $user->id }}" {{ $profile->office_admin_id == $user->id ? 'selected' : '' }}>
                                        {{ $user->present()->fullName }} ({{ $user->username }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div style="margin-top: 25px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Office Details</button>
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
                                        <option value="">-- Select Employee --</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->primary_approver_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">Line manager responsible for checking and authorizing employee baskets first.</p>
                                </div>

                                <div class="form-group" style="margin-top: 20px;">
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

                                <div class="form-group" style="margin-top: 20px;">
                                    <label for="storekeeper_id">Storekeeper (Inventory Officer) <span class="text-danger">*</span></label>
                                    <select name="storekeeper_id" id="storekeeper_id" class="form-control select2" required style="width: 100%;">
                                        <option value="">-- Select Employee --</option>
                                        @foreach($localStaff as $user)
                                            <option value="{{ $user->id }}" {{ $roles && $roles->storekeeper_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->present()->fullName }} ({{ $user->username }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="help-block">Fulfiller responsible for packing and registering physical checkout handovers.</p>
                                </div>

                                <div style="margin-top: 25px; margin-bottom: 15px;">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Office Assignments</button>
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
                                        <span class="checklist-label">Office Administrator Assigned</span>
                                        <span class="label {{ $hasAdmin ? 'label-success' : 'label-default' }}">{{ $hasAdmin ? 'Ready' : 'Missing' }}</span>
                                    </div>

                                    <div class="checklist-row">
                                        <span class="checklist-indicator {{ $hasPrimary ? 'text-success' : 'text-gray' }}"><i class="fas {{ $hasPrimary ? 'fa-check-circle' : 'fa-circle' }}"></i></span>
                                        <span class="checklist-label">Primary Approver Assigned</span>
                                        <span class="label {{ $hasPrimary ? 'label-success' : 'label-default' }}">{{ $hasPrimary ? 'Ready' : 'Missing' }}</span>
                                    </div>

                                    <div class="checklist-row">
                                        <span class="checklist-indicator {{ $hasStorekeeper ? 'text-success' : 'text-gray' }}"><i class="fas {{ $hasStorekeeper ? 'fa-check-circle' : 'fa-circle' }}"></i></span>
                                        <span class="checklist-label">Storekeeper Assigned</span>
                                        <span class="label {{ $hasStorekeeper ? 'label-success' : 'label-default' }}">{{ $hasStorekeeper ? 'Ready' : 'Missing' }}</span>
                                    </div>

                                    <div class="checklist-row">
                                        <span class="checklist-indicator {{ $hasStaff ? 'text-success' : 'text-gray' }}"><i class="fas {{ $hasStaff ? 'fa-check-circle' : 'fa-circle' }}"></i></span>
                                        <span class="checklist-label">Staff Count (Min: 1)</span>
                                        <span class="badge {{ $hasStaff ? 'bg-green' : 'bg-gray' }}">{{ $localStaff->count() }} Mapped</span>
                                    </div>

                                </div>
                                <div class="box-footer" style="font-size: 12px; line-height: 1.5;">
                                    @if($isOperational)
                                        <span class="text-success"><i class="fas fa-check-circle"></i> Verification passed. The employee storefront is active.</span>
                                    @else
                                        <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Complete the outstanding items above to unlock the catalog for local employees.</span>
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
                                    <th>Employee Name</th>
                                    <th>Username</th>
                                    <th>Email Address</th>
                                    <th>Job Title</th>
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
                                            No employee profiles are currently mapped to this location inside Snipe-IT. <br>
                                            To add staff, edit their User profiles natively inside Snipe-IT and assign their <strong>Location</strong> field to this building.
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
                                    <td style="width: 35%;"><strong>Mapped District (Zila):</strong></td>
                                    <td><strong>{{ $location->state ?: 'Unassigned' }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Mapped Upazila/City:</strong></td>
                                    <td><strong>{{ $location->city ?: 'Unassigned' }}</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Geographical Level:</strong></td>
                                    <td>{{ $profile->geoArea ? $profile->geoArea->GeoLevel : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Hierarchy ID Path:</strong></td>
                                    <td><code>{{ $profile->geoArea ? $profile->geoArea->hid : 'N/A' }}</code></td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <div class="box box-solid box-default" style="box-shadow: none; border: 1px solid #ddd;">
                                <div class="box-header with-border">
                                    <h4 class="box-title" style="font-weight: bold; font-size: 14px;"><i class="fas fa-user-check"></i> Administrative Verification</h4>
                                </div>
                                <div class="box-body">
                                    @if($profile->geo_area_verified_at)
                                        <div class="text-center" style="padding: 10px 0;">
                                            <span style="font-size: 35px; color: #00a65a;"><i class="fas fa-shield-alt"></i></span>
                                            <h4 style="font-weight: bold; margin-top: 10px; margin-bottom: 5px;">Geographic Coordinates Verified</h4>
                                            <p class="text-muted" style="font-size: 12px; margin-bottom: 0;">
                                                Sign-off executed on: <strong>{{ $profile->geo_area_verified_at->format('Y-m-d H:i') }}</strong> <br>
                                                Audited by: <strong>{{ $profile->verifier->display_name ?? 'System Administrator' }}</strong>
                                            </p>
                                        </div>
                                    @else
                                        <p class="text-muted">This office building is currently assigned to approximate geographical coordinates. Click below to verify and lock its spatial territory placement.</p>
                                        
                                        <form action="{{ route('gov.org.hub.verify-geo', $location->id) }}" method="POST" style="margin-top: 15px;">
                                            @csrf
                                            <button type="submit" class="btn btn-success"><i class="fas fa-check-shield"></i> Verify Geographic Tag Accuracy</button>
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
                                        <h3 class="timeline-header">No timeline entries found.</h3>
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