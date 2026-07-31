<!-- HTML Overlays for Context Menus -->
<div id="col-context-menu" class="gs-context-menu">
    <ul>
        <li id="menu-opt-col-change"><i class="fa fa-pencil"></i> Change Category</li>
        <li id="menu-opt-col-left"><i class="fa fa-arrow-left"></i> Move Column Left</li>
        <li id="menu-opt-col-right"><i class="fa fa-arrow-right"></i> Move Column Right</li>
        <li id="menu-opt-col-delete" class="text-red" style="border-top: 1px solid #e2e8f0; margin-top: 5px; padding-top: 8px;"><i class="fa fa-trash"></i> Delete Column</li>
    </ul>
</div>

<div id="row-context-menu" class="gs-context-menu">
    <ul>
        <li id="menu-opt-row-change"><i class="fa fa-pencil"></i> Change Office</li>
        <li id="menu-opt-row-up"><i class="fa fa-arrow-up"></i> Move Row Up</li>
        <li id="menu-opt-row-down"><i class="fa fa-arrow-down"></i> Move Row Down</li>
        <li id="menu-opt-row-delete" class="text-red" style="border-top: 1px solid #e2e8f0; margin-top: 5px; padding-top: 8px;"><i class="fa fa-trash"></i> Delete Row</li>
    </ul>
</div>

<!-- Spreadsheet Context Menus Engine -->
<script>
    (function() {
        function initMatrixMenusEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initMatrixMenusEngine, 50);
                return;
            }

            window.jQuery(function($) {
                // Append menu overlays directly to the body to prevent absolute positioning distortion
                $('body').append($('#col-context-menu')).append($('#row-context-menu'));

                var activeColUuid = null;
                var activeRowUuid = null;

                const availableCategories = @json($categories->map(fn($c) => ['id' => $c->id, 'text' => $c->name]));
                const availableLocations = @json($locations->map(fn($l) => ['id' => $l->id, 'text' => $l->name]));

                // Close menus on clicking outside, scrolling, or changing specificity
                $(document).on('click scroll', function(e) {
                    if (!$(e.target).closest('.gs-context-menu, .header-name').length) {
                        $('.gs-context-menu').hide();
                    }
                });

                $('.gs-grid-container').on('scroll', function() {
                    $('.gs-context-menu').hide();
                });

                // =============================================================
                // A. COLUMN HEADER TRIGGERS (Category dropdown options)
                // =============================================================
                $('#matrix-grid-table').on('click', '.matrix-cat-header .header-name', function(e) {
                    e.stopPropagation();
                    $('.gs-context-menu').hide();

                    var $header = $(this).closest('th');
                    activeColUuid = $header.attr('data-col-uuid');

                    var offset = $(this).offset();
                    $('#col-context-menu').css({
                        top: offset.top + $(this).height() + 10,
                        left: offset.left
                    }).show();
                });

                $('#menu-opt-col-left').on('click', function() {
                    window.GovStoreMatrix.actions.moveColumn(activeColUuid, 'left');
                });

                $('#menu-opt-col-right').on('click', function() {
                    window.GovStoreMatrix.actions.moveColumn(activeColUuid, 'right');
                });

                $('#menu-opt-col-delete').on('click', function() {
                    var col = window.GovStoreMatrix.state.columns.find(c => c.uuid === activeColUuid);
                    if (col && confirm(`Delete Column '${col.name}'? This will completely clear all allocated quantities for this item category.`)) {
                        window.GovStoreMatrix.actions.removeColumn(activeColUuid);
                    }
                });

                $('#menu-opt-col-change').on('click', function() {
                    $('.gs-context-menu').hide();
                    var $header = $(`.matrix-cat-header[data-col-uuid="${activeColUuid}"]`);
                    var col = window.GovStoreMatrix.state.columns.find(c => c.uuid === activeColUuid);

                    if ($header.length && col) {
                        $header.addClass('gs-inline-select').html(`
                            <select id="inline-category-change-select" style="width: 100%;">
                                <option value="">-- Search --</option>
                            </select>
                        `);

                        var activeCategoryIds = window.GovStoreMatrix.state.columns.map(c => parseInt(c.category_id));
                        var filteredCategories = availableCategories.filter(cat => {
                            return parseInt(cat.id) === col.category_id || !activeCategoryIds.includes(parseInt(cat.id));
                        });

                        var $select = $('#inline-category-change-select');
                        $select.select2({
                            data: filteredCategories,
                            minimumResultsForSearch: 0
                        }).select2('open');

                        $select.on('select2:select', function(e) {
                            col.category_id = parseInt(e.params.data.id);
                            col.name = e.params.data.text;

                            window.GovStoreMatrix.renderer.renderStructure();
                            window.GovStoreMatrix.refresh();
                        });

                        $select.on('select2:close', function() {
                            setTimeout(() => window.GovStoreMatrix.renderer.renderStructure(), 100);
                        });
                    }
                });

                // =============================================================
                // B. ROW HEADER TRIGGERS (Office Location dropdown options)
                // =============================================================
                $('#matrix-grid-table').on('click', '.matrix-loc-header .header-name', function(e) {
                    e.stopPropagation();
                    $('.gs-context-menu').hide();

                    var $header = $(this).closest('td');
                    activeRowUuid = $header.attr('data-row-uuid');

                    var offset = $(this).offset();
                    $('#row-context-menu').css({
                        top: offset.top + $(this).height() + 10,
                        left: offset.left
                    }).show();
                });

                $('#menu-opt-row-up').on('click', function() {
                    window.GovStoreMatrix.actions.moveRow(activeRowUuid, 'up');
                });

                $('#menu-opt-row-down').on('click', function() {
                    window.GovStoreMatrix.actions.moveRow(activeRowUuid, 'down');
                });

                $('#menu-opt-row-delete').on('click', function() {
                    var row = window.GovStoreMatrix.state.rows.find(r => r.uuid === activeRowUuid);
                    if (row && confirm(`Decommission Office '${row.name}'? This will completely clear all allocated quantities for this warehouse.`)) {
                        window.GovStoreMatrix.actions.removeRow(activeRowUuid);
                    }
                });

                $('#menu-opt-row-change').on('click', function() {
                    $('.gs-context-menu').hide();
                    var $header = $(`.matrix-loc-header[data-row-uuid="${activeRowUuid}"]`);
                    var row = window.GovStoreMatrix.state.rows.find(r => r.uuid === activeRowUuid);

                    if ($header.length && row) {
                        $header.addClass('gs-inline-select').html(`
                            <select id="inline-location-change-select" style="width: 100%;">
                                <option value="">-- Search --</option>
                            </select>
                        `);

                        var activeLocationIds = window.GovStoreMatrix.state.rows.map(r => parseInt(r.location_id));
                        var filteredLocations = availableLocations.filter(loc => {
                            return parseInt(loc.id) === row.location_id || !activeLocationIds.includes(parseInt(loc.id));
                        });

                        var $select = $('#inline-location-change-select');
                        $select.select2({
                            data: filteredLocations,
                            minimumResultsForSearch: 0
                        }).select2('open');

                        $select.on('select2:select', function(e) {
                            row.location_id = parseInt(e.params.data.id);
                            row.name = e.params.data.text;

                            window.GovStoreMatrix.renderer.renderStructure();
                            window.GovStoreMatrix.refresh();
                        });

                        $select.on('select2:close', function() {
                            setTimeout(() => window.GovStoreMatrix.renderer.renderStructure(), 100);
                        });
                    }
                });
            });
        }

        initMatrixMenusEngine();
    })();
</script>