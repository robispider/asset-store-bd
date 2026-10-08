@extends('layouts/default')

@section('title', 'Office Awaiting Activation')

@section('content')
<div class="govorg-theme">


<div class="wait-container">
    <div class="box box-warning org-inline-a87d3448">
        <div class="box-body">
            <!-- Alert Icon -->
            <span class="org-inline-e6e30944"><i class="fas fa-store-slash"></i></span>
            
            <h2 class="org-inline-356c41d4">Office Activation Pending</h2>
            <p class="text-muted org-inline-95b69185">The <strong>{{ $location->name }}</strong> is currently mapped in the system but has not completed operational setup. The catalog will unlock once the following checklist is completed:</p>
            
            <!-- Live Progress List -->
            <div class="org-inline-30890f81">
                
                <div class="setup-item">
                    <span><i class="fas fa-user-tie text-success org-inline-cbd49f07"></i>
                        <strong>Office Administrator Designated</strong></span>
                    <span class="label label-success">Completed</span>
                </div>

                <div class="setup-item">
                    <span>
                        <i class="fas {{ $readiness['checklist']['has_primary_approver'] ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' }} org-inline-cbd49f07"></i>
                        <strong>Primary Approver (Supervisor)</strong>
                    </span>
                    <span class="label {{ $readiness['checklist']['has_primary_approver'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_primary_approver'] ? 'Assigned' : 'Awaiting Setup' }}
                    </span>
                </div>

                <div class="setup-item">
                    <span>
                        <i class="fas {{ $readiness['checklist']['has_storekeeper'] ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' }} org-inline-cbd49f07"></i>
                        <strong>Storekeeper (Inventory Officer)</strong>
                    </span>
                    <span class="label {{ $readiness['checklist']['has_storekeeper'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_storekeeper'] ? 'Assigned' : 'Awaiting Setup' }}
                    </span>
                </div>

                <div class="setup-item">
                    <span>
                        <i class="fas {{ $readiness['checklist']['has_users'] ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' }} org-inline-cbd49f07"></i>
                        <strong>Assigned Staff (Min: 1)</strong>
                    </span>
                    <span class="label {{ $readiness['checklist']['has_users'] ? 'label-success' : 'label-default' }}">
                        {{ $readiness['checklist']['has_users'] ? 'Completed' : 'Awaiting Setup' }}
                    </span>
                </div>

            </div>

            <!-- Escalation Contact Details -->
            @if($profile && $profile->officeAdmin)
                <div class="well well-sm text-center org-inline-fee1b874">
                    <p class="org-inline-c396f3fb"><strong>Who can activate this?</strong></p>
                    <p class="org-inline-b47f8d00">
                        Contact your Office Administrator: <br>
                        <strong>{{ $profile->officeAdmin->present()->fullName }}</strong> 
                        ({{ $profile->officeAdmin->email ?: $profile->officeAdmin->username }})
                    </p>
                </div>
            @endif

            <div class="org-inline-f5897d74">
                <a href="{{ url('/') }}" class="btn btn-default"><i class="fas fa-home"></i> Return to Main Dashboard</a>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
