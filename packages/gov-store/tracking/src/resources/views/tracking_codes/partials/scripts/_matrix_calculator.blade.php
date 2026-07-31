<!-- Reactive Spreadsheet Calculation Engine (State-Synchronized Version) -->
<script>
    (function() {
        function initCalculatorEngine() {
            // Verify jQuery is loaded safely before executing
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initCalculatorEngine, 50);
                return;
            }

            window.jQuery(function($) {
                // 1. Listen for typing events inside cell inputs
                $('#matrix-grid-table').on('input', '.matrix-cell', function() {
                    // Update state value immediately on typing
                    var rUuid = $(this).attr('data-row-uuid');
                    var cUuid = $(this).attr('data-col-uuid');
                    var val = parseInt($(this).val()) || 0;

                    if (window.GovStoreMatrix && window.GovStoreMatrix.values[rUuid]) {
                        window.GovStoreMatrix.values[rUuid][cUuid] = val;
                    }

                    // Run non-destructive recalculations (does not rebuild DOM, preserving cursor focus)
                    calculateMatrix();
                });

                // 2. Listen for structural changes (Columns/Rows added or removed)
                $(document).on('matrix:structure_changed', function() {
                    calculateMatrix();
                });

                /**
                 * Orchestrates the state-synchronized recalculations.
                 */
                function calculateMatrix() {
                    if (!window.GovStoreMatrix) return;
                    
                    calculateHorizontalRowTotals();
                    calculateVerticalColumnTotals();
                    calculateGrandTotal();
                }

                /**
                 * Horizontal Vector: Sums all allocated quantities across the active row state.
                 */
                function calculateHorizontalRowTotals() {
                    $('#matrix-grid-body tr.matrix-row').each(function() {
                        var rUuid = $(this).attr('data-row-uuid');
                        var rowSum = 0;

                        if (window.GovStoreMatrix.values[rUuid]) {
                            window.GovStoreMatrix.columns.forEach(function(col) {
                                var val = parseInt(window.GovStoreMatrix.values[rUuid][col.uuid]) || 0;
                                rowSum += val;
                            });
                        }

                        // Write to the row's static total cell
                        $(this).find('.row-total-cell').text(rowSum);
                    });
                }

                /**
                 * Vertical Vector: Sums all location allocations for each category column state.
                 */
                function calculateVerticalColumnTotals() {
                    window.GovStoreMatrix.columns.forEach(function(col) {
                        var colSum = 0;
                        
                        window.GovStoreMatrix.rows.forEach(function(row) {
                            if (window.GovStoreMatrix.values[row.uuid]) {
                                var val = parseInt(window.GovStoreMatrix.values[row.uuid][col.uuid]) || 0;
                                colSum += val;
                            }
                        });

                        // Write to the column's footer total cell
                        $(`#total-cat-${col.category_id}`).text(colSum);
                    });
                }

                /**
                 * Grand Vector: Accumulates all active cell values inside the state.
                 */
                function calculateGrandTotal() {
                    var grandSum = 0;

                    window.GovStoreMatrix.rows.forEach(function(row) {
                        if (window.GovStoreMatrix.values[row.uuid]) {
                            window.GovStoreMatrix.columns.forEach(function(col) {
                                var val = parseInt(window.GovStoreMatrix.values[row.uuid][col.uuid]) || 0;
                                grandSum += val;
                            });
                        }
                    });

                    $('#matrix-grand-total').text(grandSum);
                }

                // Initial run on script boot to populate preloaded values cleanly
                calculateMatrix();
            });
        }

        initCalculatorEngine();
    })();
</script>