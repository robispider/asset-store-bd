@extends('layouts/default')

@section('title', 'Pick & Issue Items: ' . $serviceRequest->request_number)

@section('content')
<div class="row">
    <!-- LEFT: progressive checkout table -->
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-boxes"></i> Log Inventory Handover</h3>
            </div>
            <form action="{{ route('gov.requests.fulfillment.process', $serviceRequest->id) }}" method="POST">
                @csrf
                <div class="box-body table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Item Details</th>
                                <th style="width: 100px;">Approved</th>
                                <th style="width: 100px;">Already Issued</th>
                                <th style="width: 100px;">Remaining</th>
                                <th style="width: 130px;">Issue Qty Now</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($serviceRequest->items as $item)
                                @if($item->line_approval_status !== 'approved') @continue @endif
                                
                                @php
                                    $model = $item->requested;
                                    $name = $model ? ($model->present()->name ?: ($model->name ?? $model->asset_tag)) : 'Unknown Item';
                                    $remaining = $item->approved_qty - $item->issued_qty;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $name }}</strong><br>
                                        <small class="text-muted">{{ ucfirst($item->requested_type) }}</small>
                                    </td>
                                    <td style="vertical-align: middle;" class="text-center"><strong>{{ $item->approved_qty }}</strong></td>
                                    <td style="vertical-align: middle;" class="text-center text-success"><strong>{{ $item->issued_qty }}</strong></td>
                                    <td style="vertical-align: middle;" class="text-center text-danger"><strong>{{ $remaining }}</strong></td>
                                    <td style="vertical-align: middle;">
                                        @if($remaining === 0)
                                            <span class="text-success" style="font-weight: bold;"><i class="fas fa-check"></i> Fully Issued</span>
                                        @else
                                            <input type="number" name="issue[{{ $item->id }}]" class="form-control input-sm text-center" value="{{ $remaining }}" min="0" max="{{ $remaining }}">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <a href="{{ route('gov.requests.fulfillment.index') }}" class="btn btn-default pull-left"><i class="fas fa-arrow-left"></i> Back</a>
                    <button type="submit" class="btn btn-primary pull-right" onclick="return confirm('Confirming handover? This will deduct Snipe-IT inventory and write to history logs.')">
                        <i class="fas fa-clipboard-check"></i> Log Checkout & Issue Items
                    </button>
                </div>
            </form>
        </div>

        <!-- FORCE CLOSURE OPTION -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-ban"></i> Terminate / Close Request</h3>
            </div>
            <form action="{{ route('gov.requests.fulfillment.close', $serviceRequest->id) }}" method="POST">
                @csrf
                <div class="box-body">
                    <p class="text-muted">If items cannot be fulfilled due to permanent stockout, you can force close the remaining line items.</p>
                    <input type="text" name="reason" class="form-control" placeholder="Provide reason for force closure..." required>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-danger pull-right" onclick="return confirm('Are you sure you want to terminate this request? Unissued lines will be cancelled.')">
                        <i class="fas fa-times-circle"></i> Force Close Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Timeline Audit Log -->
    <div class="col-md-4">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-history"></i> Request Timeline</h3>
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
                            @elseif($event->event_type === 'item_issued')
                                <i class="fa fa-truck bg-green-active"></i>
                            @else
                                <i class="fa fa-info bg-gray"></i>
                            @endif

                            <div class="timeline-item" style="box-shadow: none; border: 1px solid #eee; background-color: #fafafa; margin-left: 45px;">
                                <span class="time"><i class="fa fa-clock"></i> {{ $event->created_at->format('H:i') }}</span>
                                <h3 class="timeline-header" style="font-size: 13px; font-weight: bold; border-bottom: none; padding: 5px 10px;">
                                    {{ ucwords(str_replace('_', ' ', $event->event_type)) }}
                                </h3>
                                <div class="timeline-body" style="padding: 5px 10px; font-size: 12px; color: #555;">
                                    Executed by: <strong>{{ $event->user->display_name }}</strong>
                                    @if(isset($event->details['item']))
                                        <p style="margin-top: 5px;">
                                            Issued: <strong>{{ $event->details['item'] }}</strong> (Qty: {{ $event->details['issued_qty'] }})
                                        </p>
                                    @endif
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