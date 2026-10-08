@php
    $index = $config['row_index'] ?? 'NEW';
    $qty = $config['quantity'] ?? ($item ? $item->quantity : 1);
    $metadata = $item ? $item->metadata->groupBy('row_index') : collect();
@endphp

<div  class="storeops-inline-148">
    <h4  class="storeops-inline-147">
        <i class="fa fa-barcode"></i> {{ __('storeops::storeops.serial_required') }}
    </h4>
    <p class="text-muted storeops-inline-146" >
        {{ __('storeops::storeops.serial_help') }}
    </p>

    <div  class="storeops-inline-145">
        @for($i = 0; $i < $qty; $i++)
            @php
                $existingSerial = $metadata->has($i) ? $metadata->get($i)->where('field_key', 'serial_number')->first() : null;
                $val = $existingSerial ? $existingSerial->value : '';
            @endphp
            <div  class="storeops-inline-144">
                <div  class="storeops-inline-143">
                    {{ __('storeops::storeops.unit_number', ['number' => $i + 1]) }}
                </div>
                <div  class="storeops-inline-142">
                    <input type="text"
                           name="items[{{ $index }}][meta][{{ $i }}][serial_number]"
                           class="form-control storeops-inline-141"
                           aria-label="{{ __('storeops::storeops.unit_number', ['number' => $i + 1]) }} — {{ __('storeops::storeops.serial_required') }}"
                           placeholder="{{ __('storeops::storeops.serial_placeholder') }}"
                           value="{{ $val }}"
                           required
                           >
                    <i class="fa fa-barcode storeops-inline-140" ></i>
                </div>
            </div>
        @endfor
    </div>
</div>
