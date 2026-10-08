@extends('layouts/default')

@section('title', __('organization_labels::orglabel.company_admin_title'))

@section('content')
<div class="govorg-theme">
<div class="row">
    <!-- LEFT: Delegate New Company Admin Form -->
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-university text-purple"></i> {{ __('organization_labels::orglabel.company_admin_assign_title') }}</h3>
            </div>
            <form action="{{ route('gov.org.company_admins.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    
                    <div class="form-group">
                        <label for="user_id">{{ __('organization_labels::orglabel.company_admin_select_user') }} <span class="text-danger">*</span></label>
                        <select class="form-control select2 org-inline-442a70a1" name="user_id" id="user_id" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                        <p class="help-block org-inline-dd9e524d">{{ __('organization_labels::orglabel.company_admin_help_user') }}</p>
                    </div>

                    <div class="form-group org-inline-b6ec21fd">
                        <label for="company_id">{{ __('organization_labels::orglabel.company_admin_select_company') }} <span class="text-danger">*</span></label>
                        <select class="form-control select2 org-inline-442a70a1" name="company_id" id="company_id" required>
                            <option value="">-- Select Ministry / Company --</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <p class="help-block org-inline-dd9e524d">{{ __('organization_labels::orglabel.company_admin_help_company') }}</p>
                    </div>

                </div>
                <div class="box-footer">
                    <button class="btn btn-primary btn-block org-inline-3cdfe113" type="submit">
                        <i class="fas fa-sitemap"></i> {{ __('organization_labels::orglabel.company_admin_btn_save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Assigned Company Admins Datatable -->
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-users-cog text-blue"></i> {{ __('organization_labels::orglabel.company_admin_list_title') }}</h3>
            </div>
            <div class="box-body table-responsive">
                <x-gs::table class="table table-striped table-hover table-bordered">
                    <thead class="org-inline-1f5081db">
                        <tr>
                            <th>{{ __('organization_labels::orglabel.company_admin_col_user') }}</th>
                            <th>{{ __('organization_labels::orglabel.company_admin_col_company') }}</th>
                            <th>{{ __('organization_labels::orglabel.company_admin_col_home_office') }}</th>
                            <th class="text-center org-inline-b086e59f">{{ __('organization_labels::orglabel.company_admin_col_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($admins as $admin)
                            <tr>
                                <td>
                                    <strong>{{ $admin->user->present()->fullName ?? 'Unknown User' }}</strong><br>
                                    <small class="text-muted"><i class="fas fa-user"></i> {{ $admin->user->username ?? '-' }}</small>
                                </td>
                                <td class="org-inline-092f0f30">
                                    <span class="label bg-purple org-inline-0ffaf047">
                                        <i class="fas fa-university"></i> {{ $admin->company->name ?? 'Unmapped Company' }}
                                    </span>
                                </td>
                                <td class="org-inline-092f0f30">
                                    <small class="text-muted"><i class="fas fa-building"></i> {{ $admin->user->location->name ?? 'No Home Office Assigned' }}</small>
                                </td>
                                <td class="org-inline-092f0f30">
                                    <form action="{{ route('gov.org.company_admins.destroy', $admin->id) }}" method="POST" class="org-inline-412e6498">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger btn-block" onclick="return confirm('{{ __('organization_labels::orglabel.company_admin_confirm_revoke') }}')">
                                            <i class="fas fa-trash"></i> {{ __('organization_labels::orglabel.company_admin_btn_revoke') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted org-inline-ed0f0aa0" colspan="4">{{ __('organization_labels::orglabel.company_admin_empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-gs::table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection