@extends('layouts/default')
@section('title', 'Programme Operations Portfolio')

@section('content')
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
                <div class="initiative-card initiative-card--planning">
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
                <div class="initiative-card initiative-card--closed">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title text-muted">🏁 {{ $init->title }}</h3>
                        <ul class="initiative-meta initiative-meta--flush">
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
                <div class="initiative-card initiative-card--archived">
                    <div class="initiative-card-body">
                        <h3 class="initiative-card-title text-muted">📁 {{ $init->title }}</h3>
                        <ul class="initiative-meta initiative-meta--flush">
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
        <div class="col-md-6 col-md-offset-3">
            <x-gs::empty-state icon="fa-briefcase" title="No Initiatives Yet">
                Programme Tracking helps you:<br>
                <i class="fa fa-check text-green"></i> Organize projects and revenue budgets<br>
                <i class="fa fa-check text-green"></i> Create operational tracking codes<br>
                <i class="fa fa-check text-green"></i> Monitor physical deliveries<br>
                <i class="fa fa-check text-green"></i> Produce executive fiscal reports
                <x-slot:actions>
                    <a href="{{ route('gov.tracking.initiatives.create') }}" class="btn btn-primary btn-lg">Launch First Initiative</a>
                </x-slot:actions>
            </x-gs::empty-state>
        </div>
    </div>
@endif

@stop