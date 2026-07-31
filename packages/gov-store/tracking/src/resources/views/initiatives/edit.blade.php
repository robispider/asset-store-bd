@extends('layouts/default')
@section('title', 'Edit Initiative Properties')

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <form action="{{ route('gov.tracking.initiatives.update', $initiative->id) }}" method="POST">
            @csrf @method('PUT')
            
            <!-- State indicator alert -->
            @if($initiative->status !== 'Planning')
                <div class="callout callout-warning">
                    <h4><i class="fa fa-lock"></i> Operational State Lock Active</h4>
                    <p>Because this initiative is currently <strong>{{ strtoupper($initiative->status) }}</strong>, its core financial attributes (Primary Segment, Owning Organization) are locked and cannot be changed to preserve ledger integrity.</p>
                </div>
            @endif

            <!-- Section 1: The Identity -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-aqua">1. Initiative Details</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Initiative Title</label>
                        <input type="text" name="title" class="form-control input-lg" value="{{ old('title', $initiative->title) }}" required {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                    </div>
                    <div class="form-group">
                        <label>Business Purpose / Description</label>
                        <textarea name="purpose" class="form-control" rows="3" {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>{{ old('purpose', $initiative->purpose) }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Primary Segment Type</label>
                            @if($initiative->status === 'Planning')
                                <select name="primary_funding" class="form-control" required>
                                    <option value="ADP" {{ $initiative->primary_funding == 'ADP' ? 'selected' : '' }}>ADP (Development Budget)</option>
                                    <option value="REVENUE" {{ $initiative->primary_funding == 'REVENUE' ? 'selected' : '' }}>Revenue Budget (Non-Development)</option>
                                    <option value="OTHER" {{ $initiative->primary_funding == 'OTHER' ? 'selected' : '' }}>Other / Autonomous Reserves</option>
                                </select>
                            @else
                                <input type="text" class="form-control" value="{{ $initiative->primary_funding }} Budget" disabled>
                                <input type="hidden" name="primary_funding" value="{{ $initiative->primary_funding }}">
                            @endif
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Initiative State (Lifecycle Stage)</label>
                            <select name="status" class="form-control" required {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                                <option value="Planning" {{ $initiative->status == 'Planning' ? 'selected' : '' }}>Planning (Setup Phase)</option>
                                <option value="Active" {{ $initiative->status == 'Active' ? 'selected' : '' }}>Active (Open for Operations)</option>
                                <option value="Closed" {{ $initiative->status == 'Closed' ? 'selected' : '' }}>Closed (Procurement Suspended)</option>
                                <option value="Archived" {{ $initiative->status == 'Archived' ? 'selected' : '' }}>Archived (Read-Only Audit Record)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Ownership & Admin -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-orange">2. Ownership & Administration</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Which organization legally owns this initiative?</label>
                        @if($initiative->status === 'Planning')
                            <select name="owner_company_id" class="form-control select2" required>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ $initiative->owner_company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $initiative->ownerCompany->name ?? 'Unknown' }}" disabled>
                            <input type="hidden" name="owner_company_id" value="{{ $initiative->owner_company_id }}">
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Which office manages and maintains this initiative's execution?</label>
                        <select name="manager_location_id" class="form-control select2" required {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ $initiative->manager_location_id == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
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
                                <input type="checkbox" name="require_documents" value="1" {{ $initiative->require_documents ? 'checked' : '' }} {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                                <strong>Require Official Documents:</strong> Ensure a PDF order/document is uploaded when a Tracking Code is created.
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="allow_overshoot" value="1" {{ $initiative->allow_overshoot ? 'checked' : '' }} {{ $initiative->status == 'Archived' ? 'disabled' : '' }}>
                                <strong>Allow Target Overshoot:</strong> Allow operations (like GRNs) to exceed planned targets without requiring a formal override justification.
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- State-aware action footer -->
                <div class="box-footer text-right">
                    @if($initiative->status === 'Planning')
                        <!-- Delete is strictly only visible during Setup/Planning phase -->
                        <button type="button" class="btn btn-danger pull-left" onclick="confirmDelete()"><i class="fa fa-trash"></i> Delete Initiative</button>
                    @endif
                    
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default">Cancel</a>
                    
                    @if($initiative->status !== 'Archived')
                        <button type="submit" class="btn btn-warning">Save Changes</button>
                    @endif
                </div>
            </div>

        </form>
    </div>
</div>

@if($initiative->status === 'Planning')
    <!-- Hidden Delete Action Form -->
    <form id="delete-initiative-form" action="{{ route('gov.tracking.initiatives.destroy', $initiative->id) }}" method="POST" style="display: none;">
        @csrf @method('DELETE')
    </form>
    
    <script>
        function confirmDelete() {
            if (confirm('Delete this Initiative permanently? This will purge all associated setup records. This action is irreversible.')) {
                document.getElementById('delete-initiative-form').submit();
            }
        }
    </script>
@endif
@stop