@extends('layouts/default')

@section('title', 'ICT Officer Jurisdictions')

@section('content')
<div class="row">
    <!-- LEFT: Delegate New ICT Officer Form -->
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-shield"></i> Map ICT Officer Boundary</h3>
            </div>
            <form action="{{ route('gov.org.jurisdictions.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    
                    <div class="form-group">
                        <label for="user_id">Select Employee User <span class="text-danger">*</span></label>
                        <select name="user_id" id="user_id" class="form-control select2" required style="width: 100%;">
                            <option value="">-- Select Employee --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                        <p class="help-block">Select the employee user account who will act as the field ICT Officer.</p>
                    </div>

                    <!-- GEOGRAPHIC JURISDICTION SELECTOR -->
                    <div class="form-group">
                        <label for="jurisdictionSelector">Operational Jurisdiction Boundary <span class="text-danger">*</span></label>
                        <select name="geo_area_id" id="jurisdictionSelector" class="form-control" required style="width: 100%;">
                            <option value="">-- Start typing to search --</option>
                        </select>
                        <p class="help-block">Assign this officer to a specific Division, District, or Upazila. They can only provision offices within this bound.</p>
                    </div>

                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-shield-alt"></i> Save & Delegate Officer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Assigned ICT Officers Datatable -->
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-cog"></i> Mapped ICT Provisioning Officers</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Officer Details</th>
                            <th>Home Office Base</th>
                            <th>Assigned Jurisdiction Boundary</th>
                            <th style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jurisdictions as $jur)
                            <tr>
                                <td>
                                    <strong>{{ $jur->user->present()->fullName ?? 'Unknown User' }}</strong><br>
                                    <small class="text-muted"><i class="fas fa-user"></i> Username: {{ $jur->user->username ?? '-' }}</small>
                                </td>
                                <td style="vertical-align: middle;">
                                    <small class="text-muted"><i class="fas fa-building"></i> {{ $jur->user->location->name ?? 'No home office set' }}</small>
                                </td>
                                <td style="vertical-align: middle;">
                                    <span class="label bg-orange" style="font-size: 11px;">
                                        <i class="fas fa-map-marker-alt"></i> {{ $jur->geoArea->en_name ?? 'Unmapped' }} ({{ ucfirst($jur->geoArea->geo_type ?? 'N/A') }})
                                    </span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <form action="{{ route('gov.org.jurisdictions.destroy', $jur->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger btn-block" onclick="return confirm('Revoke geographic provisioning privileges for this user?')">
                                            <i class="fas fa-trash"></i> Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding: 30px;">No ICT Officers mapped in the database yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    // Initialise Ajax Select2 to search across Divisions, Districts, or Upazilas
    $('#jurisdictionSelector').select2({
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("gov.org.provisioning.geo-search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: "Search Division, District, or Upazila..."
    });
});
</script>
@endsection