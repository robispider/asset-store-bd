@extends('layouts/default')
@section('title', 'Operation Unit Management')

@section('content')
<style>
    .team-card {
        border-top: 3px solid;
        border-radius: 4px;
        background-color: #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 25px;
    }
    .team-card-head { border-top-color: #f39c12; }
    .team-card-officer { border-top-color: #00c0ef; }
    .team-card-support { border-top-color: #00a65a; }
    
    .team-header {
        padding: 15px;
        border-bottom: 1px solid #f4f4f4;
        background-color: #f8fafc;
    }
    .team-title {
        margin: 0;
        font-size: 16px;
        font-weight: bold;
    }
    .team-body { padding: 15px; }
    .staff-row {
        padding: 10px;
        border-bottom: 1px dashed #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .staff-row:last-child { border-bottom: none; }
    
    .select2-container .select2-selection--single {
        height: 34px !important;
        padding: 6px 12px;
        border: 1px solid #ccc;
    }
</style>

<div class="row">
    <div class="col-md-10 col-md-offset-1">
        
        <div class="box box-solid" style="margin-bottom: 25px;">
            <div class="box-body text-right" style="background-color: #f8fafc;">
                <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default pull-left"><i class="fa fa-arrow-left"></i> Back to Workspace</a>
                <span class="lead pull-right" style="margin-bottom: 0;">Initiative: <strong>{{ $initiative->title }}</strong></span>
            </div>
        </div>

        <div class="callout callout-info" style="background-color: #3c8dbc !important; border-color: #367fa9;">
            <h4><i class="fa fa-users"></i> Operation Unit Assignments</h4>
            <p>Designate the administrative authorities for this initiative. <strong>A single Operation Head and at least one Operation Officer are required</strong> before this project can be activated for procurement operations.</p>
        </div>

        <!-- 1. OPERATION HEAD -->
        <!-- 1. OPERATION HEAD -->
        <div class="team-card team-card-head">
            <div class="team-header">
                <h4 class="team-title"><i class="fa fa-star text-yellow"></i> Operation Head (Project Director / Lead)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Full authority over the initiative. Only one person may hold this designation.</p>
            </div>
            <div class="team-body">
                @if($head)
                    <div class="staff-row bg-warning" style="border-left: 3px solid #f39c12; background-color: #fcf8e3; border-radius: 3px;">
                        <div>
                            @php
                                // Defensive Fallback
                                $headName = $head->user ? "{$head->user->first_name} {$head->user->last_name}" : "Unknown User (ID: {$head->user_id})";
                            @endphp
                            <span style="font-size: 15px; font-weight: bold; color: #8a6d3b;">{{ $headName }}</span><br>
                            <small style="color: #8a6d3b;">Username: {{ $head->user->username ?? 'N/A' }} | EMP No: {{ $head->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $head->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove the Operation Head?')"><i class="fa fa-times"></i> Remove</button>
                        </form>
                    </div>
                @else
                    <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline" style="padding: 10px; background-color: #f9fafb; border: 1px dashed #cbd5e1; border-radius: 4px;">
                        @csrf
                        <input type="hidden" name="designation" value="HEAD">
                        <div class="form-group" style="width: 70%;">
                            <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                                <option value="">Search Staff Directory...</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning pull-right"><i class="fa fa-user-plus"></i> Assign Head</button>
                    </form>
                @endif
            </div>
        </div>

        <!-- 2. OPERATION OFFICERS -->
        <div class="team-card team-card-officer">
            <div class="team-header">
                <h4 class="team-title"><i class="fa fa-user-tie text-aqua"></i> Operation Officers (Planners & Approvers)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Authorized to define exact delivery matrices and manage execution tracking codes.</p>
            </div>
            <div class="team-body">
                @forelse($officers as $officer)
                    @php
                        // Defensive Fallback
                        $officerName = $officer->user ? "{$officer->user->first_name} {$officer->user->last_name}" : "Unknown User (ID: {$officer->user_id})";
                    @endphp
                    <div class="staff-row">
                        <div>
                            <span style="font-size: 15px; font-weight: bold; color: #333;">{{ $officerName }}</span><br>
                            <small class="text-muted">Username: {{ $officer->user->username ?? 'N/A' }} | EMP No: {{ $officer->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $officer->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger" title="Remove Officer"><i class="fa fa-times"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding: 15px; margin: 0; font-style: italic;">No Operation Officers designated yet.</p>
                @endforelse

                <hr style="margin: 15px 0; border-top: 1px solid #e2e8f0;">
                <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="designation" value="OFFICER">
                    <div class="form-group" style="width: 75%;">
                        <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                            <option value="">Search Staff Directory...</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info pull-right"><i class="fa fa-plus"></i> Assign Officer</button>
                </form>
            </div>
        </div>

        <!-- 3. SUPPORT STAFF -->
        <div class="team-card team-card-support">
            <div class="team-header">
                <h4 class="team-title"><i class="fa fa-users text-green"></i> Support Staff (Document Handlers)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Optional. Authorized to upload official documents and execute retrospective tagging.</p>
            </div>
            <div class="team-body">
                @forelse($support as $staff)
                    @php
                        // Defensive Fallback
                        $staffName = $staff->user ? "{$staff->user->first_name} {$staff->user->last_name}" : "Unknown User (ID: {$staff->user_id})";
                    @endphp
                    <div class="staff-row">
                        <div>
                            <span style="font-size: 15px; font-weight: bold; color: #333;">{{ $staffName }}</span><br>
                            <small class="text-muted">Username: {{ $staff->user->username ?? 'N/A' }} | EMP No: {{ $staff->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $staff->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger" title="Remove Support Staff"><i class="fa fa-times"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding: 15px; margin: 0; font-style: italic;">No support staff designated.</p>
                @endforelse

                <hr style="margin: 15px 0; border-top: 1px solid #e2e8f0;">
                <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="designation" value="SUPPORT">
                    <div class="form-group" style="width: 75%;">
                        <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                            <option value="">Search Staff Directory...</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success pull-right"><i class="fa fa-plus"></i> Assign Support</button>
                </form>
            </div>
        </div>

        <!-- 2. OPERATION OFFICERS -->
        <div class="team-card team-card-officer">
            <div class="team-header">
                <h4 class="team-title"><i class="fa fa-user-tie text-aqua"></i> Operation Officers (Planners & Approvers)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Authorized to define exact delivery matrices and manage execution tracking codes.</p>
            </div>
            <div class="team-body">
                @forelse($officers as $officer)
                    <div class="staff-row">
                        <div>
                            <span style="font-size: 15px; font-weight: bold; color: #333;">{{ $officer->user->first_name }} {{ $officer->user->last_name }}</span><br>
                            <small class="text-muted">Username: {{ $officer->user->username }} | EMP No: {{ $officer->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $officer->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger" title="Remove Officer"><i class="fa fa-times"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding: 15px; margin: 0; font-style: italic;">No Operation Officers designated yet.</p>
                @endforelse

                <hr style="margin: 15px 0; border-top: 1px solid #e2e8f0;">
                <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="designation" value="OFFICER">
                    <div class="form-group" style="width: 75%;">
                        <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                            <option value="">Search Staff Directory...</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info pull-right"><i class="fa fa-plus"></i> Assign Officer</button>
                </form>
            </div>
        </div>

        <!-- 3. SUPPORT STAFF -->
        <div class="team-card team-card-support">
            <div class="team-header">
                <h4 class="team-title"><i class="fa fa-users text-green"></i> Support Staff (Document Handlers)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Optional. Authorized to upload official documents and execute retrospective tagging.</p>
            </div>
            <div class="team-body">
                @forelse($support as $staff)
                    <div class="staff-row">
                        <div>
                            <span style="font-size: 15px; font-weight: bold; color: #333;">{{ $staff->user->first_name }} {{ $staff->user->last_name }}</span><br>
                            <small class="text-muted">Username: {{ $staff->user->username }} | EMP No: {{ $staff->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $staff->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger" title="Remove Support Staff"><i class="fa fa-times"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding: 15px; margin: 0; font-style: italic;">No support staff designated.</p>
                @endforelse

                <hr style="margin: 15px 0; border-top: 1px solid #e2e8f0;">
                <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="designation" value="SUPPORT">
                    <div class="form-group" style="width: 75%;">
                        <select name="user_id" class="form-control user-search-select" style="width: 100%;" required>
                            <option value="">Search Staff Directory...</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success pull-right"><i class="fa fa-plus"></i> Assign Support</button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof window.jQuery === 'undefined') return;

        window.jQuery(function($) {
            // Initialize AJAX Select2 on all staff search dropdowns
            $('.user-search-select').select2({
                placeholder: 'Search Staff Directory...',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('gov.tracking.operation-unit.search-users') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { 
                            q: params.term,
                            initiative_id: "{{ $initiative->id }}" // FIXED: Passed the active initiative id to scope the search
                        };
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    }
                }
            });
        });
    });
</script>
@stop