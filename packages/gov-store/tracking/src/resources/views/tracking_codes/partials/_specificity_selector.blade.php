@php
    $activeSpec = old('specificity_level', $trackingCode?->specificity_level ?? '2_CATEGORY');
@endphp

<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-blue">4. How will deliveries be managed?</h3></div>
    <div class="box-body">
        <label>Select Delivery Strategy</label>
        
        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="1_BLANKET" {{ $activeSpec === '1_BLANKET' ? 'checked' : '' }} onchange="toggleSpecificityPanels()">
                <strong>Open Allocation</strong> (No item or quantity restrictions. Expense tracking only).
            </label>
        </div>
        
        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="2_CATEGORY" {{ $activeSpec === '2_CATEGORY' ? 'checked' : '' }} onchange="toggleSpecificityPanels()">
                <strong>Category Targets</strong> (Define item quantities, distributed anywhere in permitted coverage area).
            </label>
        </div>
        
        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="3_MATRIX" {{ $activeSpec === '3_MATRIX' ? 'checked' : '' }} onchange="toggleSpecificityPanels()">
                <strong>Office Delivery Schedule</strong> (Define exact item quantities for specific individual offices).
            </label>
        </div>
    </div>
</div>