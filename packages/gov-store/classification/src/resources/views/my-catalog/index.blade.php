@extends('layouts/default')
@section('title', 'My Organization Catalog')

@section('content')
<div class="classification-theme">
<div class="row classify-inline-b75fad00">
    <!-- Top Level Summary Cards -->
    <div class="col-md-3 col-sm-6">
        <div class="info-box classify-inline-d4846a26">
            <span class="info-box-icon bg-blue"><i class="fas fa-boxes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Active</span>
                <span class="info-box-number classify-inline-7f09016c">{{ $metrics['total_active'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box classify-inline-d4846a26">
            <span class="info-box-icon bg-green"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Healthy Categories</span>
                <span class="info-box-number classify-inline-b6f75aa2">{{ $metrics['total_active'] - $metrics['needs_cleanup'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box classify-inline-d4846a26">
            <span class="info-box-icon bg-yellow"><i class="fas fa-broom"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Needs Cleanup</span>
                <span class="info-box-number classify-inline-4a6ffb24">{{ $metrics['needs_cleanup'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box classify-inline-d4846a26">
            <span class="info-box-icon bg-gray"><i class="fas fa-archive"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Archived</span>
                <span class="info-box-number classify-inline-7f09016c">{{ $metrics['archived'] }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom classify-inline-d4846a26">
            
            <!-- Intent-Driven Tabs -->
            <ul class="nav nav-tabs">
                <li class="{{ $activeTab === 'active' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'active']) }}">
                        <i class="fas fa-folder-open text-blue"></i> Active Catalog
                    </a>
                </li>
                <li class="{{ $activeTab === 'cleanup' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'cleanup']) }}">
                        <i class="fas fa-broom text-yellow"></i> Cleanup Center @if($metrics['needs_cleanup'] > 0)<span class="label label-warning classify-inline-3fed2a7e">{{ $metrics['needs_cleanup'] }}</span>@endif
                    </a>
                </li>
                <li class="{{ $activeTab === 'archived' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'archived']) }}">
                        <i class="fas fa-archive text-muted"></i> Archived
                    </a>
                </li>
                
                <!-- Dual-Mode Explorer Switch -->
                <li class="pull-right">
                    <a href="{{ route('gov.catalog.discover.explorer', ['mode' => 'local']) }}" class="text-muted" class="classify-inline-434c3c45">
                        <i class="fas fa-sitemap"></i> Switch to Explorer View
                    </a>
                </li>
            </ul>

            <div class="tab-content no-padding">
                <div class="box box-solid classify-inline-bcc39f9a">
                    
                    @if($activeTab === 'cleanup')
                    <div class="box-header">
                        <div class="alert alert-warning classify-inline-3c613ab2">
                            <i class="fas fa-info-circle"></i> <strong>Taxonomy Cleanup:</strong> These categories have exactly 0 physical items (assets/consumables) in your active office. Dropping them removes clutter from your inventory dropdowns.
                        </div>
                    </div>
                    @endif

                    <div class="box-body table-responsive">
                        <x-gs::table class="table table-striped table-hover table-bordered">
                            <thead class="classify-inline-0e5be659">
                                <tr>
                                    <th>Category Title (UNSPSC Code)</th>
                                    <th>Origin Source</th>
                                    <th class="text-center">Local Usage</th>
                                    <th class="text-center classify-inline-3854f2e9">Health Status</th>
                                    @if(!$isReadOnly)
                                        <th class="text-center classify-inline-5cb57137">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $cat)
                                    <tr>
                                        <td>
                                            <strong class="classify-inline-f112760e">{{ $cat->name }}</strong><br>
                                            <code class="text-muted classify-inline-45c5a1e0">{{ $cat->unspsc_code ?? 'Unmapped' }}</code>
                                            <span class="label label-default pull-right">{{ ucfirst($cat->category_type) }}</span>
                                        </td>
                                        
                                        <!-- Simplified Governance Source -->
                                        <td class="classify-inline-da57c5e8">
                                            @if($cat->governance_type === 'global')
                                                <span><i class="fas fa-globe text-green"></i> Global Standard</span>
                                            @elseif($cat->governance_type === 'company' || $cat->governance_type === 'location')
                                                <span><i class="fas fa-building text-orange"></i> Organization</span>
                                            @else
                                                <span class="text-muted"><i class="fas fa-server"></i> Native</span>
                                            @endif
                                        </td>

                                        <!-- Usage Count -->
                                        <td class="text-center classify-inline-ccbff60c">
                                            {{ $cat->total_usage_count }} Items
                                        </td>
                                        
                                        <!-- The New Health Indicator -->
                                        <td class="text-center classify-inline-da57c5e8">
                                            @if($cat->total_usage_count > 0)
                                                <span class="text-success"><i class="fas fa-circle"></i> Healthy</span>
                                            @else
                                                <span class="text-warning"><i class="fas fa-exclamation-circle"></i> Unused</span>
                                            @endif
                                        </td>

                                        @if(!$isReadOnly)
                                            <td class="text-center classify-inline-da57c5e8">
                                                <a href="{{ route('gov.catalog.my_catalog.show', $cat->id) }}" class="btn btn-sm btn-default" title="Category Dashboard">
                                                    <i class="fas fa-cog"></i> Manage
                                                </a>
                                                
                                                <!-- Quick Drop Action in Cleanup Tab -->
                                                @if($activeTab === 'cleanup')
                                                    <button class="btn btn-sm btn-danger btn-abandon-quick" data-id="{{ $cat->id }}" class="classify-inline-29ab0e70" title="Stop Using">
                                                        <i class="fas fa-trash-alt"></i> Drop
                                                    </button>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center text-muted classify-inline-86ca81e2" colspan="{{ $isReadOnly ? 4 : 5 }}">
                                            <i class="fas fa-folder-open fa-3x classify-inline-477d38c3"></i><br>
                                            @if($activeTab === 'cleanup')
                                                <h4 class="classify-inline-1da9facb">No empty categories found.</h4>
                                                <p>Your catalog is clean and healthy!</p>
                                            @else
                                                No categories found in this section.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-gs::table>
                        {{ $categories->appends(['tab' => $activeTab])->links() }}
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