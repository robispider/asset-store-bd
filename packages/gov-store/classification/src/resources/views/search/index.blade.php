@extends('layouts/default')

@section('title', __('classification::texts.search_title'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <!-- Main Explorer Header -->
        <div class="box box-solid bg-gray-light" style="border-bottom: 2px solid #ddd; margin-bottom: 20px;">
            <div class="box-body">
                <h3 style="margin-top: 5px; font-weight: bold;"><i class="fas fa-search text-blue"></i> {{ __('classification::texts.search_header_title') }}</h3>
                <p class="text-muted" style="margin-bottom: 0;">{{ __('classification::texts.search_header_desc') }}</p>
            </div>
        </div>

        <!-- Master-Detail Split Container -->
        <div class="row">
            <!-- LEFT PANEL: Search & Results List (40% Width) -->
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('classification::texts.search_col_results') }}</h3>
                    </div>
                    <div class="box-body" style="padding: 15px;">
                        <!-- Search Box and Autocomplete Input -->
                        <div class="form-group" style="margin-bottom: 10px;">
                            <div class="input-group">
                                <span class="input-group-addon" style="background-color: #fff;"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="catalog-search-input" class="form-control input-lg" 
                                       placeholder="{{ __('classification::texts.search_placeholder_code_or_keyword') }}" autocomplete="off" autofocus>
                            </div>
                        </div>

                        <!-- Filters & Recent Search Chips -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-xs-12">
                                <!-- Search Filters/Chips -->
                                <div class="pull-left" style="margin-top: 5px;">
                                    <label style="margin-right: 15px; font-weight: normal; cursor: pointer; font-size: 12px;" class="text-muted">
                                        <input type="checkbox" id="filter-unmapped" style="margin-right: 5px; vertical-align: middle; position: relative; top: -1px;"> {{ __('classification::texts.search_filter_unmapped_only') }}
                                    </label>
                                    <label style="font-weight: normal; cursor: pointer; font-size: 12px;" class="text-muted">
                                        <input type="checkbox" id="filter-commodities" checked style="margin-right: 5px; vertical-align: middle; position: relative; top: -1px;"> {{ __('classification::texts.search_filter_commodities_only') }}
                                    </label>
                                </div>
                                
                                <!-- Recent Searches container (Local Storage) -->
                                <div class="pull-right" id="recent-searches-container" style="display: none; margin-top: 5px;">
                                    <span class="text-muted" style="margin-right: 5px; font-size: 11px;">{{ __('classification::texts.search_recent_label') }}</span>
                                    <span id="recent-searches-chips"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Results List Container -->
                        <div id="catalog-results" style="max-height: 550px; overflow-y: auto; padding-right: 5px;">
                            <div class="text-center text-muted" style="padding: 60px 20px;">
                                <i class="fas fa-search fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                                <h4>{{ __('classification::texts.search_begin_typing_title') }}</h4>
                                <p class="small">{{ __('classification::texts.search_begin_typing_desc') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Detail Workspace Panel (60% Width) -->
            <div class="col-md-7">
                <div class="box box-solid box-default" id="detail-workspace-box" style="min-height: 720px; border-left: 4px solid #d2d6de;">
                    <div class="box-body" id="detail-workspace-container" style="padding: 30px 20px;">
                        <!-- Initial Empty State -->
                        <div class="text-center text-muted" style="padding-top: 200px;">
                            <i class="fas fa-info-circle fa-4x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                            <h3>{{ __('classification::texts.search_no_item_selected') }}</h3>
                            <p class="lead">{{ __('classification::texts.search_no_item_desc') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
function bootstrapCatalogExplorer() {
    const searchInput = $('#catalog-search-input');
    const resultsContainer = $('#catalog-results');
    const workspaceContainer = $('#detail-workspace-container');
    const workspaceBox = $('#detail-workspace-box');

    let activeIndex = -1; // Keyboard navigation index tracker

    // Debounce helper to prevent excessive SQL parsing during active typing
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Capture keyup events for search
    searchInput.on('keyup', debounce(function(e) {
        // Prevent search firing on navigation keys
        if ([38, 40, 13, 27].includes(e.keyCode)) return;

        const query = $(this).val().trim();
        
        if (query.length < 2) {
            resultsContainer.html(`
                <div class="text-center text-muted" style="padding: 60px 20px;">
                    <i class="fas fa-search fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                    <h4>Begin Typing to Search</h4>
                    <p class="small">Enter a classification title or official UNSPSC code to inspect.</p>
                </div>
            `);
            return;
        }

        executeSearch(query);
    }, 250));

    // Handle Checkbox / Chip clicks to instantly update search results
    $('#filter-unmapped, #filter-commodities').on('change', function() {
        const query = searchInput.val().trim();
        if (query.length >= 2) {
            executeSearch(query);
        }
    });

    // Execute AJAX Search
    function executeSearch(query) {
        resultsContainer.html(`
            <div class="text-center" style="padding: 80px 20px;">
                <i class="fas fa-spinner fa-spin fa-3x text-blue" style="margin-bottom: 15px;"></i>
                <p class="text-muted">{{ __('classification::texts.search_searching_catalog_records') }}</p>
            </div>
        `);

        // Save search keyword in LocalStorage list
        saveRecentSearch(query);

        $.ajax({
            url: '{{ route("gov.catalog.search.ajax") }}',
            data: { 
                q: query, 
                scheme: 'UNSPSC',
                unmapped: $('#filter-unmapped').is(':checked') ? 'true' : 'false',
                commodities: $('#filter-commodities').is(':checked') ? 'true' : 'false'
            },
            success: function(response) {
                renderResultsList(response.results, query);
            }
        });
    }

    // Render Compact Left List Cards with Highlighted matches
    function renderResultsList(results, query) {
        activeIndex = -1; // Reset keyboard nav index on new search

        if (results.length === 0) {
            resultsContainer.html(`
                <div class="well text-center" style="background-color: #fff; border-style: dashed; padding: 40px 10px;">
                    <h4 class="text-muted"><i class="fas fa-search-minus"></i> {{ __('classification::texts.search_no_matches_found') }}</h4>
                    <p class="small text-muted">{{ __('classification::texts.search_verify_spelling_filters') }}</p>
                </div>
            `);
            return;
        }

        let html = '<div class="list-group" style="margin-bottom: 0;" id="results-list-group">';
        let breadcrumbCache = {}; 

        results.forEach(function(node) {
            const rawTitle = node.text.replace(/^\[.*?\]\s*/, ''); 
            
            // Apply fast O(1) text highlighting to titles and codes
            const highlightedTitle = highlightMatchText(rawTitle, query);
            const highlightedCode = highlightMatchText(node.code, query);

            if (breadcrumbCache[node.hid] === undefined) {
                const parts = node.hid.split('/').filter(Boolean);
                breadcrumbCache[node.hid] = parts.length > 2 ? parts.slice(0, -1).join(' > ') : 'Top Level';
            }
            
            const breadcrumbHtml = `<div class="text-muted" style="font-size: 11px; margin-top: 4px;">${breadcrumbCache[node.hid]}</div>`;
            const levelBadge = getLevelBadge(node.level);
            const mappingStatus = node.has_mapping 
                ? '<span class="text-success"><i class="fas fa-check-circle"></i> Mapped</span>' 
                : '<span class="text-muted"><i class="far fa-circle"></i> Unmapped</span>';

            html += `
                <a href="#" class="list-group-item catalog-result-item" data-code="${node.code}" style="border-left: 4px solid #d2d6de; margin-bottom: 6px; padding: 12px 15px; transition: background 0.1s;">
                    <h4 class="list-group-item-heading" style="font-size: 15px; font-weight: bold; line-height: 1.4; margin-bottom: 6px;">
                        ${highlightedTitle} ${levelBadge}
                    </h4>
                    <p class="list-group-item-text text-muted" style="font-size: 12px; margin-bottom: 0;">
                        Code: <code>${highlightedCode}</code> <span style="margin: 0 5px;">|</span> ${mappingStatus}
                    </p>
                    ${breadcrumbHtml}
                </a>
            `;
        });

        html += '</div>';
        resultsContainer.html(html);

        // Click Handler for Result Cards
        $('.catalog-result-item').on('click', function(e) {
            e.preventDefault();
            
            $('.catalog-result-item').css('border-left-color', '#d2d6de').removeClass('active kbd-focused').css('background-color', '');
            $(this).css('border-left-color', '#3c8dbc').addClass('active');

            activeIndex = $(this).index(); // Sync keyboard navigation to clicked card
            const code = $(this).data('code');
            loadWorkspaceDetails(code);
        });
    }

    // Load Right Detail Panel dynamically
    function loadWorkspaceDetails(code) {
        workspaceContainer.html(`
            <div class="text-center" style="padding-top: 200px;">
                <i class="fas fa-sync-alt fa-spin fa-4x text-blue" style="margin-bottom: 20px;"></i>
                <h4>{{ __('classification::texts.search_retrieving_metadata') }}</h4>
            </div>
        `);
        workspaceBox.css('border-left-color', '#3c8dbc');

        workspaceContainer.load('{{ route("gov.catalog.mapping") }}?code=' + code, function() {
            $.ajax({
                url: '{{ route("gov.catalog.context.ajax") }}',
                data: { code: code },
                success: function(response) {
                    renderContextTree(response.ancestors, response.siblings, code);
                }
            });
        });
    }

    // Render the mini "explorer" tree on the right panel
    function renderContextTree(ancestors, siblings, selectedCode) {
        let html = '<ul class="list-unstyled" style="padding-left: 5px; font-size: 13.5px; line-height: 1.8;">';
        
        // Render ancestor folders
        ancestors.forEach(function(ancestor, index) {
            if (ancestor.code === selectedCode) return;
            html += `
                <li style="padding-left: ${index * 15}px; margin-bottom: 3px; color: #666;">
                    <i class="far fa-folder-open text-yellow" style="margin-right: 6px;"></i> ${ancestor.title_en}
                </li>
            `;
        });

        const activeIndent = ancestors.length > 0 ? (ancestors.length - 1) * 15 : 0;

        // Render sibling nodes
        siblings.forEach(function(sibling) {
            html += `
                <li style="padding-left: ${activeIndent}px; margin-bottom: 3px; color: #888;">
                    <i class="far fa-file" style="margin-right: 6px;"></i> ${sibling.title_en}
                </li>
            `;
        });
        
        html += '</ul>';

        // Render and highlight active selection node
        const selectedNode = ancestors.find(a => a.code === selectedCode);
        if (selectedNode) {
            const activeNodeHtml = `
                <div style="background-color: #f0f7ff; padding: 6px 12px; border-left: 3px solid #3c8dbc; margin-left: ${activeIndent}px; margin-top: 5px; margin-bottom: 5px; border-radius: 0 4px 4px 0;">
                    <strong class="text-blue"><i class="fas fa-file-alt" style="margin-right: 6px; color: #3c8dbc;"></i> ${selectedNode.title_en}</strong>
                </div>
            `;
            html = html.replace('</ul>', activeNodeHtml + '</ul>');
        }

        $('#context-hierarchy-tree').html(html);
    }

    // Keyup highlighter using raw HTML wrapper matching
    function highlightMatchText(text, query) {
        if (!query) return text;
        const escapedQuery = query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'); // Sanitize regex inputs
        const regex = new RegExp(`(${escapedQuery})`, 'gi');
        return text.replace(regex, '<mark style="background-color: #fcf8e3; padding: .1em .2em; border-radius: 2px;">$1</mark>');
    }

    // ==============================================
    // KEYBOARD NAVIGATION SUBSYSTEM
    // ==============================================
    $(document).off('keydown').on('keydown', function(e) {
        const resultItems = $('.catalog-result-item');
        if (resultItems.length === 0) return;

        if (e.keyCode === 40) { // Arrow Down
            e.preventDefault();
            activeIndex = (activeIndex + 1) % resultItems.length;
            updateKeyboardSelection(resultItems);
        } 
        else if (e.keyCode === 38) { // Arrow Up
            e.preventDefault();
            activeIndex = (activeIndex - 1 + resultItems.length) % resultItems.length;
            updateKeyboardSelection(resultItems);
        } 
        else if (e.keyCode === 13) { // Enter Key
            if (activeIndex >= 0 && activeIndex < resultItems.length) {
                e.preventDefault();
                resultItems.eq(activeIndex).click();
            }
        } 
        else if (e.keyCode === 27) { // Escape Key (Resets Search Focus)
            e.preventDefault();
            searchInput.val('').focus();
            resultsContainer.html(`
                <div class="text-center text-muted" style="padding: 60px 20px;">
                    <i class="fas fa-search fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                    <h4>Begin Typing to Search</h4>
                    <p class="small">Enter a classification title or official UNSPSC code to inspect.</p>
                </div>
            `);
            workspaceContainer.html(`
                <div class="text-center text-muted" style="padding-top: 200px;">
                    <i class="fas fa-info-circle fa-4x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No Item Selected</h3>
                    <p class="lead">Select a classification from the search results on the left to inspect its definitions, synonyms, and mapping status.</p>
                </div>
            `);
            workspaceBox.css('border-left-color', '#d2d6de');
        }
    });

    function updateKeyboardSelection(items) {
        items.removeClass('kbd-focused').css('background-color', '');
        
        if (activeIndex >= 0) {
            const activeItem = items.eq(activeIndex);
            activeItem.addClass('kbd-focused').css('background-color', '#f4f4f4');
            
            // Auto-scroll the left panel to keep the keyboard selection visible
            const container = resultsContainer;
            const scrollPos = activeItem.position().top + container.scrollTop() - container.position().top - 100;
            container.animate({ scrollTop: scrollPos }, 50);
        }
    }

    // ==============================================
    // LOCALSTORAGE RECENT SEARCH CHIPS
    // ==============================================
    function saveRecentSearch(query) {
        if (!query || query.length < 2) return;
        let recents = JSON.parse(localStorage.getItem('gov_catalog_recents') || '[]');
        
        recents = recents.filter(item => item !== query); // Deduplicate
        recents.unshift(query); // Push to front
        recents = recents.slice(0, 4); // Limit to top 4

        localStorage.setItem('gov_catalog_recents', JSON.stringify(recents));
        renderRecentChips();
    }

    function renderRecentChips() {
        const recents = JSON.parse(localStorage.getItem('gov_catalog_recents') || '[]');
        if (recents.length === 0) {
            $('#recent-searches-container').hide();
            return;
        }

        let html = '';
        recents.forEach(function(query) {
            html += `<span class="label label-info recent-chip" style="cursor: pointer; margin-right: 5px; font-weight: normal; padding: 4px 8px; font-size: 11px;">${query}</span>`;
        });

        $('#recent-searches-chips').html(html);
        $('#recent-searches-container').show();

        // Click handler to re-fire searches from chips
        $('.recent-chip').off('click').on('click', function() {
            searchInput.val($(this).text());
            executeSearch($(this).text());
        });
    }

    function getLevelBadge(level) {
        switch(parseInt(level)) {
            case 1: return '<span class="label label-default pull-right" style="font-size: 10px; font-weight: normal; padding: 3px 6px;">Segment</span>';
            case 2: return '<span class="label label-default pull-right" style="font-size: 10px; font-weight: normal; padding: 3px 6px;">Family</span>';
            case 3: return '<span class="label label-default pull-right" style="font-size: 10px; font-weight: normal; padding: 3px 6px;">Class</span>';
            case 4: return '<span class="label label-primary pull-right" style="font-size: 10px; font-weight: normal; padding: 3px 6px;">Commodity</span>';
            default: return '';
        }
    }

    // Initialize Recent Searches on Load
    renderRecentChips();
}

// ----------------------------------------------------
// BULLETPROOF JQUERY BOOTSTRAPPER
// ----------------------------------------------------
if (typeof jQuery === 'undefined') {
    window.addEventListener('load', function() {
        if (typeof jQuery !== 'undefined') {
            bootstrapCatalogExplorer();
        } else {
            console.error("Catalog Explorer Error: jQuery failed to load.");
        }
    });
} else {
    bootstrapCatalogExplorer();
}
</script>