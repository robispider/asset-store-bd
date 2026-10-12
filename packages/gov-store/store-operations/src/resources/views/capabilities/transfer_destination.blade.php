@php
    $index = $config['row_index'] ?? 'NEW';
    $existing = $item ? $item->metadata()->where('field_key', 'destination_location_id')->first()?->value : '';
@endphp

<div class="col-md-6 form-group storeops-inline-151" >
    <label  class="storeops-inline-150"><i class="fa fa-map-marker"></i> Destination Location</label>
    <select name="items[{{ $index }}][meta][0][destination_location_id]" class="form-control input-sm storeops-inline-149" required >
        <option value="">-- Select Target Office --</option>
        @foreach(\App\Models\Location::orderBy('name')->get() as $loc)
            <option value="{{ $loc->id }}" {{ (string)$existing === (string)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
        @endforeach
    </select>
</div>