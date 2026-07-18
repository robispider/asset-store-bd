@extends('layouts/default')

@section('title', __('storeops::storeops.issue_goods_title'))

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-sign-out"></i> {{ __('storeops::storeops.create_goods_issue') }}</h3>
            </div>
            
            <form action="{{ route('storeops.issues.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <!-- Header Info -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-4">
                            <label>{{ __('storeops::storeops.issue_type_label') }}</label>
                            <select name="issue_type" id="issueType" class="form-control" required>
                                <option value="TO_USER">{{ __('storeops::storeops.issue_to_employee') }}</option>
                                <option value="TO_DEPARTMENT">{{ __('storeops::storeops.issue_to_department') }}</option>
                            </select>
                        </div>
                        <div class="col-md-5" id="userSelectDiv">
                            <label>{{ __('storeops::storeops.issued_to_label') }}</label>
                            <select name="issued_to_id" class="form-control select2">
                                <option value="">-- {{ __('storeops::storeops.select_employee') }} --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->present()->fullName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- The Fast Grid -->
                    <h4>{{ __('storeops::storeops.items_to_issue') }}</h4>
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr style="background-color: #f9f9f9;">
                                <th>{{ __('storeops::storeops.item_name') }}</th>
                                <th style="width: 150px;">{{ __('storeops::storeops.available_stock') }}</th>
                                <th style="width: 150px;">{{ __('storeops::storeops.issue_qty') }}</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td>
                                    <select name="items[0][id]" class="form-control item-select select2" required>
                                        <option value="">-- {{ __('storeops::storeops.select_item') }} --</option>
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
                        <i class="fa fa-plus"></i> {{ __('storeops::storeops.add_item') }}
                    </button>
                </div>
                
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-warning">{{ __('storeops::storeops.issue_stock_button') }}</button>
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
