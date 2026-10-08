<div class="form-group">
    <label for="transfer-item-{{ $rowIndex }}">{{ __('storeops::storeops.destination_item') }}</label>
    <select id="transfer-item-{{ $rowIndex }}" name="items[{{ $rowIndex }}][meta][0][destination_stockable_id]" class="form-control" required {{ $isDraft ? '' : 'disabled' }}>
        <option value="">{{ __('storeops::storeops.select_destination_item') }}</option>
        @foreach($targets as $target)
            <option value="{{ $target->id }}" @selected((int) $item?->metadata()->where('field_key', 'destination_stockable_id')->value('value') === (int) $target->id)>{{ $target->name }} — {{ $target->item_no ?: $target->id }}</option>
        @endforeach
    </select>
    @if($targets->isEmpty())<p class="help-block">{{ __('storeops::storeops.no_destination_item') }}</p>@endif
</div>
