@extends('layouts/default')

@section('title', __('requestlabels::requests.basket_index_title'))

@section('content')
<div class="row">
    <!-- LEFT COLUMN: Line Items -->
    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-shopping-basket"></i> {{ __('requestlabels::requests.basket_index_header_draft_items') }}</h3>
            </div>
            <div class="box-body table-responsive">
                @if($basket->items->isEmpty())
                    <div class="text-center" style="padding: 40px;">
                        <i class="fas fa-cart-arrow-down fa-3x text-muted"></i>
                        <h4 class="text-muted" style="margin-top: 15px;">{{ __('requestlabels::requests.basket_index_empty_basket') }}</h4>
                        <a href="{{ route('gov.requests.catalog') }}" class="btn btn-primary" style="margin-top: 10px;">
                            <i class="fas fa-store"></i> {{ __('requestlabels::requests.basket_index_btn_browse_catalog') }}
                        </a>
                    </div>
                @else
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th style="width: 120px; text-align: center;">Requested Qty</th>
                                <th style="width: 60px;">Action</th>
                            </tr>
                        </thead>
                       <tbody>
                            @foreach($basket->items as $item) {{-- FIXED: Changed @forelse to @foreach --}}
                                @php
                                    try {
                                        // Leverage our Phase 2 factory to safely fetch the correct display name
                                        $adapter = \GovStore\CustomRequests\Factories\RequestableFactory::make($item->requested_type, $item->requested_id);
                                        $name = $adapter->getDisplayName();
                                    } catch (\Exception $e) {
                                        $name = 'Unknown Item';
                                    }
                                @endphp
                                <tr>
                                    <td style="vertical-align: middle;"><strong>{{ $name }}</strong></td>
                                    <td style="vertical-align: middle;">
                                        <span class="label label-info">{{ ucfirst($item->requested_type) }}</span>
                                    </td>
                                    <td style="vertical-align: middle; text-align: center;">
                                        @if($item->requested_type === 'asset')
                                            <input type="text" class="form-control input-sm text-center" value="1" disabled title="{{ __('requestlabels::requests.basket_index_tooltip_asset_restricted') }}" style="width: 70px; margin: 0 auto;">
                                        @else
                                            <!-- Modern Auto-saving Input Field (No nested forms, fully responsive) -->
                                            <div class="qty-wrapper" style="display: inline-flex; align-items: center; gap: 8px;">
                                                <input type="number" 
                                                       class="form-control input-sm text-center basket-qty-input" 
                                                       data-item-id="{{ $item->id }}" 
                                                       value="{{ $item->requested_qty }}" 
                                                       min="1" 
                                                       style="width: 70px; margin: 0 auto; border: 1px solid #ccc; border-radius: 4px;">
                                                <span class="save-status-indicator" data-item-id="{{ $item->id }}" style="font-size: 11px; color: #555; width: 45px; text-align: left;"></span>
                                            </div>
                                        @endif
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <form action="{{ route('gov.requests.basket.remove', $item->id) }}" method="POST" style="margin: 0;">
                                            @csrf
                                            <button type="submit" class="btn btn-danger btn-sm" title="{{ __('requestlabels::requests.basket_index_btn_remove_title') }}"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach {{-- FIXED: Changed @endforelse to @endforeach --}}
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Submission Metadata -->
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-file-signature"></i> {{ __('requestlabels::requests.basket_index_header_service_request_details') }}</h3>
            </div>
            <form action="{{ route('gov.requests.basket.submit') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="request_type">{{ __('requestlabels::requests.basket_index_label_request_type') }} <span class="text-danger">*</span></label>
                        <select name="request_type" id="request_type" class="form-control" required>
                            <option value="new_employee">New Employee Setup</option>
                            <option value="replacement">Equipment Replacement</option>
                            <option value="project">Project Requirement</option>
                            <option value="office_setup">Office Relocation / Setup</option>
                            <option value="repair">Repair / Maintenance</option>
                            <option value="emergency">Emergency Request</option>
                            <option value="other" selected>Other / General Supply</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="purpose">{{ __('requestlabels::requests.basket_index_label_purpose') }} <span class="text-danger">*</span></label>
                        <input type="text" name="purpose" id="purpose" class="form-control" placeholder="{{ __('requestlabels::requests.basket_index_placeholder_purpose') }}" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="justification">{{ __('requestlabels::requests.basket_index_label_justification') }} <span class="text-danger">*</span></label>
                        <textarea name="justification" id="justification" class="form-control" rows="4" placeholder="{{ __('requestlabels::requests.basket_index_placeholder_justification') }}" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="required_by_date">{{ __('requestlabels::requests.basket_index_label_required_by_date') }}</label>
                                <input type="date" name="required_by_date" id="required_by_date" class="form-control" min="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                            <label for="cost_center">{{ __('requestlabels::requests.basket_index_label_cost_center') }}</label>
                            <input type="text" name="cost_center" id="cost_center" class="form-control" placeholder="{{ __('requestlabels::requests.basket_index_placeholder_cost_center') }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="delivery_location_id">{{ __('requestlabels::requests.basket_index_label_delivery_location') }}</label>
                        <select name="delivery_location_id" id="delivery_location_id" class="form-control">
                            <option value="">-- {{ __('requestlabels::requests.basket_index_select_no_location') }} --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-lg btn-block" {{ $basket->items->isEmpty() ? 'disabled' : '' }}>
                        <i class="fas fa-paper-plane"></i> {{ __('requestlabels::requests.basket_index_btn_submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic background auto-saver on quantity edit (600ms debounce)
    let autoSaveTimer = null;

    document.querySelectorAll('.basket-qty-input').forEach(input => {
        input.addEventListener('input', function() {
            let itemId = this.dataset.itemId;
            let qty = this.value;
            let statusIndicator = document.querySelector(`.save-status-indicator[data-item-id="${itemId}"]`);

            if (qty < 1) return;

            statusIndicator.innerHTML = '<i class="fas fa-spinner fa-spin text-muted"></i>';

            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                fetch('{{ route("gov.requests.basket.update") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ item_id: itemId, qty: qty })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        statusIndicator.innerHTML = '<span class="text-success"><i class="fas fa-check"></i></span>';
                        setTimeout(() => { statusIndicator.innerHTML = ''; }, 1500);
                    } else {
                        statusIndicator.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i></span>';
                    }
                })
                .catch(err => {
                    statusIndicator.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i></span>';
                });
            }, 600);
        });
    });
});
</script>
@endsection