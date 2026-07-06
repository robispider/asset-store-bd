@extends('layouts/default')

@section('title', 'My Requested Items')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">My Gov-Store Requests</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Requested Date</th>
                            <th>Item Name</th>
                            <th>Category Type</th>
                            <th>Status</th>
                            <th>My Notes / Admin Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td>{{ $req->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    {{ \GovStore\CustomRequests\Factories\RequestableFactory::make($req->requestable_type, $req->requestable_id)->getDisplayName() }}
                                </td>
                                <td><span class="label label-info">{{ class_basename($req->requestable_type) }}</span></td>
                                <td>
                                    @if($req->status == 'pending') 
                                        <span class="label label-warning"><i class="fas fa-clock"></i> Pending</span>
                                    @elseif($req->status == 'approved') 
                                        <span class="label label-success"><i class="fas fa-check"></i> Approved</span>
                                    @else 
                                        <span class="label label-danger"><i class="fas fa-times"></i> Rejected</span> 
                                    @endif
                                </td>
                                <td>{{ $req->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">You have not requested any items yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection