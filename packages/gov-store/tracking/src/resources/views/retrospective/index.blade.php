@extends('layouts/default')
@section('title', 'Assign Legacy Assets: ' . $initiative->title)

@section('content')
<div class="row">
    <!-- Filter Bar Card -->
    <div class="col-md-12">
        <div class="callout callout-warning">
            <h4><i class="fa fa-history"></i> Retrospective Tagging Console</h4>
            <p>Search for historical assets already present in Snipe-IT inventory and link them to an active Tracking Code under this Initiative.</p>
        </div>
        
        <form method="GET" action="{{ route('gov.tracking.initiatives.retrospective.index', $initiative->id) }}">
            <input type="hidden" name="search_trigger" value="1">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-filter"></i> Search Legacy Inventory Parameters</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control select2">
                                <option value="">-- All Categories --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Manufacturer</label>
                            <select name="manufacturer_id" class="form-control select2">
                                <option value="">-- All Manufacturers --</option>
                                @foreach($manufacturers as $manufacturer)
                                    <option value="{{ $manufacturer->id }}" {{ request('manufacturer_id') == $manufacturer->id ? 'selected' : '' }}>{{ $manufacturer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Supplier</label>
                            <select name="supplier_id" class="form-control select2">
                                <option value="">-- All Suppliers --</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Purchase Date Range</label>
                            <div class="input-group">
                                <input type="date" name="purchase_start" class="form-control" value="{{ request('purchase_start') }}">
                                <span class="input-group-addon">to</span>
                                <input type="date" name="purchase_end" class="form-control" value="{{ request('purchase_end') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default">Back to Workspace</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Find Assets</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if($searched)
<div class="row">
    <div class="col-md-12">
        <form action="{{ route('gov.tracking.initiatives.retrospective.associate', $initiative->id) }}" method="POST">
            @csrf
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">Query Results (Showing up to 500 items)</h3>
                    
                    @if($assets->count() > 0)
                        <div class="pull-right form-inline">
                            <select name="tracking_code_id" class="form-control input-sm" required style="width: 250px;">
                                <option value="">-- Select Target Tracking Code --</option>
                                @foreach($trackingCodes as $code)
                                    <option value="{{ $code->id }}">{{ $code->tracking_code }} ({{ $code->task_title }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-success btn-sm" id="bulk-submit" disabled>
                                <i class="fa fa-link"></i> Link Selected Assets
                            </button>
                        </div>
                    @endif
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="30" class="text-center"><input type="checkbox" id="select-all-trigger"></th>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Manufacturer</th>
                                <th>Purchase Date</th>
                                <th>Tag Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assets as $asset)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" class="asset-checkbox" {{ $asset->is_already_tagged ? 'disabled' : '' }} onchange="evaluateSubmitButton()">
                                    </td>
                                    <td><code>{{ $asset->asset_tag }}</code></td>
                                    <td>{{ $asset->name }}</td>
                                    <td>{{ $asset->model->category->name ?? 'N/A' }}</td>
                                    <td>{{ $asset->model->manufacturer->name ?? 'N/A' }}</td>
                                    <td>{{ $asset->purchase_date }}</td>
                                    <td>
                                        @if($asset->is_already_tagged)
                                            <span class="label label-warning"><i class="fa fa-check"></i> Already Tagged</span>
                                        @else
                                            <span class="text-muted">Available</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">No assets found matching specified parameters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const selectAll = document.getElementById('select-all-trigger');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checked = this.checked;
            document.querySelectorAll('.asset-checkbox').forEach(box => {
                if (!box.disabled) box.checked = checked;
            });
            evaluateSubmitButton();
        });
    }

    function evaluateSubmitButton() {
        const checkedBoxes = document.querySelectorAll('.asset-checkbox:checked').length;
        document.getElementById('bulk-submit').disabled = (checkedBoxes === 0);
    }
</script>
@endif
@stop