<div class="col-md-6 form-group">
    <label><i class="fa fa-map-marker"></i> Destination Office / Store</label>
    @php
        $existing = $item ? $item->metadata()->where('field_key', 'destination_location_id')->first()?->value : '';
    @endphp
    <select name="items[{{ $item->id ?? 'NEW' }}][meta][0][destination_location_id]" class="form-control input-sm" required>
        <option value="">-- Select Target Office --</option>
        @foreach($locations as $loc)
            <option value="{{ $loc->id }}" {{ (string)$existing === (string)$loc->id ? 'selected' : '' }}>
                {{ $loc->name }}
            </option>
        @endforeach
    </select>
</div>