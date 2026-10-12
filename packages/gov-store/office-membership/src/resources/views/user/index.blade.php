@extends('layouts/default')

@section('title', __('office_membership::member.user_page_title'))

@section('content')
<div class="office-membership-theme">
@include('govmem::hooks.notices')
<div class="row">
    <!-- LEFT PANEL: Active Memberships and dynamic Clearance Engine indicators -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-id-badge"></i> {{ __('office_membership::member.user_active_memberships_title') }}</h3>
            </div>
            <div class="box-body table-responsive">
                <x-gs::table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('office_membership::member.user_table_office') }}</th>
                            <th>{{ __('office_membership::member.user_table_status') }}</th>
                            <th>{{ __('office_membership::member.user_table_clearance') }}</th>
                            <th class="om-inline-5a2f1a27">{{ __('office_membership::member.user_table_action') }}</th>
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
                                    <strong>{{ $mem->location->name ?? __('office_membership::member.user_table_office') }}</strong><br>
                                    <small class="text-muted">{{ $mem->location->company->name ?? __('office_membership::member.standalone') }}</small>
                                </td>
                                <td class="om-inline-092f0f30">
                                    @if($mem->status === 'active')
                                        <span class="label label-success">{{ __('office_membership::member.user_status_active') }}</span>
                                        @if($mem->is_home_office) <span class="label label-primary"><i class="fas fa-star"></i> {{ __('office_membership::member.user_status_home_base') }}</span> @endif
                                    @elseif($mem->status === 'release_requested')
                                        <span class="label bg-orange">{{ __('office_membership::member.user_status_release_requested') }}</span>
                                    @elseif($mem->status === 'released')
                                        <span class="label label-default">{{ __('office_membership::member.user_status_released') }}</span>
                                    @endif
                                </td>
                                <td class="om-inline-092f0f30">
                                    @if($mem->status === 'active')
                                        <ul class="list-unstyled om-inline-7fdeb8b8">
                                            @foreach($checks as $name => $result)
                                                <li class="{{ $result->isPassed ? 'text-success' : 'text-danger' }}">
                                                    <i class="fas {{ $result->isPassed ? 'fa-check-circle' : 'fa-times-circle' }}"></i> {{ $name }}
                                                    @if(!$result->isPassed)
                                                        <br><small class="text-muted om-inline-3119a226">{{ $result->reason }}</small>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted">{{ __('office_membership::member.user_clearance_na') }}</span>
                                    @endif
                                </td>
                                <td class="om-inline-092f0f30">
                                    @if($mem->status === 'active')
                                        <form action="{{ route('gov.membership.request-release', $mem->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-block {{ $isCleared ? 'btn-danger' : 'btn-default' }}" @if(!$isCleared) disabled title="{{ __('office_membership::member.clearance_blocks') }}" @endif onclick="return confirm('{{ __('office_membership::member.user_request_release_confirm') }}')">
                                                <i class="fas fa-sign-out-alt"></i> {{ __('office_membership::member.user_request_release_button') }}
                                            </button>
                                        </form>
                                    @else
                                        <button class="btn btn-sm btn-block btn-default" disabled><i class="fas fa-lock"></i> {{ __('office_membership::member.user_locked_button') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted om-inline-ed0f0aa0">{{ __('office_membership::member.user_no_memberships') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-gs::table>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Handshakes & Handovers -->
    <div class="col-md-5">
        
        <!-- VERIFICATION CODE GENERATOR WIDGET -->
        <div class="box box-success om-inline-709ea946">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-qrcode text-success"></i> {{ __('office_membership::member.user_credential_title') }}</h3>
            </div>
            <div class="box-body text-center om-inline-6fe3c4ac">
                <p class="text-muted om-inline-eca5fde5">
                    {{ __('office_membership::member.user_credential_hint') }}
                </p>
                
                @if(isset($activeToken) && $activeToken)
                    <div class="om-inline-20b7438c">
                        <span class="om-inline-d18dd8b8">{{ __('office_membership::member.user_token_active_label') }}</span>
                        <span class="om-inline-63a3af60">{{ $activeToken->token }}</span>
                        <span class="om-inline-5e98ec0d">
                            <i class="fas fa-clock"></i> {{ __('office_membership::member.expires') }}: {{ $activeToken->expires_at->diffForHumans() }}
                        </span>
                    </div>
                @else
                    <div class="om-inline-3312350a">
                        <span class="om-inline-c7560035"><i class="fas fa-lock"></i> {{ __('office_membership::member.user_token_no_active') }}</span>
                    </div>
                @endif

                <form action="{{ route('gov.membership.token.generate') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm btn-block">
                        <i class="fas fa-sync-alt"></i> {{ isset($activeToken) && $activeToken ? __('office_membership::member.user_token_regenerate') : __('office_membership::member.user_token_generate') }}
                    </button>
                </form>
            </div>
        </div>

        <!-- JOIN OFFICE VIA MASS INVITATION CODE WIDGET -->
        <div class="box box-primary om-inline-39a594e2">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-building text-primary"></i> {{ __('office_membership::member.user_join_title') }}</h3>
            </div>
            <form action="{{ route('gov.membership.join') }}" method="POST">
                @csrf
                <div class="box-body text-center om-inline-6fe3c4ac">
                    <p class="text-muted om-inline-eca5fde5">
                        {{ __('office_membership::member.user_join_hint') }}
                    </p>
                    <div class="form-group">
                        <input class="form-control text-center om-inline-b9a57387" type="text" name="office_code" placeholder="{{ __('office_membership::member.user_join_code_placeholder') }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                        <i class="fas fa-paper-plane"></i> {{ __('office_membership::member.user_join_send_button') }}
                    </button>
                </div>
            </form>
        </div>
        
        <!-- INCOMING HANDSHAKES PROPOSALS -->
        @if($incomingRequests->count() > 0)
        <div class="box box-warning om-inline-ce5e73b6">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-bell text-warning"></i> {{ __('office_membership::member.user_handover_title') }}</h3>
            </div>
            <div class="box-body">
                @foreach($incomingRequests as $inc)
                    <div class="om-inline-f76a3cae">
                        <strong>{{ $inc->outgoingUser ? $inc->outgoingUser->present()->fullName : __('office_membership::member.staff_unknown_employee') }}</strong> {{ __('office_membership::member.user_handover_delegate_text') }} 
                        <span class="label bg-orange om-inline-19b327ae">{{ __('office_membership::member.role_'.$inc->role_slug) }}</span> {{ __('office_membership::member.user_handover_role_to_you_for') }} <strong>{{ $inc->location->name ?? __('office_membership::member.staff_claim_hint') }}</strong>.
                        
                        <div class="om-inline-03804ad7">
                            <form action="{{ route('gov.membership.handshake.accept', $inc->id) }}" method="POST" class="om-inline-7bf38354">
                                @csrf <button class="btn btn-success btn-sm btn-block" onclick="return confirm('{{ __('office_membership::member.user_handover_accept_confirm') }}')"><i class="fas fa-check"></i> {{ __('office_membership::member.user_handover_accept_button') }}</button>
                            </form>
                            <form action="{{ route('gov.membership.handshake.reject', $inc->id) }}" method="POST" class="om-inline-7bf38354">
                                @csrf <button class="btn btn-danger btn-sm btn-block"><i class="fas fa-times"></i> {{ __('office_membership::member.user_handover_reject_button') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- MY ACTIVE RESPONSIBILITIES (DELEGATION CONTROLS) -->
        <div class="box box-default om-inline-80472c54">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-shield text-muted"></i> {{ __('office_membership::member.user_responsibilities_title') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted om-inline-d75cbef7">{{ __('office_membership::member.user_responsibilities_hint') }}</p>
                
                @forelse($myActiveRoles as $locId => $rolesList)
                    @php $locName = $memberships->firstWhere('location_id', $locId)?->location?->name ?? __('office_membership::member.user_table_office'); @endphp
                    <h5 class="om-inline-494240c8">{{ $locName }}</h5>
                    
                    <x-gs::table class="table table-condensed">
                        @foreach($rolesList as $roleType)
                            @php
                                $pendingOutgoing = $outgoingRequests->where('location_id', $locId)->where('role_slug', $roleType)->first();
                            @endphp
                            <tr>
                                <td class="om-inline-092f0f30">
                                    <span class="label bg-blue">{{ __('office_membership::member.role_'.$roleType) }}</span>
                                </td>
                                <td class="om-inline-b198ccc8">
                                    @if($pendingOutgoing)
                                        <span class="text-warning om-inline-cba5f610">
                                            <i class="fas fa-hourglass-half"></i> {{ __('office_membership::member.awaiting', ['name' => $pendingOutgoing->incomingUser?->first_name ?? __('office_membership::member.user_modal_colleague_label')]) }}
                                        </span>
                                        <form action="{{ route('gov.membership.handshake.cancel', $pendingOutgoing->id) }}" method="POST" class="om-inline-f8bef7f8">
                                            @csrf <button type="submit" class="btn btn-xs btn-default text-danger" title="{{ __('office_membership::member.cancel_request') }}"><i class="fas fa-times"></i></button>
                                        </form>
                                    @else
                                        <button class="btn btn-xs btn-default" data-location="{{ $locId }}" data-role="{{ $roleType }}" data-role-name="{{ __('office_membership::member.role_'.$roleType) }}" onclick="openDelegateModal(this.dataset.location, this.dataset.role, this.dataset.roleName)">
                                            <i class="fas fa-exchange-alt"></i> {{ __('office_membership::member.delegate') }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-gs::table>
                @empty
                    <div class="text-center text-muted om-inline-921a2ecf">{{ __('office_membership::member.user_no_active_roles') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- DELEGATION HANDSHAKE MODAL -->
<div class="modal fade" id="delegateModal" tabindex="-1" role="dialog" aria-labelledby="delegateModalTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('gov.membership.handshake.propose') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="delegateModalTitle"><i class="fas fa-exchange-alt"></i> {{ __('office_membership::member.user_modal_title') }}</h4>
                </div>
                <div class="modal-body">
                    <p>{{ __('office_membership::member.delegate_hint') }} <strong id="modalRoleName"></strong></p>
                    
                    <input type="hidden" name="location_id" id="modalLocId">
                    <input type="hidden" name="role_type" id="modalRoleType">

                    <div class="form-group">
                        <label for="colleagueSelector">{{ __('office_membership::member.user_modal_colleague_label') }}</label>
                        <select class="form-control om-inline-442a70a1" name="assigned_user_id" id="colleagueSelector" required>
                            <option value="">{{ __('office_membership::member.user_modal_colleague_placeholder') }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left" data-dismiss="modal">{{ __('office_membership::member.user_modal_cancel_button') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('office_membership::member.user_modal_propose_button') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
@endsection

@section('moar_scripts')
<script>
    var colleagues = @json($eligibleColleagues);
    var modalColleaguePlaceholder = @json(__('office_membership::member.user_modal_colleague_placeholder'));

    function openDelegateModal(locId, roleType, roleName) {
        $('#modalLocId').val(locId);
        $('#modalRoleType').val(roleType);
        $('#modalRoleName').text(roleName);
        
        var select = $('#colleagueSelector');
        select.empty().append(new Option(modalColleaguePlaceholder, ''));
        
        if (colleagues[locId]) {
            colleagues[locId].forEach(function(user) {
                select.append(new Option(user.first_name + ' ' + (user.last_name || '') + ' (' + user.username + ')', user.id));
            });
        }
        
        $('#delegateModal').modal('show');
    }
</script>
@endsection
