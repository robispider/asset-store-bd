@extends('layouts/default')
@section('title', 'Task Component: ' . $trackingCode->tracking_code)

@section('content')
<div class="row">
    <!-- LEFT COLUMN: Official Headers, Task Info, Scope, & Allocation Schedules (Ratio 8/12) -->
    <div class="col-md-8">
        
        <!-- Official Document Header (Middle-Aligned inside Left Column) -->
        <div class="text-center" style="margin-bottom: 30px; border-bottom: 2px solid var(--box-header-bottom-border-color); padding-bottom: 20px;">
            <h4 class="text-uppercase" style="font-weight: bold; color: var(--text-help); margin-bottom: 5px; letter-spacing: 0.5px;">
                তথ্য ও যোগাযোগ প্রযুক্তি অধিদপ্তর / Department of ICT, তথ্য ও যোগাযোগ প্রযুক্তি বিভাগ
            </h4>
            <h3 style="margin-top: 0; margin-bottom: 10px; color: var(--main-theme-color); font-weight: bold; letter-spacing: 0.5px;">
                [ {{ $initiative->title }} ]
            </h3>
            <h2 style="margin-top: 0; font-weight: bold; color: var(--color-fg); font-size: 26px;">
                Task/Component: {{ $trackingCode->task_title }}
            </h2>
        </div>

        <!-- Task / Component Information -->
        <div class="box box-solid" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-info-circle text-primary"></i> Task/Component Information
                </h3>
            </div>
            <div class="box-body" style="padding: 20px;">
                <!-- Full-Width Task Title Row -->
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--box-header-bottom-border-color);">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 5px;">Task / Component Title</span>
                    <span style="font-size: 20px; font-weight: bold; color: var(--color-fg); display: block; line-height: 1.4;">
                        {{ $trackingCode->task_title }}
                    </span>
                </div>

                <!-- Dual Sub-Columns Grid -->
                <div class="row">
                    <!-- Left Sub-Column (Basic Identifiers) -->
                    <div class="col-sm-6">
                        <div style="margin-bottom: 15px;">
                            <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Official Tracking Code</span>
                            <b style="font-size: 14px; color: var(--color-fg);">{{ $trackingCode->tracking_code }}</b>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Current Status</span>
                            @if($trackingCode->status === 'ACTIVE')
                                <span class="text-success" style="font-weight: bold; font-size: 14px;"><i class="fa fa-circle"></i> ACTIVE</span>
                            @else
                                <span class="text-muted" style="font-weight: bold; font-size: 14px;"><i class="fa fa-circle-o"></i> {{ $trackingCode->status }}</span>
                            @endif
                        </div>
                        <div>
                            <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Created Date</span>
                            <span style="font-size: 14px; color: var(--color-fg);">{{ $trackingCode->created_at->format('d F Y') }}</span>
                        </div>
                    </div>

                    <!-- Right Sub-Column (Accounting & Budget Parameters) -->
                    <div class="col-sm-6" style="border-left: 1px solid var(--box-header-bottom-border-color);">
                        <div style="margin-bottom: 15px; padding-left: 15px;">
                            <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Fiscal Period</span>
                            <b style="font-size: 14px; color: var(--color-fg);">FY {{ $trackingCode->fiscal_year }}</b>
                        </div>
                        <div style="margin-bottom: 15px; padding-left: 15px;">
                            <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Budget Sector</span>
                            <span style="font-size: 14px; color: var(--color-fg);">{{ $initiative->primary_funding }} Sector Budget</span>
                        </div>
                        <div style="padding-left: 15px;">
                            <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Funding Classification Segment</span>
                            <b style="font-size: 14px; color: var(--color-fg);">{{ $trackingCode->fundingType->name ?? 'N/A' }}</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authorized Scope (Two-Sub-Column Layout) -->
        <div class="box box-solid" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-map-marker text-purple"></i> Authorized Scope
                </h3>
            </div>
            <div class="box-body" style="padding: 20px;">
                <div class="row">
                    <!-- Left Sub-Column: Geographical Coverage -->
                    @php
                        $geoScope = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
                        $partScope = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();
                    @endphp
                    <div class="col-sm-6">
                        <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 5px;">Geographical Coverage</span>
                        @if($geoScope && $geoScope->target_type === 'GeoArea' && class_exists('GovStore\GeoAreas\Models\GeoArea'))
                            @php
                                $geoArea = \GovStore\GeoAreas\Models\GeoArea::find($geoScope->target_id);
                            @endphp
                            <b style="font-size: 14px; color: var(--color-fg);">{{ $geoArea->en_name ?? 'Specific District' }}</b> 
                            <span class="label label-warning" style="font-size: 10px; margin-left: 5px;">{{ $geoArea->geo_type ?? 'Region' }}</span>
                        @else
                            <b style="font-size: 14px; color: var(--color-fg);">Nationwide</b>
                            <span class="label label-default" style="font-size: 10px; margin-left: 5px;">Bangladesh</span>
                        @endif
                    </div>

                    <!-- Right Sub-Column: Participating Organizations -->
                    <div class="col-sm-6" style="border-left: 1px solid var(--box-header-bottom-border-color); padding-left: 20px;">
                        <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 5px;">Participating Organizations</span>
                        @if($partScope && $partScope->target_type === 'CrossTenant')
                            <b style="font-size: 14px; color: var(--color-fg);">Cross-Ministry Enabled</b>
                            <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0; line-height: 1.4;">
                                Any operating government office within the geographical coverage boundaries is authorized to process transactions.
                            </p>
                        @else
                            <b style="font-size: 14px; color: var(--color-fg);">Internal Agency Only</b>
                            <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0; line-height: 1.4;">
                                Receipt operations are restricted strictly to branches of the owning department.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Approved Allocation Schedule -->
        <div class="box box-solid" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-table text-success"></i> Approved Allocation Schedule
                </h3>
            </div>
            <div class="box-body" style="padding: 0;">
                @if($trackingCode->specificity_level === '1_BLANKET')
                    <div style="padding: 40px 20px; text-align: center; color: var(--text-help);">
                        <i class="fa fa-globe" style="font-size: 40px; margin-bottom: 15px;"></i>
                        <h4 style="font-weight: bold; color: var(--color-fg); margin-top: 0;">Open Allocation (Blanket Mode)</h4>
                        <p style="font-size: 13px; max-width: 500px; margin: 0 auto; line-height: 1.6;">
                            This program operates in blanket mode. No category-level allocations are enforced. Storekeepers at permitted office locations can receive any categories under this task.
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped" style="margin-bottom: 0;">
                            <thead>
                                <tr style="background-color: var(--table-stripe-bg-alt);">
                                    <th style="width: 10%; text-align: center; padding: 12px 8px;">Sl.</th>
                                    <th style="padding: 12px 8px;">Item Category</th>
                                    <th style="width: 25%; text-align: right; padding: 12px 8px;">Approved Allocation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($trackingCode->targets as $index => $target)
                                    @php
                                        $allocated = $trackingCode->specificity_level === '3_MATRIX' ? $target->allocated_qty : $target->planned_qty;
                                    @endphp
                                    <tr>
                                        <td style="text-align: center; padding: 12px 8px; vertical-align: middle;">
                                            {{ $index + 1 }}
                                        </td>
                                        <td style="padding: 12px 8px; vertical-align: middle;">
                                            <b style="color: var(--color-fg);">{{ $target->category->name ?? 'Unknown Category' }}</b>
                                            @if($target->economic_code)
                                                <div style="font-size: 11px; color: var(--text-help); margin-top: 2px;">
                                                    Economic Classification: <code>{{ $target->economic_code }}</code>
                                                </div>
                                            @endif
                                        </td>
                                        <td style="text-align: right; padding: 12px 8px; font-weight: bold; vertical-align: middle;">
                                            {{ number_format($allocated) }} Units
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" style="padding: 25px; text-align: center; color: var(--text-help);">
                                            No planning targets are currently allocated under this task component.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @if($trackingCode->specificity_level === '3_MATRIX')
                <div class="box-footer" style="background-color: var(--table-stripe-bg-alt); padding: 15px;">
                    <span style="font-size: 12px; color: var(--text-help);">
                        <i class="fa fa-building-o"></i> Showing allocation targets assigned specifically to your office: <b>{{ $locationName }}</b> (ID: {{ $locationId }}).
                    </span>
                </div>
            @endif
        </div>
    </div>

    <!-- RIGHT COLUMN: Supporting Files & Side Directory (Ratio 4/12) -->
    <div class="col-md-4">
        
        <!-- Attached Order -->
        <div class="box box-solid" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-paperclip text-danger"></i> Attached Order
                </h3>
            </div>
            <div class="box-body text-center" style="padding: 20px;">
                @if($trackingCode->order_pdf_path)
                    <a href="{{ route('gov.tracking.tracking-codes.download', $trackingCode->id) }}" class="btn btn-theme btn-block" style="font-weight: bold; padding: 10px;">
                        <i class="fa fa-download"></i> Download Signed Memo PDF
                    </a>
                @else
                    <span class="text-muted" style="font-size: 13px;"><i class="fa fa-info-circle"></i> No scanned government order is attached to this task.</span>
                @endif
            </div>
        </div>

        <!-- Responsible Operation Unit -->
        <div class="box box-solid" style="border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-users text-blue"></i> Responsible Operation Unit
                </h3>
            </div>
            <div class="box-body" style="padding: 15px;">
                @if($operationHead)
                    <div style="margin-bottom: 15px; border-bottom: 1px solid var(--box-header-bottom-border-color); padding-bottom: 12px;">
                        <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Program/Project/Task Responsible</span>
                        <b style="font-size: 14px; color: var(--color-fg);">{{ $operationHead->user->first_name ?? '' }} {{ $operationHead->user->last_name ?? '' }}</b>
                        @if($operationHead->user->phone)
                            <div style="font-size: 12px; color: var(--color-fg); margin-top: 4px;">
                                <i class="fa fa-phone"></i> {{ $operationHead->user->phone }}
                            </div>
                        @endif
                        @if($operationHead->user->email)
                            <div style="font-size: 12px; color: var(--color-fg); margin-top: 2px;">
                                <i class="fa fa-envelope-o"></i> {{ $operationHead->user->email }}
                            </div>
                        @endif
                    </div>
                @else
                    <div style="margin-bottom: 15px; border-bottom: 1px solid var(--box-header-bottom-border-color); padding-bottom: 12px; color: var(--text-help); font-size: 12px;">
                        <i class="fa fa-exclamation-circle"></i> not assigned.
                    </div>
                @endif

                @if($operationOfficer)
                    <div style="margin-bottom: 5px;">
                        <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold; margin-bottom: 3px;">Officer List</span>
                        <b style="font-size: 14px; color: var(--color-fg);">{{ $operationOfficer->user->first_name ?? '' }} {{ $operationOfficer->user->last_name ?? '' }}</b>
                        @if($operationOfficer->user->phone)
                            <div style="font-size: 12px; color: var(--color-fg); margin-top: 4px;">
                                <i class="fa fa-phone"></i> {{ $operationOfficer->user->phone }}
                            </div>
                        @endif
                        @if($operationOfficer->user->email)
                            <div style="font-size: 12px; color: var(--color-fg); margin-top: 2px;">
                                <i class="fa fa-envelope-o"></i> {{ $operationOfficer->user->email }}
                            </div>
                        @endif
                    </div>
                @else
                    <div style="color: var(--text-help); font-size: 12px; margin-bottom: 5px;">
                        <i class="fa fa-exclamation-circle"></i> Operation Officer not assigned.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Print Control Row -->
<div class="row hidden-print" style="margin-bottom: 40px; margin-top: 15px;">
    <div class="col-md-12 text-center">
        <button class="btn btn-primary btn-lg" onclick="window.print();" style="font-weight: bold; padding: 12px 30px;">
            <i class="fa fa-print"></i> Print Task Information Sheet
        </button>
    </div>
</div>

<style>
    @media print {
        .content-header,
        .main-header,
        .main-sidebar,
        .main-footer,
        .hidden-print {
            display: none !important;
        }
        body,
        .content-wrapper,
        .content {
            background-color: #fff !important;
            color: #000 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .col-md-8, .col-md-4 {
            width: 100% !important;
            float: none !important;
            margin-bottom: 25px !important;
        }
        .box {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            background: #fff !important;
        }
        .table-display dt {
            width: 30% !important;
        }
        .table-display dd {
            width: 70% !important;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #f9f9f9 !important;
        }
    }
</style>
@stop