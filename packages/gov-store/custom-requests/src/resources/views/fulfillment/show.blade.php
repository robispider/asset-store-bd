@extends('layouts/default')
@section('title', 'Fulfillment Workspace: ' . $serviceRequest->request_number)

@section('content')
<style>
    .picking-card { background: #fff; border: 1px solid #d2d6de; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .picking-card-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #f4f4f4; padding-bottom: 15px; margin-bottom: 15px; }
    .item-icon { font-size: 28px; color: #3c8dbc; margin-right: 15px; }
    .item-title { font-size: 18px; font-weight: bold; margin: 0; color: #333; }
    .item-meta { font-size: 13px; color: #777; }
    .metrics-row { display: flex; gap: 20px; margin-bottom: 20px; }
    .metric-box { background: #f9fafb; border: 1px solid #eee; border-radius: 4px; padding: 10px 15px; text-align: center; flex: 1; }
    .metric-value { font-size: 22px; font-weight: bold; color: #333; }
    .metric-label { font-size: 11px; text-transform: uppercase; color: #777; }
    .scanner-row { background: #f4f4f4; padding: 10px 15px; border-radius: 4px; margin-bottom: 10px; display: flex; align-items: center; }
    .scanner-number { font-weight: bold; width: 30px; color: #555; }
    .scanner-input { flex: 1; }
</style>

<!-- TOP PANEL: The Legal Header -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid bg-blue" style="border-radius: 4px;">
            <div class="box-body" style="padding: 20px;">
                <div class="row">
                    <div class="col-md-4">
                        <h3 style="margin: 0 0 10px 0; font-weight: bold;">{{ $serviceRequest->request_number }}</h3>
                        <span class="label bg-green" style="font-size: 13px; padding: 5px 10px;">APPROVED</span>
                    </div>
                    <div class="col-md-4" style="border-left: 1px solid rgba(255,255,255,0.2);">
                        <p style="margin: 0; font-size: 15px;"><strong>Requester:</strong> {{ $serviceRequest->requester->present()->fullName }}</p>
                        <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;"><strong>Purpose:</strong> {{ $serviceRequest->purpose }}</p>
                    </div>
                    <div class="col-md-4" style="border-left: 1px solid rgba(255,255,255,0.2);">
                        <p style="margin: 0; font-size: 13px;"><strong>Approved By:</strong> {{ $serviceRequest->approvedBy->present()->fullName ?? 'System' }}</p>
                        <p style="margin: 5px 0 0 0; font-size: 13px;"><strong>Date:</strong> {{ $serviceRequest->approved_at ? $serviceRequest->approved_at->format('d M Y') : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Form: Wraps the picking grid and the main fulfillment action -->
    <form id="workspaceForm" action="{{ route('gov.requests.fulfillment.process', $serviceRequest->id) }}" method="POST">
        @csrf
        
        <!-- LEFT COLUMN: The Picking Cards -->
        <div class="col-md-8">
            @foreach($serviceRequest->items as $item)
                @if($item->line_approval_status !== 'approved') @continue @endif
                
                @php
                    $type = strtolower(class_basename($item->requested_type));
                    $isAssetModel = in_array($type, ['assetmodel', 'asset_model']);
                    $remaining = $item->approved_qty - $item->issued_qty;
                    
                    try {
                        $adapter = \GovStore\CustomRequests\Factories\RequestableFactory::make($item->requested_type, $item->requested_id);
                        $name = $adapter->getDisplayName();
                        $currentStock = $adapter->getAvailableQuantity();
                    } catch (\Exception $e) {
                        $name = 'Unknown Item';
                        $currentStock = 0;
                    }
                @endphp

                <div class="picking-card" data-line-id="{{ $item->id }}" data-type="{{ $isAssetModel ? 'asset' : 'bulk' }}" data-remaining="{{ $remaining }}">
                    
                    <div class="picking-card-header">
                        <div style="display: flex; align-items: center;">
                            <div class="item-icon">
                                {!! $isAssetModel ? '<i class="fas fa-laptop"></i>' : '<i class="fas fa-box-open"></i>' !!}
                            </div>
                            <div>
                                <h4 class="item-title" id="item_name_{{ $item->id }}">{{ $name }}</h4>
                                <span class="item-meta">{{ $isAssetModel ? 'Asset Model' : ucfirst($type) }}</span>
                                <div id="sub_badge_{{ $item->id }}"></div>
                                <input type="hidden" name="substitutions[{{ $item->id }}]" id="sub_input_{{ $item->id }}" value="">
                            </div>
                        </div>
                        <div>
                            @if($remaining > 0)
                                <button type="button" class="btn btn-sm btn-default" onclick="openSubstitutionModal({{ $item->id }}, '{{ $item->requested_type }}', '{{ $name }}')">
                                    <i class="fas fa-exchange-alt text-orange"></i> Substitute
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="metrics-row">
                        <div class="metric-box">
                            <div class="metric-value">{{ $item->approved_qty }}</div>
                            <div class="metric-label">Approved</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value text-success">{{ $item->issued_qty }}</div>
                            <div class="metric-label">Issued</div>
                        </div>
                        <div class="metric-box" style="background: #fdf2f2; border-color: #f2dede;">
                            <div class="metric-value text-danger">{{ $remaining }}</div>
                            <div class="metric-label">Remaining</div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #f4f4f4; padding-top: 15px;">
                        @if($remaining === 0)
                            <div class="text-center text-success" style="font-size: 16px; font-weight: bold; padding: 10px;">
                                <i class="fas fa-check-circle fa-2x"></i><br>Fully Issued
                            </div>
                        @else
                            
                            <!-- SCENARIO A: ASSET MODEL (The Scanner Sub-Grid) -->
                            @if($isAssetModel)
                                <label style="margin-bottom: 10px; color: #555;"><i class="fas fa-barcode"></i> Select Specific Physical Assets to Issue:</label>
                                @for($i = 0; $i < $remaining; $i++)
                                    <div class="scanner-row">
                                        <div class="scanner-number">#{{ $i + 1 }}</div>
                                        <div class="scanner-input">
                                            <select name="issue[{{ $item->id }}][]" class="form-control asset-scanner-select" style="width: 100%;">
                                                <option value="">-- Scan Barcode or Select Asset --</option>
                                                @if(isset($availableAssets[$item->id]))
                                                    @foreach($availableAssets[$item->id] as $asset)
                                                        <option value="{{ $asset->id }}">
                                                            [{{ $asset->asset_tag }}] SN: {{ $asset->serial ?: 'N/A' }} — Shelf: {{ $asset->location->name ?? 'Default' }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                @endfor

                            <!-- SCENARIO B: BULK ITEMS (The Big Number Input) -->
                            @else
                                <label style="margin-bottom: 10px; color: #555;">Issue Quantity Now:</label>
                                <div class="input-group input-group-lg" style="width: 250px;">
                                    <input type="number" name="issue[{{ $item->id }}]" class="form-control text-center bulk-issue-qty" 
                                           min="0" max="{{ $remaining }}" value="0" style="font-weight: bold;">
                                    <span class="input-group-addon bg-gray">/ {{ $remaining }}</span>
                                </div>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">Warehouse Stock Available: <strong>{{ $currentStock }}</strong></p>
                            @endif

                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- RIGHT COLUMN: The Action Sidebar -->
        <div class="col-md-4">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Fulfillment Action</h3>
                </div>
                <div class="box-body">
                    
                    <ul class="list-group list-group-unbordered" id="fulfillmentChecklist" style="margin-bottom: 20px;">
                        <!-- JS Dynamically injects checklist state here -->
                    </ul>

                    <div class="form-group">
                        <label class="text-muted">Handover Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="E.g., Handed over to department peon..."></textarea>
                    </div>

                    <button type="submit" id="completeIssueBtn" class="btn btn-primary btn-lg btn-block" disabled onclick="return confirm('Complete this issue operation?')">
                        <i class="fas fa-clipboard-check"></i> Complete Issue
                    </button>
                </div>
            </div>
            
            </form> <!-- FIXED: CLOSED MAIN FORM HERE TO PREVENT NESTING -->

            <!-- FORCE CLOSURE OPTION (Separate, distinct form) -->
            <div class="box box-solid" style="margin-top: 20px;">
                <div class="box-header with-border">
                    <h3 class="box-title" style="color: #dd4b39;"><i class="fas fa-ban"></i> Terminate / Out of Stock</h3>
                </div>
                <div class="box-body">
                    <button type="button" class="btn btn-danger btn-block" data-toggle="collapse" data-target="#forceClosePanel">
                        <i class="fas fa-exclamation-triangle"></i> Cancel / Out of Stock
                    </button>
                    <div id="forceClosePanel" class="collapse" style="margin-top: 10px; padding: 15px; background: #fdf2f2; border: 1px solid #ebccd1; border-radius: 4px;">
                        <form action="{{ route('gov.requests.fulfillment.close', $serviceRequest->id) }}" method="POST" id="closeForm" style="margin: 0;">
                            @csrf
                            <input type="text" name="reason" class="form-control input-sm" placeholder="Reason for termination..." required style="margin-bottom: 10px; border: 1px solid #dd4b39;">
                            <button type="submit" class="btn btn-danger btn-sm btn-block" onclick="return confirm('Force close this request permanently?')">Confirm Close</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- The Timeline -->
            @include('govstore::components.timeline-widget', ['events' => $serviceRequest->events])
        </div>
    
</div>

<!-- SUBSTITUTION MODAL -->
@include('govstore::components.substitution-modal')

@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    
    // 1. Initialize Asset Scanners with Select2 for fast typing/barcode scanning
    $('.asset-scanner-select').select2();

    // 2. Prevent Duplicate Asset Selection (Barcode Collision Check)
    $('.asset-scanner-select').on('change', function() {
        let selectedValue = $(this).val();
        if (!selectedValue) {
            evaluateChecklist();
            return;
        }

        let $card = $(this).closest('.picking-card');
        let duplicateFound = false;

        $card.find('.asset-scanner-select').not(this).each(function() {
            if ($(this).val() === selectedValue) {
                duplicateFound = true;
            }
        });

        if (duplicateFound) {
            alert('WARNING: You cannot scan the exact same Asset twice for one request.');
            $(this).val('').trigger('change');
        } else {
            evaluateChecklist();
        }
    });

    // 3. Listen to Bulk Quantity Changes
    $('.bulk-issue-qty').on('input change', function() {
        let max = parseInt($(this).attr('max'));
        let val = parseInt($(this).val());
        
        if (val > max) $(this).val(max);
        if (val < 0 || isNaN(val)) $(this).val(0);
        
        evaluateChecklist();
    });

    // 4. Evaluate Checklist and Unlock Submit Button
    function evaluateChecklist() {
        let totalLinesToPick = $('.picking-card').length;
        let linesSatisfied = 0;
        let $checklist = $('#fulfillmentChecklist');
        let totalItemsSelected = 0;

        if ($checklist.length === 0) return;
        $checklist.empty();

        $('.picking-card').each(function() {
            let $card = $(this);
            let type = $card.data('type');
            let name = $card.find('.item-title').text();
            let remaining = parseInt($card.data('remaining'));
            
            let lineSatisfied = false;

            if (remaining === 0) {
                lineSatisfied = true;
                linesSatisfied++;
                $checklist.append(`<li class="list-group-item"><i class="fas fa-check text-green"></i> ${name} (Fully Issued)</li>`);
            } 
            else if (type === 'asset') {
                let assetsSelected = 0;
                $card.find('.asset-scanner-select').each(function() {
                    if ($(this).val()) assetsSelected++;
                });

                totalItemsSelected += assetsSelected;
                
                if (assetsSelected === remaining) {
                    lineSatisfied = true;
                    linesSatisfied++;
                    $checklist.append(`<li class="list-group-item"><i class="fas fa-check text-green"></i> ${name} (${assetsSelected}/${remaining} Selected)</li>`);
                } else if (assetsSelected > 0) {
                    $checklist.append(`<li class="list-group-item"><i class="fas fa-dot-circle text-yellow"></i> ${name} (Partial: ${assetsSelected}/${remaining})</li>`);
                } else {
                    $checklist.append(`<li class="list-group-item"><i class="far fa-circle text-muted"></i> ${name} (Pending)</li>`);
                }
            } 
            else { // Bulk (Consumable)
                let qtyEntered = parseInt($card.find('.bulk-issue-qty').val()) || 0;
                totalItemsSelected += qtyEntered;

                if (qtyEntered === remaining) {
                    lineSatisfied = true;
                    linesSatisfied++;
                    $checklist.append(`<li class="list-group-item"><i class="fas fa-check text-green"></i> ${name} (All ${qtyEntered} Ready)</li>`);
                } else if (qtyEntered > 0) {
                    $checklist.append(`<li class="list-group-item"><i class="fas fa-dot-circle text-yellow"></i> ${name} (Partial: ${qtyEntered})</li>`);
                } else {
                    $checklist.append(`<li class="list-group-item"><i class="far fa-circle text-muted"></i> ${name} (Pending)</li>`);
                }
            }
        });

        // Unlock Submit if ANY items are selected (Allows safe partial fulfillments)
        if (totalItemsSelected > 0) {
            $('#completeIssueBtn').removeAttr('disabled').removeClass('btn-default').addClass('btn-primary');
        } else {
            $('#completeIssueBtn').attr('disabled', 'disabled').removeClass('btn-primary').addClass('btn-default');
        }
    }

    // Run once on load
    evaluateChecklist();
});
</script>
@endsection