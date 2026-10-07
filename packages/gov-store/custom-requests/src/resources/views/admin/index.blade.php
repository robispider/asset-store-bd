@extends('layouts/default')

@section('title', __('requestlabels::requests.admin_index_title'))

@section('content')
@include('govstore::components.notices')
<div class="row">
    <!-- PENDING QUEUE -->
    <div class="col-md-12">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-hourglass-half"></i> {{ __('requestlabels::requests.admin_index_header_pending') }}</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('requestlabels::requests.request_number') }}</th>
                            <th>{{ __('requestlabels::requests.requester') }}</th>
                            <th>{{ __('requestlabels::requests.item_type') }}</th>
                            <th>{{ __('requestlabels::requests.purpose') }}</th>
                            <th>{{ __('requestlabels::requests.submitted_date') }}</th>
                            <th>{{ __('requestlabels::requests.items') }}</th>
                            <th>{{ __('requestlabels::requests.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingRequests as $req)
                            <tr>
                                <td><strong style="color: #3c8dbc;">{{ $req->request_number }}</strong></td>
                                <td>{{ $req->requester->present()->fullName ?? __('requestlabels::requests.unknown_user') }}</td>
                                <td><span class="label label-default">{{ __('requestlabels::requests.request_type_'.$req->request_type) }}</span></td>
                                <td>{{ $req->purpose }}</td>
                                <td>{{ $req->submitted_at ? $req->submitted_at->format('Y-m-d H:i') : $req->created_at->format('Y-m-d') }}</td>
                                <td><span class="badge bg-blue">{{ $req->items->count() }} {{ __('requestlabels::requests.lines') }}</span></td>
                                <td>
                                    <a href="{{ route('gov.requests.admin.show', $req->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Review & Process
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center" style="padding: 30px;">{{ __('requestlabels::requests.admin_index_empty_pending') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- RECENT AUDIT HISTORY -->
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-history"></i> {{ __('requestlabels::requests.admin_index_header_processed') }}</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('requestlabels::requests.request_number') }}</th>
                            <th>{{ __('requestlabels::requests.requester') }}</th>
                            <th>{{ __('requestlabels::requests.document_status') }}</th>
                            <th>{{ __('requestlabels::requests.processed_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($processedRequests as $req)
                            <tr>
                                <td><strong>{{ $req->request_number }}</strong></td>
                                <td>{{ $req->requester->present()->fullName ?? __('requestlabels::requests.unknown_user') }}</td>
                               <td>
                                    @if($req->approval_status === 'approved')
                                        <span class="label label-success">{{ __('requestlabels::requests.admin_index_status_approved') }}</span>
                                    @elseif($req->approval_status === 'partially_approved')
                                        <span class="label bg-purple">{{ __('requestlabels::requests.admin_index_status_partially_approved') }}</span>
                                    @elseif($req->approval_status === 'closed')
                                        <span class="label label-success"><i class="fas fa-check-double"></i> {{ __('requestlabels::requests.admin_index_status_closed_fulfilled') }}</span>
                                    @elseif($req->approval_status === 'rejected')
                                        <span class="label label-danger">{{ __('requestlabels::requests.admin_index_status_rejected') }}</span>
                                    @else
                                        <span class="label label-info">{{ __('requestlabels::requests.event_'.$req->approval_status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $req->approved_at ? $req->approved_at->format('Y-m-d H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="padding: 20px;">{{ __('requestlabels::requests.admin_index_empty_processed') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
