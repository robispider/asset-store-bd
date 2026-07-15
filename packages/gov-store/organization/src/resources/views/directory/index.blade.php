@extends('layouts/default')

@section('title', 'Government Directory Import')

@section('content')
<div class="row">
    <!-- LEFT: Ingestion Console -->
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cloud-download"></i> Synchronize Directory</h3>
            </div>
            
            <form action="{{ route('gov.org.directory.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="box-body">
                    <p class="text-muted">
                        This service imports the authoritative Bangladesh Government directory. 
                        It recursively builds hierarchical indexes and automatically registers matching flat Company entries inside Snipe-IT's core catalog.
                    </p>

                    <!-- Alert Banners showing exact Sync results -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h4><i class="icon fa fa-check"></i> Synchronization Complete!</h4>
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="well" style="background-color: #fcfcfc;">
                        <h4>Option A: Run Bundled Dataset</h4>
                        <p class="small text-muted">Imports the pre-verified core dataset included in the GovStore package (bangladesh_ministries_bilingual.csv).</p>
                        <button type="submit" class="btn btn-default btn-block"><i class="fa fa-play"></i> Run Bundled Package Import</button>
                    </div>

                    <div class="well" style="background-color: #fcfcfc; margin-top: 15px;">
                        <h4>Option B: Upload Custom Dataset</h4>
                        <div class="form-group">
                            <label>Upload CSV File</label>
                            <input type="file" name="csv_file" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-upload"></i> Upload & Synchronize Directory</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Status & Preview -->
    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-database"></i> Registry Status</h3>
                <div class="box-tools pull-right">
                    <span class="label label-info" style="font-size: 13px;">Total Registered Nodes: {{ $totalRecords }}</span>
                </div>
            </div>
            <div class="box-body table-responsive">
                <h4>Recent Directory Preview (Root Level Nodes)</h4>
                <table class="table table-striped table-bordered" style="margin-top: 15px;">
                    <thead>
                        <tr style="background-color: #f9f9f9;">
                            <th style="width: 50px;">ID</th>
                            <th>English Name</th>
                            <th>Bangla Name</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestRecords as $row)
                            <tr>
                                <td>{{ $row->id }}</td>
                                <td><strong>{{ $row->en_name }}</strong></td>
                                <td>{{ $row->bn_name }}</td>
                                <td><span class="label label-default">{{ $row->org_type }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Directory has not been populated yet. Run an import on the left to begin.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
