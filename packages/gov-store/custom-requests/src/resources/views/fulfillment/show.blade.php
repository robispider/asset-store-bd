@extends('layouts/default')
@section('title', __('requestlabels::requests.fulfillment_show_title_prefix') . $serviceRequest->request_number)

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
                        <span class="label bg-green" style="font-size: 13px; padding: 5px 10px;">{{ __('requestlabels::requests.event_'.$serviceRequest->approval_status) }}</span>
                    </div>
                    <div class="col-md-4" style="border-left: 1px solid rgba(255,255,255,0.2);">
                        <p style="margin: 0; font-size: 15px;"><strong>{{ __('requestlabels::requests.requester') }}</strong> {{ $serviceRequest->requester->present()->fullName }}</p>
                        <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;"><strong>{{ __('requestlabels::requests.purpose') }}</strong> {{ $serviceRequest->purpose }}</p>
                    </div>
                    <div class="col-md-4" style="border-left: 1px solid rgba(255,255,255,0.2);">
                        <p style="margin: 0; font-size: 13px;"><strong>{{ __('requestlabels::requests.approved_by') }}</strong> {{ $serviceRequest->approvedBy?->present()->fullName ?? __('requestlabels::requests.system') }}</p>
                        <p style="margin: 5px 0 0 0; font-size: 13px;"><strong>{{ __('requestlabels::requests.date') }}</strong> {{ $serviceRequest->approved_at ? $serviceRequest->approved_at->format('d M Y') : __('requestlabels::requests.not_available') }}</p>
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
    </form>

        <!-- LEFT COLUMN: The Picking Cards -->
        <div class="col-md-8">
            @foreach($serviceRequest->items as $item)
                @if($item->line_approval_status !== 'approved') @continue @endif

                @php
                    $type = strtolower(class_basename($item->requested_type));
                    $isAssetModel = in_array($type, ['assetmodel', 'asset_model']);
                    $remaining = $item->approved_qty - $item->issued_qty;

                    try {
                        $adapter = \GovStore\CustomRequests\Factories\RequestableFactory::make($item->fulfilled_type ?: $item->requested_type, $item->fulfilled_id ?: $item->requested_id);
                        $name = $adapter->getDisplayName();
                        $stock = app(\GovStore\CustomRequests\Services\RequestInventory::class)->validateItem($item->fulfilled_type ?: $item->requested_type, $item->fulfilled_id ?: $item->requested_id);
                        $currentStock = app(\GovStore\CustomRequests\Services\RequestInventory::class)->available($type, $stock, $serviceRequest->office_id, $item->id);
                    } catch (\Exception $e) {
                        $name = __('requestlabels::requests.unknown_item');
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
                                <span class="item-meta">{{ __('requestlabels::requests.type_'.$type) }}</span>
                                <div id="sub_badge_{{ $item->id }}"></div>
                                <input form="workspaceForm" type="hidden" name="substitutions[{{ $item->id }}]" id="sub_input_{{ $item->id }}" value="">
                            </div>
                        </div>
                        <div>
                            @if($remaining > 0)
                                <button type="button" class="btn btn-sm btn-default" data-substitute-line="{{ $item->id }}" data-substitute-type="{{ $item->requested_type }}" data-substitute-name="{{ $name }}">
                                    <i class="fas fa-exchange-alt text-orange"></i> {{ __('requestlabels::requests.fulfillment_show_btn_substitute') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="metrics-row">
                        <div class="metric-box">
                            <div class="metric-value">{{ $item->approved_qty }}</div>
                            <div class="metric-label">{{ __('requestlabels::requests.fulfillment_show_col_approved') }}</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value text-success">{{ $item->issued_qty }}</div>
                            <div class="metric-label">{{ __('requestlabels::requests.issued_label') }}</div>
                        </div>
                        <div class="metric-box" style="background: #fdf2f2; border-color: #f2dede;">
                            <div class="metric-value text-danger">{{ $remaining }}</div>
                            <div class="metric-label">{{ __('requestlabels::requests.remaining') }}</div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #f4f4f4; padding-top: 15px;">
                        @if($remaining === 0)
                            <div class="text-center text-success" style="font-size: 16px; font-weight: bold; padding: 10px;">
                                <i class="fas fa-check-circle fa-2x"></i><br>{{ __('requestlabels::requests.fulfillment_show_fully_issued') }}
                            </div>
                        @else

                            <!-- SCENARIO A: ASSET MODEL (The Scanner Sub-Grid) -->
                            @if($isAssetModel)
                                <label style="margin-bottom: 10px; color: #555;"><i class="fas fa-barcode"></i> {{ __('requestlabels::requests.select_serials') }}</label>
                                @for($i = 0; $i < $remaining; $i++)
                                    <div class="scanner-row">
                                        <div class="scanner-number">#{{ $i + 1 }}</div>
                                        <div class="scanner-input">
                                            <select form="workspaceForm" name="issue[{{ $item->id }}][]" class="form-control asset-scanner-select" style="width: 100%;">
                                                <option value="">{{ __('requestlabels::requests.select_asset') }}</option>
                                                @if(isset($availableAssets[$item->id]))
                                                    @foreach($availableAssets[$item->id] as $asset)
                                                        <option value="{{ $asset->id }}">
                                                            [{{ $asset->asset_tag }}] {{ __('requestlabels::requests.serial') }}: {{ $asset->serial ?: __('requestlabels::requests.not_available') }} — {{ __('requestlabels::requests.location') }}: {{ $asset->location->name ?? __('requestlabels::requests.working_office') }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                @endfor

                            <!-- SCENARIO B: BULK ITEMS (The Big Number Input) -->
                            @else
                                <label style="margin-bottom: 10px; color: #555;">{{ __('requestlabels::requests.fulfillment_show_col_issue_qty') }}</label>
                                <div class="input-group input-group-lg" style="width: 250px;">
                                    <input form="workspaceForm" type="number" name="issue[{{ $item->id }}]" class="form-control text-center bulk-issue-qty"
                                           min="0" max="{{ $remaining }}" value="0" style="font-weight: bold;">
                                    <span class="input-group-addon bg-gray">/ {{ $remaining }}</span>
                                </div>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">{{ __('requestlabels::requests.stock_available') }}: <strong>{{ $currentStock }}</strong></p>
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
                    <h3 class="box-title">{{ __('requestlabels::requests.fulfillment_action') }}</h3>
                </div>
                <div class="box-body">

                    <ul class="list-group list-group-unbordered" id="fulfillmentChecklist" style="margin-bottom: 20px;">
                        <!-- JS Dynamically injects checklist state here -->
                    </ul>

                    <div class="form-group">
                        <label for="handover-notes" class="text-muted">{{ __('requestlabels::requests.handover_notes') }}</label>
                        <textarea id="handover-notes" form="workspaceForm" name="notes" class="form-control" rows="3" maxlength="2000" placeholder="{{ __('requestlabels::requests.handover_placeholder') }}"></textarea>
                    </div>

                    <button form="workspaceForm" type="submit" id="completeIssueBtn" class="btn btn-primary btn-lg btn-block" disabled data-request-confirm="{{ __('requestlabels::requests.fulfillment_show_confirm_handover') }}">
                        <i class="fas fa-clipboard-check"></i> {{ __('requestlabels::requests.complete_issue') }}
                    </button>
                </div>
            </div>



            <!-- FORCE CLOSURE OPTION (Separate, distinct form) -->
            <div class="box box-solid" style="margin-top: 20px;">
                <div class="box-header with-border">
                    <h3 class="box-title" style="color: #dd4b39;"><i class="fas fa-ban"></i> {{ __('requestlabels::requests.fulfillment_show_header_terminate') }}</h3>
                </div>
                <div class="box-body">
                    <button type="button" class="btn btn-danger btn-block" data-toggle="collapse" data-target="#forceClosePanel">
                        <i class="fas fa-exclamation-triangle"></i> {{ __('requestlabels::requests.fulfillment_show_btn_force_close') }}
                    </button>
                    <div id="forceClosePanel" class="collapse" style="margin-top: 10px; padding: 15px; background: #fdf2f2; border: 1px solid #ebccd1; border-radius: 4px;">
                        <form action="{{ route('gov.requests.fulfillment.close', $serviceRequest->id) }}" method="POST" id="closeForm" style="margin: 0;">
                            @csrf
                            <input type="text" name="reason" class="form-control input-sm" placeholder="{{ __('requestlabels::requests.fulfillment_show_input_reason_placeholder') }}" required minlength="5" maxlength="2000" style="margin-bottom: 10px; border: 1px solid #dd4b39;">
                            <button type="submit" class="btn btn-danger btn-sm btn-block" data-request-confirm="{{ __('requestlabels::requests.fulfillment_show_confirm_force_close') }}">{{ __('requestlabels::requests.confirm_close') }}</button>
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

@endsection
