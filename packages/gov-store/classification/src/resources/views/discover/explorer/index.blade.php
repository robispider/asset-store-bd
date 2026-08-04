@extends('layouts/default')
@section('title', 'Catalog Explorer')

@php
    $canManageCollections = auth()->user() && (auth()->user()->isSuperUser() || auth()->user()->hasAccess('admin'));
@endphp

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            
            <!-- Breadcrumbs -->
            <div class="box-header with-border" style="background-color: #f4f8fa;">
                <h3 class="box-title" style="font-size: 16px;">
                    <a href="{{ route('gov.catalog.discover.explorer') }}" class="text-blue"><i class="fas fa-home"></i> Master Catalog</a>
                    @foreach($breadcrumbs as $crumb)
                        <span class="text-muted" style="margin: 0 5px;">/</span>
                        @if(!$loop->last)
                            <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $crumb->code]) }}" class="text-blue">{{ $crumb->title_en }}</a>
                        @else
                            <strong>{{ $crumb->title_en }}</strong>
                        @endif
                    @endforeach
                </h3>
            </div>

            <!-- Bulk Action Bar -->
            <div id="bulk-action-bar" class="box-body" style="background-color: #fffaeb; border-bottom: 1px solid #f4ecd8; display: none; padding: 10px 15px;">
                <strong id="selected-count" class="text-orange" style="font-size: 15px; margin-right: 15px;">0 Items Selected</strong>
                <button type="button" class="btn btn-warning btn-sm" onclick="executeExplorerBulkAdoption()" style="margin-right: 5px;">
                    <i class="fas fa-rocket"></i> Bulk Adopt Selected
                </button>
                
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
                            <th>Title</th>
                            <th class="text-center" style="width: 150px;">Status</th>
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
                                    <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $upCode]) }}" class="text-muted" style="font-style: italic;">... Go up one level</a>
                                </td>
                            </tr>
                        @endif

                        <!-- Nodes -->
                        @foreach($nodes as $node)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">
                                    @if(!$node->is_adopted)
                                        <input type="checkbox" class="node-checkbox" value="{{ $node->code }}">
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle; font-size: 18px;">
                                    @if($node->is_folder)
                                        <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code]) }}"><i class="fas fa-folder text-yellow"></i></a>
                                    @else
                                        <i class="fas fa-file-alt text-muted"></i>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;"><code>{{ $node->code }}</code></td>
                                <td style="vertical-align: middle;">
                                    @if($node->is_folder)
                                        <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code]) }}" style="font-weight: bold; font-size: 14px;">{{ $node->title_en }}</a>
                                    @else
                                        <span style="font-weight: normal; font-size: 14px;">{{ $node->title_en }}</span>
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    @if($node->is_folder)
                                        <span class="label label-default" style="font-weight: normal; background-color: #f39c12 !important;">Folder</span>
                                    @elseif($node->is_adopted)
                                        <span class="label label-success"><i class="fas fa-check"></i> Adopted</span>
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
                                                    <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code]) }}">
                                                        <i class="fas fa-folder-open text-yellow"></i> Browse
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="#" onclick="event.preventDefault(); triggerBulkAdoption(['{{ $node->code }}'])">
                                                        <i class="fas fa-rocket text-green"></i> Adopt All Commodities
                                                    </a>
                                                </li>
                                                @if($canManageCollections)
                                                    <li>
                                                        <a href="#" onclick="event.preventDefault(); triggerAddToCollection(['{{ $node->code }}'])">
                                                            <i class="fas fa-boxes text-purple"></i> Add All to Collection
                                                        </a>
                                                    </li>
                                                @endif
                                            @else
                                                <li>
                                                    <a href="{{ route('gov.catalog.mapping.show', $node->code) }}">
                                                        <i class="fas fa-eye text-blue"></i> View Metadata
                                                    </a>
                                                </li>
                                                @if(!$node->is_adopted)
                                                    <li>
                                                        <a href="#" onclick="event.preventDefault(); triggerBulkAdoption(['{{ $node->code }}'])">
                                                            <i class="fas fa-rocket text-green"></i> Adopt Category
                                                        </a>
                                                    </li>
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
                    {{ $nodes->appends(['parent' => $parentCode])->links() }}
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

// Trigger Bulk Adoption Engine (Recursive)
function executeExplorerBulkAdoption() {
    let codes = [];
    jQuery('.node-checkbox:checked').each(function() {
        codes.push(jQuery(this).val());
    });
    
    if (codes.length > 0) {
        triggerBulkAdoption(codes); // From bulk-preview.blade.php
    }
}

// Trigger Bulk Collection Association (Recursive)
function executeExplorerBulkAddToCollection() {
    let codes = [];
    jQuery('.node-checkbox:checked').each(function() {
        codes.push(jQuery(this).val());
    });
    
    if (codes.length > 0) {
        triggerAddToCollection(codes); // From collection-modal.blade.php
    }
}
</script>
@endsection