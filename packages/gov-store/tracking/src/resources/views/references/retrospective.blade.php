@extends('layouts/default')

@section('title', 'Retrospective Tagging Console: ' . $reference->reference_code)

@section('content')
<div class="row">
    <!-- Filter Bar Card -->
    <div class="col-md-12">
        <form method="GET" action="{{ route('gov.tracking.references.retrospective.index', $reference->id) }}">
            <input type="hidden" name="search_trigger" value="1">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-filter"></i> Search Legacy Inventory Parameters</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">-- All Categories --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Manufacturer</label>
                            <select name="manufacturer_id" class="form-control">
                                <option value="">-- All Manufacturers --</option>
                                @foreach($manufacturers as $manufacturer)
                                    <option value="{{ $manufacturer->id }}" {{ request('manufacturer_id') == $manufacturer->id ? 'selected' : '' }}>{{ $manufacturer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Supplier</label>
                            <select name="supplier_id" class="form-control">
                                <option value="">-- All Suppliers --</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
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
                    <a href="{{ route('gov.tracking.references.show', $reference->id) }}" class="btn btn-default">Back to Profile</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Query Assets</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if($searched)
<div class="row">
    <div class="col-md-12">
        <form action="{{ route('gov.tracking.references.retrospective.associate', $reference->id) }}" method="POST">
            @csrf
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Query Results (Showing up to 500 items)</h3>
                    @if($assets->count() > 0)
                        <button type="submit" class="btn btn-success pull-right btn-sm" id="bulk-submit" disabled>
                            <i class="fa fa-tag"></i> Link Selected Assets
                        </button>
                    @endif
                </div>
                <div class="box-body">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="30" class="text-center">
                                    <input type="checkbox" id="select-all-trigger">
                                </th>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Manufacturer</th>
                                <th>Supplier</th>
                                <th>Purchase Date</th>
                                <th>Existing Link Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assets as $asset)
                                <tr>
                                    <td class="text-center">
                                        <!-- Prevent linking already assigned items to this same reference -->
                                        @php
                                            $isLinkedToSelf = $asset->existing_associations->contains('tracking_reference_id', $reference->id);
                                        @endphp
                                        <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" class="asset-checkbox" {{ $isLinkedToSelf ? 'disabled' : '' }} onchange="evaluateSubmitButton()">
                                    </td>
                                    <td><code>{{ $asset->asset_tag }}</code></td>
                                    <td>{{ $asset->name }}</td>
                                    <td>{{ $asset->model->category->name }}</td>
                                    <td>{{ $asset->model->manufacturer->name }}</td>
                                    <td>{{ $asset->supplier->name ?? 'N/A' }}</td>
                                    <td>{{ $asset->purchase_date }}</td>
                                    <td>
                                        @if($isLinkedToSelf)
                                            <span class="label label-success">Linked to this reference</span>
                                        @elseif($asset->existing_associations->count() > 0)
                                            @foreach($asset->existing_associations as $assoc)
                                                <span class="label label-warning">Linked to Reference #{{ $assoc->tracking_reference_id }}</span>
                                            @endforeach
                                        @else
                                            <span class="text-muted">No associations</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No assets found matching specified parameters.</td>
                                </tr>
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
                if (!box.disabled) {
                    box.checked = checked;
                }
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