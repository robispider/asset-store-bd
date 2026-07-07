@extends('layouts/default')

@section('title', 'Provision New Government Office')

@section('content')
<div class="row">
    <!-- LEFT COLUMN: Guided Creation Form -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-building"></i> Office Registration Workspace</h3>
            </div>
            <form action="{{ route('gov.org.provisioning.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    
                    <!-- Step 1: Office Identity -->
                    <div class="form-group">
                        <label for="name">Office Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Debidwar Upazila Health Complex" required value="{{ old('name') }}">
                    </div>

                    <!-- Step 2: Mandatory Geographic Territory -->
                    <div class="form-group">
                        <label for="geoAreaSelector">Geographical Boundary (Upazila / Union) <span class="text-danger">*</span></label>
                        <select name="geo_area_id" id="geoAreaSelector" class="form-control" required style="width: 100%;">
                            <option value="">-- Search and select Upazila or Union --</option>
                        </select>
                        <p class="help-block">Mandatory. Tags the office building to its standard territory.</p>
                    </div>

                    <!-- Step 3: Organizational Metadata -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="company_id">Ministry / Department (Optional)</label>
                                <select name="company_id" id="company_id" class="form-control select2" style="width: 100%;">
                                    <option value="">-- No Ministry (Standalone) --</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="parent_id">Parent Regional/District Office (Optional)</label>
                                <select name="parent_id" id="parent_id" class="form-control select2" style="width: 100%;">
                                    <option value="">-- No Parent (Root Location) --</option>
                                    @foreach($offices as $parentLoc)
                                        <option value="{{ $parentLoc->id }}">{{ $parentLoc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Office Administrator Delegation -->
                    <div class="form-group">
                        <label for="office_admin_id">Designate Office Administrator (Optional)</label>
                        <select name="office_admin_id" id="office_admin_id" class="form-control select2" style="width: 100%;">
                            <option value="">-- Leave Unassigned for Now --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                        <p class="help-block">The designated user receives authority to configure local storekeeper and approver roles.</p>
                    </div>

                </div>
                <div class="box-footer">
                    <a href="{{ route('gov.org.provisioning.index') }}" class="btn btn-default pull-left"><i class="fas fa-arrow-left"></i> Cancel</a>
                    <button type="submit" class="btn btn-primary pull-right"><i class="fas fa-save"></i> Save & Provision Office</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: Live Duplicate Awareness Widget -->
    <div class="col-md-5">
        <div class="box box-warning" id="duplicateWidget" style="display: none;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-exclamation-triangle text-warning"></i> Possible Duplicate Detected</h3>
            </div>
            <div class="box-body">
                <p>An office belonging to the selected Ministry is already registered within this geographic territory:</p>
                <ul id="duplicateList" class="list-group"></ul>
                <p class="text-muted" style="font-size: 12px; margin-bottom: 0;">Notice: This does not block office creation. Please verify if this is an intentioned separate building before proceeding.</p>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-info-circle"></i> Onboarding Guidelines</h3>
            </div>
            <div class="box-body" style="font-size: 13px; line-height: 1.6; color: #555;">
                <p><strong>Field ICT Rollout Rules:</strong></p>
                <ul>
                    <li><strong>Geographical Tag:</strong> Must be selected before saving. City and District names will be auto-populated from standard maps.</li>
                    <li><strong>Ministry Ownership:</strong> Optional during initial setup. Can be linked later when organizational hierarchy is assigned.</li>
                    <li><strong>Office Administrator:</strong> Designated local user can log in to <code>My Office Setup</code> to assign storekeeper and approver roles.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    // 1. Initialize Ajax Select2 for geographical territory search
    $('#geoAreaSelector').select2({
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("gov.org.provisioning.geo-search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: "Type to search Upazila or Union..."
    });

    // 2. Live Duplicate Awareness Check
    function checkDuplicates() {
        var companyId = $('#company_id').val();
        var geoAreaId = $('#geoAreaSelector').val();

        if (companyId && geoAreaId) {
            $.ajax({
                url: '{{ route("gov.org.provisioning.check-duplicate") }}',
                data: { company_id: companyId, geo_area_id: geoAreaId },
                dataType: 'json',
                success: function(data) {
                    if (data.length > 0) {
                        var listHtml = '';
                        $.each(data, function(index, item) {
                            listHtml += '<li class="list-group-item"><strong>' + item.name + '</strong> <small class="text-muted">(' + item.geo_name + ')</small></li>';
                        });
                        $('#duplicateList').html(listHtml);
                        $('#duplicateWidget').fadeIn();
                    } else {
                        $('#duplicateWidget').fadeOut();
                    }
                }
            });
        } else {
            $('#duplicateWidget').fadeOut();
        }
    }

    $('#company_id').on('change', checkDuplicates);
    $('#geoAreaSelector').on('change', checkDuplicates);
});
</script>
@endsection