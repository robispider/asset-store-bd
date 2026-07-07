@extends('layouts/default')

@section('title', 'Office Awaiting Activation')

@section('content')
<style>
    .wait-container { max-width: 650px; margin: 40px auto; text-align: center; }
    .setup-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; }
</style>

<div class="wait-container">
    <div class="box box-warning" style="border-top: 3px solid #f39c12; border-radius: 8px; overflow: hidden; padding: 25px;">
        <div class="box-body">
            <!-- Alert Icon -->
            <span style="font-size: 50px; color: #f39c12;"><i class="fas fa-store-slash"></i></span>
            
            <h2 style="font-weight: bold; margin-top: 15px; margin-bottom: 5px;">Office Activation Pending</h2>
            <p class="text-muted" style="font-size: 15px;">The <strong>{{ $location->name }}</strong> is currently mapped in the system but has not completed operational setup. The catalog will unlock once the following checklist is completed:</p>
            
            <!-- Live Progress List -->
            <div style="text-align: left; margin: 25px 0;">
                
                <div class="setup-item">
                    <span><i class="fas fa-user-tie text-success" style="margin-right: 10px;"></i> <strong>Office Administrator Designated</strong></span>
                    <span class="label label-success">Completed</span>
                </div>

                <div class="setup-item">
                    <span>
                        <i class="fas {{ $readiness['checklist']['has_primary_approver'] ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' }}" style="margin-right: 10px;"></i> 
                        <strong>Primary Approver (Supervisor)</strong>
                    </span>
                    <span class="label {{ $readiness['checklist']['has_primary_approver'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_primary_approver'] ? 'Assigned' : 'Awaiting Setup' }}
                    </span>
                </div>

                <div class="setup-item">
                    <span>
                        <i class="fas {{ $readiness['checklist']['has_storekeeper'] ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' }}" style="margin-right: 10px;"></i> 
                        <strong>Storekeeper (Inventory Officer)</strong>
                    </span>
                    <span class="label {{ $readiness['checklist']['has_storekeeper'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_storekeeper'] ? 'Assigned' : 'Awaiting Setup' }}
                    </span>
                </div>

                <div class="setup-item">
                    <span>
                        <i class="fas {{ $readiness['checklist']['has_users'] ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' }}" style="margin-right: 10px;"></i> 
                        <strong>Assigned Staff (Min: 1)</strong>
                    </span>
                    <span class="label {{ $readiness['checklist']['has_users'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_users'] ? 'Completed' : 'Awaiting Setup' }}
                    </span>
                </div>

            </div>

            <!-- Escalation Contact Details -->
            @if($profile && $profile->officeAdmin)
                <div class="well well-sm text-center" style="background-color: #fafafa; border: 1px solid #eee; border-radius: 4px; padding: 15px; margin-top: 20px;">
                    <p style="margin-bottom: 5px; font-size: 13px;"><strong>Who can activate this?</strong></p>
                    <p style="margin-bottom: 0; font-size: 14px;">
                        Contact your Office Administrator: <br>
                        <strong>{{ $profile->officeAdmin->present()->fullName }}</strong> 
                        ({{ $profile->officeAdmin->email ?: $profile->officeAdmin->username }})
                    </p>
                </div>
            @endif

            <div style="margin-top: 25px;">
                <a href="{{ url('/') }}" class="btn btn-default"><i class="fas fa-home"></i> Return to Main Dashboard</a>
            </div>
        </div>
    </div>
</div>
@endsection