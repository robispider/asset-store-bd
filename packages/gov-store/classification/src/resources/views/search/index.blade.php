@extends('layouts/default')

@section('title', __('classification::texts.search_title'))

@section('content')
<div class="classification-theme">
<div class="row">
    <div class="col-md-12">
        <!-- Main Explorer Header -->
        <div class="box box-solid bg-gray-light classify-inline-c5bb90ff">
            <div class="box-body">
                <h3 class="classify-inline-bc5af139"><i class="fas fa-search text-blue"></i> {{ __('classification::texts.search_header_title') }}</h3>
                <p class="text-muted classify-inline-648149ce">{{ __('classification::texts.search_header_desc') }}</p>
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
                    <div class="box-body classify-inline-cfb76d2b">
                        <!-- Search Box and Autocomplete Input -->
                        <div class="form-group classify-inline-2d98adf5">
                            <div class="input-group">
                                <span class="input-group-addon classify-inline-eec876ec"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="catalog-search-input" class="form-control input-lg" 
                                       placeholder="{{ __('classification::texts.search_placeholder_code_or_keyword') }}" autocomplete="off" autofocus>
                            </div>
                        </div>

                        <!-- Filters & Recent Search Chips -->
                        <div class="row classify-inline-b75fad00">
                            <div class="col-xs-12">
                                <!-- Search Filters/Chips -->
                                <div class="pull-left classify-inline-49e4866e">
                                    <label class="classify-inline-5b2b685a text-muted">
                                        <input type="checkbox" id="filter-unmapped" class="classify-inline-70eae362"> {{ __('classification::texts.search_filter_unmapped_only') }}
                                    </label>
                                    <label class="classify-inline-543bd8ac text-muted">
                                        <input type="checkbox" id="filter-commodities" checked class="classify-inline-70eae362"> {{ __('classification::texts.search_filter_commodities_only') }}
                                    </label>
                                </div>
                                
                                <!-- Recent Searches container (Local Storage) -->
                                <div class="pull-right classify-inline-4bc3bf9e" id="recent-searches-container">
                                    <span class="text-muted classify-inline-38e6816e">{{ __('classification::texts.search_recent_label') }}</span>
                                    <span id="recent-searches-chips"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Results List Container -->
                        <div id="catalog-results" class="classify-inline-f039a379">
                            <div class="text-center text-muted classify-inline-9cb0d6dd">
                                <i class="fas fa-search fa-3x classify-inline-5f0ae864"></i>
                                <h4>{{ __('classification::texts.search_begin_typing_title') }}</h4>
                                <p class="small">{{ __('classification::texts.search_begin_typing_desc') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Detail Workspace Panel (60% Width) -->
            <div class="col-md-7">
                <div class="box box-solid box-default classify-inline-4c10bdcf" id="detail-workspace-box">
                    <div class="box-body classify-inline-5bf81b71" id="detail-workspace-container">
                        <!-- Initial Empty State -->
                        <div class="text-center text-muted classify-inline-4c4c0fab">
                            <i class="fas fa-info-circle fa-4x classify-inline-77b06690"></i>
                            <h3>{{ __('classification::texts.search_no_item_selected') }}</h3>
                            <p class="lead">{{ __('classification::texts.search_no_item_desc') }}</p>
                        </div>
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
                <div>
                    <i class="fas fa-search fa-3x classify-inline-5f0ae864"></i>
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
        resultsContainer.html(`<div class="text-center classify-inline-73fc9c37"><i class="fas fa-spinner fa-spin fa-2x text-blue"></i></div>`);

        $.ajax({
            url: '{{ route("gov.catalog.search.universal.ajax") }}', // NEW ROUTE
            data: { q: query },
            success: function(res) {
                renderUniversalResults(res.results, query);
            }
        });
    }

    function renderUniversalResults(data, query) {
        if (data.collections.length === 0 && data.catalog.length === 0 && data.local.length === 0) {
            resultsContainer.html(`<div class="text-center text-muted classify-inline-73fc9c37">No matches found.</div>`);
            return;
        }

        let html = '';

        // Render Collections Group
        if (data.collections.length > 0) {
            html += `<h5 class="classify-inline-9c0e35ab">📚 Collections</h5><div class="list-group">`;
            data.collections.forEach(item => {
                html += `<a class="list-group-item classify-inline-c78b7504" href="/gov-store/operations/catalog/discover/collections/${item.id}">
                            <i class="${item.icon} text-muted classify-inline-ef97e545"></i> <strong>${highlightMatchText(item.text, query)}</strong>
                         </a>`;
            });
            html += `</div>`;
        }

        // Render Master Catalog Group
        if (data.catalog.length > 0) {
            html += `<h5 class="classify-inline-1991ea71">🌐 Official Catalog (UNSPSC)</h5><div class="list-group">`;
            data.catalog.forEach(item => {
                html += `<a class="list-group-item catalog-result-item classify-inline-1ed9afdb" href="#" data-code="${item.code}">
                            <small class="text-muted pull-right">${item.code}</small>
                            <strong>${highlightMatchText(item.text, query)}</strong>
                         </a>`;
            });
            html += `</div>`;
        }

        // Render Local Inventory Group
        if (data.local.length > 0) {
            html += `<h5 class="classify-inline-49116149">🏢 Existing Office Inventory</h5><div class="list-group">`;
            data.local.forEach(item => {
                html += `<a class="list-group-item classify-inline-15daa8ae" href="/gov-store/operations/catalog/my-catalog/${item.id}">
                            <span class="label label-default pull-right">${item.cat_type}</span>
                            <strong>${highlightMatchText(item.text, query)}</strong>
                         </a>`;
            });
            html += `</div>`;
        }

        resultsContainer.html(html);

        // Bind clicks for the Master Catalog items to load the right pane
        $('.catalog-result-item').on('click', function(e) {
            e.preventDefault();
            $('.list-group-item').removeClass('active');
            $(this).addClass('active');
            loadWorkspaceDetails($(this).data('code')); // Your existing function
        });
    }

    // Render Compact Left List Cards with Highlighted matches
    function renderResultsList(results, query) {
        activeIndex = -1; // Reset keyboard nav index on new search

        if (results.length === 0) {
            resultsContainer.html(`
                <div class="well text-center classify-inline-b45d9b35">
                    <h4 class="text-muted"><i class="fas fa-search-minus"></i> {{ __('classification::texts.search_no_matches_found') }}</h4>
                    <p class="small text-muted">{{ __('classification::texts.search_verify_spelling_filters') }}</p>
                </div>
            `);
            return;
        }

        let html = '<div class="list-group classify-inline-648149ce" id="results-list-group">';
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
            
            const breadcrumbHtml = `<div class="text-muted classify-inline-f33e2bfa">${breadcrumbCache[node.hid]}</div>`;
            const levelBadge = getLevelBadge(node.level);
            const mappingStatus = node.has_mapping 
                ? '<span class="text-success"><i class="fas fa-check-circle"></i> Mapped</span>' 
                : '<span class="text-muted"><i class="far fa-circle"></i> Unmapped</span>';

            html += `
                <a class="list-group-item catalog-result-item classify-inline-9974ea9b" href="#" data-code="${node.code}">
                    <h4 class="list-group-item-heading classify-inline-ffebe43c">
                        ${highlightedTitle} ${levelBadge}
                    </h4>
                    <p class="list-group-item-text text-muted classify-inline-93833fd7">
                        Code: <code>${highlightedCode}</code> <span class="classify-inline-446e6f7e">|</span> ${mappingStatus}
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
            
            $('.catalog-result-item').removeClass('active kbd-focused');
            $(this).addClass('active');

            activeIndex = $(this).index(); // Sync keyboard navigation to clicked card
            const code = $(this).data('code');
            loadWorkspaceDetails(code);
        });
    }

    // Load Right Detail Panel dynamically
    function loadWorkspaceDetails(code) {
        workspaceContainer.html(`
            <div class="text-center classify-inline-4c4c0fab">
                <i class="fas fa-sync-alt fa-spin fa-4x text-blue classify-inline-b75fad00"></i>
                <h4>{{ __('classification::texts.search_retrieving_metadata') }}</h4>
            </div>
        `);
        workspaceBox.addClass('catalog-workspace-active');

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
        let html = '<ul class="list-unstyled classify-inline-25f47a97">';
        
        // Render ancestor folders
        ancestors.forEach(function(ancestor, index) {
            if (ancestor.code === selectedCode) return;
            html += `
                <li class="catalog-search-path-item" style="--catalog-indent: ${index * 15}px;">
                    <i class="far fa-folder-open text-yellow classify-inline-a36b1709"></i> ${ancestor.title_en}
                </li>
            `;
        });

        const activeIndent = ancestors.length > 0 ? (ancestors.length - 1) * 15 : 0;

        // Render sibling nodes
        siblings.forEach(function(sibling) {
            html += `
                <li class="catalog-search-path-item--active" style="--catalog-indent: ${activeIndent}px;">
                    <i class="far fa-file classify-inline-a36b1709"></i> ${sibling.title_en}
                </li>
            `;
        });
        
        html += '</ul>';

        // Render and highlight active selection node
        const selectedNode = ancestors.find(a => a.code === selectedCode);
        if (selectedNode) {
            const activeNodeHtml = `
                <div class="catalog-search-current-item" style="--catalog-indent: ${activeIndent}px;">
                    <strong class="text-blue"><i class="fas fa-file-alt classify-inline-9df5a4e3"></i> ${selectedNode.title_en}</strong>
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
        return text.replace(regex, '<mark class="classify-inline-e5f83b82">$1</mark>');
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
                <div>
                    <i class="fas fa-search fa-3x classify-inline-5f0ae864"></i>
                    <h4>Begin Typing to Search</h4>
                    <p class="small">Enter a classification title or official UNSPSC code to inspect.</p>
                </div>
            `);
            workspaceContainer.html(`
                <div class="text-center text-muted classify-inline-4c4c0fab">
                    <i class="fas fa-info-circle fa-4x classify-inline-77b06690"></i>
                    <h3>No Item Selected</h3>
                    <p class="lead">Select a classification from the search results on the left to inspect its definitions, synonyms, and mapping status.</p>
                </div>
            `);
            workspaceBox.removeClass('catalog-workspace-active');
        }
    });

    function updateKeyboardSelection(items) {
        items.removeClass('kbd-focused');
        
        if (activeIndex >= 0) {
            const activeItem = items.eq(activeIndex);
            activeItem.addClass('kbd-focused');
            
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
            html += `<span class="label label-info recent-chip classify-inline-35b0ce68">${query}</span>`;
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
            case 1: return '<span class="label label-default pull-right classify-inline-3100222b">Segment</span>';
            case 2: return '<span class="label label-default pull-right classify-inline-3100222b">Family</span>';
            case 3: return '<span class="label label-default pull-right classify-inline-3100222b">Class</span>';
            case 4: return '<span class="label label-primary pull-right classify-inline-3100222b">Commodity</span>';
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
