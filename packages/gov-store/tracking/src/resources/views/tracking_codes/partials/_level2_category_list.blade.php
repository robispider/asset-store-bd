<div id="panel-level2" class="box box-solid" style="{{ (isset($trackingCode) && $trackingCode->specificity_level !== '2_CATEGORY') ? 'display: none;' : '' }}">
    <div class="box-header with-border"><h3 class="box-title text-green">{{ __('govtracking::general.task.targets_title') }}</h3></div>
    <div class="box-body">
        <p class="text-muted">{{ __('govtracking::general.task.target_help') }}</p>

        <table class="table table-bordered table-striped" id="targets-table">
            <thead>
                <tr>
                    <th>{{ __('govtracking::general.task.category_required') }}</th>
                    <th>{{ __('govtracking::general.task.planned_required') }}</th>
                    <th>{{ __('govtracking::general.task.economic_optional') }}</th>
                    <th>{{ __('govtracking::general.ui.action') }}</th>
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
                                <option value="">{{ __('govtracking::general.task.select_category') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ ($targetItem && $targetItem->category_id == $category->id) ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="targets[{{ $index }}][planned_qty]" class="form-control target-qty-input" value="{{ $targetItem ? $targetItem->planned_qty : '' }}" min="1" placeholder="{{ __('govtracking::general.ui.extra_e_g_150') }}" required>
                        </td>
                        <td>
                            <input type="text" name="targets[{{ $index }}][economic_code]" class="form-control" value="{{ $targetItem ? $targetItem->economic_code : '' }}" placeholder="{{ __('govtracking::general.ui.extra_e_g_4112202') }}">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-row" {{ $index === 0 ? 'disabled' : '' }}><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <button type="button" class="btn btn-default btn-sm" id="add-target-row"><i class="fa fa-plus"></i> {{ __('govtracking::general.task.add_category') }}</button>
    </div>
</div>
