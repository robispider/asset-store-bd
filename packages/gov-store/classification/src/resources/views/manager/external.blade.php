@extends('layouts/default')

@section('title', 'External Mappings')

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">External Catalog Mappings</h3>
            </div>

            <div class="box-body">
                <p class="text-muted">
                    Manage mappings between external classification schemes (such as CGA or HS Codes) and the Global Catalog.
                </p>

                <!-- Changed to a safe disabled placeholder button to prevent RouteNotFoundException -->
                <button class="btn btn-primary" disabled title="External mapping creation will be available in the upcoming Phase 3 localization release.">
                    <i class="fas fa-plus"></i> New External Mapping (Phase 3)
                </button>

                <table class="table table-striped table-bordered" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Source Scheme</th>
                            <th>Target Scheme</th>
                            <th>Mapping Rule</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No external mappings configured. External crosswalk integrations are scheduled for Phase 3.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection