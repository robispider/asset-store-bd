<table class="table table-striped table-bordered">
    <thead>
        <tr style="background-color: #f4f4f4;">
            <th>Date & Time</th>
            <th>Reference Document</th>
            <th>Operator</th>
            <th class="text-center text-success">IN (+)</th>
            <th class="text-center text-danger">OUT (-)</th>
            <th class="text-center" style="background-color: #e8e8e8;">Running Balance</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movements as $movement)
            <tr>
                <td>{{ \Carbon\Carbon::parse($movement->created_at)->format('d M Y, h:i A') }}</td>
                <td>
                    @if($movement->document)
                        <strong>{{ $movement->document->receipt_no ?? $movement->document->issue_no ?? $movement->document->adjustment_no }}</strong>
                    @else
                        System Initialization
                    @endif
                </td>
                <td>{{ $movement->creator->first_name ?? 'System' }}</td>
                
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
                <td colspan="6" class="text-center text-muted">No inventory movements recorded yet.</td>
            </tr>
        @endforelse
    </tbody>
</table>
