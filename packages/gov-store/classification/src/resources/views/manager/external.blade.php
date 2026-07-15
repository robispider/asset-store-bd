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
                    Manage mappings between external classification schemes and the Global Catalog.
                </p>

                <a href="{{ route('gov.catalog.external.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New External Mapping
                </a>

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
                            <td colspan="5" class="text-center text-muted">No external mappings configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
