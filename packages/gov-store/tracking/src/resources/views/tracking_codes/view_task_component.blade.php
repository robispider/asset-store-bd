@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_task_component') . $trackingCode->tracking_code)

@section('content')
<!-- Print Control Row -->
<div class="row hidden-print gs-tracking-print-toolbar" style="margin-bottom: 15px;">
    <div class="col-md-12 text-right">
        <button class="btn btn-primary btn-lg" data-tracking-print style="font-weight: bold; padding: 12px 30px;">
            <i class="fa fa-print"></i> {{ __('govtracking::general.task.task_sheet') }}
        </button>
    </div>
</div>

<x-gs::document :title="$trackingCode->task_title" :doc-no="$trackingCode->tracking_code" :date="$trackingCode->created_at->format('d F Y')">
    <x-slot:office>
        <div class="gs-tracking-doc-ministry">তথ্য ও যোগাযোগ প্রযুক্তি অধিদপ্তর / Department of ICT, তথ্য ও যোগাযোগ প্রযুক্তি বিভাগ</div>
        <div class="gs-tracking-doc-initiative">[ {{ $initiative->title }} ]</div>
    </x-slot:office>

    <div class="row">
        <!-- LEFT COLUMN: Task Info, Scope, & Allocation Schedules -->
        <div class="col-md-8">

            <!-- Task / Component Information -->
            <x-gs::box :title="__('govtracking::general.ui.extra_task_component_information')" icon="fa fa-info-circle">
                <!-- Full-Width Task Title Row -->
                <div class="gs-tracking-doc-field gs-tracking-doc-field--bordered">
                    <span class="gs-tracking-doc-label">{{ __('govtracking::general.task.task_title') }}</span>
                    <span class="gs-tracking-doc-value--lg">{{ $trackingCode->task_title }}</span>
                </div>

                <!-- Dual Sub-Columns Grid -->
                <div class="row">
                    <!-- Left Sub-Column (Basic Identifiers) -->
                    <div class="col-sm-6">
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __('govtracking::general.task.official_code') }}</span>
                            <b class="gs-tracking-doc-value">{{ $trackingCode->tracking_code }}</b>
                        </div>
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __('govtracking::general.ui.current_status') }}</span>
                            @if($trackingCode->status === 'ACTIVE')
                                <span class="text-success" style="font-weight: bold; font-size: 14px;"><i class="fa fa-circle"></i> ACTIVE</span>
                            @else
                                <span class="text-muted" style="font-weight: bold; font-size: 14px;"><i class="fa fa-circle-o"></i> {{ $trackingCode->status }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="gs-tracking-doc-label">{{ __('govtracking::general.ui.created_date') }}</span>
                            <span class="gs-tracking-doc-value">{{ $trackingCode->created_at->format('d F Y') }}</span>
                        </div>
                    </div>

                    <!-- Right Sub-Column (Accounting & Budget Parameters) -->
                    <div class="col-sm-6 gs-tracking-doc-col-divider">
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __('govtracking::general.task.fiscal_period') }}</span>
                            <b class="gs-tracking-doc-value">FY {{ $trackingCode->fiscal_year }}</b>
                        </div>
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __('govtracking::general.task.budget_sector') }}</span>
                            <span class="gs-tracking-doc-value">{{ $initiative->primary_funding }} {{ __('govtracking::general.ui.extra_sector_budget') }}</span>
                        </div>
                        <div>
                            <span class="gs-tracking-doc-label">{{ __('govtracking::general.ui.funding_classification_segment') }}</span>
                            <b class="gs-tracking-doc-value">{{ $trackingCode->fundingType->name ?? 'N/A' }}</b>
                        </div>
                    </div>
                </div>
            </x-gs::box>

            <!-- Authorized Scope (Two-Sub-Column Layout) -->
            <x-gs::box :title="__('govtracking::general.ui.extra_authorized_scope')" icon="fa fa-map-marker">
                <div class="row">
                    <!-- Left Sub-Column: Geographical Coverage -->
                    @php
                        $geoScope = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
                        $partScope = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();
                    @endphp
                    <div class="col-sm-6">
                        <span class="gs-tracking-doc-label">{{ __('govtracking::general.task.geography') }}</span>
                        @if($geoScope && $geoScope->target_type === 'GeoArea' && class_exists('GovStore\GeoAreas\Models\GeoArea'))
                            @php
                                $geoArea = \GovStore\GeoAreas\Models\GeoArea::find($geoScope->target_id);
                            @endphp
                            <b class="gs-tracking-doc-value">{{ $geoArea->en_name ?? 'Specific District' }}</b>
                            <span class="label label-warning" style="font-size: 10px; margin-left: 5px;">{{ $geoArea->geo_type ?? 'Region' }}</span>
                        @else
                            <b class="gs-tracking-doc-value">{{ __('govtracking::general.task.nationwide') }}</b>
                            <span class="label label-default" style="font-size: 10px; margin-left: 5px;">{{ __('govtracking::general.task.bangladesh') }}</span>
                        @endif
                    </div>

                    <!-- Right Sub-Column: Participating Organizations -->
                    <div class="col-sm-6 gs-tracking-doc-col-divider">
                        <span class="gs-tracking-doc-label">{{ __('govtracking::general.task.participating_orgs') }}</span>
                        @if($partScope && $partScope->target_type === 'CrossTenant')
                            <b class="gs-tracking-doc-value">{{ __('govtracking::general.task.scope_cross') }}</b>
                            <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0; line-height: 1.4;">
                                {{ __('govtracking::general.task.any_office') }}
                            </p>
                        @else
                            <b class="gs-tracking-doc-value">{{ __('govtracking::general.task.scope_internal') }}</b>
                            <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0; line-height: 1.4;">
                                {{ __('govtracking::general.task.restricted_office') }}
                            </p>
                        @endif
                    </div>
                </div>
            </x-gs::box>

            <!-- Approved Allocation Schedule -->
            <x-gs::box :title="__('govtracking::general.ui.extra_approved_allocation_schedule')" icon="fa fa-table">
                @if($trackingCode->specificity_level === '1_BLANKET')
                    <div class="gs-tracking-doc-empty">
                        <i class="fa fa-globe" style="font-size: 40px; margin-bottom: 15px;"></i>
                        <h4 class="gs-tracking-doc-value--lg" style="margin-top: 0;">{{ __('govtracking::general.task.open_mode') }}</h4>
                        <p style="font-size: 13px; max-width: 500px; margin: 0 auto; line-height: 1.6;">
                            {{ __('govtracking::general.ui.this_program_operates_in_blanket_mode_no_category_level_allocations_are_enforced_storekeepers_at_permitted_office_locations_can_receive_any_categories_under_this_task') }}
                        </p>
                    </div>
                @else
                    <x-gs::table>
                        <thead>
                            <tr class="gs-tracking-doc-table-head">
                                <th style="width: 10%; text-align: center;">Sl.</th>
                                <th>{{ __('govtracking::general.task.item_category') }}</th>
                                <th style="width: 25%; text-align: right;">{{ __('govtracking::general.task.approved') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($trackingCode->targets as $index => $target)
                                @php
                                    $allocated = $trackingCode->specificity_level === '3_MATRIX' ? $target->allocated_qty : $target->planned_qty;
                                @endphp
                                <tr>
                                    <td style="text-align: center;">{{ $index + 1 }}</td>
                                    <td>
                                        <b class="gs-tracking-doc-value">{{ $target->category->name ?? __('govtracking::general.ui.extra_unknown_category') }}</b>
                                        @if($target->economic_code)
                                            <div class="gs-tracking-doc-label" style="margin-top: 2px;">
                                                {{ __('govtracking::general.task.economic') }} <code>{{ $target->economic_code }}</code>
                                            </div>
                                        @endif
                                    </td>
                                    <td style="text-align: right; font-weight: bold;">
                                        {{ number_format($allocated) }} {{ __('govtracking::general.ui.extra_units') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center gs-tracking-doc-label">
                                        {{ __('govtracking::general.task.no_targets') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-gs::table>
                @endif

                @if($trackingCode->specificity_level === '3_MATRIX')
                    <x-slot:footer>
                        <span class="gs-tracking-doc-label" style="font-size: 12px;">
                            <i class="fa fa-building-o"></i> {{ __('govtracking::general.task.office_allocation') }} <b>{{ $locationName }}</b> (ID: {{ $locationId }}).
                        </span>
                    </x-slot:footer>
                @endif
            </x-gs::box>
        </div>

        <!-- RIGHT COLUMN: Supporting Files & Side Directory -->
        <div class="col-md-4">

            <!-- Attached Order -->
            <x-gs::box :title="__('govtracking::general.ui.extra_attached_order')" icon="fa fa-paperclip">
                <div class="text-center">
                    @if($trackingCode->order_pdf_path)
                        <a href="{{ route('gov.tracking.tracking-codes.download', $trackingCode->id) }}" class="btn btn-theme btn-block" style="font-weight: bold; padding: 10px;">
                            <i class="fa fa-download"></i> {{ __('govtracking::general.task.signed_memo') }}
                        </a>
                    @else
                        <span class="text-muted" style="font-size: 13px;"><i class="fa fa-info-circle"></i> {{ __('govtracking::general.task.no_order') }}</span>
                    @endif
                </div>
            </x-gs::box>

            <!-- Responsible Operation Unit -->
            <x-gs::box :title="__('govtracking::general.ui.extra_responsible_operation_unit')" icon="fa fa-users">
                @if($operationHead)
                    <div class="gs-tracking-doc-field gs-tracking-doc-field--bordered">
                        <span class="gs-tracking-doc-label">{{ __('govtracking::general.task.responsible') }}</span>
                        <b class="gs-tracking-doc-value">{{ $operationHead->user->first_name ?? '' }} {{ $operationHead->user->last_name ?? '' }}</b>
                        @if($operationHead->user->phone)
                            <div class="gs-tracking-doc-value" style="font-size: 12px; margin-top: 4px;">
                                <i class="fa fa-phone"></i> {{ $operationHead->user->phone }}
                            </div>
                        @endif
                        @if($operationHead->user->email)
                            <div class="gs-tracking-doc-value" style="font-size: 12px; margin-top: 2px;">
                                <i class="fa fa-envelope-o"></i> {{ $operationHead->user->email }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="gs-tracking-doc-field gs-tracking-doc-field--bordered gs-tracking-doc-label" style="font-size: 12px;">
                        <i class="fa fa-exclamation-circle"></i> {{ __('govtracking::general.task.head_missing') }}
                    </div>
                @endif

                @if($operationOfficer)
                    <div style="margin-bottom: 5px;">
                        <span class="gs-tracking-doc-label">{{ __('govtracking::general.ui.officer_list') }}</span>
                        <b class="gs-tracking-doc-value">{{ $operationOfficer->user->first_name ?? '' }} {{ $operationOfficer->user->last_name ?? '' }}</b>
                        @if($operationOfficer->user->phone)
                            <div class="gs-tracking-doc-value" style="font-size: 12px; margin-top: 4px;">
                                <i class="fa fa-phone"></i> {{ $operationOfficer->user->phone }}
                            </div>
                        @endif
                        @if($operationOfficer->user->email)
                            <div class="gs-tracking-doc-value" style="font-size: 12px; margin-top: 2px;">
                                <i class="fa fa-envelope-o"></i> {{ $operationOfficer->user->email }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="gs-tracking-doc-label" style="font-size: 12px; margin-bottom: 5px;">
                        <i class="fa fa-exclamation-circle"></i> {{ __('govtracking::general.task.officer_missing') }}
                    </div>
                @endif
            </x-gs::box>
        </div>
    </div>
</x-gs::document>
@stop
