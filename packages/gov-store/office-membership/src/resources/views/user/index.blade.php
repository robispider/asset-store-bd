@extends('layouts/default')

@section('title', 'My Office Memberships & Handovers')

@section('content')
<div class="row">
    <!-- LEFT PANEL: Active Memberships and dynamic Clearance Engine indicators -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-id-badge"></i> Active Office Memberships</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Office Building</th>
                            <th>Membership Status</th>
                            <th>Clearance Rules</th>
                            <th style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($memberships as $mem)
                            @php
                                $checks = $clearanceMatrix[$mem->id] ?? [];
                                $isCleared = isset($engine) ? $engine->isCleared($checks) : false;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $mem->location->name ?? 'Unknown Location' }}</strong><br>
                                    <small class="text-muted">{{ $mem->location->company->name ?? 'Standalone' }}</small>
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($mem->status === 'active')
                                        <span class="label label-success">Active</span>
                                        @if($mem->is_home_office) <span class="label label-primary"><i class="fas fa-star"></i> Home Base</span> @endif
                                    @elseif($mem->status === 'release_requested')
                                        <span class="label bg-orange">Release Requested</span>
                                    @elseif($mem->status === 'released')
                                        <span class="label label-default">Released</span>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($mem->status === 'active')
                                        <ul class="list-unstyled" style="margin-bottom: 0; font-size: 12px; line-height: 1.6;">
                                            @foreach($checks as $name => $result)
                                                <li class="{{ $result->isPassed ? 'text-success' : 'text-danger' }}">
                                                    <i class="fas {{ $result->isPassed ? 'fa-check-circle' : 'fa-times-circle' }}"></i> {{ $name }}
                                                    @if(!$result->isPassed)
                                                        <br><small class="text-muted" style="margin-left: 15px;">{{ $result->reason }}</small>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($mem->status === 'active')
                                        <form action="{{ route('gov.membership.request-release', $mem->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-block {{ $isCleared ? 'btn-danger' : 'btn-default' }}" {{ $isCleared ? '' : 'disabled title="Clearance blocks exist"' }} onclick="return confirm('Request formal release from this office?')">
                                                <i class="fas fa-sign-out-alt"></i> Request Release
                                            </button>
                                        </form>
                                    @else
                                        <button class="btn btn-sm btn-block btn-default" disabled><i class="fas fa-lock"></i> Locked</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted" style="padding: 30px;">You do not belong to any registered office.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Handshakes & Handovers -->
    <div class="col-md-5">
        
        <!-- INCOMING HANDSHAKES PROPOSALS -->
        @if($incomingRequests->count() > 0)
        <div class="box box-warning" style="border-top: 3px solid #f39c12;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-bell text-warning"></i> Action Required: Incoming Handovers</h3>
            </div>
            <div class="box-body">
                @foreach($incomingRequests as $inc)
                    <div style="padding: 12px; border: 1px solid #faebcc; background: #fffcf5; border-radius: 4px; margin-bottom: 12px;">
                        <strong>{{ $inc->outgoingUser->present()->fullName }}</strong> wishes to delegate the 
                        <span class="label bg-orange" style="font-size: 11px;">{{ ucwords(str_replace('_', ' ', $inc->role_type)) }}</span> role to you for <strong>{{ $inc->location->name }}</strong>.
                        
                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <form action="{{ route('gov.membership.handshake.accept', $inc->id) }}" method="POST" style="flex: 1;">
                                @csrf <button class="btn btn-success btn-sm btn-block" onclick="return confirm('Confirm acceptance? This updates active database roles instantly.')"><i class="fas fa-check"></i> Accept</button>
                            </form>
                            <form action="{{ route('gov.membership.handshake.reject', $inc->id) }}" method="POST" style="flex: 1;">
                                @csrf <button class="btn btn-danger btn-sm btn-block"><i class="fas fa-times"></i> Reject</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- MY ACTIVE RESPONSIBILITIES (DELEGATION CONTROLS) -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-shield"></i> Handover Office Responsibilities</h3>
            </div>
            <div class="box-body">
                <p class="text-muted" style="font-size: 13px;">If you hold an active role, you cannot be released. You must delegate your role to a colleague below.</p>
                
                @forelse($myActiveRoles as $locId => $rolesList)
                    @php $locName = \App\Models\Location::find($locId)->name ?? 'Office'; @endphp
                    <h5 style="font-weight: bold; margin-top: 15px; border-bottom: 1px solid #f4f4f4; padding-bottom: 6px;">{{ $locName }}</h5>
                    
                    <table class="table table-condensed">
                        @foreach($rolesList as $roleType)
                            @php
                                $pendingOutgoing = $outgoingRequests->where('location_id', $locId)->where('role_type', $roleType)->first();
                            @endphp
                            <tr>
                                <td style="vertical-align: middle;">
                                    <span class="label bg-blue">{{ ucwords(str_replace('_', ' ', $roleType)) }}</span>
                                </td>
                                <td style="vertical-align: middle; text-align: right;">
                                    @if($pendingOutgoing)
                                        <span class="text-warning" style="font-size: 12px; margin-right: 10px;"><i class="fas fa-hourglass-half"></i> Awaiting {{ $pendingOutgoing->incomingUser->first_name }}</span>
                                        <form action="{{ route('gov.membership.handshake.cancel', $pendingOutgoing->id) }}" method="POST" style="display:inline;">
                                            @csrf <button type="submit" class="btn btn-xs btn-default text-danger" title="Cancel Request"><i class="fas fa-times"></i></button>
                                        </form>
                                    @else
                                        <!-- Fixed Syntax Parameter below -->
                                        <button class="btn btn-xs btn-default" onclick="openDelegateModal({{ $locId }}, '{{ $roleType }}', '{{ ucwords(str_replace('_', ' ', $roleType)) }}')">
                                            <i class="fas fa-exchange-alt"></i> Delegate
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @empty
                    <div class="text-center text-muted" style="padding: 15px; font-size: 13px;">You currently hold no administrative roles inside active offices.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- DELEGATION HANDSHAKE MODAL -->
<div class="modal fade" id="delegateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('gov.membership.handshake.propose') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-exchange-alt"></i> Propose Role Delegation Handshake</h4>
                </div>
                <div class="modal-body">
                    <p>Select a local colleague to take over the <strong id="modalRoleName"></strong> role. Once they accept, you will be cleared from this responsibility.</p>
                    
                    <input type="hidden" name="location_id" id="modalLocId">
                    <input type="hidden" name="role_type" id="modalRoleType">

                    <div class="form-group">
                        <label>Select Colleague</label>
                        <select name="assigned_user_id" id="colleagueSelector" class="form-control" required style="width: 100%;">
                            <option value="">-- Choose Colleague --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Propose Handover</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
    var colleagues = @json($eligibleColleagues);

    function openDelegateModal(locId, roleType, roleName) {
        $('#modalLocId').val(locId);
        $('#modalRoleType').val(roleType);
        $('#modalRoleName').text(roleName);
        
        var select = $('#colleagueSelector');
        select.empty().append('<option value="">-- Choose Colleague --</option>');
        
        if (colleagues[locId]) {
            colleagues[locId].forEach(function(user) {
                select.append('<option value="' + user.id + '">' + user.first_name + ' ' + user.last_name + ' (' + user.username + ')</option>');
            });
        }
        
        $('#delegateModal').modal('show');
    }
</script>
@endsection