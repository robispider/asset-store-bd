@extends('layouts/default')

@section('title', 'Government Office Registry')

@section('content')

{{-- Registry Custom Styling --}}
<style>
    .filter-bar {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .registry-table td { vertical-align: middle !important; }
    .status-badge-container { line-height: 1.4; }
</style>

<!-- TOP METRICS STATS BAR -->
<div class="row">
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-blue">
            <div class="inner">
                <h3>{{ $totalOfficesCount }}</h3>
                <p>Total Registered Offices</p>
            </div>
            <div class="icon"><i class="fas fa-building"></i></div>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3>{{ $operationalCount }}</h3>
                <p>Operational Offices</p>
            </div>
            <div class="icon"><i class="fas fa-check-double"></i></div>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>{{ $pendingCount }}</h3>
                <p>Configuration Pending</p>
            </div>
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-purple">
            <div class="inner">
                <h3>{{ $ministriesCount }}</h3>
                <p>Ministries Engaged</p>
            </div>
            <div class="icon"><i class="fas fa-university"></i></div>
        </div>
    </div>
</div>

<!-- FILTER TOOLBAR & PROVISION CTA BUTTON -->
<div class="row">
    <div class="col-md-12">
        <div class="filter-bar">
            <form action="{{ route('gov.org.provisioning.index') }}" method="GET" class="form-inline">
                
                <!-- Free Search -->
                <div class="form-group" style="margin-right: 15px;">
                    <label for="search" style="margin-right: 5px;"><i class="fas fa-search"></i> Search:</label>
                    <input type="text" name="search" id="search" class="form-control input-sm" placeholder="Office name or admin..." value="{{ request('search') }}">
                </div>

                <!-- Ministry Filter -->
                <div class="form-group" style="margin-right: 15px;">
                    <label for="ministry_id" style="margin-right: 5px;"><i class="fas fa-university"></i> Ministry:</label>
                    <select name="ministry_id" id="ministry_id" class="form-control input-sm select2" style="min-width: 180px;">
                        <option value="">-- All Ministries --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ request('ministry_id') == $company->id ? 'selected' : '' }}>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- District Filter -->
                <div class="form-group" style="margin-right: 15px;">
                    <label for="district_id" style="margin-right: 5px;"><i class="fas fa-map-marker-alt"></i> District:</label>
                    <select name="district_id" id="district_id" class="form-control input-sm select2" style="min-width: 180px;">
                        <option value="">-- All Districts --</option>
                        @foreach($districts as $dist)
                            <option value="{{ $dist->GeoAreaId }}" {{ request('district_id') == $dist->GeoAreaId ? 'selected' : '' }}>
                                {{ $dist->en_name }} ({{ $dist->bn_name }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="form-group" style="margin-right: 15px;">
                    <label for="status" style="margin-right: 5px;"><i class="fas fa-tasks"></i> Status:</label>
                    <select name="status" id="status" class="form-control input-sm">
                        <option value="">-- All Statuses --</option>
                        <option value="operational" {{ request('status') == 'operational' ? 'selected' : '' }}>Operational</option>
                        <option value="configured" {{ request('status') == 'configured' ? 'selected' : '' }}>Configured</option>
                        <option value="provisioned" {{ request('status') == 'provisioned' ? 'selected' : '' }}>Provisioned (Pending)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-sm btn-default"><i class="fas fa-filter"></i> Filter</button>
                <a href="{{ route('gov.org.provisioning.index') }}" class="btn btn-sm btn-link">Reset</a>

                <!-- PROVISION CALL TO ACTION BUTTON -->
                <a href="{{ route('gov.org.provisioning.create') }}" class="btn btn-sm btn-primary pull-right">
                    <i class="fas fa-plus"></i> Provision New Office
                </a>
            </form>
        </div>
    </div>
</div>

<!-- HIGH-DENSITY OFFICE REGISTRY GRID -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sitemap"></i> Registered Government Offices ({{ $offices->count() }})</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover registry-table">
                    <thead>
                        <tr>
                            <th>Office Building</th>
                            <th>Administrative Territory</th>
                            <th>Owning Ministry</th>
                            <th>Office Administrator</th>
                            <th>Readiness Status</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offices as $loc)
                            @php
                                $profile = $loc->profile ?? null;
                                $status = $profile ? $profile->lifecycle_status : 'unconfigured';
                                $geoName = $profile && $profile->geoArea ? $profile->geoArea->en_name : 'Unmapped';
                                $geoType = $profile && $profile->geoArea ? ucfirst($profile->geoArea->geo_type) : 'N/A';
                                $adminName = $profile && $profile->officeAdmin ? $profile->officeAdmin->present()->fullName : 'Unassigned';
                                
                                // Calculate role completeness checklist
                                $roles = \GovStore\Organization\Models\LocationRole::where('location_id', $loc->id)->first();
                                $hasPrimary = $roles && $roles->primary_approver_id;
                                $hasStorekeeper = $roles && $roles->storekeeper_id;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $loc->name }}</strong><br>
                                    <small class="text-muted"><i class="fas fa-sitemap"></i> Parent: {{ $loc->parent->name ?? 'Root Office' }}</small>
                                </td>
                                
                                <td>
                                    <span class="text-primary" style="font-weight: bold;"><i class="fas fa-map-marker-alt"></i> {{ $geoName }}</span><br>
                                    <small class="text-muted">Type: {{ $geoType }}</small>
                                </td>

                                <td>
                                    <i class="fas fa-university text-muted"></i> {{ $loc->company->name ?? 'Standalone Office' }}
                                </td>

                                <td>
                                    <i class="fas fa-user-tie text-muted"></i> {{ $adminName }}
                                </td>

                                <td>
                                    <div class="status-badge-container">
                                        @if($status === 'operational')
                                            <span class="label label-success"><i class="fas fa-check-double"></i> Operational</span>
                                        @elseif($status === 'configured')
                                            <span class="label label-info"><i class="fas fa-sliders-h"></i> Configured</span>
                                        @elseif($status === 'provisioned')
                                            <span class="label label-warning"><i class="fas fa-building"></i> Provisioned</span>
                                            <small class="text-muted" style="display:block; margin-top: 3px;">
                                                Needs: 
                                                <span class="{{ $hasPrimary ? 'text-success' : 'text-danger' }}">{{ $hasPrimary ? '✓' : '✗' }} Primary</span> &bull; 
                                                <span class="{{ $hasStorekeeper ? 'text-success' : 'text-danger' }}">{{ $hasStorekeeper ? '✓' : '✗' }} Storekeeper</span>
                                            </small>
                                        @else
                                            <span class="label label-default">{{ ucfirst($status) }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <a href="{{ route('gov.org.hub.show', $loc->id) }}" class="btn btn-xs btn-primary" title="Open Office Hub">
                                        <i class="fas fa-external-link-alt"></i> View Hub
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding: 40px;">
                                    <i class="fas fa-building fa-2x"></i>
                                    <p style="margin-top: 10px;">No government offices found matching your criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection