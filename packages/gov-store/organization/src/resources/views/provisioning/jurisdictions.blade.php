@extends('layouts/default')

@section('title', __('organization_labels::orglabel.jurisdictions_title'))

@section('content')
<div class="row">
    <!-- LEFT: Delegate New ICT Officer Form -->
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-shield"></i> {{ __('organization_labels::orglabel.jurisdictions_map_title') }}</h3>
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
                        <p class="help-block">{{ __('organization_labels::orglabel.jurisdictions_help_employee') }}</p>
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
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-shield-alt"></i> {{ __('organization_labels::orglabel.jurisdictions_save_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Assigned ICT Officers Datatable -->
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-cog"></i> {{ __('organization_labels::orglabel.jurisdictions_assigned_title') }}</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('organization_labels::orglabel.jurisdictions_col_officer_details') }}</th>
                            <th>{{ __('organization_labels::orglabel.jurisdictions_col_home_office') }}</th>
                            <th>{{ __('organization_labels::orglabel.jurisdictions_col_jurisdiction_boundary') }}</th>
                            <th style="width: 100px;">{{ __('organization_labels::orglabel.jurisdictions_col_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jurisdictions as $jur)
                            <tr>
                                <td>
                                    <strong>{{ $jur->user->present()->fullName ?? 'Unknown User' }}</strong><br>
                                    <small class="text-muted"><i class="fas fa-user"></i> {{ __('organization_labels::orglabel.jurisdictions_username_label') }} {{ $jur->user->username ?? '-' }}</small>
                                </td>
                                <td style="vertical-align: middle;">
                                    <small class="text-muted"><i class="fas fa-building"></i> {{ $jur->user->location->name ?? __('organization_labels::orglabel.jurisdictions_no_home_office') }}</small>
                                </td>
                                <td style="vertical-align: middle;">
                                    <span class="label bg-orange" style="font-size: 11px;">
                                        <i class="fas fa-map-marker-alt"></i> {{ $jur->geoArea->en_name ?? 'Unmapped' }} ({{ ucfirst($jur->geoArea->geo_type ?? 'N/A') }})
                                    </span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <form action="{{ route('gov.org.jurisdictions.destroy', $jur->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger btn-block" onclick="return confirm('{{ __('organization_labels::orglabel.jurisdictions_revoke_confirm') }}')">
                                            <i class="fas fa-trash"></i> {{ __('organization_labels::orglabel.jurisdictions_revoke_button') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding: 30px;">{{ __('organization_labels::orglabel.jurisdictions_empty_state') }}</td>
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
    $('#jurisdictionSelector').select2({
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("gov.geo.search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: "Search Division, District, Upazila, or Union..."
    });
});
</script>
@endsection