<!-- Dynamic Select2 Filter Engine -->
<script>
    (function() {
        function initMatrixFilters() {
            // Verify jQuery and Select2 are fully loaded before executing
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initMatrixFilters, 50);
                return;
            }

            window.jQuery(function($) {
                // Load server-passed complete master collections as local static arrays
                const availableCategories = @json($categories->map(fn($c) => ['id' => $c->id, 'text' => $c->name]));
                const availableLocations = @json($locations->map(fn($l) => ['id' => $l->id, 'text' => $l->name]));

                // =============================================================
                // A. INLINE COLUMN (CATEGORY) FILTER & SPAWN
                // =============================================================
                $('#matrix-grid-table').on('click', '#btn-spawn-column', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return; // Already open

                    // Compile active category IDs currently mapped in the central state
                    var activeCategoryIds = window.GovStoreMatrix.columns.map(col => parseInt(col.category_id));

                    // Filter out already active categories dynamically
                    var filteredCategories = availableCategories.filter(function(cat) {
                        return !activeCategoryIds.includes(parseInt(cat.id));
                    });

                    if (filteredCategories.length === 0) {
                        alert('All available item categories have already been added to the planning matrix.');
                        return;
                    }

                    // Convert static header cell into an inline Select2 container
                    $spawner.addClass('gs-inline-select').html(`
                        <select id="inline-category-select" style="width: 100%;">
                            <option value="">-- Search --</option>
                        </select>
                    `);

                    var $select = $('#inline-category-select');
                    $select.select2({
                        data: filteredCategories,
                        minimumResultsForSearch: 0
                    }).select2('open');

                    // Bind selection commit
                    $select.on('select2:select', function(e) {
                        var catId = e.params.data.id;
                        var catName = e.params.data.text;

                        // Mutate state and trigger automated re-render
                        window.GovStoreMatrix.addColumn(catId, catName);
                        $(document).trigger('matrix:structure_changed');
                    });

                    $select.on('select2:close', function() {
                        setTimeout(function() {
                            $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Category');
                        }, 100);
                    });
                });

                // =============================================================
                // B. INLINE ROW (LOCATION) FILTER & SPAWN
                // =============================================================
                $('#matrix-grid-table').on('click', '#btn-spawn-row', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return; // Already open

                    // Compile active location IDs currently mapped in the central state
                    var activeLocationIds = window.GovStoreMatrix.rows.map(row => parseInt(row.location_id));

                    // Filter out already active office locations dynamically
                    var filteredLocations = availableLocations.filter(function(loc) {
                        return !activeLocationIds.includes(parseInt(loc.id));
                    });

                    if (filteredLocations.length === 0) {
                        alert('All available participating offices have already been added to the planning matrix.');
                        return;
                    }

                    // Convert static cell into an inline Select2 container
                    $spawner.addClass('gs-inline-select').html(`
                        <select id="inline-location-select" style="width: 100%;">
                            <option value="">-- Search --</option>
                        </select>
                    `);

                    var $select = $('#inline-location-select');
                    $select.select2({
                        data: filteredLocations,
                        minimumResultsForSearch: 0
                    }).select2('open');

                    // Bind selection commit
                    $select.on('select2:select', function(e) {
                        var locId = e.params.data.id;
                        var locName = e.params.data.text;

                        // Mutate state and trigger automated re-render
                        window.GovStoreMatrix.addRow(locId, locName);
                        $(document).trigger('matrix:structure_changed');
                    });

                    $select.on('select2:close', function() {
                        setTimeout(function() {
                            $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Select Office...');
                        }, 100);
                    });
                });
            });
        }

        initMatrixFilters();
    })();
</script>