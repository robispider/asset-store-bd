@extends('layouts/default')
@section('title', __('govtracking::general.report_title'))

@section('content')
<style>
    /* Premium high-contrast typewriter/draft reporting sheet */
    .report-sheet {
        background-color: #ffffff;
        color: #000000;
        padding: 40px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        font-family: 'Courier New', Courier, monospace; /* Authentic, print-perfect draft font */
        line-height: 1.6;
    }

    .report-divider {
        border: none;
        border-top: 1px dashed #000000;
        margin: 25px 0;
    }

    .report-section-title {
        font-size: 16px;
        font-weight: bold;
        text-transform: uppercase;
        margin-top: 0;
        margin-bottom: 15px;
        color: #000000;
    }

    /* Defensive, high-padding table styling */
    .report-table {
        width: 100% !important;
        margin-bottom: 20px !important;
        border-collapse: collapse !important;
        font-family: inherit !important;
    }

    .report-table th, .report-table td {
        border-bottom: 1px solid #cbd5e1 !important;
        padding: 12px 15px !important; /* Generous, readable spacing */
        text-align: left !important;
        vertical-align: middle !important;
    }

    .report-table th {
        background-color: #f1f5f9 !important;
        font-weight: bold !important;
        color: #000000 !important;
        border-top: 1px solid #cbd5e1 !important;
        border-bottom: 2px solid #000000 !important;
    }

    .list-tree {
        list-style: none;
        padding-left: 0;
    }

    .list-tree li {
        margin-bottom: 10px;
        position: relative;
    }

    .list-tree .bullet {
        font-weight: bold;
        margin-right: 5px;
    }

    .list-tree .sub-node {
        padding-left: 20px;
    }

    /* ========================================================================= */
    /* 🖨️ PRINT-FRIENDLY CSS STYLE CODES */
    /* ========================================================================= */
    @media print {
        .main-header, 
        .main-sidebar, 
        .main-footer, 
        .btn, 
        .callout,
        .hidden-print {
            display: none !important;
        }

        .content-wrapper {
            margin-left: 0 !important;
            padding: 0 !important;
            border: none !important;
            background-color: #ffffff !important;
        }

        .report-sheet {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            color: #000000 !important;
        }

        .report-table th {
            background-color: #f1f5f9 !important;
            color: #000000 !important;
            border-bottom: 2px solid #000000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .report-table td {
            border-bottom: 1px solid #cbd5e1 !important;
        }
    }
</style>

<div class="row">
    <div class="col-md-10 col-md-offset-1">
        
        <!-- Control Header Banners -->
        <div class="box box-solid box-default hidden-print" style="margin-bottom: 15px;">
            <div class="box-body text-right">
                <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default pull-left"><i class="fa fa-arrow-left"></i> Back to Workspace</a>
                <button onclick="window.print()" class="btn btn-primary btn-lg"><i class="fa fa-print"></i> Print Executive Report</button>
            </div>
        </div>

        <!-- The Document Sheet -->
        <div class="report-sheet">
            
            <!-- Dynamic Reporting Period Aggregator -->
            @php
                $activeFiscalYears = $trackingCodes->pluck('fiscal_year')->unique()->filter()->implode(', ');
            @endphp

            <!-- Official Government Header Format -->
            <div style="border-bottom: 3px double #000000; padding-bottom: 15px; margin-bottom: 25px; line-height: 1.8;">
                <h2 style="font-weight: bold; margin-top: 0; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px; text-align: center; border-bottom: 1px solid #000; padding-bottom: 10px;">
                    {{ __('govtracking::general.report_title') }}
                </h2>
                
                <table style="width: 100%; font-family: inherit; font-size: 14px; border-collapse: collapse;">
                    <tr>
                        <td style="width: 25%; font-weight: bold; padding: 4px 0;">{{ __('govtracking::general.programme_project') }}</td>
                        <td style="width: 3%; font-weight: bold;">:</td>
                        <td>{{ $initiative->title }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 4px 0;">{{ __('govtracking::general.current_status') }}</td>
                        <td style="font-weight: bold;">:</td>
                        <td><span class="text-bold">{{ strtoupper($initiative->status) }}</span></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 4px 0;">{{ __('govtracking::general.owning_org') }}</td>
                        <td style="font-weight: bold;">:</td>
                        <td>{{ $initiative->ownerCompany->name ?? 'Unknown' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 4px 0;">{{ __('govtracking::general.reporting_period') }}</td>
                        <td style="font-weight: bold;">:</td>
                        <td>{{ !empty($activeFiscalYears) ? 'FY ' . $activeFiscalYears : 'Unbounded' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 4px 0;">{{ __('govtracking::general.report_generated') }}</td>
                        <td style="font-weight: bold;">:</td>
                        <td>{{ now()->format('d M Y, H:i') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Section 1: Physical Progress Summary -->
            <div>
                <h4 class="report-section-title">{{ __('govtracking::general.section_physical_progress') }}</h4>
                
                @php
                    $categorySummaries = [];
                    foreach ($trackingCodes as $code) {
                        foreach ($code->targets as $target) {
                            $catId = $target->category_id;
                            $catName = $target->category->name;
                            
                            $prog = $target->progress ?? [
                                'percentage' => 0, 
                                'is_exceeded' => false, 
                                'received' => 0, 
                                'planned' => $target->planned_qty
                            ];

                            if (!isset($categorySummaries[$catId])) {
                                $categorySummaries[$catId] = [
                                    'name' => $catName,
                                    'planned' => 0,
                                    'received' => 0,
                                ];
                            }
                            $categorySummaries[$catId]['planned'] += $prog['planned'] ?? $target->planned_qty;
                            $categorySummaries[$catId]['received'] += $prog['received'] ?? 0;
                        }
                    }
                @endphp

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">{{ __('govtracking::general.col_item_category') }}</th>
                            <th style="width: 15%;">{{ __('govtracking::general.col_planned_qty') }}</th>
                            <th style="width: 15%;">{{ __('govtracking::general.col_received_qty') }}</th>
                            <th style="width: 15%;">{{ __('govtracking::general.col_remaining_qty') }}</th>
                            <th style="width: 30%;">{{ __('govtracking::general.col_progress') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorySummaries as $summary)
                            @php
                                $percent = $summary['planned'] > 0 ? round(($summary['received'] / $summary['planned']) * 100) : 0;
                                $percent = $percent > 100 ? 100 : $percent;
                                $outstanding = $summary['planned'] - $summary['received'];
                                $outstanding = $outstanding < 0 ? 0 : $outstanding;

                                // PURE TEXT-BASED ASCII PROGRESS BAR GENERATOR
                                $filledCount = round($percent / 10);
                                $emptyCount = 10 - $filledCount;
                                $filledCount = $filledCount > 10 ? 10 : $filledCount;
                                $emptyCount = $emptyCount < 0 ? 0 : $emptyCount;

                                $asciiBar = '[' . str_repeat('█', $filledCount) . str_repeat('░', $emptyCount) . ']';
                            @endphp
                            <tr>
                                <td><strong>{{ $summary['name'] }}</strong></td>
                                <td>{{ number_format($summary['planned']) }}</td>
                                <td>{{ number_format($summary['received']) }}</td>
                                <td>{{ number_format($outstanding) }}</td>
                                <td>
                                    <code style="font-size: 14px; background: transparent; padding: 0;">{{ $asciiBar }}</code> 
                                    <strong>{{ $percent }}%</strong> {{ $percent >= 100 ? __('govtracking::general.status_complete') : __('govtracking::general.status_on_track') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">{{ __('govtracking::general.msg_no_targets') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <hr class="report-divider">

            <!-- Section 2: Funding & Budget Utilization -->
            <div>
                <h4 class="report-section-title">{{ __('govtracking::general.section_budget_utilization') }}</h4>
                
                <ul class="list-tree">
                    @forelse($trackingCodes as $code)
                        <li style="margin-bottom: 20px;">
                            <span class="bullet">•</span> <strong>{{ $code->fundingType->primary_type ?? 'N/A' }} {{ __('govtracking::general.funding_source') }}</strong> | {{ $code->fundingType->name ?? 'N/A' }} ({{ __('govtracking::general.label_funding_component') }})
                            <div class="sub-node">
                                ➔ {{ __('govtracking::general.label_allocation_code') }}: <code>{{ $code->tracking_code }}</code> ({{ $code->task_title }})<br>
                                @foreach($code->targets as $target)
                                    @php
                                        $prog = $target->progress ?? ['received' => 0, 'planned' => $target->planned_qty, 'percentage' => 0];
                                    @endphp
                                    ↳ {{ __('govtracking::general.col_economic_code') }}: <strong>{{ $target->economic_code ?? 'N/A' }}</strong><br>
                                    <span style="padding-left: 15px;">Planned: {{ $prog['planned'] }} {{ $target->category->name }} | Received: {{ $prog['received'] }} {{ $target->category->name }} ({{ $prog['percentage'] }}% Complete)</span><br>
                                @endforeach
                            </div>
                        </li>
                    @empty
                        <li class="text-center text-muted">{{ __('govtracking::general.msg_no_tasks') }}</li>
                    @endforelse
                </ul>
            </div>

            <hr class="report-divider">

            <!-- Section 3: Geographical Distribution of Receipts -->
            <div>
                <h4 class="report-section-title">{{ __('govtracking::general.section_geographical_distribution') }}</h4>
                
                @php
                    $hasMatrix = $trackingCodes->contains('specificity_level', '3_MATRIX');
                @endphp

                @if($hasMatrix)
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">{{ __('govtracking::general.col_sl') }}</th>
                                <th style="width: 15%;">{{ __('govtracking::general.col_admin_area') }}</th>
                                <th style="width: 25%;">{{ __('govtracking::general.col_receiving_office') }}</th>
                                <th style="width: 15%;">{{ __('govtracking::general.col_economic_code') }}</th>
                                <th style="width: 15%;">{{ __('govtracking::general.col_item_category') }}</th>
                                <th style="width: 10%;">{{ __('govtracking::general.col_received_qty') }}</th>
                                <th style="width: 10%;">{{ __('govtracking::general.col_avg_unit_price') }}</th>
                                <th style="width: 5%;">{{ __('govtracking::general.col_receipt_docs') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trackingCodes->where('specificity_level', '3_MATRIX') as $code)
                                @forelse($code->matrixProgress ?? [] as $locId => $data)
                                    @foreach($data['items'] as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            @if($index === 0)
                                                <td rowspan="{{ count($data['items']) }}" style="vertical-align: top; font-weight: bold; border-bottom: 1px solid #cbd5e1 !important;">
                                                    @php
                                                        $geoName = 'N/A';
                                                        $location = \App\Models\Location::find($locId);
                                                        if ($location && $location->profile && class_exists('GovStore\GeoAreas\Models\GeoArea')) {
                                                            $geoName = \GovStore\GeoAreas\Models\GeoArea::find($location->profile->geo_area_id)->en_name ?? 'N/A';
                                                        }
                                                    @endphp
                                                    {{ $geoName }}
                                                </td>
                                                <td rowspan="{{ count($data['items']) }}" style="vertical-align: top; font-weight: bold; border-bottom: 1px solid #cbd5e1 !important;">
                                                    🏢 {{ $data['location_name'] }}
                                                </td>
                                            @endif
                                            
                                            @php
                                                $econCode = 'N/A';
                                                $target = DB::table('gov_tracking_targets')
                                                    ->where('tracking_code_id', $code->id)
                                                    ->where('category_id', $code->targets->where('category_name', $item['category_name'])->first()?->category_id ?? 0)
                                                    ->first();
                                                if ($target) {
                                                    $econCode = $target->economic_code ?? 'N/A';
                                                }
                                            @endphp

                                            <td><code>{{ $econCode }}</code></td>
                                            <td>{{ $item['category_name'] }}</td>
                                            <td><strong>{{ $item['received'] }} units</strong></td>
                                            <td>Calculating...</td>
                                            <td><strong>{{ $item['percentage'] >= 100 ? '🟢' : '🟡' }}</strong></td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <!-- Skip empty lines -->
                                @endforelse
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <!-- Fallback: Renders actual transactional geographical distributions if Level 3 was unconfigured -->
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">{{ __('govtracking::general.col_sl') }}</th>
                                <th style="width: 15%;">{{ __('govtracking::general.col_admin_area') }}</th>
                                <th style="width: 25%;">{{ __('govtracking::general.col_receiving_office') }}</th>
                                <th style="width: 15%;">{{ __('govtracking::general.col_economic_code') }}</th>
                                <th style="width: 15%;">{{ __('govtracking::general.col_item_category') }}</th>
                                <th style="width: 10%;">{{ __('govtracking::general.col_received_qty') }}</th>
                                <th style="width: 10%;">{{ __('govtracking::general.col_avg_unit_price') }}</th>
                                <th style="width: 5%;">{{ __('govtracking::general.col_receipt_docs') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($facts as $index => $fact)
                                @php
                                    $geoName = 'N/A';
                                    if ($fact->location && $fact->location->profile && class_exists('GovStore\GeoAreas\Models\GeoArea')) {
                                        $profile = $fact->location->profile;
                                        $geoName = \GovStore\GeoAreas\Models\GeoArea::find($profile->geo_area_id)->en_name ?? 'N/A';
                                    }

                                    $econCode = 'N/A';
                                    $target = DB::table('gov_tracking_targets')
                                        ->where('tracking_code_id', $fact->tracking_code_id)
                                        ->where('category_id', $fact->category_id)
                                        ->first();
                                    if ($target) {
                                        $econCode = $target->economic_code ?? 'N/A';
                                    }

                                    $avgPrice = $fact->received_qty > 0 ? ($fact->total_cost / $fact->received_qty) : 0.00;
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $geoName }}</strong></td>
                                    <td>{{ $fact->location->name ?? "Office #{$fact->location_id}" }}</td>
                                    <td><code>{{ $econCode }}</code></td>
                                    <td>{{ $fact->category->name }}</td>
                                    <td><strong>{{ $fact->received_qty }} units</strong></td>
                                    <td>{{ $avgPrice > 0 ? number_format($avgPrice, 2) . ' BDT' : 'N/A' }}</td>
                                    <td><strong>{{ $fact->transaction_count }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">{{ __('govtracking::general.msg_no_deliveries') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>

            <!-- Note: Section 4 Timeline is completely removed as requested -->

        </div>
    </div>
</div>
@stop