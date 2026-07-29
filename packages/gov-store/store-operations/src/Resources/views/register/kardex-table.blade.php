<table class="table table-striped table-bordered">
    <thead>
        <tr style="background-color: #f4f4f4;">
            <th>{{ __('storeops::storeops.date_time') }}</th>
            <th>{{ __('storeops::storeops.reference_document') }}</th>
            <th>{{ __('storeops::storeops.operator') }}</th>
            <th class="text-center text-success">{{ __('storeops::storeops.in_column') }}</th>
            <th class="text-center text-danger">{{ __('storeops::storeops.out_column') }}</th>
            <th class="text-center" style="background-color: #e8e8e8;">{{ __('storeops::storeops.running_balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movements as $movement)
            <tr>
                <td>{{ \Carbon\Carbon::parse($movement->created_at)->format('d M Y, h:i A') }}</td>
             <td>
                    @if($movement->document)
                        <!-- Reads new document_number, falling back to old columns for legacy data -->
                        <strong>{{ $movement->document->document_number ?? $movement->document->receipt_no ?? $movement->document->issue_no ?? $movement->document->adjustment_no }}</strong>
                    @else
                        {{ __('storeops::storeops.system_initialization') }}
                    @endif
                </td>
                <td>{{ $movement->creator->first_name ?? __('storeops::storeops.system_initialization') }}</td>
                
                <td class="text-center text-success">
                    {{ $movement->movement_type === 'IN' ? $movement->quantity : '-' }}
                </td>
                <td class="text-center text-danger">
                    {{ $movement->movement_type === 'OUT' ? $movement->quantity : '-' }}
                </td>
                <td class="text-center" style="font-weight: bold; background-color: #f9f9f9;">
                    {{ $movement->balance_after ?? 'N/A' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-muted">{{ __('storeops::storeops.no_movements_recorded') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
