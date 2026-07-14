@extends('layouts/default')

@section('title', 'Receive Goods')

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Create Goods Receipt</h3>
            </div>
            
            <form action="{{ route('storeops.receipts.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <!-- Header Info -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-4">
                            <label>Purchase Type</label>
                            <select name="purchase_type" class="form-control" required>
                                <option value="CASH">Cash Purchase / Direct</option>
                                <option value="TENDER">Tender / RFQ</option>
                                <option value="TRANSFER">Office Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Reference No (Invoice/Memo)</label>
                            <input type="text" name="reference_no" class="form-control" required placeholder="e.g., INV-001">
                        </div>
                    </div>

                    <!-- The Fast Grid -->
                    <h4>Received Items</h4>
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td>
                                    <select name="items[0][id]" class="form-control select2" required>
                                        <option value="">-- Select Item --</option>
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
                        <i class="fa fa-plus"></i> Add Item
                    </button>
                </div>
                
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-primary">Submit & Update Stock</button>
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
