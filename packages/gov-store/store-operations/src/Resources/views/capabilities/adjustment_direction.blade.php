@php
    $index = $config['row_index'] ?? 'NEW';
    $existing = $item ? $item->metadata()->where('field_key', 'adjustment_direction')->first()?->value : 'IN';
@endphp

<div class="col-md-6 form-group" style="margin-top:15px; padding: 0 10px;">
    <label style="color:#475569; font-weight:bold;"><i class="fa fa-sliders"></i> Adjustment Direction</label>
    <select name="items[{{ $index }}][meta][0][adjustment_direction]" class="form-control input-sm" style="border: 1px solid #cbd5e1; height: 36px;">
        <option value="IN" {{ $existing === 'IN' ? 'selected' : '' }}>Physical Count Found (+ IN)</option>
        <option value="OUT" {{ $existing === 'OUT' ? 'selected' : '' }}>Damaged / Expired / Lost (- OUT)</option>
    </select>
</div>