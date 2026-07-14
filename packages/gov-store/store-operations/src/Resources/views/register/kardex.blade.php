@extends('layouts/default')

@section('title', 'Stock Card: ' . $item->name)

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-book"></i> Immutable Stock Register (Kardex)
                </h3>
                <div class="box-tools pull-right">
                    <span class="label label-primary" style="font-size: 14px;">
                        Current Snipe-IT Projection: {{ $item->qty }}
                    </span>
                </div>
            </div>
            <div class="box-body">
                <h4>Item: <strong>{{ $item->name }}</strong></h4>
                
                <table class="table table-striped table-bordered" style="margin-top: 20px;">
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
                                        <!-- Displays GR-2026-000001 or similar -->
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
                                    {{ $movement->running_balance }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No inventory movements recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
