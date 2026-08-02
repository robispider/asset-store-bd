<!-- State-Driven Inline Select2 Spawning Engine -->
<script>
    (function() {
        function initMatrixSpawner() {
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initMatrixSpawner, 50);
                return;
            }

            window.jQuery(function($) {
                // Only serialize the Categories, as Locations are now searched via dynamic AJAX
                const availableCategories = @json($categories->map(fn($c) => ['id' => $c->id, 'text' => $c->name]));

                // =============================================================
                // 1. STATE-DRIVEN COLUMN (CATEGORY) SPAWNER
                // =============================================================
                $('#matrix-grid-table').on('click', '#btn-spawn-column', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return; // Already open

                    // Compile active category IDs directly from the central state
                    var activeCategoryIds = window.GovStoreMatrix.state.columns.map(col => parseInt(col.category_id));

                    // Filter out already active categories dynamically
                    var filteredCategories = availableCategories.filter(function(cat) {
                        return !activeCategoryIds.includes(parseInt(cat.id));
                    });

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
                        var catId = e.params.data.id;
                        var catName = e.params.data.text;

                        // Mutates state directly. No manual HTML string appending.
                        window.GovStoreMatrix.actions.addColumn(catId, catName);
                        resetColumnSpawner($spawner);
                    });

                    $select.on('select2:close', function() {
                        setTimeout(function() {
                            resetColumnSpawner($spawner);
                        }, 100);
                    });
                });

                function resetColumnSpawner($spawner) {
                    $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Category');
                }

                // =============================================================
                // 2. STATE-DRIVEN ROW (LOCATION) SPAWNER (AJAX ONLY)
                // =============================================================
                $('#matrix-grid-table').on('click', '#btn-spawn-row', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return; // Already open

                    $spawner.addClass('gs-inline-select').html(`
                        <select id="inline-location-select" style="width: 100%;">
                            <option value="">-- Search --</option>
                        </select>
                    `);

                    var $select = $('#inline-location-select');
                    $select.select2({
                        placeholder: 'Search Offices...',
                        minimumInputLength: 2,
                        dropdownParent: $('body'),
                        ajax: {
                            url: "{{ route('gov.tracking.api.search-offices') }}",
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                var geoOverride = $('input[name="geo_override"]:checked').val() || 'Inherit';
                                var geoAreaId = $('select[name="geo_area_id"]').val() || '';
                                var participantOverride = $('input[name="participant_override"]:checked').val() || 'Inherit';

                                return {
                                    q: params.term,
                                    initiative_id: "{{ $initiative->id }}",
                                    geo_override: geoOverride,
                                    geo_area_id: geoAreaId,
                                    participant_override: participantOverride
                                };
                            },
                            processResults: function (data) {
                                var activeLocationIds = window.GovStoreMatrix.state.rows.map(r => parseInt(r.location_id));
                                var filteredResults = data.results.filter(function(loc) {
                                    return !activeLocationIds.includes(parseInt(loc.id));
                                });
                                return { results: filteredResults };
                            }
                        }
                    }).select2('open');

                    $select.on('select2:select', function(e) {
                        var locId = e.params.data.id;
                        var locName = e.params.data.text;

                        // Mutates state directly. No manual HTML string appending.
                        window.GovStoreMatrix.actions.addRow(locId, locName);
                        resetRowSpawner($spawner);
                    });

                    $select.on('select2:close', function() {
                        setTimeout(function() {
                            resetRowSpawner($spawner);
                        }, 100);
                    });
                });

                function resetRowSpawner($spawner) {
                    $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Select Office...');
                }
            });
        }

        initMatrixSpawner();
    })();
</script>