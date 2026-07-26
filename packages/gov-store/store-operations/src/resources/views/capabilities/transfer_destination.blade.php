@php
    $index = $config['row_index'] ?? 'NEW';
    $existing = $item ? $item->metadata()->where('field_key', 'destination_location_id')->first()?->value : '';
@endphp

<div class="col-md-6 form-group" style="margin-top:15px; padding: 0 10px;">
    <label style="color:#475569; font-weight:bold;"><i class="fa fa-map-marker"></i> Destination Location</label>
    <select name="items[{{ $index }}][meta][0][destination_location_id]" class="form-control input-sm" required style="border: 1px solid #cbd5e1; height: 36px;">
        <option value="">-- Select Target Office --</option>
        @foreach(\App\Models\Location::orderBy('name')->get() as $loc)
            <option value="{{ $loc->id }}" {{ (string)$existing === (string)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
        @endforeach
    </select>
</div>