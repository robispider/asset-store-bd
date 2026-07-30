@extends('layouts/default')
@section('title', 'Launch New Initiative')

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <form action="{{ route('gov.tracking.initiatives.store') }}" method="POST">
            @csrf
            
            <!-- Section 1: The Identity -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-aqua">1. Initiative Details</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Initiative Title</label>
                        <input type="text" name="title" class="form-control input-lg" placeholder="e.g. School ICT Modernization 2027" required>
                    </div>
                    <div class="form-group">
                        <label>Business Purpose / Description</label>
                        <textarea name="purpose" class="form-control" rows="3" placeholder="What is the operational goal of this umbrella initiative?"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Primary Segment Type</label>
                            <select name="primary_funding" class="form-control" required>
                                <option value="ADP">ADP (Development Budget)</option>
                                <option value="REVENUE">Revenue Budget (Non-Development)</option>
                                <option value="OTHER">Other / Autonomous Reserves</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Initial Lifecycle Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Planning" selected>Planning (Setup Phase)</option>
                                <option value="Active">Active (Open for Operations)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Natural Language Ownership & Admin -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-orange">2. Ownership & Administration</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Which organization legally owns this initiative?</label>
                        <select id="owner_company_id" name="owner_company_id" class="form-control select2" required>
                            <option value="">-- Select Ministry / Organization --</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Which office manages and maintains this initiative's execution?</label>
                        <select id="manager_location_id" name="manager_location_id" class="form-control select2" required>
                            <option value="">-- Select Project Management Office --</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" data-company-id="{{ $location->company_id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 3: Governance Rules -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-green">3. Governance & Execution Rules</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="require_documents" value="1" checked>
                                <strong>Require Official Documents:</strong> Ensure a PDF order/document is uploaded when a Tracking Code is created.
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="allow_overshoot" value="1">
                                <strong>Allow Target Overshoot:</strong> Allow operations (like GRNs) to exceed planned targets without requiring a formal override justification.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.initiatives.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-success btn-lg">Launch Initiative Workspace</button>
                </div>
            </div>

        </form>
    </div>
</div>
@stop

@section('moar_scripts')
<script>
    $(function() {
        var $companySelect = $('#owner_company_id');
        var $locationSelect = $('#manager_location_id');
        
        // Save the original options in an array to avoid jQuery clone/data cache issues
        var allLocations = [];
        $locationSelect.find('option').each(function() {
            allLocations.push({
                value: $(this).val(),
                text: $(this).text(),
                companyId: $(this).data('company-id')
            });
        });
        
        function filterLocations() {
            var selectedCompanyId = $companySelect.val();
            
            // Clear current options
            $locationSelect.empty();
            
            // Re-append valid options
            $.each(allLocations, function(i, loc) {
                // Keep the default empty option OR options that match the selected company
                if (loc.value === "" || loc.companyId == selectedCompanyId) {
                    var $opt = $('<option></option>').attr('value', loc.value).text(loc.text);
                    $locationSelect.append($opt);
                }
            });
            
            // Trigger change so Select2 updates its UI
            $locationSelect.trigger('change');
        }
        
        // Run on initial load
        filterLocations();
        
        // Run on change
        $companySelect.on('change', function() {
            filterLocations();
        });
    });
</script>
@stop