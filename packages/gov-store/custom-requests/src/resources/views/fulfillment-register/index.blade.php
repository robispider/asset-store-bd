@extends('layouts/default')

@section('title', __('requestlabels::requests.fulfillment_register_title'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-archive"></i> {{ __('requestlabels::requests.fulfillment_register_header_title') }}</h3>
                <div class="box-tools pull-right">
                    <span class="label label-success">{{ __('requestlabels::requests.fulfillment_register_status_label') }}</span>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-bordered table-hover dataTable">
                    <thead>
                        <tr>
                            <th>Service Request #</th>
                            <th>Requested By</th>
                            <th>Purpose / Location</th>
                            <th>Completion Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedRequests as $req)
                            <tr>
                                <td><strong>{{ $req->request_number }}</strong></td>
                                <td>{{ $req->requester->present()->fullName ?? 'Unknown User' }}</td>
                              <td>
                                    {{ \Illuminate\Support\Str::limit($req->purpose, 50) }}<br>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> {{ $req->delivery_location_id ? \App\Models\Location::find($req->delivery_location_id)?->name : 'Main Office' }}</small>
                                </td>
                                <td>{{ $req->closed_at ? $req->closed_at->format('d M Y, h:i A') : 'N/A' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('gov.requests.fulfillment_register.show', $req->id) }}" class="btn btn-sm btn-default">
                                        <i class="fas fa-search"></i> {{ __('requestlabels::requests.fulfillment_register_btn_view_ledger') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 20px;">{{ __('requestlabels::requests.fulfillment_register_empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection