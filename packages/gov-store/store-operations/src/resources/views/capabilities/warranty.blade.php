@php
    $lineIndex = (int) ($config['row_index'] ?? 0);
    $quantity = (int) ($config['quantity'] ?? $item?->quantity ?? 1);
    $savedWarranty = $item?->metadata()->where('field_key', 'warranty_months')->pluck('value', 'row_index') ?? collect();
@endphp
<fieldset>
    <legend>{{ __('storeops::storeops.warranty_months') }}</legend>
    @for($unitIndex = 0; $unitIndex < $quantity; $unitIndex++)
        <div class="form-group">
            <label for="warranty-{{ $lineIndex }}-{{ $unitIndex }}">{{ __('storeops::storeops.unit_number', ['number' => $unitIndex + 1]) }}</label>
            <input id="warranty-{{ $lineIndex }}-{{ $unitIndex }}" name="items[{{ $lineIndex }}][meta][{{ $unitIndex }}][warranty_months]" type="number" min="0" max="1200" required class="form-control" value="{{ $savedWarranty->get($unitIndex) }}">
        </div>
    @endfor
</fieldset>
