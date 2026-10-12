@extends('layouts/default')
@section('title', $mode === 'local' ? 'Local Catalog Explorer' : 'Master Catalog Explorer')

@section('content')
<div class="classification-theme">
<div class="row">
    <div class="col-md-12">
        
        <!-- Mode Switcher Tabs -->
        <div class="nav-tabs-custom classify-inline-53934b72">
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
                    <a class="text-muted classify-inline-434c3c45" href="{{ route('gov.catalog.my_catalog.index') }}">
                        <i class="fas fa-list"></i> Switch to List View
                    </a>
                </li>
            </ul>
        </div>

        <div @class(['box', 'box-solid', 'catalog-mode-local' => $mode === 'local', 'catalog-mode-global' => $mode !== 'local'])>
            
            <!-- Breadcrumbs -->
            <div class="box-header with-border classify-inline-074df022">
                <h3 class="box-title classify-inline-b87efa5b">
                    <a href="{{ route('gov.catalog.discover.explorer', ['mode' => $mode]) }}" class="text-{{ $mode === 'local' ? 'orange' : 'blue' }}">
                        <i class="fas fa-home"></i> {{ $mode === 'local' ? 'My Inventory' : 'Master Catalog' }}
                    </a>
                    @foreach($breadcrumbs as $crumb)
                        <span class="text-muted classify-inline-446e6f7e">/</span>
                        @if(!$loop->last)
                            <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $crumb->code, 'mode' => $mode]) }}" class="text-{{ $mode === 'local' ? 'orange' : 'blue' }}">{{ $crumb->title_en }}</a>
                        @else
                            <strong>{{ $crumb->title_en }}</strong>
                        @endif
                    @endforeach
                </h3>
            </div>

            <!-- Bulk Action Bar -->
            <div class="box-body classify-inline-f9a35d90" id="bulk-action-bar">
                <strong class="text-orange classify-inline-9ac9a993" id="selected-count">0 Items Selected</strong>
                
                @if($mode === 'master')
                    <button class="btn btn-warning btn-sm classify-inline-9761b3f7" type="button" onclick="executeExplorerBulkAdoption()">
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
                <x-gs::table class="table table-hover table-striped">
                    <thead class="classify-inline-0e5be659">
                        <tr>
                            <th class="classify-inline-ccddc627">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th class="classify-inline-5f3eb38a"></th> <!-- Icon -->
                            <th class="classify-inline-5cb57137">Code</th>
                            <th>Classification Title</th>
                            <th class="text-center classify-inline-5cb57137">Status / Source</th>
                            <th class="text-center classify-inline-1b3b3079">Actions</th>
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
                                    <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $upCode, 'mode' => $mode]) }}" class="text-muted" class="classify-inline-b9963897">... Go up one level</a>
                                </td>
                            </tr>
                        @endif

                        <!-- Nodes -->
                        @foreach($nodes as $node)
                            <tr>
                                <td class="text-center classify-inline-da57c5e8">
                                    @if(!$node->is_folder || $mode === 'master')
                                        <input type="checkbox" class="node-checkbox" value="{{ $node->code }}">
                                    @endif
                                </td>
                                <td class="text-center classify-inline-a5a1a4ce">
                                    @if($node->is_folder)
                                        <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code, 'mode' => $mode]) }}"><i class="fas fa-folder text-yellow"></i></a>
                                    @else
                                        <i class="fas fa-file-alt text-muted"></i>
                                    @endif
                                </td>
                                <td class="classify-inline-da57c5e8"><code>{{ $node->code }}</code></td>
                                <td class="classify-inline-da57c5e8">
                                    @if($node->is_folder)
                                        <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code, 'mode' => $mode]) }}" class="classify-inline-13d744a7">{{ $node->title_en }}</a>
                                    @else
                                        <span class="classify-inline-8e1b3505">{{ $node->title_en }}</span>
                                    @endif
                                </td>
                                <td class="text-center classify-inline-da57c5e8">
                                    @if($node->is_folder)
                                        <span class="label label-default classify-inline-752b87a9">Folder</span>
                                    @elseif($node->is_global)
                                        <span class="label label-success"><i class="fas fa-globe"></i> Global Std</span>
                                    @elseif($node->is_adopted)
                                        <span class="label label-success"><i class="fas fa-building"></i> Local Adopted</span>
                                    @else
                                        <span class="label label-default classify-inline-a5fce76b">Not Adopted</span>
                                    @endif
                                </td>
                                <td class="text-center classify-inline-da57c5e8">
                                    <!-- Context Action Menu (⋮ Dropdown) -->
                                    <div class="btn-group">
                                        <button class="btn btn-default btn-xs dropdown-toggle classify-inline-ebf01a20" type="button" data-toggle="dropdown" aria-expanded="false">
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
                </x-gs::table>
                <div class="classify-inline-801160c3">
                    {{ $nodes->appends(['parent' => $parentCode, 'mode' => $mode])->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Phase 2 & Phase 3 Reusable Modals -->
@include('gov-classification::adopt.partials.bulk-preview')
@include('gov-classification::discover.partials.collection-modal')

</div>
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
