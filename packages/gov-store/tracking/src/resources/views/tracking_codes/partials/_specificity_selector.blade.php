@php
    // Default to 2_CATEGORY on create flow, or read active status on edit flow
    $activeSpec = old('specificity_level', $trackingCode?->specificity_level ?? '2_CATEGORY');
@endphp

<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-blue">3. Specificity Level Selection</h3></div>
    <div class="box-body">
        <label>How specific is this execution task?</label>
        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="1_BLANKET" {{ $activeSpec === '1_BLANKET' ? 'checked' : '' }} onchange="toggleSpecificityPanels()">
                <strong>Level 1: Blanket Code</strong> (No item or quantity restrictions. Expense tracking only).
            </label>
        </div>
        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="2_CATEGORY" {{ $activeSpec === '2_CATEGORY' ? 'checked' : '' }} onchange="toggleSpecificityPanels()">
                <strong>Level 2: Category Constraints (Default)</strong> (Define item quantities, distributed anywhere in coverage area).
            </label>
        </div>
        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="3_MATRIX" {{ $activeSpec === '3_MATRIX' ? 'checked' : '' }} onchange="toggleSpecificityPanels()">
                <strong>Level 3: Exact Delivery Schedule Matrix</strong> (Define exact item quantities for specific individual offices).
            </label>
        </div>
    </div>
</div>