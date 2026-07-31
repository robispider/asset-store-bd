<!-- Central Spreadsheet Boot Engine (Calculations Restored) -->
<script>
    (function() {
        function initMatrixBoot() {
            if (typeof window.jQuery === 'undefined' || typeof window.GovStoreMatrix.renderer === 'undefined') {
                setTimeout(initMatrixBoot, 50);
                return;
            }

            window.jQuery(function($) {
                const savedCategories = @json(isset($trackingCode) && $trackingCode->specificity_level === '3_MATRIX' ? $trackingCode->targets->map(fn($t) => ['id' => $t->category_id, 'name' => $t->category->name, 'econ' => $t->economic_code]) : []);
                const savedLocations = @json(isset($trackingCode) && $trackingCode->specificity_level === '3_MATRIX' ? $trackingCode->targets->flatMap->allocations->map(fn($a) => ['id' => $a->location_id, 'name' => $a->location->name])->unique('id')->values() : []);
                const savedValues = @json($savedMatrixValues ?? []);

                function generateUuid() {
                    return 'id-' + Math.random().toString(36).substring(2, 9);
                }

                // =============================================================
                // 1. EXECUTE BOOT PRE-POPULATION
                // =============================================================
                if (savedCategories.length > 0) {
                    savedCategories.forEach(function(cat) {
                        window.GovStoreMatrix.state.columns.push({
                            uuid: generateUuid(),
                            category_id: parseInt(cat.id),
                            name: cat.name,
                            economic_code: cat.econ ?? ''
                        });
                    });

                    savedLocations.forEach(function(loc) {
                        var rowUuid = generateUuid();
                        window.GovStoreMatrix.state.rows.push({
                            uuid: rowUuid,
                            location_id: parseInt(loc.id),
                            name: loc.name
                        });

                        window.GovStoreMatrix.state.values[rowUuid] = {};
                        window.GovStoreMatrix.state.columns.forEach(function(col) {
                            var prefilledVal = (savedValues[loc.id] && savedValues[loc.id][col.category_id]) 
                                ? savedValues[loc.id][col.category_id] 
                                : 0;
                            
                            window.GovStoreMatrix.state.values[rowUuid][col.uuid] = parseInt(prefilledVal);
                        });
                    });
                }

                // Unconditional Initial Render
                window.GovStoreMatrix.renderer.renderStructure();
                window.GovStoreMatrix.refresh();

                // =============================================================
                // 2. CONSOLIDATED EVENT BINDINGS (Single Source of Truth)
                // =============================================================
                
                // FIXED: Bind real-time cell typing inputs. 
                // Updates the central state and triggers a targeted DOM refresh on every keystroke
                $('#matrix-grid-table').on('input', '.matrix-cell', function() {
                    var rUuid = $(this).attr('data-row-uuid');
                    var cUuid = $(this).attr('data-col-uuid');
                    var val = parseInt($(this).val()) || 0;

                    if (window.GovStoreMatrix && window.GovStoreMatrix.state.values[rUuid]) {
                        window.GovStoreMatrix.state.values[rUuid][cUuid] = val;
                    }

                    // Synchronously update total nodes and validation banners without focus loss
                    window.GovStoreMatrix.refresh();
                });

                // Bind economic code field changes
                $('#matrix-grid-table').on('change', '.matrix-econ-input-action', function() {
                    var cUuid = $(this).attr('data-col-uuid');
                    window.GovStoreMatrix.actions.setEconomicCode(cUuid, $(this).val());
                });

                // Bind delete row action buttons
                $('#matrix-grid-table').on('click', '.remove-matrix-row-action', function() {
                    var rUuid = $(this).attr('data-row-uuid');
                    window.GovStoreMatrix.actions.removeRow(rUuid);
                });
            });
        }

        initMatrixBoot();
    })();
</script>