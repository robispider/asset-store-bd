@extends('layouts/default')
@section('title', 'Task Component: ' . $trackingCode->tracking_code)

@section('content')
<div class="row">
    <!-- Main Task Information and Allocations -->
    <div class="col-md-8">
        
        <!-- Section 1: What is this task? -->
        <div class="box box-solid" style="border-top: 3px solid var(--main-theme-color);">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-info-circle text-primary"></i> What is this task?
                </h3>
                <span class="label label-primary pull-right" style="font-size: 12px; padding: 5px 10px;">
                    Code: {{ $trackingCode->tracking_code }}
                </span>
            </div>
            <div class="box-body" style="padding: 20px;">
                <h2 style="margin-top: 0; font-weight: bold; color: var(--color-fg);">
                    {{ $trackingCode->task_title }}
                </h2>
                <p class="text-muted" style="font-size: 14px; margin-top: 10px; line-height: 1.6;">
                    This component belongs to the parent initiative <b>{{ $initiative->title }}</b>, owned and managed by <b>{{ $initiative->ownerCompany->name ?? 'Unknown' }}</b>.
                </p>

                @if($trackingCode->order_pdf_path)
                    <div style="margin-top: 20px; padding: 15px; background-color: var(--table-stripe-bg-alt); border-radius: 4px; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <i class="fa fa-file-pdf-o text-danger" style="font-size: 24px; margin-right: 10px; vertical-align: middle;"></i>
                            <span style="font-size: 13px; font-weight: bold;">Official Government Order / Memo Scan</span>
                        </div>
                        <a href="{{ route('gov.tracking.tracking-codes.download', $trackingCode->id) }}" class="btn btn-sm btn-theme" style="font-weight: bold;">
                            <i class="fa fa-download"></i> Download PDF Document
                        </a>
                    </div>
                @else
                    <div style="margin-top: 20px; padding: 10px 15px; background-color: var(--table-stripe-bg-alt); border-radius: 4px; color: var(--text-help); font-size: 13px;">
                        <i class="fa fa-info-circle"></i> No scanned government order is attached to this task.
                    </div>
                @endif
            </div>
        </div>

        <!-- Section 4: Allocation Worksheet (Contextual Targets Summary) -->
        <div class="box box-solid">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-cubes text-success"></i> 
                    @if($trackingCode->specificity_level === '3_MATRIX')
                        Your Office Allocation Summary
                    @else
                        Category Planning Targets
                    @endif
                </h3>
            </div>
            <div class="box-body" style="padding: 0;">
                @if($trackingCode->specificity_level === '1_BLANKET')
                    <div style="padding: 30px 20px; text-align: center; color: var(--text-help);">
                        <i class="fa fa-globe" style="font-size: 40px; margin-bottom: 15px;"></i>
                        <h4 style="font-weight: bold; color: var(--color-fg); margin-top: 0;">Open Allocation (Blanket Mode)</h4>
                        <p style="font-size: 13px; max-width: 500px; margin: 0 auto; line-height: 1.6;">
                            This program operates in blanket mode. No category-level allocations are enforced. Storekeepers at permitted office locations can receive any categories under this task.
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" style="margin-bottom: 0;">
                            <thead>
                                <tr style="background-color: var(--table-stripe-bg-alt);">
                                    <th style="width: 8%; text-align: center; padding: 12px 8px;">Sl.</th>
                                    <th style="padding: 12px 8px;">Item Category</th>
                                    <th style="width: 20%; text-align: right; padding: 12px 8px;">Allocated Qty</th>
                                    <th style="width: 20%; text-align: right; padding: 12px 8px;">Received Qty</th>
                                    <th style="width: 20%; text-align: right; padding: 12px 8px;">Remaining Balance</th>
                                    <th style="width: 15%; text-align: center; padding: 12px 8px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($trackingCode->targets as $index => $target)
                                    @php
                                        $allocated = $trackingCode->specificity_level === '3_MATRIX' ? $target->allocated_qty : $target->planned_qty;
                                        $received = $target->received_qty ?? 0;
                                        $remaining = $target->remaining_qty ?? 0;
                                        $isComplete = $allocated > 0 && $received >= $allocated;
                                    @endphp
                                    <tr>
                                        <td style="text-align: center; padding: 12px 8px; vertical-align: middle;">
                                            {{ $index + 1 }}
                                        </td>
                                        <td style="padding: 12px 8px; vertical-align: middle;">
                                            <b style="color: var(--color-fg);">{{ $target->category->name ?? 'Unknown Category' }}</b>
                                            @if($target->economic_code)
                                                <div style="font-size: 11px; color: var(--text-help); margin-top: 2px;">
                                                    Economic Code: <code>{{ $target->economic_code }}</code>
                                                </div>
                                            @endif
                                        </td>
                                        <td style="text-align: right; padding: 12px 8px; font-weight: bold; vertical-align: middle;">
                                            {{ number_format($allocated) }} Units
                                        </td>
                                        <td style="text-align: right; padding: 12px 8px; color: var(--text-success); font-weight: bold; vertical-align: middle;">
                                            {{ number_format($received) }} Units
                                        </td>
                                        <td style="text-align: right; padding: 12px 8px; color: {{ $remaining > 0 ? 'var(--text-info)' : 'var(--text-help)' }}; font-weight: bold; vertical-align: middle;">
                                            {{ number_format($remaining) }} Units
                                        </td>
                                        <td style="text-align: center; padding: 12px 8px; vertical-align: middle;">
                                            @if($isComplete)
                                                <span class="label label-success" style="font-size: 10px;">COMPLETE</span>
                                            @elseif($received > 0)
                                                <span class="label label-info" style="font-size: 10px;">IN PROGRESS</span>
                                            @else
                                                <span class="label label-default" style="font-size: 10px;">PENDING</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" style="padding: 25px; text-align: center; color: var(--text-help);">
                                            No planning targets have been allocated under this task component.
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

    <!-- Right Sidebar: Scope and Budget Details -->
    <div class="col-md-4">
        
        <!-- Section 2: Where can this task operate? -->
        <div class="box box-solid">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-map-marker text-purple"></i> Where can this task operate?
                </h3>
            </div>
            <div class="box-body" style="padding: 15px;">
                @php
                    $geoScope = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
                    $partScope = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();
                @endphp
                
                <div style="margin-bottom: 15px; border-bottom: 1px solid var(--box-header-bottom-border-color); padding-bottom: 12px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold;">Geographical Coverage</span>
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

                <div style="margin-bottom: 5px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold;">Participating Organization Scope</span>
                    @if($partScope && $partScope->target_type === 'CrossTenant')
                        <b style="font-size: 14px; color: var(--color-fg);">Cross-Ministry Enabled</b>
                        <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0;">
                            Any operating government office within the geographical coverage boundaries is authorized to process transactions.
                        </p>
                    @else
                        <b style="font-size: 14px; color: var(--color-fg);">Internal Agency Only</b>
                        <p class="help-block" style="font-size: 11px; margin-top: 5px; margin-bottom: 0;">
                            Receipt operations are restricted strictly to branches of the owning department.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Section 3: Which budget funds this task? -->
        <div class="box box-solid">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: bold;">
                    <i class="fa fa-money text-orange"></i> Which budget funds this task?
                </h3>
            </div>
            <div class="box-body" style="padding: 15px;">
                <div style="margin-bottom: 15px; border-bottom: 1px solid var(--box-header-bottom-border-color); padding-bottom: 12px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold;">Fiscal Year</span>
                    <b style="font-size: 14px; color: var(--color-fg);">FY {{ $trackingCode->fiscal_year }}</b>
                </div>

                <div style="margin-bottom: 15px; border-bottom: 1px solid var(--box-header-bottom-border-color); padding-bottom: 12px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold;">Budget Main Segment</span>
                    <b style="font-size: 14px; color: var(--color-fg);">{{ $initiative->primary_funding }} Sector Budget</b>
                </div>

                <div style="margin-bottom: 5px;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-help); display: block; font-weight: bold;">Dynamic Sub-Fund Classification</span>
                    <b style="font-size: 14px; color: var(--color-fg);">{{ $trackingCode->fundingType->name ?? 'N/A' }}</b>
                </div>
            </div>
        </div>
    </div>
</div>
@stop