@extends('layouts/default')
@section('title', 'Task Component: ' . $trackingCode->tracking_code)

@section('content')
<!-- Print Control Row -->
<div class="row hidden-print gs-tracking-print-toolbar" style="margin-bottom: 15px;">
    <div class="col-md-12 text-right">
        <button class="btn btn-primary btn-lg" onclick="window.print();" style="font-weight: bold; padding: 12px 30px;">
            <i class="fa fa-print"></i> Print Task Information Sheet
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
            <x-gs::box title="Task/Component Information" icon="fa fa-info-circle">
                <!-- Full-Width Task Title Row -->
                <div class="gs-tracking-doc-field gs-tracking-doc-field--bordered">
                    <span class="gs-tracking-doc-label">Task / Component Title</span>
                    <span class="gs-tracking-doc-value--lg">{{ $trackingCode->task_title }}</span>
                </div>

                <!-- Dual Sub-Columns Grid -->
                <div class="row">
                    <!-- Left Sub-Column (Basic Identifiers) -->
                    <div class="col-sm-6">
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">Official Tracking Code</span>
                            <b class="gs-tracking-doc-value">{{ $trackingCode->tracking_code }}</b>
                        </div>
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">Current Status</span>
                            @if($trackingCode->status === 'ACTIVE')
                                <span class="text-success" style="font-weight: bold; font-size: 14px;"><i class="fa fa-circle"></i> ACTIVE</span>
                            @else
                                <span class="text-muted" style="font-weight: bold; font-size: 14px;"><i class="fa fa-circle-o"></i> {{ $trackingCode->status }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="gs-tracking-doc-label">Created Date</span>
                            <span class="gs-tracking-doc-value">{{ $trackingCode->created_at->format('d F Y') }}</span>
                        </div>
                    </div>

                    <!-- Right Sub-Column (Accounting & Budget Parameters) -->
                    <div class="col-sm-6 gs-tracking-doc-col-divider">
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">Fiscal Period</span>
                            <b class="gs-tracking-doc-value">FY {{ $trackingCode->fiscal_year }}</b>
                        </div>
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">Budget Sector</span>
                            <span class="gs-tracking-doc-value">{{ $initiative->primary_funding }} Sector Budget</span>
                        </div>
                        <div>
                            <span class="gs-tracking-doc-label">Funding Classification Segment</span>
                            <b class="gs-tracking-doc-value">{{ $trackingCode->fundingType->name ?? 'N/A' }}</b>
                        </div>
                    </div>
                </div>
            </x-gs::box>

            <!-- Authorized Scope (Two-Sub-Column Layout) -->
            <x-gs::box title="Authorized Scope" icon="fa fa-map-marker">
                <div class="row">
                    <!-- Left Sub-Column: Geographical Coverage -->
                    @php
                        $geoScope = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
                        $partScope = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();
                    @endphp
                    <div class="col-sm-6">
                        <span class="gs-tracking-doc-label">Geographical Coverage</span>
                        @if($geoScope && $geoScope->target_type === 'GeoArea' && class_exists('GovStore\GeoAreas\Models\GeoArea'))
                            @php
                                $geoArea = \GovStore\GeoAreas\Models\GeoArea::find($geoScope->target_id);
                            @endphp
                            <b class="gs-tracking-doc-value">{{ $geoArea->en_name ?? 'Specific District' }}</b>
                            <span class="label label-warning" style="font-size: 10px; margin-left: 5px;">{{ $geoArea->geo_type ?? 'Region' }}</span>
                        @else
                            <b class="gs-tracking-doc-value">Nationwide</b>
                            <span class="label label-default" style="font-size: 10px; margin-left: 5px;">Bangladesh</span>
                        @endif
                    </div>

                    <!-- Right Sub-Column: Participating Organizations -->
                    <div class="col-sm-6 gs-tracking-doc-col-divider">
                        <span class="gs-tracking-doc-label">Participating Organizations</span>
                        @if($partScope && $partScope->target_type === 'CrossTenant')
                            <b class="gs-tracking-doc-value">Cross-Ministry Enabled</b>
                            <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0; line-height: 1.4;">
                                Any operating government office within the geographical coverage boundaries is authorized to process transactions.
                            </p>
                        @else
                            <b class="gs-tracking-doc-value">Internal Agency Only</b>
                            <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0; line-height: 1.4;">
                                Receipt operations are restricted strictly to branches of the owning department.
                            </p>
                        @endif
                    </div>
                </div>
            </x-gs::box>

            <!-- Approved Allocation Schedule -->
            <x-gs::box title="Approved Allocation Schedule" icon="fa fa-table">
                @if($trackingCode->specificity_level === '1_BLANKET')
                    <div class="gs-tracking-doc-empty">
                        <i class="fa fa-globe" style="font-size: 40px; margin-bottom: 15px;"></i>
                        <h4 class="gs-tracking-doc-value--lg" style="margin-top: 0;">Open Allocation (Blanket Mode)</h4>
                        <p style="font-size: 13px; max-width: 500px; margin: 0 auto; line-height: 1.6;">
                            This program operates in blanket mode. No category-level allocations are enforced. Storekeepers at permitted office locations can receive any categories under this task.
                        </p>
                    </div>
                @else
                    <x-gs::table>
                        <thead>
                            <tr class="gs-tracking-doc-table-head">
                                <th style="width: 10%; text-align: center;">Sl.</th>
                                <th>Item Category</th>
                                <th style="width: 25%; text-align: right;">Approved Allocation</th>
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
                                        <b class="gs-tracking-doc-value">{{ $target->category->name ?? 'Unknown Category' }}</b>
                                        @if($target->economic_code)
                                            <div class="gs-tracking-doc-label" style="margin-top: 2px;">
                                                Economic Classification: <code>{{ $target->economic_code }}</code>
                                            </div>
                                        @endif
                                    </td>
                                    <td style="text-align: right; font-weight: bold;">
                                        {{ number_format($allocated) }} Units
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center gs-tracking-doc-label">
                                        No planning targets are currently allocated under this task component.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-gs::table>
                @endif

                @if($trackingCode->specificity_level === '3_MATRIX')
                    <x-slot:footer>
                        <span class="gs-tracking-doc-label" style="font-size: 12px;">
                            <i class="fa fa-building-o"></i> Showing allocation targets assigned specifically to your office: <b>{{ $locationName }}</b> (ID: {{ $locationId }}).
                        </span>
                    </x-slot:footer>
                @endif
            </x-gs::box>
        </div>

        <!-- RIGHT COLUMN: Supporting Files & Side Directory -->
        <div class="col-md-4">

            <!-- Attached Order -->
            <x-gs::box title="Attached Order" icon="fa fa-paperclip">
                <div class="text-center">
                    @if($trackingCode->order_pdf_path)
                        <a href="{{ route('gov.tracking.tracking-codes.download', $trackingCode->id) }}" class="btn btn-theme btn-block" style="font-weight: bold; padding: 10px;">
                            <i class="fa fa-download"></i> Download Signed Memo PDF
                        </a>
                    @else
                        <span class="text-muted" style="font-size: 13px;"><i class="fa fa-info-circle"></i> No scanned government order is attached to this task.</span>
                    @endif
                </div>
            </x-gs::box>

            <!-- Responsible Operation Unit -->
            <x-gs::box title="Responsible Operation Unit" icon="fa fa-users">
                @if($operationHead)
                    <div class="gs-tracking-doc-field gs-tracking-doc-field--bordered">
                        <span class="gs-tracking-doc-label">Program/Project/Task Responsible</span>
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
                        <i class="fa fa-exclamation-circle"></i> not assigned.
                    </div>
                @endif

                @if($operationOfficer)
                    <div style="margin-bottom: 5px;">
                        <span class="gs-tracking-doc-label">Officer List</span>
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
                        <i class="fa fa-exclamation-circle"></i> Operation Officer not assigned.
                    </div>
                @endif
            </x-gs::box>
        </div>
    </div>
</x-gs::document>
@stop
