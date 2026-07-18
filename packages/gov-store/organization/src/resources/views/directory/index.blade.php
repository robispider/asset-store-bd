@extends('layouts/default')

@section('title', __('organization_labels::orglabel.directory_title'))

@section('content')
<div class="row">
    <!-- LEFT: Ingestion Console -->
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cloud-download"></i> {{ __('organization_labels::orglabel.directory_sync_title') }}</h3>
            </div>
            
            <form action="{{ route('gov.org.directory.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="box-body">
                    <p class="text-muted">
                        {{ __('organization_labels::orglabel.directory_sync_description') }}
                    </p>

                    <!-- Alert Banners showing exact Sync results -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h4><i class="icon fa fa-check"></i> {{ __('organization_labels::orglabel.directory_sync_complete') }}</h4>
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="well" style="background-color: #fcfcfc;">
                        <h4>{{ __('organization_labels::orglabel.directory_option_bundled_title') }}</h4>
                        <p class="small text-muted">{{ __('organization_labels::orglabel.directory_option_bundled_desc') }}</p>
                        <button type="submit" class="btn btn-default btn-block"><i class="fa fa-play"></i> {{ __('organization_labels::orglabel.directory_option_bundled_button') }}</button>
                    </div>

                    <div class="well" style="background-color: #fcfcfc; margin-top: 15px;">
                        <h4>{{ __('organization_labels::orglabel.directory_option_custom_title') }}</h4>
                        <div class="form-group">
                            <label>{{ __('organization_labels::orglabel.directory_upload_label') }}</label>
                            <input type="file" name="csv_file" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-upload"></i> {{ __('organization_labels::orglabel.directory_upload_button') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Status & Preview -->
    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-database"></i> {{ __('organization_labels::orglabel.directory_status_title') }}</h3>
                <div class="box-tools pull-right">
                    <span class="label label-info" style="font-size: 13px;">{{ __('organization_labels::orglabel.directory_total_registered') }}{{ $totalRecords }}</span>
                </div>
            </div>
            <div class="box-body table-responsive">
                <h4>{{ __('organization_labels::orglabel.directory_preview_title') }}</h4>
                <table class="table table-striped table-bordered" style="margin-top: 15px;">
                    <thead>
                        <tr style="background-color: #f9f9f9;">
                            <th style="width: 50px;">{{ __('organization_labels::orglabel.directory_col_id') }}</th>
                            <th>{{ __('organization_labels::orglabel.directory_col_en_name') }}</th>
                            <th>{{ __('organization_labels::orglabel.directory_col_bn_name') }}</th>
                            <th>{{ __('organization_labels::orglabel.directory_col_type') }}</th>
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
                                <td colspan="4" class="text-center text-muted">{{ __('organization_labels::orglabel.directory_empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
