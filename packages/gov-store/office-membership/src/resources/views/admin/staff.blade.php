@extends('layouts/default')

@section('title', 'Staff Management: ' . $location->name)

@section('content')
<div class="row">
    <!-- LEFT PANEL: Active Staff -->
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-users"></i> Active Office Staff ({{ $activeStaff->count() }})</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr><th>Employee</th><th>Username</th><th>Membership Type</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($activeStaff as $mem)
                            <tr>
                                <td>
                                    <!-- DEFENSIVE CHECK: Prevents 500 errors if user record is orphaned -->
                                    <strong>{{ $mem->user ? $mem->user->present()->fullName : 'Unknown Employee' }}</strong>
                                </td>
                                <td>{{ $mem->user->username ?? '-' }}</td>
                                <td>
                                    @if($mem->is_home_office) 
                                        <span class="label bg-blue">Home Base</span> 
                                    @else 
                                        <span class="label bg-gray">Secondary</span> 
                                    @endif
                                </td>
                                <td><span class="text-success"><i class="fas fa-check-circle"></i> Active</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No active staff.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Onboarding Tools -->
    <div class="col-md-4">
        
        <!-- PENDING REQUESTS -->
        @if($pendingMemberships->count() > 0)
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-clock text-warning"></i> Pending Join Requests ({{ $pendingMemberships->count() }})</h3></div>
            <div class="box-body no-padding">
                <ul class="nav nav-stacked">
                    @foreach($pendingMemberships as $req)
                    <li style="padding: 10px 15px; border-bottom: 1px solid #f4f4f4; display: flex; justify-content: space-between;">
                        <div>
                            <!-- DEFENSIVE CHECK -->
                            <strong>{{ $req->user ? $req->user->present()->fullName : 'Unknown Employee' }}</strong><br>
                            <small class="text-muted">{{ $req->user->username ?? '-' }}</small>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <form action="{{ route('gov.membership.admin.approve', $req->id) }}" method="POST">@csrf <button class="btn btn-xs btn-success"><i class="fas fa-check"></i></button></form>
                            <form action="{{ route('gov.membership.admin.reject', $req->id) }}" method="POST">@csrf <button class="btn btn-xs btn-danger"><i class="fas fa-times"></i></button></form>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- ADD BY VERIFICATION CODE -->
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-user-plus text-success"></i> Add External Employee</h3></div>
            <form action="{{ route('gov.membership.admin.add-employee') }}" method="POST">
                @csrf
                <div class="box-body">
                    <p class="text-muted" style="font-size: 12px;">Enter the employee's username and 6-character personal verification code.</p>
                    <div class="form-group"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
                    <div class="form-group"><input type="text" name="verification_code" class="form-control" placeholder="6-Char Code" required maxlength="6" style="text-transform: uppercase;"></div>
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-success btn-block">Verify & Add</button></div>
            </form>
        </div>

        <!-- MASS INVITATION CODE -->
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-bullhorn text-primary"></i> Mass Invitation Code</h3></div>
            <div class="box-body text-center">
                @if($profile->invitation_code && $profile->invitation_code_expires_at->isFuture())
                    <p class="text-muted" style="font-size: 12px;">Share this code with employees to allow them to join.</p>
                    <div style="background: #f4f4f4; border: 1px dashed #ccc; padding: 10px; margin-bottom: 10px;">
                        <span style="font-size: 24px; font-weight: bold; letter-spacing: 3px;">{{ $profile->invitation_code }}</span><br>
                        <span class="text-danger" style="font-size: 10px;">Expires: {{ $profile->invitation_code_expires_at->format('Y-m-d') }}</span>
                    </div>
                @else
                    <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> No active invitation code.</p>
                @endif
                <form action="{{ route('gov.membership.admin.generate-invite-code') }}" method="POST">
                    @csrf <button type="submit" class="btn btn-default btn-sm btn-block">Generate New Code</button>
                </form>
            </div>
        </div>

        <!-- CLAIM TRANSFERRED EMPLOYEE -->
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-exchange-alt"></i> Claim Transferred Employee</h3></div>
            <form action="{{ route('gov.membership.claim') }}" method="POST">
                @csrf
                <div class="box-body">
                    <p class="text-muted" style="font-size:12px;">Search for employees who have officially requested release from their previous home office.</p>
                    <div class="form-group">
                        <select name="user_id" class="form-control select2" required style="width: 100%;">
                            <option value="">-- Select Released Employee --</option>
                            @foreach($floatingUsers as $u)
                                <!-- DEFENSIVE OPTIONAL WRAPPER -->
                                <option value="{{ $u->id }}">{{ optional($u->present())->fullName ?? 'Unknown User' }} ({{ $u->username }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-default btn-block">Approve Transfer & Claim</button></div>
            </form>
        </div>

    </div>
</div>
@endsection