@extends('layouts/default')

@section('title', __('requestlabels::requests.fulfillment_show_title_prefix') . $serviceRequest->request_number)

@section('content')
<div class="row">
    <!-- LEFT: progressive checkout table -->
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-boxes"></i> {{ __('requestlabels::requests.fulfillment_show_header_log_handover') }}</h3>
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
                                <th style="width: 150px;">Issue Qty Now</th>
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
                                <tr id="row_{{ $item->id }}">
                                    <td>
                                        <strong id="item_name_{{ $item->id }}">{{ $name }}</strong><br>
                                        <small class="text-muted">{{ ucfirst($item->requested_type) }}</small>
                                        
                                        <!-- Substituted Product Badge Area -->
                                        <div id="sub_badge_{{ $item->id }}" style="margin-top: 5px;"></div>
                                        
                                        <!-- Hidden inputs holding chosen substitution ID -->
                                        <input type="hidden" name="substitutions[{{ $item->id }}]" id="sub_input_{{ $item->id }}" value="">
                                    </td>
                                    <td style="vertical-align: middle;" class="text-center"><strong>{{ $item->approved_qty }}</strong></td>
                                    <td style="vertical-align: middle;" class="text-center text-success"><strong>{{ $item->issued_qty }}</strong></td>
                                    <td style="vertical-align: middle;" class="text-center text-danger"><strong>{{ $remaining }}</strong></td>
                                    <td style="vertical-align: middle;">
                                        @if($remaining === 0)
                                            <span class="text-success" style="font-weight: bold;"><i class="fas fa-check"></i> {{ __('requestlabels::requests.fulfillment_show_fully_issued') }}</span>
                                        @else
                                            <input type="number" name="issue[{{ $item->id }}]" class="form-control input-sm text-center" value="{{ $remaining }}" min="0" max="{{ $remaining }}">
                                            
                                            <!-- Substitute Trigger Button -->
                                            <button type="button" class="btn btn-xs btn-default btn-block" style="margin-top: 5px;" onclick="openSubstitutionModal({{ $item->id }}, '{{ $item->requested_type }}', '{{ $name }}')">
                                                <i class="fas fa-exchange-alt"></i> {{ __('requestlabels::requests.fulfillment_show_btn_substitute') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <a href="{{ route('gov.requests.fulfillment.index') }}" class="btn btn-default pull-left"><i class="fas fa-arrow-left"></i> {{ __('requestlabels::requests.fulfillment_show_btn_back') }}</a>
                    <button type="submit" class="btn btn-primary pull-right" onclick="return confirm('{{ __('requestlabels::requests.fulfillment_show_confirm_handover') }}')">
                        <i class="fas fa-clipboard-check"></i> {{ __('requestlabels::requests.fulfillment_show_btn_log_checkout') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- FORCE CLOSURE OPTION -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-ban"></i> {{ __('requestlabels::requests.fulfillment_show_header_terminate') }}</h3>
            </div>
            <form action="{{ route('gov.requests.fulfillment.close', $serviceRequest->id) }}" method="POST">
                @csrf
                <div class="box-body">
                    <p class="text-muted">{{ __('requestlabels::requests.fulfillment_show_text_stockout') }}</p>
                    <input type="text" name="reason" class="form-control" placeholder="{{ __('requestlabels::requests.fulfillment_show_input_reason_placeholder') }}" required>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-danger pull-right" onclick="return confirm('{{ __('requestlabels::requests.fulfillment_show_confirm_force_close') }}')">
                        <i class="fas fa-times-circle"></i> {{ __('requestlabels::requests.fulfillment_show_btn_force_close') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Timeline Audit Log -->
    <div class="col-md-4">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-history"></i> {{ __('requestlabels::requests.fulfillment_show_header_timeline') }}</h3>
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
                            @elseif($event->event_type === 'item_substituted')
                                <i class="fa fa-exchange-alt bg-orange"></i>
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
                                    @if($event->event_type === 'item_substituted')
                                        <p style="margin-top: 5px;">
                                            Swapped: <strong>{{ $event->details['original'] }}</strong> <br>
                                            With: <span class="text-orange" style="font-weight: bold;">{{ $event->details['substituted_with'] }}</span>
                                        </p>
                                    @endif
                                    @if($event->event_type === 'item_issued')
                                        <p style="margin-top: 5px;">
                                            Issued: <strong>{{ $event->details['item'] }}</strong> (Qty: {{ $event->details['issued_qty'] }})
                                        </p>
                                    @endif
                                    @if(isset($event->details['message']) && $event->event_type !== 'item_substituted')
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

<!-- SUBSTITUTION MODAL -->
<div class="modal fade" id="substitutionModal" tabindex="-1" role="dialog" aria-labelledby="substitutionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="substitutionModalLabel"><i class="fas fa-exchange-alt"></i> {{ __('requestlabels::requests.fulfillment_show_modal_title') }}</h4>
            </div>
            <div class="modal-body">
                <p>Select an alternative item to fulfill the request for: <strong id="modalOriginalItemName"></strong></p>
                
                <input type="hidden" id="modalLineItemId">
                <input type="hidden" id="modalItemType">

                <div class="form-group">
                    <label for="substituteSelector">{{ __('requestlabels::requests.fulfillment_show_modal_search_label') }}</label>
                    <select id="substituteSelector" class="form-control" style="width: 100%;"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default pull-left" data-dismiss="modal">{{ __('requestlabels::requests.fulfillment_show_modal_btn_cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="applySubstitution()">{{ __('requestlabels::requests.fulfillment_show_modal_btn_save') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
function openSubstitutionModal(lineId, type, originalName) {
    $('#modalLineItemId').val(lineId);
    $('#modalItemType').val(type);
    $('#modalOriginalItemName').text(originalName);
    
    // Clear old Select2 choice
    $('#substituteSelector').val(null).trigger('change');
    
    // Initialize standard Select2 with live Ajax search from our new route
    $('#substituteSelector').select2({
        dropdownParent: $('#substitutionModal'),
        ajax: {
            url: '{{ route("gov.requests.catalog.search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    type: $('#modalItemType').val()
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 1,
        placeholder: "Type to search alternative inventory..."
    });

    $('#substitutionModal').modal('show');
}

function applySubstitution() {
    var lineId = $('#modalLineItemId').val();
    var selectedData = $('#substituteSelector').select2('data')[0];

    if (selectedData) {
        // Update hidden input with the selected substitute ID
        $('#sub_input_' + lineId).val(selectedData.id);

        // Draw an orange substitution badge below the name
        $('#sub_badge_' + lineId).html(
            '<span class="label bg-orange" style="margin-top: 5px; display: inline-block;"><i class="fas fa-exchange-alt"></i> Substitute: ' + selectedData.text + '</span>'
        );

        $('#substitutionModal').modal('hide');
    } else {
        alert('Please select an item first.');
    }
}
</script>
@endsection