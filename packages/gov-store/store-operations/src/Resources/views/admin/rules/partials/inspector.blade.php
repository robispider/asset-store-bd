<style>
    /* GitHub-Style Workspace Detail Pane */
    .detail-workspace-header {
        background: #f8fafc;
        padding: 20px 30px;
        border-bottom: 1px solid #e2e8f0;
    }
    .breadcrumb-trail {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
        color: #64748b;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .breadcrumb-trail i { font-size: 10px; color: #94a3b8; }
    .breadcrumb-trail .active { color: #0f172a; font-weight: bold; }

    /* Split-Pane Grid */
    .detail-grid {
        display: grid;
        grid-template-columns: 1.4fr 1fr; /* 3:2 Balance */
        min-height: 550px;
    }
    .detail-canvas-left {
        padding: 30px;
        border-right: 1px solid #e2e8f0;
    }
    .detail-sidebar-right {
        padding: 30px;
        background: #fcfdfe;
    }

    /* Effective Behavior Cards */
    .behavior-section-title {
        font-size: 12px;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 15px;
        border-bottom: 2px solid #cbd5e1;
        padding-bottom: 5px;
    }
    .behavior-card-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #fff;
        margin-bottom: 12px;
    }
    .behavior-details { display: flex; flex-direction: column; }
    .behavior-title { font-weight: bold; font-size: 14px; color: #1e293b; }
    .behavior-subtext { font-size: 12px; color: #64748b; margin-top: 2px; }

    /* Visual Status Labels */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 12px;
        font-size: 11.5px;
        font-weight: bold;
    }
    .status-enabled  { background-color: #d1fae5; color: #065f46; }
    .status-disabled { background-color: #fee2e2; color: #991b1b; }
    .status-optional { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    /* Sidebar Panels */
    .sidebar-widget-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
    }
</style>

<!-- TOP SECTION: CONTEXT BREADCRUMBS -->
<div class="detail-workspace-header">
    <div class="breadcrumb-trail">
        <span>Global Baseline</span> <i class="fa fa-angle-right"></i>
        <span>ICT Ministry</span> <i class="fa fa-angle-right"></i>
        @if($targetType === 'LOCATION')
            <span class="active">{{ $targetName }}</span>
        @else
            <span>Dhaka HQ</span> <i class="fa fa-angle-right"></i>
            <span class="active">{{ $targetName }}</span>
        @endif
    </div>
    <h3 style="margin: 0; font-weight: 800; color: #0f172a;">{{ $targetName }}</h3>
</div>

<!-- BOTTOM GRID: THE SPLIT WORKSPACE -->
<div class="detail-grid">
    
    <!-- COLUMN 1: CENTER BEHAVIOR CANVAS (60% Width) -->
    <div class="detail-canvas-left">
        @foreach($effectiveRules as $groupName => $rules)
            <div class="behavior-section-title">
                @if($groupName === 'Receiving Validation' || $groupName === 'Information Requirements')
                    ⚙️ When Receiving Inbound Goods
                @elseif($groupName === 'Inventory Automation' || $groupName === 'Execution Automation')
                    📦 When Posting to Ledger
                @else
                    {{ $groupName }}
                @endif
            </div>

            @foreach($rules as $code => $data)
                @php
                    $behavior = $data['state']['behavior'] ?? 'INHERIT';
                    $source = $data['state']['source_policy'] ?? 'None';
                    $ruleLayer = $data['state']['layer'] ?? 'GLOBAL';

                    // Detect if overridden directly at the current node layer
                    $isOverridden = ($targetType === 'LOCATION' && $ruleLayer === 'LOCATION') || 
                                    ($targetType === 'CATEGORY' && $ruleLayer === 'CATEGORY');

                    if ($behavior === 'ENFORCE') {
                        $badgeClass = 'status-enabled';
                        $icon = '🟢';
                        $statusText = 'Enabled';
                        $traceText = $isOverridden ? 'Overridden Here' : 'Inherited from ' . $source;
                    } elseif ($behavior === 'DISABLE') {
                        $badgeClass = 'status-disabled';
                        $icon = '🔴';
                        $statusText = 'Disabled';
                        $traceText = $isOverridden ? 'Disabled Here' : 'Blocked by ' . $source;
                    } else {
                        $badgeClass = 'status-optional';
                        $icon = '⚪';
                        $statusText = 'Optional';
                        $traceText = 'Default behavior (No active policy)';
                    }
                @endphp

                <div class="behavior-card-row">
                    <div class="behavior-details">
                        <span class="behavior-title">{{ $data['name'] }}</span>
                        <span class="behavior-subtext">{{ $traceText }}</span>
                    </div>
                    <div>
                        <span class="badge-status {{ $badgeClass }}">
                            <span>{{ $icon }}</span> {{ $statusText }}
                        </span>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    <!-- COLUMN 2: AUXILIARY SIDEBAR (40% Width) -->
    <div class="detail-sidebar-right">
        
        <!-- Quick Actions Panel -->
        <h5 style="font-weight: bold; color: #475569; text-transform: uppercase; margin-bottom: 15px;">Actions</h5>
        <div class="sidebar-widget-card" style="border-left: 4px solid #3b82f6;">
            <button class="btn btn-default btn-block text-left" style="margin-bottom:10px; padding: 8px 12px;" data-toggle="modal" data-target="#assignPolicyModal">
                <i class="fa fa-plus-circle text-blue" style="margin-right:8px;"></i> Assign Policy File
            </button>
            <button class="btn btn-default btn-block text-left" style="padding: 8px 12px;" onclick="window.location.href='{{ route('storeops.admin.rules.simulator') }}'">
                <i class="fa fa-flask text-green" style="margin-right:8px;"></i> Test in Simulator
            </button>
        </div>

        <!-- Assignments Panel -->
        <h5 style="font-weight: bold; color: #475569; text-transform: uppercase; margin-bottom: 15px;">Assigned Standards</h5>
        
        @if($assignments->isEmpty())
            <div class="alert" style="background: #f1f5f9; border: 1px dashed #94a3b8; color: #475569; font-size:12.5px; padding: 15px;">
                <i class="fa fa-info-circle"></i> No policy assignments exist directly on this node. It inherits all configurations.
            </div>
        @else
            @foreach($assignments as $assignment)
                <div class="sidebar-widget-card" style="border-left: 4px solid #10b981; position: relative;">
                    <h5 style="font-weight: bold; color: #0f172a; margin-top: 0; margin-bottom: 5px;">{{ $assignment->profile->name }}</h5>
                    <small class="text-muted" style="display: block; margin-bottom: 15px;">
                        Version: v{{ $assignment->profile->version ?? '1.0' }} &bull; Since: {{ $assignment->effective_from->format('d M Y') }}
                    </small>
                    
                    <div style="display: flex; gap: 8px;">
                        <a href="{{ route('storeops.admin.rules.policies.edit', $assignment->profile_id) }}" class="btn btn-xs btn-default" style="flex:1;">
                            <i class="fa fa-pencil"></i> Edit Rules
                        </a>
                        <form action="{{ route('storeops.admin.rules.unassign', $assignment->id) }}" method="POST" style="flex:1;" onsubmit="return confirm('Unassign this policy from this target?');">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-danger btn-block">
                                <i class="fa fa-times"></i> Unassign
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif

    </div>
</div>

<!-- NESTED ASSIGNMENT MODAL (Supports Locations & Categories cleanly) -->
<div class="modal fade" id="assignPolicyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 6px; overflow: hidden;">
            <div class="modal-header bg-primary" style="background: #3c8dbc; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;"><i class="fa fa-plus-circle"></i> Assign Policy to Target</h4>
            </div>
            
            <form action="{{ route('storeops.admin.rules.assign') }}" method="POST">
                @csrf
                <input type="hidden" name="target_type" value="{{ $dbTargetType }}">
                <input type="hidden" name="target_id" value="{{ $targetId }}">

                <div class="modal-body" style="padding: 25px;">
                    <div class="form-group">
                        <label style="color: #475569; font-weight: bold; margin-bottom: 8px;">Target Context:</label>
                        <input type="text" class="form-control" value="{{ $targetName }}" disabled style="background: #f8fafc; font-weight: bold;">
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label style="color: #475569; font-weight: bold; margin-bottom: 8px;">Select Published Policy to Apply:</label>
                        <select name="profile_id" class="form-control" required style="height: 40px; border-radius: 4px;">
                            <option value="">-- Choose Policy Template --</option>
                            @foreach($publishedProfiles as $profile)
                                <option value="{{ $profile->id }}">{{ $profile->name }} (v{{ $profile->version ?? '1.0' }})</option>
                            @endforeach
                        </select>
                        <p class="help-block" style="font-size: 11.5px; color: #64748b; margin-top: 6px;">
                            Applying a policy immediately replaces any existing active assignment on this specific target.
                        </p>
                    </div>
                </div>

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 15px 25px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: bold;">
                        Apply Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>