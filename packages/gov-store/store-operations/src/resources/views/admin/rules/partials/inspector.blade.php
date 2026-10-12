

<!-- TOP SECTION: CONTEXT BREADCRUMBS -->
<div class="detail-workspace-header">
    <div class="breadcrumb-trail">{{ __('storeops::storeops.rules_ui.global_baseline') }} &rsaquo; {{ $targetName }}</div>
    <h3  class="storeops-inline-104">{{ $targetName }}</h3>
</div>

<!-- BOTTOM GRID: THE SPLIT WORKSPACE -->
<div class="detail-grid">

    <!-- COLUMN 1: CENTER BEHAVIOR CANVAS (60% Width) -->
    <div class="detail-canvas-left">
        @foreach($effectiveRules as $groupName => $rules)
            <div class="behavior-section-title">
                {{ __('storeops::rules.'.strtolower(str_replace(' ', '_', $groupName))) }}
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
                        $statusText = __('storeops::rules.enabled');
                        $traceText = $isOverridden ? __('storeops::rules.override_here') : __('storeops::rules.from_source', ['source' => $source]);
                    } elseif ($behavior === 'DISABLE') {
                        $badgeClass = 'status-disabled';
                        $icon = '🔴';
                        $statusText = __('storeops::rules.disabled');
                        $traceText = $isOverridden ? __('storeops::rules.override_here') : __('storeops::rules.from_source', ['source' => $source]);
                    } else {
                        $badgeClass = 'status-optional';
                        $icon = '⚪';
                        $statusText = __('storeops::rules.optional');
                        $traceText = __('storeops::rules.default_behavior');
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
        <h5  class="storeops-inline-103">{{ __('storeops::storeops.rules_ui.actions') }}</h5>
        <div class="sidebar-widget-card storeops-inline-102" >
            <button class="btn btn-default btn-block text-left storeops-inline-101"  data-toggle="modal" data-target="#assignPolicyModal">
                <i class="fa fa-plus-circle text-blue storeops-inline-100" ></i> {{ __('storeops::storeops.rules_ui.assign_policy_file') }}
            </button>
            <button class="btn btn-default btn-block text-left storeops-inline-99"  onclick="window.location.href='{{ route('storeops.admin.rules.simulator') }}'">
                <i class="fa fa-flask text-green storeops-inline-98" ></i> {{ __('storeops::storeops.rules_ui.test_in_simulator') }}
            </button>
        </div>

        <!-- Assignments Panel -->
        <h5  class="storeops-inline-97">{{ __('storeops::storeops.rules_ui.assigned_standards') }}</h5>

        @if($assignments->isEmpty())
            <div class="alert storeops-inline-96" >
                <i class="fa fa-info-circle"></i> {{ __('storeops::storeops.rules_ui.no_policy_assignments_exist_directly_on_this_node_it_inherits_al') }}
            </div>
        @else
            @foreach($assignments as $assignment)
                <div class="sidebar-widget-card storeops-inline-95" >
                    <h5  class="storeops-inline-94">{{ $assignment->profile->name }}</h5>
                    <small class="text-muted storeops-inline-93" >
                        {{ __('storeops::rules.version') }}: v{{ $assignment->profile->version ?? '1.0' }} &bull; {{ __('storeops::rules.since') }}: {{ $assignment->effective_from->format('d M Y') }}
                    </small>

                    <div  class="storeops-inline-92">
                        <a href="{{ route('storeops.admin.rules.policies.edit', $assignment->profile_id) }}" class="btn btn-xs btn-default storeops-inline-91" >
                            <i class="fa fa-pencil"></i> {{ __('storeops::storeops.rules_ui.edit_rules') }}
                        </a>
                        <form action="{{ route('storeops.admin.rules.unassign', $assignment->id) }}" method="POST"  class="storeops-unassign storeops-inline-90">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-danger btn-block">
                                <i class="fa fa-times"></i> {{ __('storeops::storeops.rules_ui.unassign') }}
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
        <div class="modal-content storeops-inline-89" >
            <div class="modal-header bg-primary storeops-inline-88" >
                <button type="button" class="close storeops-inline-87" data-dismiss="modal" >&times;</button>
                <h4 class="modal-title storeops-inline-86" ><i class="fa fa-plus-circle"></i> {{ __('storeops::storeops.rules_ui.assign_policy_to_target') }}</h4>
            </div>

            <form action="{{ route('storeops.admin.rules.assign') }}" method="POST">
                @csrf
                <input type="hidden" name="target_type" value="{{ $dbTargetType }}">
                <input type="hidden" name="target_id" value="{{ $targetId }}">

                <div class="modal-body storeops-inline-85" >
                    <div class="form-group">
                        <label  class="storeops-inline-84">{{ __('storeops::storeops.rules_ui.target_context') }}</label>
                        <input type="text" class="form-control storeops-inline-83" value="{{ $targetName }}" disabled >
                    </div>

                    <div class="form-group storeops-inline-82" >
                        <label  class="storeops-inline-81">{{ __('storeops::storeops.rules_ui.select_published_policy_to_apply') }}</label>
                        <select name="profile_id" class="form-control storeops-inline-80" required >
                            <option value="">{{ __('storeops::storeops.rules_ui.choose_policy_template') }}</option>
                            @foreach($publishedProfiles as $profile)
                                <option value="{{ $profile->id }}">{{ $profile->name }} (v{{ $profile->version ?? '1.0' }})</option>
                            @endforeach
                        </select>
                        <p class="help-block storeops-inline-79" >
                            {{ __('storeops::storeops.rules_ui.applying_a_policy_immediately_replaces_any_existing_active_assig') }}
                        </p>
                    </div>
                </div>

                <div class="modal-footer storeops-inline-78" >
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('storeops::storeops.rules_ui.cancel') }}</button>
                    <button type="submit" class="btn btn-primary storeops-inline-77" >
                        {{ __('storeops::storeops.rules_ui.apply_assignment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>