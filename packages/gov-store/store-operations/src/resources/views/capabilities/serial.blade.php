<div class="col-md-12 capability-block" style="margin-bottom: 10px;">
    <h5 class="text-blue"><i class="fa fa-barcode"></i> Serial Numbers Required</h5>
    <table class="table table-condensed table-bordered bg-white">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Serial Number</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Get the existing metadata values from the EAV table if they exist
                $metadata = $item ? $item->metadata->groupBy('row_index') : collect();
                $qty = $item ? $item->quantity : 1;
            @endphp

            @for($i = 0; $i < $qty; $i++)
                @php
                    // Retrieve existing serial number for this specific row
                    $existingSerial = $metadata->has($i) ? $metadata->get($i)->where('field_key', 'serial_number')->first() : null;
                @endphp
                <tr>
                    <td class="text-center" style="vertical-align: middle;">{{ $i + 1 }}</td>
                    <td>
                        <input type="text" 
                               name="items[{{ $item->id ?? 'NEW' }}][meta][{{ $i }}][serial_number]" 
                               class="form-control input-sm" 
                               placeholder="Enter Serial Number..."
                               value="{{ $existingSerial ? $existingSerial->value : '' }}"
                               required>
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>