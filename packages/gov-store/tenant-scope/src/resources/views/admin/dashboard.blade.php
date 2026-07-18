@extends('layouts/default')

@section('title', __('tenantops::ops.dashboard_title'))

@section('content')
<div class="row">
    <!-- Stat Cards -->
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>{{ $stats['total_mappings'] }}</h3>
                <p>{{ __('tenantops::ops.stat_total_mappings') }}</p>
            </div>
            <div class="icon"><i class="fas fa-link"></i></div>
            <a href="{{ route('gov.scope.mappings') }}" class="small-box-footer">{{ __('tenantops::ops.footer_view_grid') }} <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-purple">
            <div class="inner">
                <h3>{{ $stats['company_scopes'] }}</h3>
                <p>{{ __('tenantops::ops.stat_ministry_scoped') }}</p>
            </div>
            <div class="icon"><i class="fas fa-university"></i></div>
            <a href="{{ route('gov.scope.mappings', ['scope_type' => 'company']) }}" class="small-box-footer">{{ __('tenantops::ops.footer_filter_ministry') }} <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-blue">
            <div class="inner">
                <h3>{{ $stats['location_scopes'] }}</h3>
                <p>{{ __('tenantops::ops.stat_office_scoped') }}</p>
            </div>
            <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
            <a href="{{ route('gov.scope.mappings', ['scope_type' => 'location']) }}" class="small-box-footer">{{ __('tenantops::ops.footer_filter_office') }} <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3>{{ $stats['active_configs'] }}</h3>
                <p>{{ __('tenantops::ops.stat_active_policies') }}</p>
            </div>
            <div class="icon"><i class="fas fa-sliders-h"></i></div>
            <a href="{{ route('gov.scope.config') }}" class="small-box-footer">{{ __('tenantops::ops.footer_configure_policies') }} <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions & Help -->
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-bolt"></i> {{ __('tenantops::ops.quick_actions_title') }}</h3>
            </div>
            <div class="box-body" style="padding: 20px;">
                <div class="row">
                    <div class="col-xs-6">
                        <a href="{{ route('gov.scope.mappings') }}" class="btn btn-primary btn-block btn-lg" style="margin-bottom: 10px;">
                            <i class="fas fa-search-plus"></i> {{ __('tenantops::ops.action_scoping_explorer') }}
                        </a>
                    </div>
                    <div class="col-xs-6">
                        <a href="{{ route('gov.scope.config') }}" class="btn btn-default btn-block btn-lg" style="margin-bottom: 10px;">
                            <i class="fas fa-sliders-h"></i> {{ __('tenantops::ops.action_policy_configurator') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Scoping Activity -->
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-history"></i> {{ __('tenantops::ops.recent_actions_title') }}</h3>
            </div>
            <div class="box-body table-responsive" style="padding: 0;">
                <table class="table table-striped" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>{{ __('tenantops::ops.table_item_name') }}</th>
                            <th>{{ __('tenantops::ops.table_scoped_target') }}</th>
                            <th>{{ __('tenantops::ops.table_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentMappings as $map)
                            @php
                                $refName = 'Unknown Item';
                                if ($map->reference_type === 'category') $refName = \App\Models\Category::find($map->reference_id)?->name ?? 'Deleted Category';
                                if ($map->reference_type === 'model') $refName = \App\Models\AssetModel::find($map->reference_id)?->name ?? 'Deleted Model';
                                if ($map->reference_type === 'manufacturer') $refName = \App\Models\Manufacturer::find($map->reference_id)?->name ?? 'Deleted Manufacturer';
                                if ($map->reference_type === 'supplier') $refName = \App\Models\Supplier::find($map->reference_id)?->name ?? 'Deleted Supplier';

                                $scopeName = 'Unmapped';
                                if ($map->scope_type === 'company') $scopeName = \App\Models\Company::find($map->scope_id)?->name ?? 'Deleted Ministry';
                                if ($map->scope_type === 'location') $scopeName = \App\Models\Location::find($map->scope_id)?->name ?? 'Deleted Location';
                            @endphp
                            <tr>
                                <td><strong>{{ $refName }}</strong><br><small class="text-muted">{{ ucfirst($map->reference_type) }}</small></td>
                                <td><span class="label {{ $map->scope_type === 'company' ? 'bg-purple' : 'bg-blue' }}">{{ $scopeName }}</span></td>
                               <td>{{ $map->created_at ? $map->created_at->diffForHumans() : 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted" style="padding: 20px;">{{ __('tenantops::ops.empty_no_actions') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection