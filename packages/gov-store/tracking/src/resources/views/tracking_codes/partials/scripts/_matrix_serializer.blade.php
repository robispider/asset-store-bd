<!-- Form Serialization Compiler (Deduplication & Timing Gap Fixed) -->
<script>
    (function() {
        function initSerializerEngine() {
            // Verify jQuery is fully loaded before executing
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initSerializerEngine, 50);
                return;
            }

            window.jQuery(function($) {
                var $table = $('#matrix-grid-table');
                var $form = $table.closest('form');

                // Intercept the master form submission
                $form.on('submit', function() {
                    var $container = $('#matrix-hidden-inputs');
                    $container.empty(); // Clear previously compiled inputs

                    var specificity = $('input[name="specificity_level"]:checked').val();
                    if (specificity !== '3_MATRIX') {
                        return; // Only compile matrix inputs if Level 3 (Spreadsheet) is active
                    }

                    var state = window.GovStoreMatrix.state;

                    // ==============================================================
                    // 1. DEDUPLICATE COLUMNS (Fixes Duplicate Entry DB Crashes)
                    // ==============================================================
                    var uniqueCategories = {};
                    
                    state.columns.forEach(function(col) {
                        var catId = col.category_id;
                        if (!uniqueCategories[catId]) {
                            uniqueCategories[catId] = {
                                category_id: catId,
                                economic_code: col.economic_code || '',
                                mapped_uuids: [] // Store UUIDs to accumulate values properly
                            };
                        }
                        // Accumulate multiple UUIDs if user accidentally created duplicate columns visually
                        uniqueCategories[catId].mapped_uuids.push(col.uuid);
                    });

                    // 2. Serialize Unique Columns and Economic Codes
                    Object.values(uniqueCategories).forEach(function(cat) {
                        $container.append(`<input type="hidden" name="matrix_categories[]" value="${cat.category_id}">`);
                        
                        if (cat.economic_code) {
                            $container.append(`<input type="hidden" name="matrix_economic_codes[${cat.category_id}]" value="${cat.economic_code}">`);
                        }
                    });

                    // 3. Serialize Rows and Accumulated 2D Cell Quantities
                    state.rows.forEach(function(row, rIndex) {
                        $container.append(`<input type="hidden" name="matrix_locations[${rIndex}]" value="${row.location_id}">`);

                        Object.values(uniqueCategories).forEach(function(cat) {
                            var accumulatedCellVal = 0;
                            
                            // Accumulate cell quantities across all visual columns matching this category
                            cat.mapped_uuids.forEach(function(cUuid) {
                                accumulatedCellVal += parseInt(state.values[row.uuid][cUuid]) || 0;
                            });
                            
                            $container.append(`<input type="hidden" name="matrix_values[${rIndex}][${cat.category_id}]" value="${accumulatedCellVal}">`);
                        });
                    });
                });
            });
        }

        initSerializerEngine();
    })();
</script>