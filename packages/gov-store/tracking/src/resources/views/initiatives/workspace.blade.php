@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_workspace') . $initiative->title)

@section('content')
<!-- Header Banner -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid gs-ws-header-box">
            <div class="box-body gs-ws-header-body">
                <h2 class="text-blue" style="margin-top: 0; font-weight: bold; letter-spacing: 0.5px;">
                    <i class="fa fa-university"></i> {{ strtoupper($initiative->title) }}
                </h2>
                <p class="lead text-muted gs-ws-header-lead">
                    {{ $initiative->purpose ?? __('govtracking::general.ui.extra_no_objective_described_for_this_programme') }}
                </p>
                <hr class="gs-ws-header-divider">
                <div class="row text-center">
                    <div class="col-sm-3 border-right">
                        <span class="description-text text-muted gs-ws-stat-label">{{ __('govtracking::general.ui.status') }}</span>
                        <h4 class="description-header gs-ws-stat-value">
                            @if($initiative->status == 'Active')
                                <span class="text-green"><i class="fa fa-circle"></i> {{ __('govtracking::general.ui.ready_for_operations') }}</span>
                            @elseif($initiative->status == 'Planning')
                                <span class="text-yellow"><i class="fa fa-wrench"></i> {{ __('govtracking::general.ui.setup_in_progress') }}</span>
                            @elseif($initiative->status == 'Closed')
                                <span class="text-blue"><i class="fa fa-check-circle"></i> {{ __('govtracking::general.ui.completed') }}</span>
                            @else
                                <span class="text-gray"><i class="fa fa-archive"></i> {{ __('govtracking::general.ui.archived') }}</span>
                            @endif
                        </h4>
                    </div>
                    <div class="col-sm-3 border-right">
                        <span class="description-text text-muted gs-ws-stat-label">{{ __('govtracking::general.ui.owning_dept_ministry') }}</span>
                        <h4 class="description-header gs-ws-stat-value">
                            {{ $initiative->ownerCompany->name ?? 'Unassigned' }}
                        </h4>
                    </div>
                    <div class="col-sm-3 border-right">
                        <span class="description-text text-muted gs-ws-stat-label">{{ __('govtracking::general.ui.funding_segment') }}</span>
                        <h4 class="description-header gs-ws-stat-value">
                            {{ $initiative->primary_funding }} {{ __('govtracking::general.ui.extra_budget') }}
                        </h4>
                    </div>
                    <div class="col-sm-3">
                        <span class="description-text text-muted gs-ws-stat-label">{{ __('govtracking::general.ui.umbrella_delivery') }}</span>
                        <h4 class="description-header gs-ws-stat-value">
                            {{ $health['percentage'] }}%
                            <small class="text-muted gs-ws-stat-sub">
                                {{ number_format($health['received']) }} / {{ number_format($health['planned']) }} {{ __('govtracking::general.ui.extra_items') }}
                            </small>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Toolbar -->
<div class="row">
    <div class="col-md-12" style="margin-bottom: 15px;">
        <div class="gs-ws-toolbar">
            <span class="text-muted gs-ws-toolbar-label">
                <i class="fa fa-bolt text-yellow"></i> {{ __('govtracking::general.ui.quick_actions') }}
            </span>
            <div class="btn-group-horizontal">
                <a href="{{ route('gov.tracking.initiatives.tracking-codes.create', $initiative->id) }}" class="btn btn-default btn-flat">
                    <i class="fa fa-plus text-green"></i> {{ __('govtracking::general.ui.new_tracking_code_task') }}
                </a>
                <a href="{{ route('gov.tracking.initiatives.report', $initiative->id) }}" class="btn btn-default btn-flat">
                    <i class="fa fa-bar-chart text-purple"></i> {{ __('govtracking::general.ui.full_progress_report') }}
                </a>
                <a href="{{ route('gov.tracking.initiatives.operation-unit.index', $initiative->id) }}" class="btn btn-default btn-flat">
                    <i class="fa fa-users text-blue"></i> {{ __('govtracking::general.ui.manage_operation_team') }}
                </a>
                <a href="{{ route('gov.tracking.initiatives.edit', $initiative->id) }}" class="btn btn-default btn-flat">
                    <i class="fa fa-cog text-gray"></i> {{ __('govtracking::general.ui.edit_general_properties') }}
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Workspace Layout -->
<div class="row">
    <!-- Left Column: Tasks and Analytics Snapshots -->
    <div class="col-md-8">

        <!-- 1. Current Execution Tasks -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;"><i class="fa fa-tasks"></i> {{ __('govtracking::general.ui.current_execution_tasks_tracking_codes') }}</h3>
            </div>
            <div class="box-body" style="padding: 0;">
                @if($trackingCodes->isEmpty())
                    <div class="text-center text-muted gs-ws-empty-tasks">
                        <i class="fa fa-info-circle style-span" style="font-size: 36px; margin-bottom: 15px;"></i>
                        <h4>{{ __('govtracking::general.ui.no_operational_tasks_defined_under_this_umbrella') }}</h4>
                        <p>{{ __('govtracking::general.ui.create_a_tracking_code_to_begin_registering_physical_receipts_and_monitoring_delivery_goals') }}</p>
                        <a href="{{ route('gov.tracking.initiatives.tracking-codes.create', $initiative->id) }}" class="btn btn-success btn-sm" style="margin-top: 10px;">
                            <i class="fa fa-plus"></i> {{ __('govtracking::general.ui.add_first_tracking_code') }}
                        </a>
                    </div>
                @else
                    <ul class="products-list product-list-in-box">
                        @foreach($trackingCodes as $code)
                            <li class="item gs-ws-task-item">
                                <div class="product-info" style="margin-left: 0;">
                                    <!-- Code Identifier -->
                                    <div class="gs-ws-task-identifier">
                                        <span class="gs-ws-task-code">
                                            {{ __('govtracking::general.ui.extra_code') }} {{ $code->tracking_code }} <span class="gs-ws-task-sep">|</span> {{ $code->task_title }}
                                        </span>
                                        <div>
                                            <span class="label bg-{{ $code->status == 'ACTIVE' ? 'green' : ($code->status == 'DRAFT' ? 'yellow' : 'gray') }}" style="font-size: 11px;">
                                                {{ $code->status }}
                                            </span>
                                            @if($code->order_pdf_path)
                                                <a href="{{ route('gov.tracking.tracking-codes.download', $code->id) }}" class="label label-info" style="margin-left: 5px; font-size: 11px;"><i class="fa fa-file-pdf-o"></i> {{ __('govtracking::general.ui.view_pdf') }}</a>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Segment Metadata -->
                                    <div class="gs-ws-task-meta">
                                        <i class="fa fa-calendar-o text-muted"></i> {{ __('govtracking::general.ui.extra_fiscal_year') }} <strong>{{ $code->fiscal_year }}</strong>
                                        <span class="gs-ws-task-meta-sep">•</span>
                                        <i class="fa fa-money text-muted"></i> {{ __('govtracking::general.ui.extra_budget') }} <strong>{{ $code->fundingType->name ?? 'N/A' }}</strong>
                                        <span class="gs-ws-task-meta-sep">•</span>

                                        @php
                                            $geoScope = $code->scopes->where('dimension', 'GEOGRAPHY')->first();
                                            $partScope = $code->scopes->where('dimension', 'PARTICIPANTS')->first();

                                            $geoDisplay = ($geoScope && $geoScope->target_type === 'GeoArea' && class_exists('GovStore\GeoAreas\Models\GeoArea'))
                                                ? \GovStore\GeoAreas\Models\GeoArea::find($geoScope->target_id)->en_name ?? __('govtracking::general.ui.extra_specific_region')
                                                : __('govtracking::general.ui.extra_nationwide');

                                            $partDisplay = ($partScope && $partScope->target_type === 'CrossTenant')
                                                ? '<span class="label label-warning" style="font-size:10px;"><i class="fa fa-exchange"></i> ' . e(__('govtracking::general.task.cross_ministry')) . '</span>'
                                                : ($partScope && $partScope->target_type === 'SpecificLocations'
                                                    ? '<span class="label label-primary" style="font-size:10px;"><i class="fa fa-map-marker"></i> ' . e(__('govtracking::general.task.specific_offices')) . '</span>'
                                                    : '<span class="label label-default" style="font-size:10px;">' . e(__('govtracking::general.task.internal')) . '</span>');
                                        @endphp
                                        {{ __('govtracking::general.ui.extra_scope') }} <strong>{{ $geoDisplay }}</strong> {!! $partDisplay !!}
                                    </div>

                                    <!-- Targets and Adaptive Progress -->
                                    <div style="margin-top: 10px;">
                                        @if($code->specificity_level === '1_BLANKET')
                                            <div class="gs-ws-strategy-blanket">
                                                <p>
                                                    <i class="fa fa-info-circle text-blue"></i> <strong>{{ __('govtracking::general.task.blanket_title') }}</strong>
                                                    {{ __('govtracking::general.task.blanket_description') }}
                                                </p>
                                            </div>
                                        @elseif($code->specificity_level === '2_CATEGORY')
                                            <div class="gs-ws-strategy-category">
                                                <h5>{{ __('govtracking::general.task.category_mode') }}</h5>
                                                <div class="row">
                                                    @foreach($code->targets as $target)
                                                        @php
                                                            $prog = $target->progress ?? ['percentage' => 0, 'is_exceeded' => false, 'received' => 0, 'planned' => $target->planned_qty];
                                                            $barColor = $prog['is_exceeded'] ? 'progress-bar-yellow' : ($prog['percentage'] >= 100 ? 'progress-bar-success' : 'progress-bar-aqua');
                                                            $textColor = $prog['is_exceeded'] ? 'text-yellow' : ($prog['percentage'] >= 100 ? 'text-green' : 'text-muted');
                                                            $categoryName = $target->category->name ?? __('govtracking::general.ui.extra_undefined_category');
                                                        @endphp

                                                        <div class="col-sm-6 gs-ws-target-row">
                                                            <div class="gs-ws-target-row-head">
                                                                <span>
                                                                    <i class="fa fa-cube text-muted"></i> <strong>{{ $categoryName }}</strong>
                                                                    @if($target->economic_code)
                                                                        <span class="text-muted gs-ws-target-econ">({{ __('govtracking::general.ui.extra_econ') }} {{ $target->economic_code }})</span>
                                                                    @endif
                                                                </span>
                                                                <span class="{{ $textColor }}" style="font-weight: bold;">{{ $prog['percentage'] }}%</span>
                                                            </div>
                                                            <div class="progress progress-xs gs-ws-target-bar">
                                                                <div class="progress-bar {{ $barColor }}" style="width: {{ $prog['percentage'] > 100 ? 100 : $prog['percentage'] }}%"></div>
                                                            </div>
                                                            <span class="text-muted text-sm gs-ws-target-foot">
                                                                {{ __('govtracking::general.ui.extra_received') }} <strong>{{ number_format($prog['received']) }}</strong> / {{ number_format($prog['planned']) }} {{ __('govtracking::general.ui.extra_units') }}
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @elseif($code->specificity_level === '3_MATRIX')
                                            <!-- Structured Office Level Breakdown -->
                                            <div class="panel-group gs-ws-matrix-panel" id="accordion-{{ $code->id }}">
                                                <div class="panel panel-default gs-ws-matrix-heading">
                                                    <div class="panel-heading gs-ws-matrix-heading-bar">
                                                        <h4 class="panel-title gs-ws-matrix-heading-title">
                                                            <a data-toggle="collapse" data-parent="#accordion-{{ $code->id }}" href="#collapse-{{ $code->id }}" class="gs-ws-matrix-heading-link">
                                                                <span><i class="fa fa-map-marker text-purple"></i> {{ __('govtracking::general.ui.view_segmented_delivery_progress_per_office') }}</span>
                                                                <i class="fa fa-chevron-down"></i>
                                                            </a>
                                                        </h4>
                                                    </div>
                                                    <div id="collapse-{{ $code->id }}" class="panel-collapse collapse">
                                                        <div class="panel-body gs-ws-matrix-body">
                                                            @forelse($code->matrixProgress ?? [] as $locationId => $data)
                                                                <div class="gs-ws-matrix-office">
                                                                    <h5><i class="fa fa-building text-muted"></i> {{ $data['location_name'] }}</h5>
                                                                    <div class="row">
                                                                        @foreach($data['items'] as $item)
                                                                            @php
                                                                                $barColor = $item['is_exceeded'] ? 'progress-bar-yellow' : ($item['percentage'] >= 100 ? 'progress-bar-success' : 'progress-bar-primary');
                                                                                $textColor = $item['is_exceeded'] ? 'text-yellow' : ($item['percentage'] >= 100 ? 'text-green' : 'text-muted');
                                                                            @endphp
                                                                            <div class="col-sm-6 gs-ws-matrix-item">
                                                                                <div class="gs-ws-matrix-item-head">
                                                                                    <strong>{{ $item['category_name'] }}</strong>
                                                                                    <span class="{{ $textColor }}">{{ $item['percentage'] }}%</span>
                                                                                </div>
                                                                                <div class="progress progress-xs gs-ws-matrix-item-bar">
                                                                                    <div class="progress-bar {{ $barColor }}" style="width: {{ $item['percentage'] > 100 ? 100 : $item['percentage'] }}%"></div>
                                                                                </div>
                                                                                <span class="text-muted gs-ws-matrix-item-foot">
                                                                                    {{ __('govtracking::general.ui.extra_received') }} {{ $item['received'] }} / {{ $item['allocated'] }}
                                                                                </span>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <p class="text-center text-muted" style="margin-bottom: 0;">{{ __('govtracking::general.ui.no_delivery_cells_configured_within_this_matrix_scope') }}</p>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Actions & Transitions -->
                                    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            @if($code->status === 'ACTIVE')
                                                <small class="text-muted"><i class="fa fa-lock"></i> {{ __('govtracking::general.ui.ledger_status_locked_storekeeper_execution_authorized') }}</small>
                                            @elseif($code->status === 'DRAFT')
                                                <small class="text-muted"><i class="fa fa-info-circle"></i> {{ __('govtracking::general.ui.work_draft_edit_properties_prior_to_publication') }}</small>
                                            @endif
                                        </div>
                                        <div>
                                            @if($code->status === 'DRAFT')
                                                <a href="{{ route('gov.tracking.initiatives.tracking-codes.edit', [$initiative->id, $code->id]) }}" class="btn btn-xs btn-warning" style="margin-right: 5px;"><i class="fa fa-pencil"></i> {{ __('govtracking::general.ui.edit_properties') }}</a>

                                                <form action="{{ route('gov.tracking.initiatives.tracking-codes.activate', [$initiative->id, $code->id]) }}" method="POST" style="display:inline-block; margin-right: 5px;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-success" data-tracking-confirm="{{ __('govtracking::general.ui.confirm_activate') }}">
                                                        <i class="fa fa-play"></i> {{ __('govtracking::general.ui.activate_lock') }}
                                                    </button>
                                                </form>

                                                <form action="{{ route('gov.tracking.initiatives.tracking-codes.destroy', [$initiative->id, $code->id]) }}" method="POST" style="display:inline-block;">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-danger" data-tracking-confirm="{{ __('govtracking::general.ui.confirm_task') }}">
                                                        <i class="fa fa-trash"></i> {{ __('govtracking::general.ui.delete') }}
                                                    </button>
                                                </form>
                                            @elseif($code->status === 'ACTIVE')
                                                <form action="{{ route('gov.tracking.initiatives.tracking-codes.archive', [$initiative->id, $code->id]) }}" method="POST" style="display:inline-block;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-default" data-tracking-confirm="{{ __('govtracking::general.ui.confirm_archive') }}">
                                                        <i class="fa fa-archive"></i> {{ __('govtracking::general.ui.archive_task') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted text-sm" style="font-size: 11px;"><i class="fa fa-archive"></i> {{ __('govtracking::general.ui.archival_view_historic_logs_saved_for_audit_logs') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <!-- 2. Programme Snapshot (The Pre-Aggregated OLAP Cube Data) -->
        <div class="box box-solid gs-ws-snapshot-box">
            <div class="box-header with-border gs-ws-snapshot-head">
                <h3 class="box-title gs-ws-snapshot-title"><i class="fa fa-bar-chart"></i> {{ __('govtracking::general.ui.programme_snapshot_executive_fact_aggregates') }}</h3>
            </div>
            <div class="box-body gs-ws-snapshot-body">
                <div class="row">
                    <!-- Deliverables and Fiscal Aggregates -->
                    <div class="col-sm-4 gs-ws-snapshot-col">
                        <h5><i class="fa fa-truck text-muted"></i> {{ __('govtracking::general.ui.deliveries_fiscal_value') }}</h5>
                        <ul class="list-unstyled gs-ws-snapshot-list">
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.total_received') }}</span>
                                <strong>{{ number_format($snapshot['total_received_qty']) }} {{ __('govtracking::general.ui.extra_units') }}</strong>
                            </li>
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.procurement_value') }}</span>
                                <strong>{{ number_format($snapshot['total_cost'], 2) }} BDT</strong>
                            </li>
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.shipments_grns') }}</span>
                                <strong>{{ $snapshot['total_shipments'] }} {{ __('govtracking::general.ui.extra_documents') }}</strong>
                            </li>
                        </ul>
                    </div>

                    <!-- Geographic Operational Range -->
                    <div class="col-sm-4 gs-ws-snapshot-col">
                        <h5><i class="fa fa-globe text-muted"></i> {{ __('govtracking::general.ui.geographic_reach') }}</h5>
                        <ul class="list-unstyled gs-ws-snapshot-list">
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.receiving_offices') }}</span>
                                <strong>{{ $snapshot['distinct_locations'] }} {{ __('govtracking::general.ui.extra_locations') }}</strong>
                            </li>
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.districts_covered') }}</span>
                                <strong>{{ $snapshot['distinct_geo_areas'] }} {{ __('govtracking::general.ui.extra_areas') }}</strong>
                            </li>
                        </ul>
                    </div>

                    <!-- Procurement Diversity -->
                    <div class="col-sm-4 gs-ws-snapshot-col">
                        <h5><i class="fa fa-tags text-muted"></i> {{ __('govtracking::general.ui.procurement_diversity') }}</h5>
                        <ul class="list-unstyled gs-ws-snapshot-list">
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.item_categories') }}</span>
                                <strong>{{ $snapshot['distinct_categories'] }} {{ __('govtracking::general.ui.extra_types') }}</strong>
                            </li>
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.brands_makers') }}</span>
                                <strong>{{ $snapshot['distinct_manufacturers'] }} {{ __('govtracking::general.ui.extra_brands') }}</strong>
                            </li>
                            <li>
                                <span class="text-muted">{{ __('govtracking::general.ui.contracted_vendors') }}</span>
                                <strong>{{ $snapshot['distinct_suppliers'] }} {{ __('govtracking::general.ui.extra_suppliers') }}</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Column: Governance, Rules, Timeline and Exceptions -->
    <div class="col-md-4">

        <!-- 1. Operational Readiness Checker & Governance -->
        @php
            $headCount = $initiative->operationUnits()->where('designation', 'HEAD')->count();
            $officerCount = $initiative->operationUnits()->where('designation', 'OFFICER')->count();
            $isReady = ($headCount === 1 && $officerCount >= 1);
        @endphp

        @if($initiative->status === 'Planning' && !$isReady)
            <div class="box box-solid bg-red-gradient gs-ws-readiness-box">
                <div class="box-header">
                    <h3 class="box-title" style="font-weight: bold;"><i class="fa fa-shield"></i> {{ __('govtracking::general.ui.readiness_requirements') }}</h3>
                </div>
                <div class="box-body">
                    <p class="gs-ws-readiness-title">{{ __('govtracking::general.ui.operation_unit_assignments_pending') }}</p>
                    <p class="gs-ws-readiness-copy">
                        {{ __('govtracking::general.ui.this_program_cannot_move_to_an_active_operational_status_until_a_valid_managerial_context_is_defined_resolve_these_targets') }}
                    </p>
                    <ul class="gs-ws-readiness-list">
                        @if($headCount === 0)
                            <li><i class="fa fa-times-circle"></i> {{ __('govtracking::general.ui.assign_an_operation_head_1_required') }}</li>
                        @endif
                        @if($officerCount === 0)
                            <li><i class="fa fa-times-circle"></i> {{ __('govtracking::general.ui.assign_at_least_one_operation_officer') }}</li>
                        @endif
                    </ul>
                    <a href="{{ route('gov.tracking.initiatives.operation-unit.index', $initiative->id) }}" class="btn btn-default btn-block btn-sm gs-ws-readiness-cta">
                        {{ __('govtracking::general.ui.configure_operation_team') }}
                    </a>
                </div>
            </div>
        @else
            <!-- Governance Policy Summary Panel -->
            <div class="box box-solid gs-ws-gov-box">
                <div class="box-header with-border gs-ws-gov-head">
                    <h3 class="box-title gs-ws-gov-title"><i class="fa fa-shield"></i> {{ __('govtracking::general.ui.governance_rules') }}</h3>
                    <a href="{{ route('gov.tracking.initiatives.operation-unit.index', $initiative->id) }}" class="pull-right text-muted" title="{{ __('govtracking::general.ui.extra_manage_team') }}"><i class="fa fa-users"></i></a>
                </div>
                <div class="box-body gs-ws-gov-body">
                    <ul class="list-unstyled gs-ws-gov-list">
                        <li>
                            <span class="text-muted text-sm gs-ws-gov-label">{{ __('govtracking::general.task.head') }}</span>
                            <strong>
                                @if($headCount === 1)
                                    {{ $initiative->operationUnits->where('designation', 'HEAD')->first()->user->first_name ?? 'N/A' }}
                                    {{ $initiative->operationUnits->where('designation', 'HEAD')->first()->user->last_name ?? '' }}
                                @else
                                    <span class="text-red">{{ __('govtracking::general.ui.unassigned') }}</span>
                                @endif
                            </strong>
                        </li>
                        <li>
                            <span class="text-muted text-sm gs-ws-gov-label">{{ __('govtracking::general.ui.verification_documents_required') }}</span>
                            <span class="label label-{{ $initiative->require_documents ? 'success' : 'default' }}">{{ __('govtracking::general.task.' . ($initiative->require_documents ? 'yes' : 'no')) }}</span>
                        </li>
                        <li>
                            <span class="text-muted text-sm gs-ws-gov-label">{{ __('govtracking::general.ui.target_overshoot_rules') }}</span>
                            <span class="label label-warning">{{ __('govtracking::general.ui.allocation_advisory') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        @endif

        <!-- 2. Recent Operational Activity Timeline -->
        <div class="box box-solid gs-ws-gov-box">
            <div class="box-header with-border gs-ws-gov-head">
                <h3 class="box-title gs-ws-gov-title"><i class="fa fa-clock-o"></i> {{ __('govtracking::general.ui.operational_activity_log') }}</h3>
            </div>
            <div class="box-body gs-ws-timeline-body">
                <ul class="timeline timeline-inverse" style="margin-bottom: 0;">
                    @forelse($recentActivity as $event)
                        <li>
                            @php
                                $icon = 'fa-exchange bg-aqua';
                                if($event->event_type === 'OVERSHOOT_OVERRIDE_LOGGED') {
                                    $icon = 'fa-warning bg-yellow';
                                }
                            @endphp

                            <i class="fa {{ $icon }}"></i>
                            <div class="timeline-item border-0 gs-ws-timeline-item">
                                <span class="time gs-ws-timeline-time"><i class="fa fa-clock-o"></i> {{ $event->occurred_at->diffForHumans() }}</span>
                                <h3 class="timeline-header no-border gs-ws-timeline-header">
                                    <strong>{{ str_replace('_', ' ', $event->event_type) }}</strong>
                                </h3>
                                <div class="timeline-body gs-ws-timeline-copy">
                                    {{ $event->description }}
                                    @if($event->actor)
                                        <br><small class="text-muted">— by {{ $event->actor->first_name }} {{ $event->actor->last_name }}</small>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-center text-muted gs-ws-timeline-empty">{{ __('govtracking::general.ui.no_events_logged_against_this_scope_yet') }}</li>
                    @endforelse

                    <li>
                        <i class="fa fa-flag bg-blue"></i>
                        <div class="timeline-item border-0 gs-ws-timeline-item">
                            <span class="time gs-ws-timeline-time"><i class="fa fa-clock-o"></i> {{ $initiative->created_at->format('M d, Y') }}</span>
                            <h3 class="timeline-header no-border gs-ws-timeline-header">{{ __('govtracking::general.ui.project_umbrella_launched') }}</h3>
                        </div>
                    </li>
                    <li><i class="fa fa-clock-o bg-gray"></i></li>
                </ul>
            </div>
        </div>

    </div>
</div>
@stop
