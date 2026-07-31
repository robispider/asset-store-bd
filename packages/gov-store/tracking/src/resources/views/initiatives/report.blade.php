@extends('layouts/default')
@section('title', 'Executive Performance Report')

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
            
            <!-- Document Header -->
            <div style="border-bottom: 3px double #000000; padding-bottom: 15px; margin-bottom: 25px;">
                <h2 style="font-weight: bold; margin-top: 0; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;">🏫 Programme Performance & Fiscal Report</h2>
                <p style="margin-bottom: 5px; font-size: 14px;">
                    Initiative: <strong>{{ $initiative->title }}</strong> | 
                    Status: <strong>{{ strtoupper($initiative->status) }}</strong>
                </p>
                <p style="margin-bottom: 0; font-size: 13px; color: #475569;">
                    Owner: <code>{{ $initiative->ownerCompany->name ?? 'Unknown' }}</code> | 
                    Compiled Date: <strong>{{ now()->format('Y-m-d H:i') }}</strong>
                </p>
            </div>

            <!-- Section 1: Macro Physical Progress -->
            <div>
                <h4 class="report-section-title">📊 1. MACRO PHYSICAL PROGRESS (Overall Deliverables)</h4>
                
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
                            <th style="width: 25%;">Category</th>
                            <th style="width: 15%;">Planned</th>
                            <th style="width: 15%;">Received</th>
                            <th style="width: 15%;">Outstanding</th>
                            <th style="width: 30%;">Completion Ratio</th>
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
                                    <strong>{{ $percent }}%</strong> {{ $percent >= 100 ? 'Complete' : 'On Track' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No planned targets allocated to this initiative yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <hr class="report-divider">

            <!-- Section 2: Fiscal & Budget Code Expenditure -->
            <div>
                <h4 class="report-section-title">💵 2. FISCAL EXPENDITURE BY BUDGET CODES (iBAS++ Alignment)</h4>
                
                <ul class="list-tree">
                    @forelse($trackingCodes as $code)
                        <li style="margin-bottom: 20px;">
                            <span class="bullet">•</span> <strong>{{ $code->fundingType->primary_type ?? 'N/A' }} Budget Segment (Main)</strong> | {{ $code->fundingType->name ?? 'N/A' }} (Sub-source)
                            <div class="sub-node">
                                ➔ Task Code: <code>{{ $code->tracking_code }}</code> ({{ $code->task_title }})<br>
                                @foreach($code->targets as $target)
                                    @php
                                        $prog = $target->progress ?? ['received' => 0, 'planned' => $target->planned_qty, 'percentage' => 0];
                                    @endphp
                                    ↳ Economic Code: <strong>{{ $target->economic_code ?? 'N/A' }}</strong><br>
                                    <span style="padding-left: 15px;">Planned: {{ $prog['planned'] }} {{ $target->category->name }} | Received: {{ $prog['received'] }} {{ $target->category->name }} ({{ $prog['percentage'] }}% Complete)</span><br>
                                @endforeach
                            </div>
                        </li>
                    @empty
                        <li class="text-center text-muted">No active tracking tasks registered under this initiative.</li>
                    @endforelse
                </ul>
            </div>

            <hr class="report-divider">

            <!-- Section 3: Geographical Dispersion Workspace (The Final Star-Schema Grid) -->
            <div>
                <h4 class="report-section-title">📍 3. GEOGRAPHICAL DISPERSION WORKSPACE (Actual Receipts)</h4>
                
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">Sl.</th>
                            <th style="width: 15%;">GeoArea (District)</th>
                            <th style="width: 25%;">Receiving Office Location</th>
                            <th style="width: 15%;">Economic Code</th>
                            <th style="width: 15%;">Category</th>
                            <th style="width: 10%;">Received Qty</th>
                            <th style="width: 10%;">Avg Price (in GRN)</th>
                            <th style="width: 5%;">GRN Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($facts as $index => $fact)
                            @php
                                // Resolve District GeoArea dynamically via the Organization package links safely
                                $geoName = 'N/A';
                                if ($fact->location && $fact->location->profile && class_exists('GovStore\GeoAreas\Models\GeoArea')) {
                                    $profile = $fact->location->profile;
                                    $geoName = \GovStore\GeoAreas\Models\GeoArea::find($profile->geo_area_id)->en_name ?? 'N/A';
                                }

                                // Resolve Economic Code dynamically from the task targets
                                $econCode = 'N/A';
                                $target = DB::table('gov_tracking_targets')
                                    ->where('tracking_code_id', $fact->tracking_code_id)
                                    ->where('category_id', $fact->category_id)
                                    ->first();
                                if ($target) {
                                    $econCode = $target->economic_code ?? 'N/A';
                                }

                                // Calculate dynamic average price safely from the additive columns
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
                                <td colspan="8" class="text-center text-muted">No physical delivery transactions have been materialized yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Note: Section 4 Timeline is completely removed as requested -->

        </div>
    </div>
</div>
@stop