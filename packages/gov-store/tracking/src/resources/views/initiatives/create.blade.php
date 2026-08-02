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

            <!-- Section 2: Natural Language Ownership & Accountability -->
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title text-orange">2. Ownership & Accountability</h3></div>
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
                    
                    <div class="callout callout-warning" style="margin-bottom: 0; background-color: #fcf8e3 !important; border-color: #faf2cc !important; color: #8a6d3b !important;">
                        <h4><i class="fa fa-users"></i> Operation Unit Required</h4>
                        <p class="text-sm">You must designate an Operation Head and at least one Operation Officer inside the workspace immediately after launching before this Initiative can be activated.</p>
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