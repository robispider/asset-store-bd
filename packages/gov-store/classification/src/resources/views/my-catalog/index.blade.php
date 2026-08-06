@extends('layouts/default')
@section('title', 'My Organization Catalog')

@section('content')
<div class="row" style="margin-bottom: 20px;">
    <!-- Top Level Summary Cards -->
    <div class="col-md-3 col-sm-6">
        <div class="info-box" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span class="info-box-icon bg-blue"><i class="fas fa-boxes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Active</span>
                <span class="info-box-number" style="font-size: 24px;">{{ $metrics['total_active'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span class="info-box-icon bg-green"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Healthy Categories</span>
                <span class="info-box-number" style="font-size: 24px; color: #00a65a;">{{ $metrics['total_active'] - $metrics['needs_cleanup'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span class="info-box-icon bg-yellow"><i class="fas fa-broom"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Needs Cleanup</span>
                <span class="info-box-number" style="font-size: 24px; color: #f39c12;">{{ $metrics['needs_cleanup'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span class="info-box-icon bg-gray"><i class="fas fa-archive"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Archived</span>
                <span class="info-box-number" style="font-size: 24px;">{{ $metrics['archived'] }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            
            <!-- Intent-Driven Tabs -->
            <ul class="nav nav-tabs">
                <li class="{{ $activeTab === 'active' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'active']) }}">
                        <i class="fas fa-folder-open text-blue"></i> Active Catalog
                    </a>
                </li>
                <li class="{{ $activeTab === 'cleanup' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'cleanup']) }}">
                        <i class="fas fa-broom text-yellow"></i> Cleanup Center @if($metrics['needs_cleanup'] > 0)<span class="label label-warning" style="margin-left:5px;">{{ $metrics['needs_cleanup'] }}</span>@endif
                    </a>
                </li>
                <li class="{{ $activeTab === 'archived' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'archived']) }}">
                        <i class="fas fa-archive text-muted"></i> Archived
                    </a>
                </li>
                
                <!-- Dual-Mode Explorer Switch -->
                <li class="pull-right">
                    <a href="{{ route('gov.catalog.discover.explorer', ['mode' => 'local']) }}" class="text-muted" style="background-color: #f9f9f9; border-left: 1px solid #ddd;">
                        <i class="fas fa-sitemap"></i> Switch to Explorer View
                    </a>
                </li>
            </ul>

            <div class="tab-content no-padding">
                <div class="box box-solid" style="margin-bottom: 0; box-shadow: none;">
                    
                    @if($activeTab === 'cleanup')
                    <div class="box-header">
                        <div class="alert alert-warning" style="margin-bottom: 0; padding: 12px 15px; border-radius: 4px;">
                            <i class="fas fa-info-circle"></i> <strong>Taxonomy Cleanup:</strong> These categories have exactly 0 physical items (assets/consumables) in your active office. Dropping them removes clutter from your inventory dropdowns.
                        </div>
                    </div>
                    @endif

                    <div class="box-body table-responsive">
                        <table class="table table-striped table-hover table-bordered">
                            <thead style="background-color: #f9f9f9;">
                                <tr>
                                    <th>Category Title (UNSPSC Code)</th>
                                    <th>Origin Source</th>
                                    <th class="text-center">Local Usage</th>
                                    <th class="text-center" style="width: 130px;">Health Status</th>
                                    @if(!$isReadOnly)
                                        <th class="text-center" style="width: 150px;">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $cat)
                                    <tr>
                                        <td>
                                            <strong style="font-size: 15px;">{{ $cat->name }}</strong><br>
                                            <code class="text-muted" style="background: transparent; padding: 0;">{{ $cat->unspsc_code ?? 'Unmapped' }}</code>
                                            <span class="label label-default pull-right">{{ ucfirst($cat->category_type) }}</span>
                                        </td>
                                        
                                        <!-- Simplified Governance Source -->
                                        <td style="vertical-align: middle;">
                                            @if($cat->governance_type === 'global')
                                                <span><i class="fas fa-globe text-green"></i> Global Standard</span>
                                            @elseif($cat->governance_type === 'company' || $cat->governance_type === 'location')
                                                <span><i class="fas fa-building text-orange"></i> Organization</span>
                                            @else
                                                <span class="text-muted"><i class="fas fa-server"></i> Native</span>
                                            @endif
                                        </td>

                                        <!-- Usage Count -->
                                        <td class="text-center" style="vertical-align: middle; font-size: 15px; font-weight: bold;">
                                            {{ $cat->total_usage_count }} Items
                                        </td>
                                        
                                        <!-- The New Health Indicator -->
                                        <td class="text-center" style="vertical-align: middle;">
                                            @if($cat->total_usage_count > 0)
                                                <span class="text-success"><i class="fas fa-circle"></i> Healthy</span>
                                            @else
                                                <span class="text-warning"><i class="fas fa-exclamation-circle"></i> Unused</span>
                                            @endif
                                        </td>

                                        @if(!$isReadOnly)
                                            <td class="text-center" style="vertical-align: middle;">
                                                <a href="{{ route('gov.catalog.my_catalog.show', $cat->id) }}" class="btn btn-sm btn-default" title="Category Dashboard">
                                                    <i class="fas fa-cog"></i> Manage
                                                </a>
                                                
                                                <!-- Quick Drop Action in Cleanup Tab -->
                                                @if($activeTab === 'cleanup')
                                                    <button class="btn btn-sm btn-danger btn-abandon-quick" data-id="{{ $cat->id }}" style="margin-left: 5px;" title="Stop Using">
                                                        <i class="fas fa-trash-alt"></i> Drop
                                                    </button>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isReadOnly ? 4 : 5 }}" class="text-center text-muted" style="padding: 50px;">
                                            <i class="fas fa-folder-open fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i><br>
                                            @if($activeTab === 'cleanup')
                                                <h4 style="margin:0;">No empty categories found.</h4>
                                                <p>Your catalog is clean and healthy!</p>
                                            @else
                                                No categories found in this section.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        {{ $categories->appends(['tab' => $activeTab])->links() }}
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
    // Quick Drop Action for the Cleanup Tab
    jQuery('.btn-abandon-quick').on('click', function() {
        if(!confirm('Are you sure you want to stop using this category? It will be removed from your office dropdowns.')) return;
        
        const btn = jQuery(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        jQuery.post('{{ route("gov.catalog.adoption.abandon") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            btn.closest('tr').fadeOut('fast', function() { jQuery(this).remove(); });
        }).fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Cannot remove category.'));
            btn.html('<i class="fas fa-trash-alt"></i> Drop').prop('disabled', false);
        });
    });
});
</script>
@endsection