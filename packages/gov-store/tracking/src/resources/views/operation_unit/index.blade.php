@extends('layouts/default')
@section('title', 'Operation Unit Management')

@section('content')
<div class="row gs-op-unit-page">
    <div class="col-md-10 col-md-offset-1">

        <div class="box box-solid" style="margin-bottom: 25px;">
            <div class="box-body text-right gs-op-summary-bar">
                <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default pull-left"><i class="fa fa-arrow-left"></i> Back to Workspace</a>
                <span class="lead pull-right" style="margin-bottom: 0;">Initiative: <strong>{{ $initiative->title }}</strong></span>
            </div>
        </div>

        <x-gs::alert tone="info" title="Operation Unit Assignments">
            <p>Designate the administrative authorities for this initiative. <strong>A single Operation Head and at least one Operation Officer are required</strong> before this project can be activated for procurement operations.</p>
        </x-gs::alert>

        <!-- 1. OPERATION HEAD -->
        <div class="gs-op-team-card gs-op-team-card--head">
            <div class="gs-op-team-header">
                <h4 class="gs-op-team-title"><i class="fa fa-star text-yellow"></i> Operation Head (Project Director / Lead)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Full authority over the initiative. Only one person may hold this designation.</p>
            </div>
            <div class="gs-op-team-body">
                @if($head)
                    <div class="gs-op-staff-row gs-op-staff-row--head">
                        <div>
                            @php
                                $headName = $head->user ? "{$head->user->first_name} {$head->user->last_name}" : "Unknown User (ID: {$head->user_id})";
                            @endphp
                            <span class="gs-op-staff-name">{{ $headName }}</span><br>
                            <small class="gs-op-staff-meta">Username: {{ $head->user->username ?? 'N/A' }} | EMP No: {{ $head->user->employee_num ?? 'N/A' }}</small>
                        </div>
                        <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $head->id]) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove the Operation Head?')"><i class="fa fa-times"></i> Remove</button>
                        </form>
                    </div>
                @else
                    <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="form-inline gs-op-assign-form">
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
        <div class="gs-op-team-card gs-op-team-card--officer">
            <div class="gs-op-team-header">
                <h4 class="gs-op-team-title"><i class="fa fa-user-tie text-aqua"></i> Operation Officers (Planners & Approvers)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Authorized to define exact delivery matrices and manage execution tracking codes.</p>
            </div>
            <div class="gs-op-team-body">
                @forelse($officers as $officer)
                    @php
                        $officerName = $officer->user ? "{$officer->user->first_name} {$officer->user->last_name}" : "Unknown User (ID: {$officer->user_id})";
                    @endphp
                    <div class="gs-op-staff-row">
                        <div>
                            <span class="gs-op-staff-name">{{ $officerName }}</span><br>
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

                <hr style="margin: 15px 0;">
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
        <div class="gs-op-team-card gs-op-team-card--support">
            <div class="gs-op-team-header">
                <h4 class="gs-op-team-title"><i class="fa fa-users text-green"></i> Support Staff (Document Handlers)</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; margin-bottom: 0;">Optional. Authorized to upload official documents and execute retrospective tagging.</p>
            </div>
            <div class="gs-op-team-body">
                @forelse($support as $staff)
                    @php
                        $staffName = $staff->user ? "{$staff->user->first_name} {$staff->user->last_name}" : "Unknown User (ID: {$staff->user_id})";
                    @endphp
                    <div class="gs-op-staff-row">
                        <div>
                            <span class="gs-op-staff-name">{{ $staffName }}</span><br>
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

                <hr style="margin: 15px 0;">
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
                            initiative_id: "{{ $initiative->id }}"
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
