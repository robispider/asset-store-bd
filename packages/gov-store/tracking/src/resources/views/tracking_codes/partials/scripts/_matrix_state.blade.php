<!-- Core Namespace, State, Actions & Calculations Engine -->
<script>
    (function() {
        // Initialize the global namespace immediately upon browser parsing
        window.GovStoreMatrix = window.GovStoreMatrix || {};

        window.GovStoreMatrix.state = {
            columns: [],
            rows: [],
            values: {},
            totals: {
                rows: {},
                columns: {},
                grand: 0
            },
            validation: {
                errors: [],
                warnings: [],
                invalidCells: {}
            }
        };

        function generateUuid() {
            return 'id-' + Math.random().toString(36).substring(2, 9);
        }

        // =============================================================
        // ACTIONS (Mutators)
        // =============================================================
        window.GovStoreMatrix.actions = {
            addColumn: function(catId, catName, econCode = '') {
                var colUuid = generateUuid();
                window.GovStoreMatrix.state.columns.push({
                    uuid: colUuid,
                    category_id: parseInt(catId),
                    name: catName,
                    economic_code: econCode
                });

                window.GovStoreMatrix.state.rows.forEach(function(row) {
                    window.GovStoreMatrix.state.values[row.uuid][colUuid] = 0;
                });

                window.GovStoreMatrix.renderer.renderStructure();
                window.GovStoreMatrix.refresh();
            },

            addRow: function(locId, locName) {
                var rowUuid = generateUuid();
                window.GovStoreMatrix.state.rows.push({
                    uuid: rowUuid,
                    location_id: parseInt(locId),
                    name: locName
                });

                window.GovStoreMatrix.state.values[rowUuid] = {};
                window.GovStoreMatrix.state.columns.forEach(function(col) {
                    window.GovStoreMatrix.state.values[rowUuid][col.uuid] = 0;
                });

                window.GovStoreMatrix.renderer.renderStructure();
                window.GovStoreMatrix.refresh();
            },

            removeColumn: function(colUuid) {
                window.GovStoreMatrix.state.columns = window.GovStoreMatrix.state.columns.filter(c => c.uuid !== colUuid);
                window.GovStoreMatrix.state.rows.forEach(function(row) {
                    delete window.GovStoreMatrix.state.values[row.uuid][colUuid];
                });

                window.GovStoreMatrix.renderer.renderStructure();
                window.GovStoreMatrix.refresh();
            },

            removeRow: function(rowUuid) {
                window.GovStoreMatrix.state.rows = window.GovStoreMatrix.state.rows.filter(r => r.uuid !== rowUuid);
                delete window.GovStoreMatrix.state.values[rowUuid];

                window.GovStoreMatrix.renderer.renderStructure();
                window.GovStoreMatrix.refresh();
            },

            moveColumn: function(colUuid, direction) {
                var cols = window.GovStoreMatrix.state.columns;
                var idx = cols.findIndex(c => c.uuid === colUuid);
                
                if (direction === 'left' && idx > 0) {
                    var temp = cols[idx];
                    cols[idx] = cols[idx - 1];
                    cols[idx - 1] = temp;
                } else if (direction === 'right' && idx < cols.length - 1) {
                    var temp = cols[idx];
                    cols[idx] = cols[idx + 1];
                    cols[idx + 1] = temp;
                }

                window.GovStoreMatrix.renderer.renderStructure();
                window.GovStoreMatrix.refresh();
            },

            moveRow: function(rowUuid, direction) {
                var rows = window.GovStoreMatrix.state.rows;
                var idx = rows.findIndex(r => r.uuid === rowUuid);

                if (direction === 'up' && idx > 0) {
                    var temp = rows[idx];
                    rows[idx] = rows[idx - 1];
                    rows[idx - 1] = temp;
                } else if (direction === 'down' && idx < rows.length - 1) {
                    var temp = rows[idx];
                    rows[idx] = rows[idx + 1];
                    rows[idx + 1] = temp;
                }

                window.GovStoreMatrix.renderer.renderStructure();
                window.GovStoreMatrix.refresh();
            },

            setQuantity: function(rowUuid, colUuid, qty) {
                if (window.GovStoreMatrix.state.values[rowUuid]) {
                    window.GovStoreMatrix.state.values[rowUuid][colUuid] = parseInt(qty) || 0;
                }
                window.GovStoreMatrix.refresh();
            },

            setEconomicCode: function(colUuid, code) {
                var col = window.GovStoreMatrix.state.columns.find(c => c.uuid === colUuid);
                if (col) {
                    col.economic_code = code;
                }
            }
        };

        // =============================================================
        // CALCULATIONS
        // =============================================================
        window.GovStoreMatrix.calculations = {
            computeTotals: function() {
                var state = window.GovStoreMatrix.state;
                var grand = 0;

                state.rows.forEach(function(row) {
                    var rowSum = 0;
                    state.columns.forEach(function(col) {
                        rowSum += parseInt(state.values[row.uuid][col.uuid]) || 0;
                    });
                    state.totals.rows[row.uuid] = rowSum;
                });

                state.columns.forEach(function(col) {
                    var colSum = 0;
                    state.rows.forEach(function(row) {
                        colSum += parseInt(state.values[row.uuid][col.uuid]) || 0;
                    });
                    state.totals.columns[col.uuid] = colSum;
                    grand += colSum;
                });

                state.totals.grand = grand;
            },

            validate: function() {
                var state = window.GovStoreMatrix.state;
                state.validation.errors = [];
                state.validation.warnings = [];
                state.validation.invalidCells = {};

                state.rows.forEach(function(row) {
                    state.columns.forEach(function(col) {
                        var val = parseInt(state.values[row.uuid][col.uuid]) || 0;
                        if (val < 0) {
                            state.validation.invalidCells[row.uuid + '-' + col.uuid] = true;
                            if (!state.validation.errors.includes('Negative quantities are not allowed.')) {
                                state.validation.errors.push('Negative quantities are not allowed.');
                            }
                        }
                    });
                });

                state.rows.forEach(function(row) {
                    if (state.totals.rows[row.uuid] === 0) {
                        if (!state.validation.warnings.includes('Some participating offices have zero items allocated.')) {
                            state.validation.warnings.push('Some participating offices have zero items allocated.');
                        }
                    }
                });
            }
        };

        // Refresh Coordinator
        window.GovStoreMatrix.refresh = function() {
            window.GovStoreMatrix.calculations.computeTotals();
            window.GovStoreMatrix.calculations.validate();
            window.GovStoreMatrix.renderer.renderTotals();
            window.GovStoreMatrix.renderer.renderValidation();
        };
    })();
</script>