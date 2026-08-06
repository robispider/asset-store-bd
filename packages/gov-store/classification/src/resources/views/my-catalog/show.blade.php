@extends('layouts/default')
@section('title', 'Category Dashboard: ' . $category->name)

@php
    $totalUsage = $stats['assets'] + $stats['consumables'] + $stats['accessories'] + $stats['components'] + $stats['licenses'];
    $isArchived = !$adoption->is_active;
    
    // Safely cast $node to ensure Blade doesn't crash if it is null
    $hasNode = isset($node) && $node !== null;
@endphp

@section('content')
<!-- Header Summary Banner -->
<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-12">
        <a href="{{ route('gov.catalog.my_catalog.index') }}" class="btn btn-default btn-sm" style="margin-bottom: 15px;">
            <i class="fas fa-arrow-left"></i> Back to My Catalog
        </a>
        
        <div class="box box-solid bg-gray-light" style="border-bottom: 3px solid #3c8dbc; margin-bottom: 0;">
            <div class="box-body" style="padding: 20px;">
                <div class="pull-right text-right">
                    <!-- Health Indicator -->
                    @if($totalUsage > 0)
                        <span class="label label-success" style="font-size: 13px;"><i class="fas fa-circle"></i> Healthy (In Use)</span>
                    @else
                        <span class="label label-warning" style="font-size: 13px;"><i class="fas fa-exclamation-circle"></i> Unused</span>
                    @endif
                </div>
                <h2 style="margin-top: 0; font-weight: bold; color: #333;">
                    <i class="fas fa-box text-blue"></i> {{ $category->name }}
                </h2>
                <p class="text-muted" style="margin-bottom: 0; font-size: 15px;">
                    <strong>Source:</strong> 
                    @if($governance && $governance->governance_type === 'global')
                        <span class="text-green"><i class="fas fa-globe"></i> Global Standard</span>
                    @elseif($governance && ($governance->governance_type === 'company' || $governance->governance_type === 'location'))
                        <span class="text-orange"><i class="fas fa-building"></i> {{ ucfirst($governance->governance_type) }} Standard</span>
                    @else
                        <span class="text-muted"><i class="fas fa-server"></i> Native Snipe-IT Category</span>
                    @endif
                    <span style="margin: 0 10px;">|</span>
                    <strong>Type:</strong> {{ ucfirst($category->category_type) }}
                    
                    @if($hasNode)
                        <span style="margin: 0 10px;">|</span>
                        <strong>Official Code:</strong> <code>{{ $node->code }}</code>
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <!-- Dashboard Tabs -->
        <div class="nav-tabs-custom" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <ul class="nav nav-tabs">
                <li class="active"><a href="#tab_overview" data-toggle="tab"><i class="fas fa-chart-bar text-blue"></i> Overview & Usage</a></li>
                <li><a href="#tab_hierarchy" data-toggle="tab"><i class="fas fa-sitemap text-yellow"></i> Hierarchy & Context</a></li>
                <li class="pull-right"><a href="#tab_lifecycle" data-toggle="tab" class="text-danger"><i class="fas fa-cog"></i> Lifecycle Controls</a></li>
            </ul>
            
            <div class="tab-content" style="padding: 20px;">
                
                <!-- TAB 1: OVERVIEW & USAGE -->
                <div class="tab-pane active" id="tab_overview">
                    <div class="row">
                        <!-- Usage Stats -->
                        <div class="col-md-6">
                            <h4 style="font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Local Inventory Usage</h4>
                            <table class="table table-striped table-hover" style="font-size: 15px;">
                                <tr><td><i class="fas fa-laptop text-muted"></i> Active Hardware Assets</td><td class="text-right"><strong class="text-blue">{{ $stats['assets'] }}</strong></td></tr>
                                <tr><td><i class="fas fa-tint text-muted"></i> Consumables</td><td class="text-right"><strong>{{ $stats['consumables'] }}</strong></td></tr>
                                <tr><td><i class="fas fa-keyboard text-muted"></i> Accessories</td><td class="text-right"><strong>{{ $stats['accessories'] }}</strong></td></tr>
                                <tr><td><i class="fas fa-hdd text-muted"></i> Components</td><td class="text-right"><strong>{{ $stats['components'] }}</strong></td></tr>
                                <tr><td><i class="fas fa-save text-muted"></i> Licenses</td><td class="text-right"><strong>{{ $stats['licenses'] }}</strong></td></tr>
                                <tr style="background-color: #f4f8fa;">
                                    <td><strong>Total Items</strong></td>
                                    <td class="text-right"><strong style="font-size: 18px;">{{ $totalUsage }}</strong></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Collections Awareness (Read Only) -->
                        <div class="col-md-6">
                            <h4 style="font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Official Collections</h4>
                            <p class="text-muted" style="font-size: 13px;">This category is officially recommended and bundled in the following master collections:</p>
                            
                            @if($hasNode && $node->collections->count() > 0)
                                <div class="list-group">
                                    @foreach($node->collections as $collection)
                                        <div class="list-group-item" style="border-left: 3px solid #605ca8;">
                                            <i class="{{ $collection->icon }} text-purple" style="margin-right: 10px; font-size: 18px; vertical-align: middle;"></i>
                                            <strong style="font-size: 15px;">{{ $collection->name }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="well well-sm text-center text-muted" style="background-color: #fcfcfc;">
                                    <i class="fas fa-layer-group fa-2x" style="opacity: 0.3; margin-bottom: 10px;"></i><br>
                                    Not currently part of any official collection.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- TAB 2: HIERARCHY & CONTEXT -->
                <div class="tab-pane" id="tab_hierarchy">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 style="font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Master Catalog Structural Path</h4>
                            
                            @if($hasNode && isset($breadcrumbs) && $breadcrumbs->count() > 0)
                                <ul class="list-unstyled" style="padding-left: 5px; font-size: 15px; line-height: 2.2;">
                                    @foreach($breadcrumbs as $index => $crumb)
                                        @if($crumb->code === $node->code)
                                            <!-- Current Node Highlight -->
                                            <li style="padding-left: {{ $index * 20 }}px;">
                                                <div style="background-color: #f0f7ff; padding: 8px 15px; border-left: 3px solid #3c8dbc; border-radius: 0 4px 4px 0; display: inline-block;">
                                                    <strong class="text-blue"><i class="fas fa-file-alt" style="margin-right: 8px;"></i> {{ $crumb->title_en }} (Level {{ $crumb->level }})</strong>
                                                </div>
                                            </li>
                                        @else
                                            <!-- Ancestor Folders -->
                                            <li style="padding-left: {{ $index * 20 }}px; color: #666;">
                                                <i class="fas fa-folder-open text-yellow" style="margin-right: 8px;"></i> {{ $crumb->title_en }}
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                                
                                <!-- Cross Navigation to Explorer -->
                                @php
                                    $parentCrumb = $breadcrumbs->count() > 1 ? $breadcrumbs[$breadcrumbs->count() - 2]->code : null;
                                @endphp
                                <div style="margin-top: 25px; padding-top: 15px; border-top: 1px dashed #ddd;">
                                    <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $parentCrumb, 'mode' => 'master']) }}" class="btn btn-default">
                                        <i class="fas fa-search"></i> Locate in Master Explorer
                                    </a>
                                </div>
                            @else
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> This category is not linked to the official Master Catalog (Orphaned Native Category).
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- TAB 3: LIFECYCLE CONTROLS -->
                <div class="tab-pane" id="tab_lifecycle">
                    <div class="row">
                        <div class="col-md-6">
                            @if($isArchived)
                                <h4 class="text-muted" style="margin-top: 0; font-weight: bold;"><i class="fas fa-eye-slash"></i> Category is Archived</h4>
                                <p class="text-muted">This category is currently hidden from all active creation forms and dropdowns. You can safely restore it at any time.</p>
                                <button class="btn btn-success btn-restore" data-id="{{ $category->id }}" style="margin-top: 15px;">
                                    <i class="fas fa-undo"></i> Restore / Reactivate Category
                                </button>
                            @elseif($totalUsage === 0)
                                <h4 class="text-success" style="margin-top: 0; font-weight: bold;"><i class="fas fa-check-circle"></i> Safe to Drop</h4>
                                <p class="text-muted">Your active office has zero items registered. You may safely stop using (drop) this category to clean up your dropdown menus, or simply archive it.</p>
                                <div style="margin-top: 15px;">
                                    <button class="btn btn-danger btn-abandon" data-id="{{ $category->id }}">
                                        <i class="fas fa-trash-alt"></i> Drop (Stop Using Completely)
                                    </button>
                                    <button class="btn btn-default btn-archive" data-id="{{ $category->id }}" style="margin-left: 10px;">
                                        <i class="fas fa-eye-slash"></i> Soft-Archive
                                    </button>
                                </div>
                            @else
                                <h4 class="text-warning" style="margin-top: 0; font-weight: bold;"><i class="fas fa-exclamation-triangle"></i> Category In Use</h4>
                                <p class="text-muted">You cannot delete this category because <strong>{{ $totalUsage }} active items</strong> use it. However, you can <strong>Soft-Archive</strong> it to hide it from new asset checkout menus.</p>
                                <button class="btn btn-warning btn-archive" data-id="{{ $category->id }}" style="margin-top: 15px;">
                                    <i class="fas fa-eye-slash"></i> Soft-Archive Category
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
@parent
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Un-adopt completely
    jQuery('.btn-abandon').on('click', function() {
        if(!confirm('Are you sure you want to stop using this category entirely? It will be removed from your office.')) return;
        const btn = jQuery(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        jQuery.post('{{ route("gov.catalog.adoption.abandon") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            window.location.href = '{{ route("gov.catalog.my_catalog.index") }}';
        }).fail(function(xhr) {
            alert('Governance Blocked: ' + (xhr.responseJSON?.message || 'Cannot abandon category.'));
            btn.html('<i class="fas fa-trash-alt"></i> Drop').prop('disabled', false);
        });
    });

    // 2. Soft-Archive
    jQuery('.btn-archive').on('click', function() {
        const btn = jQuery(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        jQuery.post('{{ route("gov.catalog.my_catalog.archive") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            window.location.reload();
        }).fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to archive.'));
            btn.prop('disabled', false);
        });
    });

    // 3. Restore
    jQuery('.btn-restore').on('click', function() {
        const btn = jQuery(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        jQuery.post('{{ route("gov.catalog.my_catalog.restore") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            window.location.reload();
        }).fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to restore.'));
            btn.prop('disabled', false);
        });
    });
});
</script>
@endsection