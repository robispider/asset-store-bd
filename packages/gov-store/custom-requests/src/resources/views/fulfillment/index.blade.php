@extends('layouts/default')

@section('title', 'Fulfillment Queue')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-shipping-fast"></i> Approved Items Awaiting Issuance</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Requested By</th>
                            <th>Purpose / Location</th>
                            <th>Approval Date</th>
                            <th>Fulfillment Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeRequests as $req)
                            <tr>
                                <td><strong style="color: #3c8dbc;">{{ $req->request_number }}</strong></td>
                                <td>{{ $req->requester->present()->fullName ?? 'Unknown User' }}</td>
                                <td>
                                    {{ $req->purpose }}<br>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> {{ $req->delivery_location_id ? \App\Models\Location::find($req->delivery_location_id)?->name : 'Main Office' }}</small>
                                </td>
                                <td>{{ $req->approved_at ? $req->approved_at->format('Y-m-d H:i') : '-' }}</td>
                                <td>
                                    @if($req->fulfillment_status === 'unstarted')
                                        <span class="label label-default">Awaiting Picking</span>
                                    @elseif($req->fulfillment_status === 'partially_issued')
                                        <span class="label bg-purple">Partially Dispatched</span>
                                    @else
                                        <span class="label label-info">{{ ucfirst($req->fulfillment_status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('gov.requests.fulfillment.show', $req->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-box-open"></i> Pick & Issue Items
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 30px;">No requests are currently awaiting inventory fulfillment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection