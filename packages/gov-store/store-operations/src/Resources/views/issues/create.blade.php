@extends('layouts/default')

@section('title', 'Issue Goods')

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-sign-out"></i> Create Goods Issue (Outbound)</h3>
            </div>
            
            <form action="{{ route('storeops.issues.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <!-- Header Info -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-4">
                            <label>Issue Type</label>
                            <select name="issue_type" id="issueType" class="form-control" required>
                                <option value="TO_USER">Issue to Employee</option>
                                <option value="TO_DEPARTMENT">Issue to Department / General Use</option>
                            </select>
                        </div>
                        <div class="col-md-5" id="userSelectDiv">
                            <label>Issued To</label>
                            <select name="issued_to_id" class="form-control select2">
                                <option value="">-- Select Employee --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->present()->fullName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- The Fast Grid -->
                    <h4>Items to Issue</h4>
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr style="background-color: #f9f9f9;">
                                <th>Item Name</th>
                                <th style="width: 150px;">Available Stock</th>
                                <th style="width: 150px;">Issue Qty</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td>
                                    <select name="items[0][id]" class="form-control item-select select2" required>
                                        <option value="">-- Select Item --</option>
                                        @foreach($stockables as $item)
                                            <option value="{{ $item->id }}" data-max="{{ $item->qty }}">{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control max-qty-display" disabled value="0">
                                </td>
                                <td>
                                    <input type="number" name="items[0][qty]" class="form-control issue-qty" required min="1">
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-primary" id="addRowBtn">
                        <i class="fa fa-plus"></i> Add Item
                    </button>
                </div>
                
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-warning">Issue Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('moar_scripts')
<script>
    // Handle UI toggle for Issue Type
    document.getElementById('issueType').addEventListener('change', function(e) {
        let userSelect = document.getElementById('userSelectDiv');
        if(e.target.value === 'TO_USER') {
            userSelect.style.display = 'block';
            userSelect.querySelector('select').required = true;
        } else {
            userSelect.style.display = 'none';
            userSelect.querySelector('select').required = false;
            userSelect.querySelector('select').value = '';
        }
    });

    // Dynamic UI for Available Stock display
    document.getElementById('itemsTable').addEventListener('change', function(e) {
        if(e.target.classList.contains('item-select')) {
            let selectedOption = e.target.options[e.target.selectedIndex];
            let maxStock = selectedOption.getAttribute('data-max') || 0;
            
            let row = e.target.closest('tr');
            row.querySelector('.max-qty-display').value = maxStock;
            
            let qtyInput = row.querySelector('.issue-qty');
            qtyInput.setAttribute('max', maxStock);
        }
    });

    // Add Row logic
    let rowIndex = 1;
    document.getElementById('addRowBtn').addEventListener('click', function() {
        let tbody = document.getElementById('itemsBody');
        let template = `
            <tr>
                <td>
                    <select name="items[${rowIndex}][id]" class="form-control item-select" required>
                        <option value="">-- Select Item --</option>
                        @foreach($stockables as $item)
                            <option value="{{ $item->id }}" data-max="{{ $item->qty }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control max-qty-display" disabled value="0">
                </td>
                <td>
                    <input type="number" name="items[${rowIndex}][qty]" class="form-control issue-qty" required min="1">
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
