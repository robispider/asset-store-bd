@extends('layouts/default')

@section('title', 'Organization Administration')

@section('content')
<div class="row">
    <!-- LEFT: Provision New Office Location Form -->
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-plus"></i> Provision New Office</h3>
            </div>
            <form action="{{ route('gov.org.provisioning.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="name">Office Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Kaliganj Upazila Office" required>
                    </div>

                    <!-- MANDATORY GEOGRAPHIC AREA SELECTOR -->
                    <div class="form-group">
                        <label for="geoAreaSelector">Geographical Boundary (Upazila/Union) <span class="text-danger">*</span></label>
                        <select name="geo_area_id" id="geoAreaSelector" class="form-control" required style="width: 100%;">
                            <option value="">-- Start typing to search --</option>
                        </select>
                        <p class="help-block">Mandatory. This tags the office to its physical administrative territory.</p>
                    </div>

                    <div class="form-group">
                        <label for="company_id">Ministry / Department (Optional)</label>
                        <select name="company_id" id="company_id" class="form-control select2" style="width: 100%;">
                            <option value="">-- No Ministry (Standalone) --</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="parent_id">Parent Regional/District Office (Optional)</label>
                        <select name="parent_id" id="parent_id" class="form-control select2" style="width: 100%;">
                            <option value="">-- No Parent (Root Location) --</option>
                            @foreach($offices as $parentLoc)
                                <option value="{{ $parentLoc->id }}">{{ $parentLoc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="office_admin_id">Assign Office Administrator (Optional)</label>
                        <select name="office_admin_id" id="office_admin_id" class="form-control select2" style="width: 100%;">
                            <option value="">-- Leave Unassigned for Now --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="city">Upazila / City (Text)</label>
                                <input type="text" name="city" id="city" class="form-control" placeholder="e.g. Kaliganj">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="state">District / State (Text)</label>
                                <input type="text" name="state" id="state" class="form-control" placeholder="e.g. Jhenaidah">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-building"></i> Save & Provision Office</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Master Organizational Map Grid -->
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sitemap"></i> Registered Government Offices</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Office Details</th>
                            <th>Geographic Area</th>
                            <th>Office Administrator</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($offices as $loc)
                            @php
                                $profile = $profiles[$loc->id] ?? null;
                                $status = $profile ? $profile->lifecycle_status : 'unconfigured';
                                $adminId = $profile ? $profile->office_admin_id : null;
                                $geoName = $profile && $profile->geoArea ? $profile->geoArea->en_name : 'Unmapped';
                                $geoType = $profile && $profile->geoArea ? ucfirst($profile->geoArea->geo_type) : 'N/A';
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $loc->name }}</strong><br>
                                    <small class="text-muted"><i class="fas fa-sitemap"></i> Parent: {{ $loc->parent->name ?? 'None' }}</small> <br>
                                    <small class="text-muted"><i class="fas fa-university"></i> Ministry: {{ $loc->company->name ?? 'Standalone' }}</small>
                                </td>
                                
                                <td style="vertical-align: middle;">
                                    <span class="text-primary" style="font-weight: bold;"><i class="fas fa-map-marker-alt"></i> {{ $geoName }}</span> <br>
                                    <small class="text-muted">Type: {{ $geoType }}</small>
                                </td>
                                
                                <!-- Assign Admin Inline Dropdown Form -->
                                <td style="vertical-align: middle;">
                                    <form action="{{ route('gov.org.provisioning.assign-admin') }}" method="POST" style="display: flex; gap: 5px;">
                                        @csrf
                                        <input type="hidden" name="location_id" value="{{ $loc->id }}">
                                        <select name="office_admin_id" onchange="this.form.submit()" class="form-control input-sm select2" style="max-width: 180px;">
                                            <option value="">-- Unassigned --</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ $adminId == $user->id ? 'selected' : '' }}>
                                                    {{ $user->present()->fullName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>

                                <td style="vertical-align: middle;">
                                    @if($status === 'operational')
                                        <span class="label label-success"><i class="fas fa-check-double"></i> Operational</span>
                                    @elseif($status === 'configured')
                                        <span class="label label-info"><i class="fas fa-sliders-h"></i> Configured</span>
                                    @elseif($status === 'provisioned')
                                        <span class="label label-warning"><i class="fas fa-building"></i> Provisioned</span>
                                    @else
                                        <span class="label label-default">{{ ucfirst($status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    // Initialize standard Select2 with scoped live AJAX search targeting our geo-search route
    $('#geoAreaSelector').select2({
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("gov.org.provisioning.geo-search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // Search query input
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: "Search and select Upazila or Union..."
    });
});
</script>
@endsection