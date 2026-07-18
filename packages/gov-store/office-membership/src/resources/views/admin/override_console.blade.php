@extends('layouts/default')
@section('title', __('office_membership::member.override_console_title'))
@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="box box-danger" style="border-top: 3px solid #c0392b;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-exclamation-triangle text-danger"></i> {{ __('office_membership::member.override_execute_label') }}</h3>
            </div>
            <form action="{{ route('gov.membership.override') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>{{ __('office_membership::member.override_target_label') }}</label>
                        <select name="user_id" class="form-control select2" required style="width:100%;">
                            <option value="">{{ __('office_membership::member.override_target_placeholder') }}</option>
                            @foreach(\App\Models\User::all() as $u)
                                <option value="{{ $u->id }}">{{ optional($u->present())->fullName ?? 'Unknown' }} ({{ $u->username }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('office_membership::member.override_action_label') }}</label>
                        <select name="override_type" class="form-control" required>
                            <option value="strip_roles">{{ __('office_membership::member.override_strip_roles_option') }}</option>
                            <option value="force_release">{{ __('office_membership::member.override_force_release_option') }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('office_membership::member.override_justification_label') }} <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required minlength="10" placeholder="{{ __('office_membership::member.override_justification_placeholder') }}"></textarea>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('{{ __('office_membership::member.override_confirm_warning') }}')">
                        <i class="fas fa-radiation"></i> {{ __('office_membership::member.override_execute_button') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-shield-alt"></i> {{ __('office_membership::member.override_audit_title') }}</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>{{ __('office_membership::member.override_audit_date') }}</th><th>{{ __('office_membership::member.override_audit_executor') }}</th><th>{{ __('office_membership::member.override_audit_target') }}</th><th>{{ __('office_membership::member.override_audit_action') }}</th><th>{{ __('office_membership::member.override_audit_justification') }}</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td><span class="label label-danger">{{ $log->executor->username ?? 'System' }}</span></td>
                                <td><!-- DEFENSIVE CHECK FOR HISTORICAL LOGS -->
                                    <strong>{{ $log->targetUser ? $log->targetUser->present()->fullName : __('office_membership::member.staff_unknown_employee') }}</strong>
                                </td>
                                <td><code>{{ $log->override_type }}</code></td>
                                <td><small>{{ $log->reason }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ __('office_membership::member.override_audit_no_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection