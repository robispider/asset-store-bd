@extends('layouts/default')

@section('title', __('office_membership::member.provisioning_registry_title'))

@section('content')
<div class="office-membership-theme">

<!-- TOP ROLLING ROLLOUT METRICS -->
<div class="row">
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-blue">
            <div class="inner">
                <h3>{{ $totalOfficesCount ?? 0 }}</h3>
                <p>{{ __('office_membership::member.provisioning_metric_total') }}</p>
            </div>
            <div class="icon"><i class="fas fa-building"></i></div>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3>{{ $operationalCount ?? 0 }}</h3>
                <p>{{ __('office_membership::member.provisioning_metric_operational') }}</p>
            </div>
            <div class="icon"><i class="fas fa-check-double"></i></div>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>{{ $pendingCount ?? 0 }}</h3>
                <p>{{ __('office_membership::member.provisioning_metric_pending') }}</p>
            </div>
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-purple">
            <div class="inner">
                <h3>{{ $ministriesCount ?? 0 }}</h3>
                <p>{{ __('office_membership::member.provisioning_metric_ministries') }}</p>
            </div>
            <div class="icon"><i class="fas fa-university"></i></div>
        </div>
    </div>
</div>

<!-- SEARCH, FILTERS & DUAL ACTION BUTTON PANEL -->
<div class="row">
    <div class="col-md-12">
        <div class="filter-bar">
            <form action="{{ route('gov.org.provisioning.index') }}" method="GET" class="form-inline">
                
                <!-- Search term -->
                <div class="form-group om-inline-00abe7ff">
                    <label for="search" class="om-inline-ae731390"><i class="fas fa-search"></i> {{ __('office_membership::member.provisioning_filter_search_label') }}</label>
                    <input type="text" name="search" id="search" class="form-control input-sm" placeholder="{{ __('office_membership::member.provisioning_filter_search_label') }} office name or admin..." value="{{ request('search') }}">
                </div>

                <!-- Ministry Filter -->
                <div class="form-group om-inline-00abe7ff">
                    <label for="ministry_id" class="om-inline-ae731390"><i class="fas fa-university"></i> {{ __('office_membership::member.provisioning_filter_ministry_label') }}</label>
                    <select name="ministry_id" id="ministry_id" class="form-control input-sm select2 om-inline-4c3ea102">
                        <option value="">{{ __('office_membership::member.provisioning_filter_all_ministries') }}</option>
                        @foreach($companies ?? [] as $company)
                            <option value="{{ $company->id }}" {{ request('ministry_id') == $company->id ? 'selected' : '' }}>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- District Filter -->
                <div class="form-group om-inline-00abe7ff">
                    <label for="district_id" class="om-inline-ae731390"><i class="fas fa-map-marker-alt"></i> {{ __('office_membership::member.provisioning_filter_district_label') }}</label>
                    <select name="district_id" id="district_id" class="form-control input-sm select2 om-inline-4c3ea102">
                        <option value="">{{ __('office_membership::member.provisioning_filter_all_districts') }}</option>
                        @foreach($districts ?? [] as $dist)
                            <option value="{{ $dist->GeoAreaId }}" {{ request('district_id') == $dist->GeoAreaId ? 'selected' : '' }}>
                                {{ $dist->en_name }} ({{ $dist->bn_name }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="form-group om-inline-00abe7ff">
                    <label for="status" class="om-inline-ae731390"><i class="fas fa-tasks"></i> {{ __('office_membership::member.provisioning_filter_status_label') }}</label>
                    <select name="status" id="status" class="form-control input-sm">
                        <option value="">{{ __('office_membership::member.provisioning_filter_all_statuses') }}</option>
                        <option value="operational" {{ request('status') == 'operational' ? 'selected' : '' }}>{{ __('office_membership::member.provisioning_filter_operational') }}</option>
                        <option value="configured" {{ request('status') == 'configured' ? 'selected' : '' }}>{{ __('office_membership::member.provisioning_filter_configured') }}</option>
                        <option value="provisioned" {{ request('status') == 'provisioned' ? 'selected' : '' }}>{{ __('office_membership::member.provisioning_filter_provisioned') }}</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-sm btn-default"><i class="fas fa-filter"></i> {{ __('office_membership::member.provisioning_filter_button') }}</button>
                <a href="{{ route('gov.org.provisioning.index') }}" class="btn btn-sm btn-link">{{ __('office_membership::member.provisioning_reset_button') }}</a>

                <!-- INTEGRATED ONBOARD & CREATE TRIGGERS -->
                <div class="pull-right">
                    <a href="{{ route('gov.org.provisioning.onboard') }}" class="btn btn-sm btn-default om-inline-ae731390">
                        <i class="fas fa-plug text-success"></i> {{ __('office_membership::member.provisioning_onboard_button') }}
                    </a>
                    <a href="{{ route('gov.org.provisioning.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> {{ __('office_membership::member.provisioning_create_button') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DATA GRID: OFFICES LIST -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sitemap"></i> {{ __('office_membership::member.provisioning_grid_title') }} ({{ $offices->count() }})</h3>
            </div>
            <div class="box-body table-responsive">
                <x-gs::table class="table table-striped table-hover registry-table">
                    <thead>
                        <tr>
                            <th>{{ __('office_membership::member.provisioning_grid_office') }}</th>
                            <th>{{ __('office_membership::member.provisioning_grid_territory') }}</th>
                            <th>{{ __('office_membership::member.provisioning_grid_ministry') }}</th>
                            <th>{{ __('office_membership::member.provisioning_grid_admin') }}</th>
                            <th>{{ __('office_membership::member.provisioning_grid_status') }}</th>
                            <th class="om-inline-aedab6f0">{{ __('office_membership::member.provisioning_grid_actions') }}</th>
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
                                
                                // Direct checklist evaluation
                                $roles = \GovStore\Organization\Models\LocationRole::where('location_id', $loc->id)->first();
                                $hasPrimary = $roles && $roles->primary_approver_id;
                                $hasStorekeeper = $roles && $roles->storekeeper_id;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $loc->name }}</strong><br>
                                    <small class="text-muted"><i class="fas fa-sitemap"></i> {{ __('office_membership::member.provisioning_grid_parent') }} {{ $loc->parent->name ?? __('office_membership::member.provisioning_grid_root_office') }}</small>
                                </td>
                                
                                <td>
                                    <span class="text-primary om-inline-3cdfe113"><i class="fas fa-map-marker-alt"></i> {{ $geoName }}</span><br>
                                    <small class="text-muted">{{ __('office_membership::member.provisioning_grid_type') }}: {{ $geoType }}</small>
                                </td>

                                <td>
                                    <i class="fas fa-university text-muted"></i> {{ $loc->company->name ?? __('office_membership::member.provisioning_grid_standalone') }}
                                </td>

                                <td>
                                    <i class="fas fa-user-tie text-muted"></i> {{ $adminName }}
                                </td>

                                <td>
                                    <div class="status-badge-container">
                                        @if($status === 'operational')
                                            <span class="label label-success"><i class="fas fa-check-double"></i> {{ __('office_membership::member.provisioning_status_operational') }}</span>
                                        @elseif($status === 'configured')
                                            <span class="label label-info"><i class="fas fa-sliders-h"></i> {{ __('office_membership::member.provisioning_status_configured') }}</span>
                                        @elseif($status === 'provisioned')
                                            <span class="label label-warning"><i class="fas fa-building"></i> {{ __('office_membership::member.provisioning_status_provisioned') }}</span>
                                            <small class="text-muted om-inline-1ac8bcc9">
                                                {{ __('office_membership::member.provisioning_status_needs') }} 
                                                <span class="{{ $hasPrimary ? 'text-success' : 'text-danger' }}">{{ $hasPrimary ? '✓' : '✗' }} {{ __('office_membership::member.provisioning_status_primary') }}</span> &bull; 
                                                <span class="{{ $hasStorekeeper ? 'text-success' : 'text-danger' }}">{{ $hasStorekeeper ? '✓' : '✗' }} {{ __('office_membership::member.provisioning_status_storekeeper') }}</span>
                                            </small>
                                        @else
                                            <span class="label label-default">{{ ucfirst($status) }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <a href="{{ route('gov.org.hub.show', $loc->id) }}" class="btn btn-xs btn-primary btn-block" title="{{ __('office_membership::member.provisioning_view_hub_title') }}">
                                        <i class="fas fa-external-link-alt"></i> {{ __('office_membership::member.provisioning_view_hub_button') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted om-inline-ea7b6b7b">
                                    <i class="fas fa-building fa-2x"></i>
                                    <p class="om-inline-3225db96">{{ __('office_membership::member.provisioning_no_offices') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-gs::table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
