@extends('layouts/default')
@section('title', __('govtracking::general.ui.programme_operations_portfolio'))

@section('content')
<!-- Top Portfolio Header & Summary -->
<div class="row">
    <div class="col-md-12">
        <div class="portfolio-header">
            <h2 class="portfolio-title"><i class="fa fa-briefcase text-blue"></i> {{ __('govtracking::general.ui.programme_operations_portfolio') }}</h2>
            <p class="text-muted" style="font-size: 15px;">{{ __('govtracking::general.ui.manage_development_projects_revenue_programmes_tracking_codes_and_operational_compliance') }}</p>

            <div class="summary-strip">
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Active')->count() }}</span>
                    <span class="label">{{ __('govtracking::general.ui.operational') }}</span>
                </div>
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Planning')->count() }}</span>
                    <span class="label">{{ __('govtracking::general.ui.in_setup') }}</span>
                </div>
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Closed')->count() }}</span>
                    <span class="label">{{ __('govtracking::general.ui.closed_ops') }}</span>
                </div>
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Archived')->count() }}</span>
                    <span class="label">{{ __('govtracking::general.ui.historical') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Global Quick Actions Toolbar -->
<div class="row margin-bottom-15">
    <div class="col-md-12">
        <a href="{{ route('gov.tracking.initiatives.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('govtracking::general.ui.launch_new_initiative') }}</a>
    </div>
</div>

<!-- ======================================================================= -->
<!-- 🟢 OPERATIONAL (Active) -->
<!-- ======================================================================= -->
@php $activeInitiatives = $initiatives->where('status', 'Active'); @endphp
@if($activeInitiatives->count() > 0)
    <div class="section-divider">
        <span class="section-title"><i class="fa fa-circle text-green"></i> Ready for Operations ({{ $activeInitiatives->count() }})</span>
    </div>

    <div class="row">
        @foreach($activeInitiatives as $init)
            <div class="col-md-4 col-sm-6">
                <div class="initiative-card">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title">🏫 {{ $init->title }}</h3>
                        <p class="initiative-card-purpose">
                            {{ \Illuminate\Support\Str::limit($init->purpose ?? __('govtracking::general.ui.extra_no_purpose_defined'), 110) }}
                        </p>

                        <ul class="initiative-meta">
                            <li><span>{{ __('govtracking::general.ui.status') }}</span> <strong><span class="text-green">🟢 Ready for Operations</span></strong></li>
                            <li><span>{{ __('govtracking::general.ui.funding') }}</span> <strong class="text-muted">{{ $init->primary_funding }} {{ __('govtracking::general.ui.extra_budget') }}</strong></li>
                            <li><span>{{ __('govtracking::general.ui.owner') }}</span> <strong class="text-muted">{{ $init->ownerCompany->name ?? __('govtracking::general.ui.extra_unknown') }}</strong></li>
                            <li><span>{{ __('govtracking::general.ui.trackers') }}</span> <strong class="text-muted">{{ $init->tracking_codes_count }} Tracking Codes</strong></li>
                            <li><span>{{ __('govtracking::general.ui.updated') }}</span> <strong class="text-muted">{{ $init->updated_at->diffForHumans() }}</strong></li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-success btn-sm">{{ __('govtracking::general.ui.open_workspace') }}</a>
                        <a href="{{ route('gov.tracking.initiatives.edit', $init->id) }}" class="btn btn-default btn-sm"><i class="fa fa-cog"></i> {{ __('govtracking::general.ui.properties') }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- ======================================================================= -->
<!-- 🟡 SETUP IN PROGRESS (Planning) -->
<!-- ======================================================================= -->
@php $planningInitiatives = $initiatives->where('status', 'Planning'); @endphp
@if($planningInitiatives->count() > 0)
    <div class="section-divider">
        <span class="section-title"><i class="fa fa-circle text-yellow"></i> Setup in Progress ({{ $planningInitiatives->count() }})</span>
    </div>

    <div class="row">
        @foreach($planningInitiatives as $init)
            <div class="col-md-4 col-sm-6">
                <div class="initiative-card initiative-card--planning">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title">🚧 {{ $init->title }}</h3>
                        <p class="initiative-card-purpose">
                            {{ \Illuminate\Support\Str::limit($init->purpose ?? __('govtracking::general.ui.extra_project_under_initial_drafting_and_configuration'), 110) }}
                        </p>

                        <ul class="initiative-meta">
                            <li><span>{{ __('govtracking::general.ui.status') }}</span> <strong><span class="text-yellow">🟡 Setup in Progress</span></strong></li>
                            <li><span>{{ __('govtracking::general.ui.owner') }}</span> <strong class="text-muted">{{ $init->ownerCompany->name ?? __('govtracking::general.ui.extra_unknown') }}</strong></li>

                            <!-- Dynamic Operations Readiness Check -->
                            <li>
                                <span>{{ __('govtracking::general.ui.alert') }}</span>
                                @if($init->operation_units_count > 0)
                                    <strong class="text-muted">{{ __('govtracking::general.ui.drafting_planning_goals') }}</strong>
                                @else
                                    <strong class="text-red"><i class="fa fa-warning"></i> {{ __('govtracking::general.ui.needs_operation_team_assigned') }}</strong>
                                @endif
                            </li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-warning btn-sm">{{ __('govtracking::general.ui.continue_setup') }}</a>
                        <a href="{{ route('gov.tracking.initiatives.edit', $init->id) }}" class="btn btn-default btn-sm"><i class="fa fa-cog"></i></a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- ======================================================================= -->
<!-- 🔵 OPERATIONS COMPLETE (Closed) -->
<!-- ======================================================================= -->
@php $closedInitiatives = $initiatives->where('status', 'Closed'); @endphp
@if($closedInitiatives->count() > 0)
    <div class="section-divider">
        <span class="section-title"><i class="fa fa-circle text-blue"></i> Operations {{ __('govtracking::general.ui.extra_complete') }} ({{ $closedInitiatives->count() }})</span>
    </div>

    <div class="row">
        @foreach($closedInitiatives as $init)
            <div class="col-md-4 col-sm-6">
                <div class="initiative-card initiative-card--closed">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title text-muted">🏁 {{ $init->title }}</h3>
                        <ul class="initiative-meta initiative-meta--flush">
                            <li><span>{{ __('govtracking::general.ui.status') }}</span> <strong><span class="text-blue">🔵 Operations {{ __('govtracking::general.ui.extra_complete') }}</span></strong></li>
                            <li><span>{{ __('govtracking::general.ui.trackers') }}</span> <strong class="text-muted">{{ $init->tracking_codes_count }} Executed Tasks</strong></li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-info btn-sm">{{ __('govtracking::general.ui.view_analytics') }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- ======================================================================= -->
<!-- ⚫ HISTORICAL RECORDS (Archived) -->
<!-- ======================================================================= -->
@php $archivedInitiatives = $initiatives->where('status', 'Archived'); @endphp
@if($archivedInitiatives->count() > 0)
    <div class="section-divider">
        <span class="section-title"><i class="fa fa-circle text-gray"></i> Historical Records ({{ $archivedInitiatives->count() }})</span>
    </div>

    <div class="row">
        @foreach($archivedInitiatives as $init)
            <div class="col-md-4 col-sm-6">
                <div class="initiative-card initiative-card--archived">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title text-muted">📁 {{ $init->title }}</h3>
                        <ul class="initiative-meta initiative-meta--flush">
                            <li><span>{{ __('govtracking::general.ui.status') }}</span> <strong><span class="text-muted">⚫ Historical Record</span></strong></li>
                            <li><span>{{ __('govtracking::general.ui.closed_on') }}</span> <strong class="text-muted">{{ $init->updated_at->format('M d, Y') }}</strong></li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-default btn-sm">{{ __('govtracking::general.ui.view_archive') }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- ======================================================================= -->
<!-- EMPTY STATE (No Initiatives Exist) -->
<!-- ======================================================================= -->
@if($initiatives->count() === 0)
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <x-gs::empty-state icon="fa-briefcase" :title="__('govtracking::general.ui.extra_no_initiatives_yet')">
                {{ __('govtracking::general.ui.programme_tracking_helps_you') }}<br>
                <i class="fa fa-check text-green"></i> {{ __('govtracking::general.ui.organize_projects_and_revenue_budgets') }}<br>
                <i class="fa fa-check text-green"></i> {{ __('govtracking::general.ui.create_operational_tracking_codes') }}<br>
                <i class="fa fa-check text-green"></i> {{ __('govtracking::general.ui.monitor_physical_deliveries') }}<br>
                <i class="fa fa-check text-green"></i> {{ __('govtracking::general.ui.produce_executive_fiscal_reports') }}
                <x-slot:actions>
                    <a href="{{ route('gov.tracking.initiatives.create') }}" class="btn btn-primary btn-lg">{{ __('govtracking::general.ui.launch_first_initiative') }}</a>
                </x-slot:actions>
            </x-gs::empty-state>
        </div>
    </div>
@endif

@stop