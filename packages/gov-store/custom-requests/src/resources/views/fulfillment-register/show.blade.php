@extends('layouts/default')

@section('title', __('requestlabels::requests.fulfillment_register_show_title_prefix') . $serviceRequest->request_number)

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('requestlabels::requests.fulfillment_register_show_header_summary') }}</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <th style="width: 200px;">Request Number</th>
                        <td><strong class="text-blue">{{ $serviceRequest->request_number }}</strong></td>
                    </tr>
                    <tr>
                        <th>Requested By</th>
                        <td>{{ $serviceRequest->requester->present()->fullName ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Purpose</th>
                        <td>{{ $serviceRequest->purpose }}</td>
                    </tr>
                    <tr>
                        <th>Completion Timestamp</th>
                        <td>{{ $serviceRequest->closed_at ? $serviceRequest->closed_at->format('d M Y, h:i A') : 'N/A' }}</td>
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
                        <div class="panel-heading" style="background-color: #f9fafc;">
                            <strong>{{ __('requestlabels::requests.fulfillment_register_show_doc_label') }}</strong> <span class="text-green">{{ $issue->issue_no }}</span>
                            <span class="pull-right text-muted">Issued By: {{ $issue->creator->first_name ?? 'System' }} on {{ $issue->created_at->format('d M Y') }}</span>
                        </div>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Item Type</th>
                                    <th>Quantity Deducted</th>
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
                <ul class="timeline">
                    @foreach($serviceRequest->events as $event)
                        <li>
                            <i class="fa fa-info bg-gray"></i>
                            <div class="timeline-item" style="box-shadow: none; border: 1px solid #eee;">
                                <span class="time"><i class="fa fa-clock"></i> {{ $event->created_at->format('H:i') }}</span>
                                <h3 class="timeline-header" style="font-size: 13px; font-weight: bold;">
                                    {{ ucwords(str_replace('_', ' ', $event->event_type)) }}
                                </h3>
                                <div class="timeline-body" style="padding: 5px 10px; font-size: 12px;">
                                    <strong>Executed by: {{ $event->user->first_name }}</strong><br>
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
@endsection