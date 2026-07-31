<!-- Decentralized DOM Renderer Engine -->
<script>
    (function() {
        window.GovStoreMatrix = window.GovStoreMatrix || {};

        window.GovStoreMatrix.renderer = {
            
            renderStructure: function() {
                var state = window.GovStoreMatrix.state;
                var $table = $('#matrix-grid-table');

                $table.find('thead').empty();
                $table.find('tbody').empty();
                $table.find('tfoot').empty();

                // 1. Header Row (With Draggable Handles)
                var headerHtml = '<tr id="matrix-header-row">';
                headerHtml += '<th width="320">Office / Warehouse Location</th>';
                
                state.columns.forEach(function(col) {
                    headerHtml += `
                        <th class="matrix-cat-header" draggable="true" data-col-uuid="${col.uuid}" style="text-align: center; background-color: #f8fafc; cursor: grab;">
                            <span class="header-name">
                                <i class="fa fa-ellipsis-v text-muted" style="margin-right: 5px; cursor: move;" title="Drag to reorder column"></i>
                                ${col.name} <i class="fa fa-caret-down text-muted"></i>
                            </span>
                            <input type="hidden" name="matrix_categories[]" value="${col.category_id}">
                            <br>
                            <small class="text-muted">
                                <input type="text" class="form-control input-sm text-center matrix-econ-input-action" data-col-uuid="${col.uuid}" value="${col.economic_code}" placeholder="Econ Code" style="margin-top: 5px; width: 100px; display:inline-block; height: 26px; padding: 2px 6px;">
                            </small>
                        </th>
                    `;
                });

                headerHtml += '<th width="150" class="gs-inline-spawner" id="btn-spawn-column" style="vertical-align: middle;"><i class="fa fa-plus"></i> Category</th>';
                headerHtml += '<th width="120" id="col-row-total-header" style="background-color: #f1f5f9; font-weight: bold; border-right: 2px solid #cbd5e1; line-height: 24px;">ROW TOTAL</th>';
                headerHtml += '<th width="80" style="text-align: right; background-color: #f8fafc;">Action</th>';
                headerHtml += '</tr>';
                $table.find('thead').append(headerHtml);

                // 2. Body Rows (With Draggable Handles)
                var bodyHtml = '';
                state.rows.forEach(function(row, rIndex) {
                    bodyHtml += `<tr data-row-index="${rIndex}" class="matrix-row-container" data-row-uuid="${row.uuid}">`;
                    bodyHtml += `
                        <td class="matrix-loc-header" draggable="true" data-row-uuid="${row.uuid}" style="cursor: grab;">
                            <span class="header-name">
                                <i class="fa fa-ellipsis-v text-muted" style="margin-right: 7px; cursor: move;" title="Drag to reorder row"></i>
                                <strong>${row.name}</strong> <i class="fa fa-caret-down text-muted"></i>
                            </span>
                            <input type="hidden" name="matrix_locations[${rIndex}]" value="${row.location_id}">
                        </td>
                    `;

                    state.columns.forEach(function(col, cIndex) {
                        var val = state.values[row.uuid][col.uuid] || 0;
                        bodyHtml += `
                            <td class="text-center cell-${col.category_id}">
                                <input type="number" class="gs-cell-input matrix-cell" data-row-uuid="${row.uuid}" data-col-uuid="${col.uuid}" data-row="${rIndex}" data-col="${cIndex}" value="${val}" min="0" required>
                            </td>
                        `;
                    });

                    bodyHtml += `
                        <td class="row-total-cell text-center text-bold" data-row-uuid="${row.uuid}" style="background-color: #f1f5f9; font-weight: bold; border-right: 2px solid #cbd5e1; line-height: 36px;">0</td>
                        <td class="text-right cell-actions" style="background-color: #f8fafc; padding: 5px 12px; line-height: 26px;">
                            <button type="button" class="btn btn-xs btn-danger remove-matrix-row-action" data-row-uuid="${row.uuid}"><i class="fa fa-trash"></i></button>
                        </td>
                    `;
                    bodyHtml += '</tr>';
                });
                $table.find('tbody').append(bodyHtml);

                // 3. Spawner Footers
                var footerHtml = '';
                footerHtml += '<tr id="matrix-spawner-row">';
                footerHtml += '<td class="gs-inline-spawner" id="btn-spawn-row" style="text-align: left;"><i class="fa fa-plus"></i> Select Office...</td>';
                
                state.columns.forEach(function(col) {
                    footerHtml += `<td class="spacer-${col.category_id}" style="background-color: #f8fafc; border-bottom: 1px solid #cbd5e1;"></td>`;
                });
                
                footerHtml += '<td id="matrix-spawner-spacer"></td>';
                footerHtml += '<td style="background-color: #f8fafc; border-right: 2px solid #cbd5e1;"></td>';
                footerHtml += '<td></td>';
                footerHtml += '</tr>';

                // Grand Totals Footers
                var grandTotal = 0;
                footerHtml += '<tr id="matrix-footer-row">';
                footerHtml += '<td>TOTAL ALLOCATIONS</td>';
                
                state.columns.forEach(function(col) {
                    footerHtml += `<td id="total-cat-${col.category_id}" class="col-total-cell text-center text-bold" data-col-uuid="${col.uuid}" style="background-color: #f1f5f9; font-weight: bold; border-top: 2px solid #cbd5e1;">0</td>`;
                });

                footerHtml += `<td id="matrix-grand-total" style="background-color: #f1f5f9; font-weight: bold; border-right: 2px solid #cbd5e1;">0</td>`;
                footerHtml += '<td style="background-color: #f8fafc;"></td>';
                footerHtml += '</tr>';
                $table.find('tfoot').append(footerHtml);

                $(document).trigger('matrix:rendered');
            },

            renderTotals: function() {
                var state = window.GovStoreMatrix.state;

                state.rows.forEach(function(row) {
                    $(`.row-total-cell[data-row-uuid="${row.uuid}"]`).text(state.totals.rows[row.uuid]);
                });

                state.columns.forEach(function(col) {
                    $(`.col-total-cell[data-col-uuid="${col.uuid}"]`).text(state.totals.columns[col.uuid]);
                });

                $('#matrix-grand-total').text(state.totals.grand);
            },

            renderValidation: function() {
                var state = window.GovStoreMatrix.state;
                var $table = $('#matrix-grid-table');
                var $statusBar = $('#matrix-status-text');

                $('.matrix-cell').css('background-color', '');
                $('.matrix-row-container').css('border-left', '');

                state.rows.forEach(function(row) {
                    state.columns.forEach(function(col) {
                        if (state.validation.invalidCells[row.uuid + '-' + col.uuid]) {
                            $(`.matrix-cell[data-row-uuid="${row.uuid}"][data-col-uuid="${col.uuid}"]`).css('background-color', '#fee2e2');
                        }
                    });

                    if (state.totals.rows[row.uuid] === 0) {
                        $(`.matrix-row-container[data-row-uuid="${row.uuid}"]`).css('border-left', '4px solid #f59e0b');
                    }
                });

                var html = '';
                if (state.validation.errors.length > 0) {
                    html = `<span class="text-red"><i class="fa fa-times-circle"></i> <strong>Spreadsheet Error:</strong> ${state.validation.errors.join(' ')} (Saving blocked)</span>`;
                    $table.css('border-color', '#ef4444');
                    $('button[type="submit"]').prop('disabled', true);
                } else if (state.validation.warnings.length > 0) {
                    html = `<span class="text-yellow"><i class="fa fa-warning"></i> <strong>Operational Warning:</strong> ${state.validation.warnings.join(' ')} (Draft saving allowed)</span>`;
                    $table.css('border-color', '#f59e0b');
                    $('button[type="submit"]').prop('disabled', false);
                } else {
                    html = `<span class="text-green"><i class="fa fa-check-circle"></i> <strong>Spreadsheet Status:</strong> Healthy (All allocations conform to planning rules)</span>`;
                    $table.css('border-color', '#cbd5e1');
                    $('button[type="submit"]').prop('disabled', false);
                }

                $statusBar.html(html);
            }
        };
    })();
</script>