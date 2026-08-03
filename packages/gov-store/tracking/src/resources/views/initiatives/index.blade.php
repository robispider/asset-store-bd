@extends('layouts/default')
@section('title', 'Programme Operations Portfolio')

@section('content')
<style>
    .portfolio-header {
        background-color: #ffffff;
        padding: 25px;
        border-radius: 6px;
        border-top: 4px solid #3c8dbc;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .portfolio-title {
        margin-top: 0;
        font-weight: bold;
        color: #1e293b;
        letter-spacing: 0.5px;
    }
    
    .summary-strip {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }
    
    .summary-box {
        flex: 1;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 15px;
        text-align: center;
    }
    
    .summary-box .count {
        display: block;
        font-size: 24px;
        font-weight: bold;
        color: #0f172a;
    }
    
    .summary-box .label {
        font-size: 13px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: transparent;
        padding: 0;
    }
    
    .section-divider {
        margin: 40px 0 20px 0;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 10px;
    }
    
    .section-title {
        font-size: 16px;
        font-weight: bold;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .initiative-card {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .initiative-card-body {
        padding: 20px;
        flex-grow: 1;
    }
    
    .initiative-card-title {
        font-size: 18px;
        font-weight: bold;
        color: #0f172a;
        margin-top: 0;
        margin-bottom: 8px;
    }
    
    .initiative-card-purpose {
        color: #475569;
        font-size: 14px;
        min-height: 42px;
        margin-bottom: 15px;
    }
    
    .initiative-meta {
        list-style: none;
        padding: 0;
        margin: 0;
        border-top: 1px solid #f1f5f9;
        padding-top: 15px;
    }
    
    .initiative-meta li {
        margin-bottom: 8px;
        font-size: 13px;
        display: flex;
    }
    
    .initiative-meta li span:first-child {
        width: 100px;
        color: #64748b;
        font-weight: 600;
    }
    
    .initiative-card-footer {
        background-color: #f8fafc;
        border-top: 1px solid #e2e8f0;
        padding: 15px 20px;
        border-radius: 0 0 6px 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Dark Mode Overrides */
    body.dark-mode .portfolio-header, body.dark-mode .initiative-card {
        background-color: #1f2937;
        border-color: #374151;
    }
    body.dark-mode .portfolio-title, body.dark-mode .initiative-card-title { color: #f3f4f6; }
    body.dark-mode .summary-box, body.dark-mode .initiative-card-footer {
        background-color: #111827;
        border-color: #374151;
    }
    body.dark-mode .summary-box .count { color: #f3f4f6; }
    body.dark-mode .section-divider { border-color: #374151; }
    body.dark-mode .section-title { color: #d1d5db; }
    body.dark-mode .initiative-meta { border-color: #374151; }
    body.dark-mode .initiative-card-purpose { color: #9ca3af; }
</style>

<!-- Top Portfolio Header & Summary -->
<div class="row">
    <div class="col-md-12">
        <div class="portfolio-header">
            <h2 class="portfolio-title"><i class="fa fa-briefcase text-blue"></i> Programme Operations Portfolio</h2>
            <p class="text-muted" style="font-size: 15px;">Manage development projects, revenue programmes, tracking codes, and operational compliance.</p>
            
            <div class="summary-strip">
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Active')->count() }}</span>
                    <span class="label">Operational</span>
                </div>
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Planning')->count() }}</span>
                    <span class="label">In Setup</span>
                </div>
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Closed')->count() }}</span>
                    <span class="label">Closed Ops</span>
                </div>
                <div class="summary-box">
                    <span class="count">{{ $initiatives->where('status', 'Archived')->count() }}</span>
                    <span class="label">Historical</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Global Quick Actions Toolbar -->
<div class="row margin-bottom-15">
    <div class="col-md-12">
        <a href="{{ route('gov.tracking.initiatives.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Launch New Initiative</a>
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
                            {{ \Illuminate\Support\Str::limit($init->purpose ?? 'No purpose defined.', 110) }}
                        </p>
                        
                        <ul class="initiative-meta">
                            <li><span>Status</span> <strong><span class="text-green">🟢 Ready for Operations</span></strong></li>
                            <li><span>Funding</span> <strong class="text-muted">{{ $init->primary_funding }} Budget</strong></li>
                            <li><span>Owner</span> <strong class="text-muted">{{ $init->ownerCompany->name ?? 'Unknown' }}</strong></li>
                            <li><span>Trackers</span> <strong class="text-muted">{{ $init->tracking_codes_count }} Tracking Codes</strong></li>
                            <li><span>Updated</span> <strong class="text-muted">{{ $init->updated_at->diffForHumans() }}</strong></li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-success btn-sm">Open Workspace &rarr;</a>
                        <a href="{{ route('gov.tracking.initiatives.edit', $init->id) }}" class="btn btn-default btn-sm"><i class="fa fa-cog"></i> Properties</a>
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
                <div class="initiative-card" style="border-top: 3px solid #f39c12;">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title">🚧 {{ $init->title }}</h3>
                        <p class="initiative-card-purpose">
                            {{ \Illuminate\Support\Str::limit($init->purpose ?? 'Project under initial drafting and configuration.', 110) }}
                        </p>
                        
                        <ul class="initiative-meta">
                            <li><span>Status</span> <strong><span class="text-yellow">🟡 Setup in Progress</span></strong></li>
                            <li><span>Owner</span> <strong class="text-muted">{{ $init->ownerCompany->name ?? 'Unknown' }}</strong></li>
                            
                            <!-- Dynamic Operations Readiness Check -->
                            <li>
                                <span>Alert</span> 
                                @if($init->operation_units_count > 0)
                                    <strong class="text-muted">Drafting Planning Goals</strong>
                                @else
                                    <strong class="text-red"><i class="fa fa-warning"></i> Needs Operation Team Assigned</strong>
                                @endif
                            </li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-warning btn-sm">Continue Setup &rarr;</a>
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
        <span class="section-title"><i class="fa fa-circle text-blue"></i> Operations Complete ({{ $closedInitiatives->count() }})</span>
    </div>
    
    <div class="row">
        @foreach($closedInitiatives as $init)
            <div class="col-md-4 col-sm-6">
                <div class="initiative-card" style="opacity: 0.85;">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title text-muted">🏁 {{ $init->title }}</h3>
                        <ul class="initiative-meta" style="border-top: none; padding-top: 5px;">
                            <li><span>Status</span> <strong><span class="text-blue">🔵 Operations Complete</span></strong></li>
                            <li><span>Trackers</span> <strong class="text-muted">{{ $init->tracking_codes_count }} Executed Tasks</strong></li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-info btn-sm">View Analytics &rarr;</a>
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
                <div class="initiative-card" style="opacity: 0.6; background-color: #f8fafc;">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title text-muted">📁 {{ $init->title }}</h3>
                        <ul class="initiative-meta" style="border-top: none; padding-top: 5px;">
                            <li><span>Status</span> <strong><span class="text-muted">⚫ Historical Record</span></strong></li>
                            <li><span>Closed On</span> <strong class="text-muted">{{ $init->updated_at->format('M d, Y') }}</strong></li>
                        </ul>
                    </div>
                    <div class="initiative-card-footer">
                        <a href="{{ route('gov.tracking.initiatives.show', $init->id) }}" class="btn btn-default btn-sm">View Archive &rarr;</a>
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
        <div class="col-md-6 col-md-offset-3 text-center" style="margin-top: 40px; padding: 40px; background-color: #ffffff; border: 1px dashed #cbd5e1; border-radius: 8px;">
            <i class="fa fa-briefcase text-muted" style="font-size: 48px; margin-bottom: 20px;"></i>
            <h3 style="font-weight: bold; margin-top: 0; color: #1e293b;">No Initiatives Yet</h3>
            <p class="text-muted" style="font-size: 15px; margin-bottom: 25px; line-height: 1.6;">
                Programme Tracking helps you:<br>
                <i class="fa fa-check text-green"></i> Organize projects and revenue budgets<br>
                <i class="fa fa-check text-green"></i> Create operational tracking codes<br>
                <i class="fa fa-check text-green"></i> Monitor physical deliveries<br>
                <i class="fa fa-check text-green"></i> Produce executive fiscal reports
            </p>
            <a href="{{ route('gov.tracking.initiatives.create') }}" class="btn btn-primary btn-lg">Launch First Initiative</a>
        </div>
    </div>
@endif

@stop