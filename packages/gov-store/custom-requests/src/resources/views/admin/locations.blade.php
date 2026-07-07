@extends('layouts/default')

@section('title', 'Office Assignments Settings')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-map-marked-alt"></i> Assign Office Workflow Roles</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">Configure the Primary Approver, Optional Final Approver, and Storekeeper for each office location.</p>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Office Location</th>
                            <th>Primary Approver</th>
                            <th>Final Approver (Optional)</th>
                            <th>Storekeeper</th>
                            <th style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($locations as $loc)
                            @php
                                $roles = $locationRoles[$loc->id] ?? null;
                            @endphp
                            <tr>
                                <form action="{{ route('gov.requests.admin.locations.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="location_id" value="{{ $loc->id }}">
                                    
                                    <td style="vertical-align: middle;"><strong>{{ $loc->name }}</strong></td>
                                    
                                    <!-- Primary Approver -->
                                    <td style="vertical-align: middle;">
                                        <select name="primary_approver_id" class="form-control select2 input-sm" required style="width: 100%;">
                                            <option value="">-- Assign Primary --</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ $roles && $roles->primary_approver_id == $user->id ? 'selected' : '' }}>
                                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <!-- Final Approver -->
                                    <td style="vertical-align: middle;">
                                        <select name="final_approver_id" class="form-control select2 input-sm" style="width: 100%;">
                                            <option value="">-- No Final Approver (Level 1 Only) --</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ $roles && $roles->final_approver_id == $user->id ? 'selected' : '' }}>
                                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <!-- Storekeeper -->
                                    <td style="vertical-align: middle;">
                                        <select name="storekeeper_id" class="form-control select2 input-sm" required style="width: 100%;">
                                            <option value="">-- Assign Storekeeper --</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ $roles && $roles->storekeeper_id == $user->id ? 'selected' : '' }}>
                                                    {{ $user->present()->fullName }} ({{ $user->username }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td style="vertical-align: middle;">
                                        <button type="submit" class="btn btn-sm btn-success btn-block"><i class="fas fa-save"></i> Save</button>
                                    </td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection