@extends('layouts/default-blade')

@section('title')
    {{ __('admin/general/global_catalog_search') }}
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin/dashboard') }}">{{ __('general.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('admin/general/global_catalog_search') }}</li>
            </ol>
        </nav>

        <!-- Search Box -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fas fa-globe text-blue"></i>
                    {{ __('admin/general/global_catalog') }}
                </h3>
            </div>
            <div class="box-body">
                <!-- Search Input -->
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" id="catalog-search-input" class="form-control input-lg" 
                                   placeholder="{{ __('admin/general.search_catalog_placeholder') }}"
                                   value="{{ $query ?? '' }}">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-primary btn-lg" id="btn-search-catalog">
                                    <i class="fas fa-search"></i> {{ __('general.search') }}
                                </button>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-right">
                        <select id="scheme-selector" class="form-control">
                            <option value="UNSPSC" selected>UNSPSC</option>
                            <option value="CGA">CGA</option>
                        </select>
                    </div>
                </div>

                <!-- Results -->
                <div id="catalog-results">
                    @if(isset($query) && $results->count() > 0)
                        <h4>{{ __('admin/general.search_results', ['count' => $results->count(), 'query' => $query]) }}</h4>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('general.code') }}</th>
                                    <th>{{ __('general.title_en') }}</th>
                                    <th>{{ __('admin/general.level') }}</th>
                                    <th>{{ __('admin/general.snipe_mapping') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $node)
                                <tr>
                                    <td><code>[{{ $node->code }}]</code></td>
                                    <td>{{ $node->title_en }}</td>
                                    <td>
                                        @switch($node->level)
                                            @case(1) {{ __('admin/general.segment') }}
                                            @case(2) {{ __('admin/general.family') }}
                                            @case(3) {{ __('admin/general.class') }}
                                            @case(4) {{ __('admin/general.commodity') }}
                                        @endswitch
                                    </td>
                                    <td>
                                        @if($node->snipeMapping)
                                            <span class="label label-success">
                                                {{ $node->snipeMapping->category ? $node->snipeMapping->category->name : 'N/A' }}
                                            </span>
                                        @else
                                            <span class="label label-warning">{{ __('admin/general.not_mapped') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('gov.catalog.mapping.show', ['code' => $node->code, 'scheme' => $node->scheme]) }}" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-link"></i> {{ __('admin/general.manage_mapping') }}
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif(isset($query))
                        <p class="text-muted">{{ __('admin/general.no_results', ['query' => $query]) }}</p>
                    @else
                        <div class="text-center" style="padding: 40px;">
                            <i class="fas fa-search fa-3x text-muted"></i>
                            <p class="text-muted">{{ __('admin/general.search_catalog_hint') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Browse Tree -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fas fa-sitemap"></i>
                    {{ __('admin/general.browse_catalog') }}
                </h3>
            </div>
            <div class="box-body">
                <div id="catalog-tree" style="max-height: 500px; overflow-y: auto;">
                    <!-- Tree loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('js')
<script>
$(document).ready(function() {
    const schemeSelector = $('#scheme-selector');
    const searchInput = $('#catalog-search-input');

    // Search handler
    $('#btn-search-catalog').on('click', function() {
        const query = searchInput.val().trim();
        if (!query) return;

        $.ajax({
            url: '{{ route("gov.catalog.search.ajax") }}',
            data: { q: query, scheme: schemeSelector.val() },
            success: function(response) {
                renderResults(response.results);
            }
        });
    });

    // Enter key search
    searchInput.on('keypress', function(e) {
        if (e.which === 13) {
            $('#btn-search-catalog').click();
        }
    });

    // Scheme change
    schemeSelector.on('change', function() {
        loadTree();
    });

    // Render search results
    function renderResults(results) {
        let html = '<table class="table table-striped table-hover">';
        html += '<thead><tr><th>{{ __("general.code") }}</th><th>{{ __("general.title_en") }}</th><th>{{ __("admin/general.level") }}</th><th></th></tr></thead><tbody>';

        results.forEach(function(node) {
            html += `<tr>
                <td><code>[${node.code}]</code></td>
                <td>${node.text}</td>
                <td>Level ${node.level}</td>
                <td>
                    <a href="{{ route('gov.catalog.mapping.show') }}?code=${node.code}&scheme=${node.scheme}" 
                       class="btn btn-sm btn-info">
                        <i class="fas fa-link"></i> {{ __("admin/general.manage_mapping") }}
                    </a>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        $('#catalog-results').html(html);
    }

    // Load tree via AJAX
    function loadTree(parentCode = null) {
        $.ajax({
            url: '{{ route("gov.catalog.browse.ajax") }}',
            data: { parent_code: parentCode, scheme: schemeSelector.val() },
            success: function(response) {
                renderTree(response.results);
            }
        });
    }

    // Render tree
    function renderTree(nodes) {
        let html = '<ul class="list-unstyled">';
        nodes.forEach(function(node) {
            const hasChildren = node.children ? ' <i class="fas fa-chevron-right"></i>' : '';
            html += `<li data-code="${node.code}">
                <div style="padding: 5px 0;">
                    <span class="toggle-icon" data-code="${node.code}" style="cursor: pointer; margin-right: 10px;">${hasChildren}</span>
                    <span class="badge badge-primary">[${node.code}]</span>
                    <span>${node.text}</span>
                </div>
                ${node.children ? '<ul class="list-unstyled" style="display: none; margin-left: 20px;"></ul>' : ''}
            </li>`;
        });
        html += '</ul>';
        $('#catalog-tree').html(html);

        // Attach toggle handlers
        $('.toggle-icon').on('click', function() {
            const code = $(this).data('code');
            const childList = $(this).closest('li').find('> ul');
            
            if (childList.is(':visible')) {
                childList.slideUp();
                $(this).find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            } else {
                loadTree(code).done(function(response) {
                    childList.html(renderTree(response.results)).slideDown();
                    $(this).find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                });
            }
        });
    }

    // Initial tree load
    loadTree();
});
</script>
@endpush
