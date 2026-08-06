@extends('layouts/default')
@section('title', $mode === 'local' ? 'Local Catalog Explorer' : 'Master Catalog Explorer')

@section('content')
<div class="row">
    <div class="col-md-12">
        
        <!-- Mode Switcher Tabs -->
        <div class="nav-tabs-custom" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <ul class="nav nav-tabs">
                <li class="{{ $mode === 'master' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.discover.explorer', ['mode' => 'master', 'parent' => $parentCode]) }}">
                        <i class="fas fa-globe text-blue"></i> Master Global Catalog
                    </a>
                </li>
                <li class="{{ $mode === 'local' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.discover.explorer', ['mode' => 'local', 'parent' => $parentCode]) }}">
                        <i class="fas fa-building text-orange"></i> My Adopted Inventory
                    </a>
                </li>
                
                <!-- Cross Navigation back to List View -->
                <li class="pull-right">
                    <a href="{{ route('gov.catalog.my_catalog.index') }}" class="text-muted" style="background-color: #f9f9f9; border-left: 1px solid #ddd;">
                        <i class="fas fa-list"></i> Switch to List View
                    </a>
                </li>
            </ul>
        </div>

        <div class="box box-solid" style="border-top: 3px solid {{ $mode === 'local' ? '#f39c12' : '#3c8dbc' }};">
            
            <!-- Breadcrumbs -->
            <div class="box-header with-border" style="background-color: #fcfcfc;">
                <h3 class="box-title" style="font-size: 16px;">
                    <a href="{{ route('gov.catalog.discover.explorer', ['mode' => $mode]) }}" class="text-{{ $mode === 'local' ? 'orange' : 'blue' }}">
                        <i class="fas fa-home"></i> {{ $mode === 'local' ? 'My Inventory' : 'Master Catalog' }}
                    </a>
                    @foreach($breadcrumbs as $crumb)
                        <span class="text-muted" style="margin: 0 5px;">/</span>
                        @if(!$loop->last)
                            <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $crumb->code, 'mode' => $mode]) }}" class="text-{{ $mode === 'local' ? 'orange' : 'blue' }}">{{ $crumb->title_en }}</a>
                        @else
                            <strong>{{ $crumb->title_en }}</strong>
                        @endif
                    @endforeach
                </h3>
            </div>

            <!-- Bulk Action Bar -->
            <div id="bulk-action-bar" class="box-body" style="background-color: #fffaeb; border-bottom: 1px solid #f4ecd8; display: none; padding: 10px 15px;">
                <strong id="selected-count" class="text-orange" style="font-size: 15px; margin-right: 15px;">0 Items Selected</strong>
                
                @if($mode === 'master')
                    <button type="button" class="btn btn-warning btn-sm" onclick="executeExplorerBulkAdoption()" style="margin-right: 5px;">
                        <i class="fas fa-rocket"></i> Bulk Adopt Selected
                    </button>
                @endif
                
                @if($canManageCollections)
                    <button type="button" class="btn btn-purple btn-sm" onclick="executeExplorerBulkAddToCollection()">
                        <i class="fas fa-boxes"></i> Add Selected to Collection
                    </button>
                @endif
            </div>

            <!-- Main Explorer Table -->
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover table-striped">
                    <thead style="background-color: #f9f9f9;">
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th style="width: 50px;"></th> <!-- Icon -->
                            <th style="width: 150px;">Code</th>
                            <th>Classification Title</th>
                            <th class="text-center" style="width: 150px;">Status / Source</th>
                            <th class="text-center" style="width: 80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Go Up (If not at root) -->
                        @if($parentCode && count($breadcrumbs) > 0)
                            @php
                                $upCode = count($breadcrumbs) > 1 ? $breadcrumbs[count($breadcrumbs)-2]->code : null;
                            @endphp
                            <tr>
                                <td></td>
                                <td class="text-center"><i class="fas fa-level-up-alt text-muted"></i></td>
                                <td colspan="4">
                                    <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $upCode, 'mode' => $mode]) }}" class="text-muted" style="font-style: italic;">... Go up one level</a>
                                </td>
                            </tr>
                        @endif

                        <!-- Nodes -->
                        @foreach($nodes as $node)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">
                                    @if(!$node->is_folder || $mode === 'master')
                                        <input type="checkbox" class="node-checkbox" value="{{ $node->code }}">
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle; font-size: 18px;">
                                    @if($node->is_folder)
                                        <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code, 'mode' => $mode]) }}"><i class="fas fa-folder text-yellow"></i></a>
                                    @else
                                        <i class="fas fa-file-alt text-muted"></i>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;"><code>{{ $node->code }}</code></td>
                                <td style="vertical-align: middle;">
                                    @if($node->is_folder)
                                        <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code, 'mode' => $mode]) }}" style="font-weight: bold; font-size: 14px;">{{ $node->title_en }}</a>
                                    @else
                                        <span style="font-weight: normal; font-size: 14px;">{{ $node->title_en }}</span>
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    @if($node->is_folder)
                                        <span class="label label-default" style="font-weight: normal; background-color: #f39c12 !important;">Folder</span>
                                    @elseif($node->is_global)
                                        <span class="label label-success"><i class="fas fa-globe"></i> Global Std</span>
                                    @elseif($node->is_adopted)
                                        <span class="label label-success"><i class="fas fa-building"></i> Local Adopted</span>
                                    @else
                                        <span class="label label-default" style="background-color: #ddd; color: #777;">Not Adopted</span>
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <!-- Context Action Menu (⋮ Dropdown) -->
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" aria-expanded="false" style="padding: 2px 8px;">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-right" role="menu">
                                            @if($node->is_folder)
                                                <li>
                                                    <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code, 'mode' => $mode]) }}">
                                                        <i class="fas fa-folder-open text-yellow"></i> Browse
                                                    </a>
                                                </li>
                                                @if($mode === 'master')
                                                    <li>
                                                        <a href="#" onclick="event.preventDefault(); triggerBulkAdoption(['{{ $node->code }}'])">
                                                            <i class="fas fa-rocket text-green"></i> Adopt All Commodities
                                                        </a>
                                                    </li>
                                                @endif
                                                @if($canManageCollections)
                                                    <li>
                                                        <a href="#" onclick="event.preventDefault(); triggerAddToCollection(['{{ $node->code }}'])">
                                                            <i class="fas fa-boxes text-purple"></i> Add All to Collection
                                                        </a>
                                                    </li>
                                                @endif
                                            @else
                                                @if($mode === 'local' && ($node->is_adopted || $node->is_global))
                                                    <!-- Cross Navigation to Dashboard -->
                                                    <li>
                                                        <a href="{{ route('gov.catalog.my_catalog.show', $node->snipeMapping->category_id) }}">
                                                            <i class="fas fa-cog text-blue"></i> Manage Local Category
                                                        </a>
                                                    </li>
                                                @else
                                                    <li>
                                                        <a href="{{ route('gov.catalog.mapping.show', $node->code) }}">
                                                            <i class="fas fa-eye text-blue"></i> View Metadata
                                                        </a>
                                                    </li>
                                                    @if(!$node->is_adopted && !$node->is_global)
                                                        <li>
                                                            <a href="#" onclick="event.preventDefault(); triggerBulkAdoption(['{{ $node->code }}'])">
                                                                <i class="fas fa-rocket text-green"></i> Adopt Category
                                                            </a>
                                                        </li>
                                                    @endif
                                                @endif
                                                
                                                @if($canManageCollections)
                                                    <li>
                                                        <a href="#" onclick="event.preventDefault(); triggerAddToCollection(['{{ $node->code }}'])">
                                                            <i class="fas fa-boxes text-purple"></i> Add to Collection
                                                        </a>
                                                    </li>
                                                @endif
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="padding: 10px;">
                    {{ $nodes->appends(['parent' => $parentCode, 'mode' => $mode])->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Phase 2 & Phase 3 Reusable Modals -->
@include('gov-classification::adopt.partials.bulk-preview')
@include('gov-classification::discover.partials.collection-modal')

@endsection

@section('moar_scripts')
@parent
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Select All Checkbox
    jQuery('#select-all').on('change', function() {
        jQuery('.node-checkbox').prop('checked', jQuery(this).prop('checked'));
        updateActionBar();
    });

    // Individual Checkboxes
    jQuery('.node-checkbox').on('change', function() {
        updateActionBar();
    });

    function updateActionBar() {
        let count = jQuery('.node-checkbox:checked').length;
        if (count > 0) {
            jQuery('#selected-count').text(count + (count === 1 ? ' Item Selected' : ' Items Selected'));
            jQuery('#bulk-action-bar').slideDown('fast');
        } else {
            jQuery('#bulk-action-bar').slideUp('fast');
            jQuery('#select-all').prop('checked', false);
        }
    }
});

function executeExplorerBulkAdoption() {
    let codes = [];
    jQuery('.node-checkbox:checked').each(function() {
        codes.push(jQuery(this).val());
    });
    if (codes.length > 0) triggerBulkAdoption(codes); 
}

function executeExplorerBulkAddToCollection() {
    let codes = [];
    jQuery('.node-checkbox:checked').each(function() {
        codes.push(jQuery(this).val());
    });
    if (codes.length > 0) triggerAddToCollection(codes); 
}
</script>
@endsection