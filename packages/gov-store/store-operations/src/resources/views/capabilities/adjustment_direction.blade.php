<div class="col-md-6 form-group">
    <label><i class="fa fa-sliders"></i> Adjustment Direction</label>
    @php
        $existing = $item ? $item->metadata()->where('field_key', 'adjustment_direction')->first()?->value : 'IN';
    @endphp
    <select name="items[{{ $item->id ?? 'NEW' }}][meta][0][adjustment_direction]" class="form-control input-sm">
        <option value="IN" {{ $existing === 'IN' ? 'selected' : '' }}>Physical Count Found (+ IN)</option>
        <option value="OUT" {{ $existing === 'OUT' ? 'selected' : '' }}>Damaged / Expired / Lost (- OUT)</option>
    </select>
</div>