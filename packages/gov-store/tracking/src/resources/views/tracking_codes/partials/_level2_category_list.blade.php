@php
    $activeTargets = isset($trackingCode) && $trackingCode->specificity_level === '2_CATEGORY'
        ? $trackingCode->targets
        : [null]; // Spawns single blank row on create
    $hideLevel2 = isset($trackingCode) && $trackingCode->specificity_level !== '2_CATEGORY';
@endphp
<x-gs::box id="panel-level2" :title="__('govtracking::general.task.targets_title')" :style="$hideLevel2 ? 'display: none;' : null">
    <p class="gs-tracking-muted">{{ __('govtracking::general.task.target_help') }}</p>

    <x-gs::table id="targets-table">
        <thead>
            <tr>
                <th scope="col">{{ __('govtracking::general.task.category_required') }}</th>
                <th scope="col">{{ __('govtracking::general.task.planned_required') }}</th>
                <th scope="col">{{ __('govtracking::general.task.economic_optional') }}</th>
                <th scope="col">{{ __('govtracking::general.ui.action') }}</th>
            </tr>
        </thead>
        <tbody id="targets-body">
            @foreach($activeTargets as $index => $targetItem)
                <tr>
                    <td>
                        <select name="targets[{{ $index }}][category_id]" class="form-control target-category-select" required aria-label="{{ __('govtracking::general.task.category_required') }}">
                            <option value="">{{ __('govtracking::general.task.select_category') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ ($targetItem && $targetItem->category_id == $category->id) ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" name="targets[{{ $index }}][planned_qty]" class="form-control target-qty-input" value="{{ $targetItem ? $targetItem->planned_qty : '' }}" min="1" placeholder="{{ __('govtracking::general.ui.extra_e_g_150') }}" required aria-label="{{ __('govtracking::general.task.planned_required') }}">
                    </td>
                    <td>
                        <input type="text" name="targets[{{ $index }}][economic_code]" class="form-control" value="{{ $targetItem ? $targetItem->economic_code : '' }}" placeholder="{{ __('govtracking::general.ui.extra_e_g_4112202') }}" aria-label="{{ __('govtracking::general.task.economic_optional') }}">
                    </td>
                    <td>
                        <button type="button" class="gs-btn gs-btn--danger gs-btn--outline gs-btn--sm remove-row" aria-label="{{ __('govtracking::general.task.remove') }}" {{ $index === 0 ? 'disabled' : '' }}><i class="fas fa-trash" aria-hidden="true"></i></button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-gs::table>
    <x-gs::button tone="secondary" outline size="sm" icon="fa-plus" id="add-target-row">{{ __('govtracking::general.task.add_category') }}</x-gs::button>
</x-gs::box>
