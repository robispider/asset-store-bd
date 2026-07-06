@extends('layouts/default')

@section('title', 'Pending Item Requests')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Gov-Store Approval Dashboard</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Requested Date</th>
                            <th>Requested By</th>
                            <th>Item Type</th>
                            <th>Item Name</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td>{{ $req->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $req->requester->present()->fullName ?? 'Unknown User' }}</td>
                                <td><span class="label label-info">{{ class_basename($req->requestable_type) }}</span></td>
                                <td>
                                    {{-- Use our Phase 2 factory to get the display name securely --}}
                                    {{ \GovStore\CustomRequests\Factories\RequestableFactory::make($req->requestable_type, $req->requestable_id)->getDisplayName() }}
                                </td>
                                <td>{{ $req->notes ?? '-' }}</td>
                                <td>
                                    <!-- Approve Button -->
                                    <form action="{{ route('gov.requests.admin.approve', $req->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this request? The item will be checked out immediately.')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>

                                    <!-- Reject Button -->
                                    <form action="{{ route('gov.requests.admin.reject', $req->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this request?')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No pending requests at this time.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection