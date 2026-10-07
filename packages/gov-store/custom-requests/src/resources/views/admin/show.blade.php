@extends('layouts/default')

@section('title', __('requestlabels::requests.admin_show_title_prefix') . $serviceRequest->request_number)

@section('content')
<div class="row">
    <!-- LEFT: Line-Item Decision Form -->
    <div class="col-md-8">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-clipboard-list"></i> {{ __('requestlabels::requests.admin_show_header_adjust_items') }}</h3>
            </div>
            <form action="{{ route('gov.requests.admin.process', $serviceRequest->id) }}" method="POST">
                @csrf
                @if(!$canDecide)
                    <p class="alert alert-info">{{ __('requestlabels::requests.independent_approver_required') }}</p>
                @endif
                <fieldset {{ !$canDecide ? 'disabled' : '' }}>
                <div class="box-body table-responsive">
                    
                    <!-- Metadata Header -->
                    <table class="table table-bordered" style="background-color: #fafafa; margin-bottom: 25px;">
                        <tr>
                            <td style="width: 25%;"><strong>{{ __('requestlabels::requests.admin_show_label_purpose') }}</strong></td>
                            <td>{{ $serviceRequest->purpose }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('requestlabels::requests.justification') }}</strong></td>
                            <td>{{ $serviceRequest->justification }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('requestlabels::requests.required_location') }}</strong></td>
                            <td>
                                {{ $serviceRequest->required_by_date ?? __('requestlabels::requests.admin_show_label_no_deadline') }} / 
                                {{ $serviceRequest->delivery_location_id ? \App\Models\Location::find($serviceRequest->delivery_location_id)?->name : __('requestlabels::requests.admin_show_label_no_location') }}
                            </td>
                        </tr>
                    </table>

                    <!-- Line Items Table -->
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('requestlabels::requests.item_details') }}</th>
                                <th style="width: 110px;">{{ __('requestlabels::requests.requested') }}</th>
                                <th style="width: 120px;">{{ __('requestlabels::requests.approved_qty') }}</th>
                                <th style="width: 160px;">{{ __('requestlabels::requests.decision') }}</th>
                                <th>{{ __('requestlabels::requests.adjustment_notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($serviceRequest->items as $item)
                                @if($serviceRequest->approval_status === 'pending_final' && $item->line_approval_status === 'rejected') @continue @endif
                                @php
                                    $model = $item->requested;
                                    $name = $model ? ($model->present()->name ?: ($model->name ?? $model->asset_tag)) : __('requestlabels::requests.unknown_item');
                                @endphp
                                <tr id="row_{{ $item->id }}">
                                    <td>
                                        <strong>{{ $name }}</strong><br>
                                        <small class="text-muted">{{ __('requestlabels::requests.type_'.$item->requested_type) }}</small>
                                    </td>
                                    <td style="vertical-align: middle;"><strong>{{ $item->requested_qty }}</strong></td>
                                    <td style="vertical-align: middle;">
                                        @if($item->requested_type === 'asset')
                                            <input type="number" name="items[{{ $item->id }}][qty]" id="qty_{{ $item->id }}" class="form-control input-sm text-center" value="1" min="0" max="1" readonly>
                                        @else
                                            <input type="number" name="items[{{ $item->id }}][qty]" id="qty_{{ $item->id }}" class="form-control input-sm text-center" value="{{ $serviceRequest->primary_decided_by ? $item->approved_qty : $item->requested_qty }}" min="1" max="{{ $serviceRequest->primary_decided_by ? $item->approved_qty : $item->requested_qty }}">
                                        @endif
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <div class="btn-group" data-toggle="buttons">
                                            <label class="btn btn-sm btn-default active">
                                                <input type="radio" name="items[{{ $item->id }}][status]" value="approved" class="line-status-radio" data-id="{{ $item->id }}" checked> <i class="fas fa-check"></i> {{ __('requestlabels::requests.admin_show_btn_approve') }}
                                            </label>
                                            <label class="btn btn-sm btn-default">
                                                <input type="radio" name="items[{{ $item->id }}][status]" value="rejected" class="line-status-radio" data-id="{{ $item->id }}"> <i class="fas fa-times"></i> {{ __('requestlabels::requests.admin_show_btn_reject') }}
                                            </label>
                                        </div>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <input type="text" name="items[{{ $item->id }}][notes]" class="form-control input-sm" placeholder="{{ __('requestlabels::requests.admin_show_input_reason_placeholder') }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <a href="{{ route('gov.requests.admin.index') }}" class="btn btn-default pull-left"><i class="fas fa-arrow-left"></i> {{ __('requestlabels::requests.admin_show_btn_cancel') }}</a>
                    <button type="submit" class="btn btn-warning pull-right" data-request-confirm="{{ __('requestlabels::requests.admin_show_confirm_finalize') }}">
                        <i class="fas fa-signature"></i> {{ __('requestlabels::requests.admin_show_btn_finalize') }}
                    </button>
                </div>
                </fieldset>
            </form>
        </div>
    </div>

    <!-- RIGHT: Immutable Event History Timeline -->
    <div class="col-md-4">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-history"></i> {{ __('requestlabels::requests.admin_show_header_timeline') }}</h3>
            </div>
            <div class="box-body">
                <ul class="timeline">
                    @foreach($serviceRequest->events as $event)
                        <li>
                            @if($event->event_type === 'draft_created')
                                <i class="fa fa-plus bg-blue"></i>
                            @elseif($event->event_type === 'submitted')
                                <i class="fa fa-paper-plane bg-yellow-active"></i>
                            @elseif($event->event_type === 'under_review')
                                <i class="fa fa-eye bg-purple"></i>
                            @else
                                <i class="fa fa-info bg-gray"></i>
                            @endif

                            <div class="timeline-item" style="box-shadow: none; border: 1px solid #eee; background-color: #fafafa; margin-left: 45px;">
                                <span class="time"><i class="fa fa-clock"></i> {{ $event->created_at->format('H:i') }}</span>
                                <h3 class="timeline-header" style="font-size: 13px; font-weight: bold; border-bottom: none; padding: 5px 10px;">
                                    {{ __('requestlabels::requests.event_'.$event->event_type) }}
                                </h3>
                                <div class="timeline-body" style="padding: 5px 10px; font-size: 12px; color: #555;">
                                    {{ __('requestlabels::requests.executed_by') }}: <strong>{{ $event->user->display_name }}</strong>
                                    @if(isset($event->details['message']))
                                        <p style="margin-top: 5px;">{{ $event->details['message'] }}</p>
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

@section('moar_scripts')

@endsection
