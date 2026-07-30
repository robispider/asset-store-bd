@extends('layouts/default')
@section('title', 'Workspace: ' . $initiative->title)

@section('content')
<!-- Header Banner -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid" style="border-top: 4px solid #3c8dbc;">
            <div class="box-body">
                <h2 class="text-blue" style="margin-top: 0; font-weight: bold;">
                    <i class="fa fa-umbrella"></i> {{ strtoupper($initiative->title) }}
                </h2>
                <p class="lead text-muted">{{ $initiative->purpose ?? 'No description provided.' }}</p>
                <hr>
                <div class="row text-center">
                    <div class="col-sm-3 border-right">
                        <span class="description-text">STATUS</span>
                        <h4 class="description-header text-{{ $initiative->status == 'Active' ? 'green' : 'yellow' }}">{{ strtoupper($initiative->status) }}</h4>
                    </div>
                    <div class="col-sm-3 border-right">
                        <span class="description-text">FUNDING SOURCE</span>
                        <h4 class="description-header">{{ $initiative->fundingType->name ?? 'N/A' }}</h4>
                    </div>
                    <div class="col-sm-3 border-right">
                        <span class="description-text">OWNING ORGANIZATION</span>
                        <h4 class="description-header">{{ $initiative->ownerCompany->name ?? 'Unknown' }}</h4>
                    </div>
                    <div class="col-sm-3">
                        <span class="description-text">OVERALL HEALTH</span>
                        <h4 class="description-header text-{{ $health['percentage'] >= 100 ? 'green' : ($health['percentage'] > 0 ? 'aqua' : 'muted') }}">
                            {{ $health['percentage'] }}% 
                            <small class="text-muted" style="display:block; font-size:12px; font-weight:normal; margin-top:2px;">
                                {{ number_format($health['received']) }} / {{ number_format($health['planned']) }} Items
                            </small>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-md-12">
        <h4 class="text-uppercase" style="margin-top: 10px; margin-bottom: 20px;">
            <i class="fa fa-bolt text-yellow"></i> What would you like to do?
        </h4>
        <a href="{{ route('gov.tracking.initiatives.tracking-codes.create', $initiative->id) }}" class="btn btn-app"><i class="fa fa-plus text-green"></i> Add Tracking Code / Task</a>
        <a href="{{ route('gov.tracking.initiatives.retrospective.index', $initiative->id) }}" class="btn btn-app"><i class="fa fa-tags text-aqua"></i> Assign Legacy Assets</a>
        <a href="#" class="btn btn-app"><i class="fa fa-bar-chart text-purple"></i> View Progress Report</a>
        <a href="{{ route('gov.tracking.initiatives.edit', $initiative->id) }}" class="btn btn-app"><i class="fa fa-cog text-gray"></i> Edit Umbrella Rules</a>
    </div>
</div>

<!-- Main Workspace Body -->
<div class="row" style="margin-top: 20px;">
    <!-- Active Tracking Codes (Tasks) -->
    <div class="col-md-8">
        <!-- Main Workspace Body (Inside workspace.blade.php) -->
<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-list"></i> Active Tracking Codes (Tasks & Components)</h3>
    </div>
    <div class="box-body" style="padding: 0;">
        @if($trackingCodes->isEmpty())
            <div class="alert alert-default text-center text-muted" style="background-color: #f9fafb; margin: 15px; border: 1px dashed #d2d6de;">
                <h4>No Executable Tasks Yet</h4>
                <p>This Initiative umbrella is empty. Click "Add Tracking Code / Task" above to define execution goals.</p>
            </div>
        @else
    <ul class="products-list product-list-in-box">
        @foreach($trackingCodes as $code)
            <li class="item" style="padding: 15px;">
                <div class="product-info" style="margin-left: 0;">
                    <!-- Task Header -->
                    <span class="product-title text-blue" style="font-size: 16px;">
                        <strong>Code: {{ $code->tracking_code }}</strong> | {{ $code->task_title }}
                        
                        <!-- Status Badge -->
                        @php
                            $statusBg = $code->status == 'ACTIVE' ? 'green' : ($code->status == 'DRAFT' ? 'yellow' : 'gray');
                        @endphp
                        <span class="label bg-{{ $statusBg }}" style="margin-left: 10px;">{{ $code->status }}</span>
                        
                        <!-- Header PDF View -->
                        @if($code->order_pdf_path)
                            <a href="{{ route('gov.tracking.tracking-codes.download', $code->id) }}" class="label label-info pull-right" style="margin-left: 5px;"><i class="fa fa-download"></i> View PDF</a>
                        @endif
                    </span>
                    
                    <!-- Fiscal Profile -->
                    <span class="product-description" style="margin-top: 5px; color: #555;">
                        <i class="fa fa-bank text-muted"></i> FY: <strong>{{ $code->fiscal_year }}</strong> | 
                        Fund Source: <strong>{{ $code->fundingType->name ?? 'N/A' }}</strong> | 
                        
                        <!-- Dynamic Scopes Resolution -->
                        @php
                            $geoScope = $code->scopes->where('dimension', 'GEOGRAPHY')->first();
                            $partScope = $code->scopes->where('dimension', 'PARTICIPANTS')->first();
                            
                            $geoDisplay = ($geoScope && $geoScope->target_type === 'GeoArea' && class_exists('GovStore\GeoAreas\Models\GeoArea')) 
                                ? \GovStore\GeoAreas\Models\GeoArea::find($geoScope->target_id)->en_name ?? 'Specific Region' 
                                : 'Entire Bangladesh';
                                
                            $partDisplay = ($partScope && $partScope->target_type === 'CrossTenant') 
                                ? '<span class="label label-warning" style="font-size:10px;"><i class="fa fa-exchange"></i> Cross-Ministry</span>' 
                                : ($partScope && $partScope->target_type === 'SpecificLocations' 
                                    ? '<span class="label label-primary" style="font-size:10px;"><i class="fa fa-map-marker"></i> Specific Warehouses</span>'
                                    : '<span class="label label-default" style="font-size:10px;">Internal</span>');
                        @endphp
                        Coverage: <strong>{{ $geoDisplay }}</strong> {!! $partDisplay !!}
                    </span>
                    
                    <!-- Dynamic Targets / Progress -->
                    <div style="margin-top: 15px; padding: 10px; background-color: #f4f4f4; border-radius: 4px;">
                        <h5 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 5px;"><strong>Quantitative Targets (Economic Sub-ledgers)</strong></h5>
                        <div class="row">
                            @foreach($code->targets as $target)
                                @php
                                    $prog = $target->progress ?? ['percentage' => 0, 'is_exceeded' => false, 'received' => 0, 'planned' => $target->planned_qty];
                                    $barColor = $prog['is_exceeded'] ? 'progress-bar-yellow' : ($prog['percentage'] >= 100 ? 'progress-bar-success' : 'progress-bar-aqua');
                                    $textColor = $prog['is_exceeded'] ? 'text-yellow' : ($prog['percentage'] >= 100 ? 'text-green' : 'text-muted');
                                @endphp
                                
                                <div class="col-sm-6" style="margin-bottom: 10px;">
                                    <i class="fa fa-cube text-muted"></i> <strong>{{ $target->category->name }}</strong>
                                    @if($target->economic_code)
                                        <span class="text-muted text-sm">(Econ: {{ $target->economic_code }})</span>
                                    @endif
                                    
                                    <div class="progress progress-xs" style="margin-top: 5px; margin-bottom: 2px;">
                                        <div class="progress-bar {{ $barColor }}" style="width: {{ $prog['percentage'] > 100 ? 100 : $prog['percentage'] }}%"></div>
                                    </div>
                                    
                                    <span class="{{ $textColor }} text-sm">
                                        <strong>Progress: {{ number_format($prog['received']) }} / {{ number_format($prog['planned']) }}</strong> 
                                        ({{ $prog['percentage'] }}%)
                                        @if($prog['is_exceeded'])
                                            <i class="fa fa-warning" title="Overshoot Detected"></i>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- CONDITIONAL ACTION WORKFLOWS -->
                    <div style="margin-top: 10px;" class="text-right">
                        @if($code->status === 'DRAFT')
                            <!-- DRAFT STATE: ALLOW EDIT & DELETIONS & PROMOTIONS -->
                            <a href="{{ route('gov.tracking.initiatives.tracking-codes.edit', [$initiative->id, $code->id]) }}" class="btn btn-xs btn-warning" style="margin-right: 5px;"><i class="fa fa-pencil"></i> Edit Properties</a>
                            
                            <form action="{{ route('gov.tracking.initiatives.tracking-codes.activate', [$initiative->id, $code->id]) }}" method="POST" style="display:inline-block; margin-right: 5px;">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-success" onclick="return confirm('Turn this code ACTIVE? This will lock all targets and allow storekeepers to select it.')"><i class="fa fa-play"></i> Activate & Lock</button>
                            </form>
                            
                            <form action="{{ route('gov.tracking.initiatives.tracking-codes.destroy', [$initiative->id, $code->id]) }}" method="POST" style="display:inline-block;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this draft code permanently?')"><i class="fa fa-trash"></i> Delete</button>
                            </form>
                        @elseif($code->status === 'ACTIVE')
                            <!-- ACTIVE STATE: ONLY PERMIT ARCHIVING (IMMUTABLE LOCK ACTIVE) -->
                            <span class="text-muted text-sm pull-left" style="margin-top: 5px;"><i class="fa fa-lock"></i> Ledger locked. Editing disabled.</span>
                            
                            <form action="{{ route('gov.tracking.initiatives.tracking-codes.archive', [$initiative->id, $code->id]) }}" method="POST" style="display:inline-block;">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-default" onclick="return confirm('Archive this code? Storekeepers will no longer be able to select it in new GRNs.')"><i class="fa fa-archive"></i> Retrospective Archive</button>
                            </form>
                        @else
                            <!-- ARCHIVED STATE: ABSOLUTELY IMMUTABLE -->
                            <span class="text-muted text-sm" style="display:block; margin-top: 5px;"><i class="fa fa-archive"></i> Task archived. Historical records retained for audits.</span>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
    </div>
</div>
        
        <!-- Issues / Exceptions (Phase 7) -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title text-red"><i class="fa fa-warning"></i> Issues Needing Attention</h3>
            </div>
            <div class="box-body">
                <p class="text-muted"><i class="fa fa-check-circle text-green"></i> System compliance scan shows no active violations.</p>
            </div>
        </div>
    </div>

    <!-- Right Column: Governance & Activity -->
    <div class="col-md-4">
        <!-- Governance & Rules -->
        <div class="box box-solid bg-gray-light">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-shield"></i> Governance & Rules</h3>
            </div>
            <div class="box-body">
                <ul class="list-unstyled" style="line-height: 2.5;">
                    <li><strong>Who manages this initiative?</strong><br> ➔ <span class="text-blue">{{ $initiative->managingOffice->name ?? 'N/A' }}</span></li>
                    <li><strong>Do tracking codes require PDFs?</strong><br> ➔ {!! $initiative->require_documents ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>' !!}</li>
                    <li><strong>Is target overshoot allowed?</strong><br> ➔ {!! $initiative->allow_overshoot ? '<span class="label label-warning">Yes (Inform Only)</span>' : '<span class="label label-danger">No (Requires Override)</span>' !!}</li>
                </ul>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-clock-o"></i> Recent Activity</h3>
            </div>
            <div class="box-body" style="max-height: 400px; overflow-y: auto;">
                <ul class="timeline timeline-inverse" style="margin-bottom: 0;">
                    @forelse($recentActivity as $event)
                        <li>
                            <!-- Dynamic Icon based on Event Type -->
                            @php
                                $icon = 'fa-exchange bg-aqua';
                                if($event->event_type === 'OVERSHOOT_OVERRIDE_LOGGED') $icon = 'fa-warning bg-yellow';
                                if($event->event_type === 'RETROSPECTIVE_TAGGING') $icon = 'fa-tags bg-green';
                            @endphp
                            
                            <i class="fa {{ $icon }}"></i>
                            <div class="timeline-item border-0" style="background: transparent;">
                                <span class="time"><i class="fa fa-clock-o"></i> {{ $event->occurred_at->diffForHumans() }}</span>
                                <h3 class="timeline-header no-border" style="font-size: 13px;">
                                    <strong>{{ str_replace('_', ' ', $event->event_type) }}</strong>
                                </h3>
                                <div class="timeline-body" style="padding-top: 0; padding-bottom: 5px; color: #666; font-size: 13px;">
                                    {{ $event->description }}
                                    @if($event->actor)
                                        <br><small class="text-muted">— by {{ $event->actor->first_name }} {{ $event->actor->last_name }}</small>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-center text-muted" style="padding: 20px 0;">No operational activity recorded yet.</li>
                    @endforelse
                    
                    <li>
                        <i class="fa fa-flag bg-blue"></i>
                        <div class="timeline-item border-0" style="background: transparent;">
                            <span class="time"><i class="fa fa-clock-o"></i> {{ $initiative->created_at->format('M d, Y') }}</span>
                            <h3 class="timeline-header no-border" style="font-size: 13px;">Initiative Created</h3>
                        </div>
                    </li>
                    <li><i class="fa fa-clock-o bg-gray"></i></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@stop