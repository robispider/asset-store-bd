@extends('layouts/default')

@section('title', __('requestlabels::requests.user_index_title'))

@section('content')
<div class="cr-theme">
@include('govstore::components.notices')
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
                <table class="gs-table table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('requestlabels::requests.request_number') }}</th>
                            <th>{{ __('requestlabels::requests.item_type') }}</th>
                            <th>{{ __('requestlabels::requests.purpose') }}</th>
                            <th>{{ __('requestlabels::requests.submitted_date') }}</th>
                            <th>{{ __('requestlabels::requests.items') }}</th>
                            <th>{{ __('requestlabels::requests.document_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td><strong class="request-number">{{ $req->request_number }}</strong></td>
                                <td><span class="label label-default">{{ __('requestlabels::requests.request_type_'.$req->request_type) }}</span></td>
                                <td>{{ $req->purpose }}</td>
                                <td>{{ $req->submitted_at ? $req->submitted_at->format('Y-m-d H:i') : '-' }}</td>
                                <td>
                                    <span class="badge bg-blue">{{ $req->items->count() }} {{ __('requestlabels::requests.lines') }}</span>
                                    <small class="text-muted" style="display:block;">
                                        @foreach($req->items as $i)
                                            {{ $i->requested ? ($i->requested->name ?? $i->requested->asset_tag) : 'Item' }}{{ !$loop->last ? ',' : '' }}
                                        @endforeach
                                    </small>
                                </td>
                              <td>
                                    @if(in_array($req->fulfillment_status, ['issued', 'closed']))
                                    <span class="label label-warning"><i class="fas fa-clock"></i> {{ __('requestlabels::requests.event_'.$req->fulfillment_status) }}</span>
                                @elseif($req->approval_status === 'approved')
                                    <span class="label label-success"><i class="fas fa-check"></i> {{ __('requestlabels::requests.user_index_status_approved') }}</span>
                                @elseif($req->approval_status === 'partially_approved')
                                    <span class="label bg-purple"><i class="fas fa-adjust"></i> {{ __('requestlabels::requests.user_index_status_partially_approved') }}</span>
                                @elseif($req->approval_status === 'closed')
                                    <span class="label label-success"><i class="fas fa-check-double"></i> {{ __('requestlabels::requests.user_index_status_closed_fulfilled') }}</span>
                                @elseif($req->approval_status === 'rejected')
                                    <span class="label label-danger"><i class="fas fa-times"></i> {{ __('requestlabels::requests.user_index_status_rejected') }}</span>
                                    @else
                                        <span class="label label-info">{{ __('requestlabels::requests.event_'.$req->approval_status) }}</span>
                                    @endif
                                    @if($req->office_id && (int)$req->office_id === app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId)
                                    @if(in_array($req->approval_status, ['pending_primary', 'pending_final']) && !$req->decided_by && !$req->primary_decided_by)
                                        <form action="{{ route('gov.requests.withdraw', $req->id) }}" method="POST" style="margin-top:8px">
                                            @csrf <button class="btn btn-default btn-xs">{{ __('requestlabels::requests.withdraw') }}</button>
                                        </form>
                                    @endif
                                    @if($req->fulfillment_status === 'issued' && !$req->received_at)
                                        <form action="{{ route('gov.requests.receive', $req->id) }}" method="POST" style="margin-top:8px">
                                            @csrf <button class="btn btn-success btn-xs">{{ __('requestlabels::requests.confirm_receipt') }}</button>
                                        </form>
                                    @elseif($req->received_at)
                                        <small>{{ __('requestlabels::requests.receipt_recorded') }}</small>
                                    @endif
                                    @if(in_array($req->fulfillment_status, ['issued', 'closed']) && !$req->return_requested_at && $req->items->contains(fn($line) => $line->issued_qty > 0 && in_array($line->fulfilled_type ?: $line->requested_type, ['accessory','consumable'])))
                                        <form action="{{ route('gov.requests.return', $req->id) }}" method="POST" style="margin-top:8px">
                                            @csrf
                                            <label for="return-reason-{{ $req->id }}">{{ __('requestlabels::requests.return_reason') }}</label>
                                            <input id="return-reason-{{ $req->id }}" name="reason" type="text" class="form-control input-sm" required minlength="5" maxlength="2000">
                                            <p class="help-block">{{ __('requestlabels::requests.return_help') }}</p>
                                            <button class="btn btn-default btn-xs">{{ __('requestlabels::requests.return_bulk') }}</button>
                                        </form>
                                    @elseif($req->return_requested_at)
                                        <p>{{ __('requestlabels::requests.event_'.($req->return_document_id ? 'return_drafted' : 'return_requested')) }}</p>
                                    @endif
                                    @elseif(!$req->office_id)
                                        <p class="help-block">{{ __('requestlabels::requests.legacy_office_review') }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 30px;">
                                    <i class="fas fa-folder-open fa-2x text-muted"></i>
                                    <p class="text-muted" style="margin-top: 10px;">{{ __('requestlabels::requests.no_requests') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
