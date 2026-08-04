@extends('layouts/default')
@section('title', 'Catalog Explorer')

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

            <!-- Action Bar (Appears when checkboxes are selected) -->
            <div id="bulk-action-bar" class="box-body" style="background-color: #fffaeb; border-bottom: 1px solid #f4ecd8; display: none; padding: 10px 15px;">
                <strong id="selected-count" class="text-orange" style="font-size: 16px; margin-right: 15px;">0 Items Selected</strong>
                <button type="button" class="btn btn-warning btn-sm" onclick="executeExplorerBulkAdoption()">
                    <i class="fas fa-rocket"></i> Bulk Adopt Selected
                </button>
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
                            <th>Code</th>
                            <th>Title</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Go Up (If not at root) -->
                        @if($parentCode && count($breadcrumbs) > 0)
                            @php
                                // Get the parent of the current node to go up one level
                                $upCode = count($breadcrumbs) > 1 ? $breadcrumbs[count($breadcrumbs)-2]->code : null;
                            @endphp
                            <tr>
                                <td></td>
                                <td class="text-center"><i class="fas fa-level-up-alt text-muted"></i></td>
                                <td colspan="3">
                                    <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $upCode]) }}" class="text-muted" style="font-style: italic;">... Go up one level</a>
                                </td>
                            </tr>
                        @endif

                        <!-- Nodes -->
                        @foreach($nodes as $node)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">
                                    @if(!$node->is_folder && !$node->is_adopted)
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
                                        <a href="{{ route('gov.catalog.discover.explorer', ['parent' => $node->code]) }}" style="font-weight: bold; font-size: 15px;">{{ $node->title_en }}</a>
                                    @else
                                        <span style="font-weight: normal; font-size: 14px;">{{ $node->title_en }}</span>
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    @if($node->is_folder)
                                        <span class="label label-default" style="font-weight: normal;">Folder</span>
                                    @elseif($node->is_adopted)
                                        <span class="label label-success"><i class="fas fa-check"></i> Adopted</span>
                                    @else
                                        <span class="label label-default" style="background-color: #ddd; color: #777;">Not Adopted</span>
                                    @endif
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

<!-- Include the Phase 2 Bulk Adoption Modal -->
@include('gov-classification::adopt.partials.bulk-preview')

@endsection

@section('moar_scripts')
@parent
<script>
$(document).ready(function() {
    // Select All Checkbox
    $('#select-all').on('change', function() {
        $('.node-checkbox').prop('checked', $(this).prop('checked'));
        updateActionBar();
    });

    // Individual Checkboxes
    $('.node-checkbox').on('change', function() {
        updateActionBar();
    });

    function updateActionBar() {
        let count = $('.node-checkbox:checked').length;
        if (count > 0) {
            $('#selected-count').text(count + (count === 1 ? ' Item Selected' : ' Items Selected'));
            $('#bulk-action-bar').slideDown('fast');
        } else {
            $('#bulk-action-bar').slideUp('fast');
            $('#select-all').prop('checked', false);
        }
    }
});

// Trigger Phase 2 Modal
function executeExplorerBulkAdoption() {
    let codes = [];
    $('.node-checkbox:checked').each(function() {
        codes.push($(this).val());
    });
    
    if (codes.length > 0) {
        triggerBulkAdoption(codes); // From bulk-preview.blade.php
    }
}
</script>
@endsection