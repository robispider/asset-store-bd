<!-- State-Driven Inline Select2 Spawning Engine -->
<script>
    (function() {
        function initMatrixSpawner() {
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initMatrixSpawner, 50);
                return;
            }

            window.jQuery(function($) {
                const availableCategories = @json($categories->map(fn($c) => ['id' => $c->id, 'text' => $c->name]));
                const availableLocations = @json($locations->map(fn($l) => ['id' => $l->id, 'text' => $l->name]));

                // Column Spawner Trigger
                $('#matrix-grid-table').on('click', '#btn-spawn-column', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return;

                    var activeCategoryIds = window.GovStoreMatrix.state.columns.map(col => parseInt(col.category_id));
                    var filteredCategories = availableCategories.filter(cat => !activeCategoryIds.includes(parseInt(cat.id)));

                    if (filteredCategories.length === 0) {
                        alert('All available item categories have already been added to the planning matrix.');
                        return;
                    }

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

                    $select.on('select2:select', function(e) {
                        window.GovStoreMatrix.actions.addColumn(e.params.data.id, e.params.data.text);
                    });

                    $select.on('select2:close', function() {
                        setTimeout(() => $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Category'), 100);
                    });
                });

                // Row Spawner Trigger
                $('#matrix-grid-table').on('click', '#btn-spawn-row', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return;

                    var activeLocationIds = window.GovStoreMatrix.state.rows.map(row => parseInt(row.location_id));
                    var filteredLocations = availableLocations.filter(loc => !activeLocationIds.includes(parseInt(loc.id)));

                    if (filteredLocations.length === 0) {
                        alert('All available participating offices have already been added to the planning matrix.');
                        return;
                    }

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

                    $select.on('select2:select', function(e) {
                        window.GovStoreMatrix.actions.addRow(e.params.data.id, e.params.data.text);
                    });

                    $select.on('select2:close', function() {
                        setTimeout(() => $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Select Office...'), 100);
                    });
                });
            });
        }

        initMatrixSpawner();
    })();
</script>