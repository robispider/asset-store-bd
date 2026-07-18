@extends('layouts/default')

@section('title', __('requestlabels::requests.user_index_title'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-folder-open"></i> {{ __('requestlabels::requests.user_index_header_my_requests') }}</h3>
                <div class="box-tools pull-right">
                    <a href="{{ route('gov.requests.catalog') }}" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> {{ __('requestlabels::requests.user_index_btn_new_request') }}</a>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Type</th>
                            <th>Purpose</th>
                            <th>Submitted Date</th>
                            <th>Items</th>
                            <th>Document Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td><strong style="color: #3c8dbc;">{{ $req->request_number }}</strong></td>
                                <td><span class="label label-default">{{ ucwords(str_replace('_', ' ', $req->request_type)) }}</span></td>
                                <td>{{ $req->purpose }}</td>
                                <td>{{ $req->submitted_at ? $req->submitted_at->format('Y-m-d H:i') : '-' }}</td>
                                <td>
                                    <span class="badge bg-blue">{{ $req->items->count() }} line(s)</span>
                                    <small class="text-muted" style="display:block;">
                                        @foreach($req->items as $i)
                                            {{ $i->requested ? ($i->requested->name ?? $i->requested->asset_tag) : 'Item' }}{{ !$loop->last ? ',' : '' }}
                                        @endforeach
                                    </small>
                                </td>
                              <td>
                                    @if($req->approval_status === 'submitted')
                                    <span class="label label-warning"><i class="fas fa-clock"></i> {{ __('requestlabels::requests.user_index_status_under_review') }}</span>
                                @elseif($req->approval_status === 'approved')
                                    <span class="label label-success"><i class="fas fa-check"></i> {{ __('requestlabels::requests.user_index_status_approved') }}</span>
                                @elseif($req->approval_status === 'partially_approved')
                                    <span class="label bg-purple"><i class="fas fa-adjust"></i> {{ __('requestlabels::requests.user_index_status_partially_approved') }}</span>
                                @elseif($req->approval_status === 'closed')
                                    <span class="label label-success"><i class="fas fa-check-double"></i> {{ __('requestlabels::requests.user_index_status_closed_fulfilled') }}</span>
                                @elseif($req->approval_status === 'rejected')
                                    <span class="label label-danger"><i class="fas fa-times"></i> {{ __('requestlabels::requests.user_index_status_rejected') }}</span>
                                    @else
                                        <span class="label label-info">{{ ucfirst($req->approval_status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 30px;">
                                    <i class="fas fa-folder-open fa-2x text-muted"></i>
                                    <p class="text-muted" style="margin-top: 10px;">You have no submitted Service Requests yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection