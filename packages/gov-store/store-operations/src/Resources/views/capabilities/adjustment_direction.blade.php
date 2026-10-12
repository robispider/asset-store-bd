@php
    $index = $config['row_index'] ?? 'NEW';
    $existing = $item ? $item->metadata()->where('field_key', 'adjustment_direction')->first()?->value : 'IN';
@endphp

<div class="col-md-6 form-group storeops-inline-139" >
    <label  class="storeops-inline-138"><i class="fa fa-sliders"></i> Adjustment Direction</label>
    <select name="items[{{ $index }}][meta][0][adjustment_direction]" class="form-control input-sm storeops-inline-137" >
        <option value="IN" {{ $existing === 'IN' ? 'selected' : '' }}>Physical Count Found (+ IN)</option>
        <option value="OUT" {{ $existing === 'OUT' ? 'selected' : '' }}>Damaged / Expired / Lost (- OUT)</option>
    </select>
</div>