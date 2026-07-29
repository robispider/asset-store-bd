@php
    $index = $config['row_index'] ?? 'NEW';
    $qty = $config['quantity'] ?? ($item ? $item->quantity : 1);
    $metadata = $item ? $item->metadata->groupBy('row_index') : collect();
@endphp

<div style="margin-top: 15px; margin-bottom: 20px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 20px;">
    <h4 style="margin-top: 0; color: #1e293b; font-weight: bold; font-size: 15px;">
        <i class="fa fa-barcode text-blue"></i> Physical Serial Numbers Required
    </h4>
    <p class="text-muted" style="font-size: 12.5px; margin-bottom: 15px;">
        Please scan or enter the unique physical serial number for each of the <strong>{{ $qty }}</strong> units you are receiving.
    </p>
    
    <div style="display: flex; flex-direction: column; gap: 10px;">
        @for($i = 0; $i < $qty; $i++)
            @php
                $existingSerial = $metadata->has($i) ? $metadata->get($i)->where('field_key', 'serial_number')->first() : null;
                $val = $existingSerial ? $existingSerial->value : '';
            @endphp
            <div style="display: flex; align-items: center; gap: 15px; background: #fff; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px;">
                <div style="flex: 0 0 80px; font-weight: bold; color: #475569; font-size: 12px; text-transform: uppercase; background: #f1f5f9; padding: 6px; border-radius: 4px; text-align: center;">
                    Unit {{ $i + 1 }}
                </div>
                <div style="flex: 1; position: relative;">
                    <input type="text" 
                           name="items[{{ $index }}][meta][{{ $i }}][serial_number]" 
                           class="form-control" 
                           placeholder="Scan or type serial number..." 
                           value="{{ $val }}" 
                           required 
                           style="height: 36px; border-radius: 4px; padding-left: 30px; border: 1px solid #cbd5e1;">
                    <i class="fa fa-barcode" style="position: absolute; left: 10px; top: 11px; color: #94a3b8;"></i>
                </div>
            </div>
        @endfor
    </div>
</div>