@extends('layouts/default')

@section('title', 'Gov Approvals Dashboard')

@section('content')
<div class="row">
    <!-- PENDING QUEUE -->
    <div class="col-md-12">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-hourglass-half"></i> Requests Awaiting Review</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Requested By</th>
                            <th>Type</th>
                            <th>Purpose</th>
                            <th>Date Submitted</th>
                            <th>Items Count</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingRequests as $req)
                            <tr>
                                <td><strong style="color: #3c8dbc;">{{ $req->request_number }}</strong></td>
                                <td>{{ $req->requester->present()->fullName ?? 'Unknown User' }}</td>
                                <td><span class="label label-default">{{ ucwords(str_replace('_', ' ', $req->request_type)) }}</span></td>
                                <td>{{ $req->purpose }}</td>
                                <td>{{ $req->submitted_at ? $req->submitted_at->format('Y-m-d H:i') : $req->created_at->format('Y-m-d') }}</td>
                                <td><span class="badge bg-blue">{{ $req->items->count() }} line(s)</span></td>
                                <td>
                                    <a href="{{ route('gov.requests.admin.show', $req->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Review & Process
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center" style="padding: 30px;">No pending requests awaiting approval.</td>
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
                <h3 class="box-title"><i class="fas fa-history"></i> Recently Processed Requests</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Requester</th>
                            <th>Status</th>
                            <th>Processed Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($processedRequests as $req)
                            <tr>
                                <td><strong>{{ $req->request_number }}</strong></td>
                                <td>{{ $req->requester->present()->fullName ?? 'Unknown' }}</td>
                               <td>
                                    @if($req->approval_status === 'approved')
                                        <span class="label label-success">Approved</span>
                                    @elseif($req->approval_status === 'partially_approved')
                                        <span class="label bg-purple">Partially Approved</span>
                                    @elseif($req->approval_status === 'closed')
                                        <span class="label label-success"><i class="fas fa-check-double"></i> Closed / Fulfilled</span>
                                    @elseif($req->approval_status === 'rejected')
                                        <span class="label label-danger">Rejected</span>
                                    @else
                                        <span class="label label-info">{{ ucfirst($req->approval_status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $req->approved_at ? $req->approved_at->format('Y-m-d H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="padding: 20px;">No processed history available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection