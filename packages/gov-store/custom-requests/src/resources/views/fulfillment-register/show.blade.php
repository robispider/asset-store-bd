@extends('layouts/default')

@section('title', __('requestlabels::requests.fulfillment_register_show_title_prefix') . $serviceRequest->request_number)

@section('content')
<div class="cr-theme">
@if($serviceRequest->return_requested_at)
    <div class="alert alert-info">
        <p>{{ __('requestlabels::requests.return_help') }}</p>
        @if($serviceRequest->return_document_id)
            <a href="{{ route('storeops.documents.workspace', ['type'=>'receipt', 'id'=>$serviceRequest->return_document_id]) }}">{{ __('requestlabels::requests.open_return_receipt') }}</a>
        @else
            <form action="{{ route('gov.requests.fulfillment_register.return', $serviceRequest->id) }}" method="POST">
                @csrf
                <button class="btn btn-primary">{{ __('requestlabels::requests.draft_return_receipt') }}</button>
            </form>
        @endif
    </div>
@endif
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('requestlabels::requests.fulfillment_register_show_header_summary') }}</h3>
            </div>
            <div class="box-body">
                <table class="gs-table table table-striped">
                    <tr>
                        <th style="width: 200px;">{{ __('requestlabels::requests.request_number') }}</th>
                        <td><strong class="request-number">{{ $serviceRequest->request_number }}</strong></td>
                    </tr>
                    <tr>
                        <th>{{ __('requestlabels::requests.requester') }}</th>
                        <td>{{ $serviceRequest->requester->present()->fullName ?? __('requestlabels::requests.not_available') }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('requestlabels::requests.purpose') }}</th>
                        <td>{{ $serviceRequest->purpose }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('requestlabels::requests.completion_date') }}</th>
                        <td>{{ $serviceRequest->closed_at ? $serviceRequest->closed_at->format('d M Y, h:i A') : __('requestlabels::requests.not_available') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('requestlabels::requests.fulfillment_register_show_header_documents') }}</h3>
            </div>
            <div class="box-body">
                @forelse($goodsIssues as $issue)
                    <div class="panel panel-default">
                        <div class="panel-heading register-panel-heading">
                            <strong>{{ __('requestlabels::requests.fulfillment_register_show_doc_label') }}</strong> <span class="text-green">{{ $issue->issue_no }}</span>
                            <span class="pull-right text-muted">{{ __('requestlabels::requests.executed_by') }}: {{ $issue->creator->first_name ?? __('requestlabels::requests.system') }} — {{ $issue->created_at->format('d M Y') }}</span>
                        </div>
                        <table class="gs-table table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('requestlabels::requests.item_type') }}</th>
                                    <th>{{ __('requestlabels::requests.quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($issue->items as $issueItem)
                                    <tr>
                                        <td>{{ class_basename($issueItem->stockable_type) }}</td>
                                        <td>{{ $issueItem->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding: 10px;">{{ __('requestlabels::requests.fulfillment_register_show_empty_ledger') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('requestlabels::requests.fulfillment_register_show_header_audit') }}</h3>
            </div>
            <div class="box-body">
                <ul class="timeline request-timeline">
                    @foreach($serviceRequest->events as $event)
                        <li>
                            <i class="fa fa-info bg-gray"></i>
                            <div class="timeline-item">
                                <span class="time"><i class="fa fa-clock"></i> {{ $event->created_at->format('H:i') }}</span>
                                <h3 class="timeline-header" style="font-size: 13px; font-weight: bold;">
                                    {{ __('requestlabels::requests.event_'.$event->event_type) }}
                                </h3>
                                <div class="timeline-body" style="padding: 5px 10px; font-size: 12px;">
                                    <strong>{{ __('requestlabels::requests.executed_by') }}: {{ $event->user->first_name }}</strong><br>
                                    @if(isset($event->details['message']))
                                        {{ $event->details['message'] }}
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                    <li><i class="fa fa-clock bg-gray"></i></li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
