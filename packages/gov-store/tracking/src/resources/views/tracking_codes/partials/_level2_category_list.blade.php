<div id="panel-level2" class="box box-solid" style="{{ (isset($trackingCode) && $trackingCode->specificity_level !== '2_CATEGORY') ? 'display: none;' : '' }}">
    <div class="box-header with-border"><h3 class="box-title text-green">3. Quantitative Targets (Line Items)</h3></div>
    <div class="box-body">
        <p class="text-muted">Define the specific items authorized by this code, and their iBAS++ Economic Classification.</p>
        
        <table class="table table-bordered table-striped" id="targets-table">
            <thead>
                <tr>
                    <th>Asset Category (Mandatory)</th>
                    <th>Planned Quantity (Mandatory)</th>
                    <th>Economic Code (Optional)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="targets-body">
                @php
                    $activeTargets = isset($trackingCode) && $trackingCode->specificity_level === '2_CATEGORY' 
                        ? $trackingCode->targets 
                        : [null]; // Spawns single blank row on create
                @endphp

                @foreach($activeTargets as $index => $targetItem)
                    <tr>
                        <td>
                            <select name="targets[{{ $index }}][category_id]" class="form-control target-category-select" required>
                                <option value="">-- Select Category --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ ($targetItem && $targetItem->category_id == $category->id) ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="targets[{{ $index }}][planned_qty]" class="form-control target-qty-input" value="{{ $targetItem ? $targetItem->planned_qty : '' }}" min="1" placeholder="e.g. 150" required>
                        </td>
                        <td>
                            <input type="text" name="targets[{{ $index }}][economic_code]" class="form-control" value="{{ $targetItem ? $targetItem->economic_code : '' }}" placeholder="e.g. 4112202">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-row" {{ $index === 0 ? 'disabled' : '' }}><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <button type="button" class="btn btn-default btn-sm" id="add-target-row"><i class="fa fa-plus"></i> Add Another Category</button>
    </div>
</div>