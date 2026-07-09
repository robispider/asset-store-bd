@extends('layouts/default')
@section('title', 'Emergency Override Console')
@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="box box-danger" style="border-top: 3px solid #c0392b;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-exclamation-triangle text-danger"></i> Execute Emergency Override</h3>
            </div>
            <form action="{{ route('gov.membership.override') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Target Employee</label>
                        <select name="user_id" class="form-control select2" required style="width:100%;">
                            <option value="">-- Search Employee --</option>
                            @foreach(\App\Models\User::all() as $u)
                                <option value="{{ $u->id }}">{{ $u->present()->fullName }} ({{ $u->username }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Override Action</label>
                        <select name="override_type" class="form-control" required>
                            <option value="strip_roles">Force Strip All Operational Roles</option>
                            <option value="force_release">Force Release Membership</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mandatory Justification <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required minlength="10" placeholder="State reason for overriding standard protocols..."></textarea>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('WARNING: This bypasses all clearance rules. Proceed?')">
                        <i class="fas fa-radiation"></i> Execute Override
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-shield-alt"></i> Compliance Audit Log</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Date</th><th>Executor</th><th>Target User</th><th>Action</th><th>Justification</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td><span class="label label-danger">{{ $log->executor->username ?? 'System' }}</span></td>
                                <td><strong>{{ $log->targetUser->present()->fullName ?? 'Unknown' }}</strong></td>
                                <td><code>{{ $log->override_type }}</code></td>
                                <td><small>{{ $log->reason }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No emergency overrides executed.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection