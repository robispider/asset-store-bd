@extends('layouts/default')

@section('title', 'My Request Basket')

@section('content')
<div class="row">
    <!-- LEFT COLUMN: Line Items -->
    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-shopping-basket"></i> Draft Basket Items</h3>
            </div>
            <div class="box-body table-responsive">
                @if($basket->items->isEmpty())
                    <div class="text-center" style="padding: 40px;">
                        <i class="fas fa-cart-arrow-down fa-3x text-muted"></i>
                        <h4 class="text-muted" style="margin-top: 15px;">Your Service Request basket is empty.</h4>
                        <a href="{{ route('gov.requests.catalog') }}" class="btn btn-primary" style="margin-top: 10px;">
                            <i class="fas fa-store"></i> Browse Catalog
                        </a>
                    </div>
                @else
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th style="width: 120px;">Requested Qty</th>
                                <th style="width: 60px;">Action</th>
                            </tr>
                        </thead>
                       <tbody>
                            @foreach($basket->items as $item)
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
                                    <td style="vertical-align: middle;">
                                        @if($item->requested_type === 'asset')
                                            <input type="text" class="form-control input-sm text-center" value="1" disabled title="Hardware assets are restricted to 1 per line item.">
                                        @else
                                            <form action="{{ route('gov.requests.basket.update') }}" method="POST" style="display: flex; gap: 5px;">
                                                @csrf
                                                <input type="hidden" name="item_id" value="{{ $item->id }}">
                                                <input type="number" name="qty" class="form-control input-sm text-center" value="{{ $item->requested_qty }}" min="1" style="width: 60px;">
                                                <button type="submit" class="btn btn-default btn-sm" title="Update"><i class="fas fa-sync-alt"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <form action="{{ route('gov.requests.basket.remove', $item->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-danger btn-sm" title="Remove"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
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
                <h3 class="box-title"><i class="fas fa-file-signature"></i> Service Request Details</h3>
            </div>
            <form action="{{ route('gov.requests.basket.submit') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="request_type">Request Type <span class="text-danger">*</span></label>
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
                        <label for="purpose">Brief Title / Purpose <span class="text-danger">*</span></label>
                        <input type="text" name="purpose" id="purpose" class="form-control" placeholder="e.g. Q3 Hardware Refresh for IT Team" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="justification">Detailed Justification <span class="text-danger">*</span></label>
                        <textarea name="justification" id="justification" class="form-control" rows="4" placeholder="Provide organizational justification for auditing purposes..." required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="required_by_date">Required By Date</label>
                                <input type="date" name="required_by_date" id="required_by_date" class="form-control" min="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cost_center">Cost Center</label>
                                <input type="text" name="cost_center" id="cost_center" class="form-control" placeholder="e.g. CC-8821">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="delivery_location_id">Delivery Location</label>
                        <select name="delivery_location_id" id="delivery_location_id" class="form-control">
                            <option value="">-- Select Office Location --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-lg btn-block" {{ $basket->items->isEmpty() ? 'disabled' : '' }}>
                        <i class="fas fa-paper-plane"></i> Submit Official Service Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection