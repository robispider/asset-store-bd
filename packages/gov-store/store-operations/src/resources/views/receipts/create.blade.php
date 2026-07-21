@extends('layouts/default')

@section('title', __('storeops::storeops.receive_goods_title'))

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('storeops::storeops.create_goods_receipt') }}</h3>
            </div>
            
            <form action="{{ route('storeops.receipts.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <!-- Header Info -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-4">
                            <label>{{ __('storeops::storeops.purchase_type_label') }}</label>
                            <select name="purchase_type" class="form-control" required>
                                <option value="CASH">{{ __('storeops::storeops.cash_purchase') }}</option>
                                <option value="TENDER">{{ __('storeops::storeops.tender_rfq') }}</option>
                                <option value="TRANSFER">{{ __('storeops::storeops.office_transfer') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>{{ __('storeops::storeops.reference_no_label') }}</label>
                            <input type="text" name="reference_no" class="form-control" required placeholder="{{ __('storeops::storeops.reference_placeholder') }}">
                        </div>
                    </div>

                    <!-- The Fast Grid -->
                    <h4>{{ __('storeops::storeops.received_items') }}</h4>
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr>
                                <th>{{ __('storeops::storeops.item_name') }}</th>
                                <th>{{ __('storeops::storeops.quantity') }}</th>
                                <th>{{ __('storeops::storeops.action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td>
                                    <select name="items[0][id]" class="form-control select2" required>
                                        <option value="">-- {{ __('storeops::storeops.select_item') }} --</option>
                                        @foreach($stockables as $item)
                                            <option value="{{ $item->id }}">{{ $item->name }} (Current: {{ $item->qty }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][qty]" class="form-control" required min="1" placeholder="Qty">
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-success" id="addRowBtn">
                        <i class="fa fa-plus"></i> {{ __('storeops::storeops.add_item') }}
                    </button>
                </div>
                
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-primary">{{ __('storeops::storeops.submit_update_stock') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('moar_scripts')
<script>
    let rowIndex = 1;
    document.getElementById('addRowBtn').addEventListener('click', function() {
        let tbody = document.getElementById('itemsBody');
        let template = `
            <tr>
                <td>
                    <select name="items[${rowIndex}][id]" class="form-control" required>
                        <option value="">-- Select Item --</option>
                        @foreach($stockables as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${rowIndex}][qty]" class="form-control" required min="1">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-row"><i class="fa fa-trash"></i></button>
                </td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', template);
        rowIndex++;
    });

    document.getElementById('itemsTable').addEventListener('click', function(e) {
        if(e.target.closest('.remove-row')) {
            e.target.closest('tr').remove();
        }
    });
</script>
@endsection

@endsection
