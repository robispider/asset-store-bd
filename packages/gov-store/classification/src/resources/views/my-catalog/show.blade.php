@extends('layouts/default')

@section('title', 'Manage Category: ' . $category->name)

@section('content')
<div class="row">
    <!-- LEFT PANEL: Local Operational Usage stats in this active office -->
    <div class="col-md-6">
        <div class="box box-solid box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Local Operational Usage</h3>
                <div class="box-tools pull-right">
                    <span class="label label-primary">Active Working Office</span>
                </div>
            </div>
            <div class="box-body">
                <table class="table table-condensed table-striped text-muted" style="font-size: 15px;">
                    <tr><th>Active Hardware Assets</th><td class="text-right"><strong>{{ $stats['assets'] }}</strong></td></tr>
                    <tr><th>Consumables</th><td class="text-right"><strong>{{ $stats['consumables'] }}</strong></td></tr>
                    <tr><th>Accessories</th><td class="text-right"><strong>{{ $stats['accessories'] }}</strong></td></tr>
                    <tr><th>Components</th><td class="text-right"><strong>{{ $stats['components'] }}</strong></td></tr>
                    <tr><th>Licenses</th><td class="text-right"><strong>{{ $stats['licenses'] }}</strong></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Category Lifecycle Controls (Adoption / Archiving) -->
    <div class="col-md-6">
        @php
            $totalUsage = $stats['assets'] + $stats['consumables'] + $stats['accessories'] + $stats['components'] + $stats['licenses'];
            $isArchived = !$adoption->is_active;
        @endphp

        <div class="box box-solid {{ $isArchived ? 'box-default' : ($totalUsage === 0 ? 'box-success' : 'box-warning') }}">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-archive"></i> Category Lifecycle Controls</h3>
            </div>
            <div class="box-body" style="padding: 20px;">
                @if($isArchived)
                    <!-- Archived State -->
                    <h4 class="text-muted" style="margin-top: 0; font-weight: bold;"><i class="fas fa-eye-slash"></i> Category is Archived</h4>
                    <p class="text-muted">This category is currently hidden from all active creation forms and dropdowns. You can safely restore it at any time.</p>
                    
                    <button class="btn btn-success btn-block btn-restore" data-id="{{ $category->id }}" style="margin-top: 15px;">
                        <i class="fas fa-undo"></i> Restore / Reactivate Category
                    </button>

                @elseif($totalUsage === 0)
                    <!-- Active & Empty (Safe to fully Delete/Un-adopt) -->
                    <h4 class="text-success" style="margin-top: 0; font-weight: bold;"><i class="fas fa-check-circle"></i> Safe to Stop Using</h4>
                    <p class="text-muted">Your active office has zero items registered. You may safely stop using (delete) this category, or simply archive it.</p>
                    
                    <div style="margin-top: 15px;">
                        <button class="btn btn-danger btn-block btn-abandon" data-id="{{ $category->id }}">
                            <i class="fas fa-trash-alt"></i> Stop Using Completely
                        </button>
                        <button class="btn btn-default btn-block btn-archive" data-id="{{ $category->id }}" style="margin-top: 10px;">
                            <i class="fas fa-eye-slash"></i> Soft-Archive Category
                        </button>
                    </div>

                @else
                    <!-- Active & In Use (Can only soft-archive) -->
                    <h4 class="text-warning" style="margin-top: 0; font-weight: bold;"><i class="fas fa-exclamation-triangle"></i> Category In Use</h4>
                    <p class="text-muted">You cannot delete this category because <strong>{{ $totalUsage }}</strong> active items use it. However, you can **Soft-Archive** it to hide it from new checkout menus.</p>
                    
                    <button class="btn btn-warning btn-block btn-archive" data-id="{{ $category->id }}" style="margin-top: 15px;">
                        <i class="fas fa-eye-slash"></i> Soft-Archive Category
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@section('moar_scripts')
<script>
$(document).ready(function() {
    
    // 1. Un-adopt completely (Includes detailed Governance Blocked alerts)
    $('.btn-abandon').on('click', function() {
        if(!confirm('Are you sure you want to stop using this category entirely? This action is permanent.')) return;
        
        const btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        $.post('{{ route("gov.catalog.adoption.abandon") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            window.location.href = '{{ route("gov.catalog.my_catalog.index") }}';
        }).fail(function(xhr) {
            alert('Governance Blocked: ' + (xhr.responseJSON?.message || 'Cannot abandon category.'));
            btn.html('<i class="fas fa-trash-alt"></i> Stop Using Completely').prop('disabled', false);
        });
    });

    // 2. Soft-Archive (Hides from creation menus without data loss)
    $('.btn-archive').on('click', function() {
        const btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        $.post('{{ route("gov.catalog.my_catalog.archive") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            window.location.reload();
        }).fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to archive category.'));
            btn.prop('disabled', false);
        });
    });

    // 3. Restore (Brings the archived category back instantly)
    $('.btn-restore').on('click', function() {
        const btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        $.post('{{ route("gov.catalog.my_catalog.restore") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            window.location.reload();
        }).fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to restore category.'));
            btn.prop('disabled', false);
        });
    });
});
</script>
@endsection
@endsection