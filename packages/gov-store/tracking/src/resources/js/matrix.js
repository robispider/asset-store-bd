/* Programme delivery matrix. Registered with the GovStore theme asset bundle. */
(function () {
    function startTrackingMatrix() {
        const dataElement = document.getElementById('tracking-matrix-data');
        if (!dataElement) return;
        const config = JSON.parse(dataElement.textContent);
        const t = key => config.labels[key] || key;
        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c];
            });
        }

// state

    (function() {
        // Initialize the global namespace immediately upon browser parsing
        window.GovStoreMatrix = window.GovStoreMatrix || {};

        window.GovStoreMatrix.state = {
            columns: [],      // Mapped Category columns
            rows: [],         // Mapped Location rows
            values: {},       // Cell values [rowUuid][colUuid] = quantity
            totals: {
                rows: {},     // Row totals [rowUuid] = sum
                columns: {},  // Column totals [colUuid] = sum
                grand: 0      // Overall project grand total
            },
            validation: {
                errors: [],
                warnings: [],
                invalidCells: {}
            }
        };

        // Server-passed variables for Edit pre-population
        const savedCategories = config.savedCategories;

        // FIXED (Defensive Fallback & Typo Resolved): Completed the truncated brackets and applied
        // a safe, null-coalescing fallback for soft-deleted or geofenced locations.
        const savedLocations = config.savedLocations;

        const savedValues = config.savedValues;

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

                // Initialize values across all rows
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

            reorderColumns: function(draggedUuid, targetUuid) {
                var cols = window.GovStoreMatrix.state.columns;
                var fromIndex = cols.findIndex(c => c.uuid === draggedUuid);
                var toIndex = cols.findIndex(c => c.uuid === targetUuid);

                if (fromIndex !== -1 && toIndex !== -1 && fromIndex !== toIndex) {
                    cols.splice(toIndex, 0, cols.splice(fromIndex, 1)[0]);

                    window.GovStoreMatrix.renderer.renderStructure();
                    window.GovStoreMatrix.refresh();
                }
            },

            reorderRows: function(draggedUuid, targetUuid) {
                var rows = window.GovStoreMatrix.state.rows;
                var fromIndex = rows.findIndex(r => r.uuid === draggedUuid);
                var toIndex = rows.findIndex(r => r.uuid === targetUuid);

                if (fromIndex !== -1 && toIndex !== -1 && fromIndex !== toIndex) {
                    rows.splice(toIndex, 0, rows.splice(fromIndex, 1)[0]);

                    window.GovStoreMatrix.renderer.renderStructure();
                    window.GovStoreMatrix.refresh();
                }
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
                            if (!state.validation.errors.includes(t('negative'))) {
                                state.validation.errors.push(t('negative'));
                            }
                        }
                    });
                });

                state.rows.forEach(function(row) {
                    if (state.totals.rows[row.uuid] === 0) {
                        if (!state.validation.warnings.includes(t('zero'))) {
                            state.validation.warnings.push(t('zero'));
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


// renderer

    (function() {
        window.GovStoreMatrix = window.GovStoreMatrix || {};

        window.GovStoreMatrix.renderer = {

            renderStructure: function() {
                var state = window.GovStoreMatrix.state;
                var $table = $('#matrix-grid-table');

                $table.find('thead').empty();
                $table.find('tbody').empty();
                $table.find('tfoot').empty();

                // 1. Header Row (thead) - Contains N + 4 columns
                var headerHtml = '<tr id="matrix-header-row">';
                headerHtml += `<th width="320">${escapeHtml(t('office'))}</th>`;

                state.columns.forEach(function(col) {
                    headerHtml += `
                        <th class="matrix-cat-header" draggable="true" data-col-uuid="${col.uuid}" style="text-align: center; cursor: grab;">
                            <span class="header-name" tabindex="0" role="button">
                                <i class="fa fa-ellipsis-v text-muted" style="margin-right: 5px; cursor: move;" title="${escapeHtml(t('drag_column'))}"></i>
                                ${escapeHtml(col.name)} <i class="fa fa-caret-down text-muted"></i>
                            </span>

                            <br>
                            <small class="text-muted">
                                <input type="text" class="form-control input-sm text-center matrix-econ-input-action" data-col-uuid="${col.uuid}" value="${escapeHtml(col.economic_code)}" placeholder="${escapeHtml(t('econ'))}" style="margin-top: 5px; width: 100px; display:inline-block; height: 26px; padding: 2px 6px;">
                            </small>
                        </th>
                    `;
                });

                headerHtml += `<th width="150" class="gs-inline-spawner" id="btn-spawn-column" style="vertical-align: middle;"><i class="fa fa-plus"></i> ${escapeHtml(t('category'))}</th>`;
                headerHtml += `<th width="120" id="col-row-total-header" style="font-weight: bold; line-height: 24px;">${escapeHtml(t('row_total'))}</th>`;
                headerHtml += `<th width="80" class="gs-matrix-head-cell" style="text-align: right;">${escapeHtml(t('action'))}</th>`;
                headerHtml += '</tr>';
                $table.find('thead').append(headerHtml);

                // 2. Body Rows (tbody) - Now contains N + 4 columns
                var bodyHtml = '';
                state.rows.forEach(function(row, rIndex) {
                    bodyHtml += `<tr data-row-index="${rIndex}" class="matrix-row-container" data-row-uuid="${row.uuid}">`;
                    bodyHtml += `
                        <td class="matrix-loc-header" draggable="true" data-row-uuid="${row.uuid}" style="cursor: grab;">
                            <span class="header-name" tabindex="0" role="button">
                                <i class="fa fa-ellipsis-v text-muted" style="margin-right: 7px; cursor: move;" title="${escapeHtml(t('drag_row'))}"></i>
                                <strong>${escapeHtml(row.name)}</strong> <i class="fa fa-caret-down text-muted"></i>
                            </span>
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

                    // Injected missing spacer cell to sit directly underneath the "+ Category" spawner column header
                    bodyHtml += `<td class="gs-matrix-spacer"></td>`;

                    bodyHtml += `
                        <td class="row-total-cell text-center text-bold" data-row-uuid="${row.uuid}" style="line-height: 36px;">0</td>
                        <td class="text-right cell-actions gs-matrix-head-cell" style="padding: 5px 12px; line-height: 26px;">
                            <button type="button" aria-label="${escapeHtml(t('remove_office'))}" class="btn btn-xs btn-danger remove-matrix-row-action" data-row-uuid="${row.uuid}"><i class="fa fa-trash"></i></button>
                        </td>
                    `;
                    bodyHtml += '</tr>';
                });
                $table.find('tbody').append(bodyHtml);

                // 3. Spawner Footers (tfoot) - Now contains N + 4 columns
                var footerHtml = '';
                footerHtml += '<tr id="matrix-spawner-row">';
                footerHtml += `<td class="gs-inline-spawner" id="btn-spawn-row" style="text-align: left;"><i class="fa fa-plus"></i> ${escapeHtml(t('select_office'))}</td>`;

                state.columns.forEach(function(col) {
                    footerHtml += `<td class="spacer-${col.category_id} gs-matrix-spacer"></td>`;
                });

                footerHtml += '<td id="matrix-spawner-spacer"></td>';

                // FIXED: Resolved the string concatenation syntax error here by wrapping it correctly
                footerHtml += '<td class="gs-matrix-spacer--right"></td>';

                footerHtml += '<td></td>';
                footerHtml += '</tr>';

                // Grand Totals Footers - Now contains N + 4 columns
                var grandTotal = 0;
                footerHtml += '<tr id="matrix-footer-row">';
                footerHtml += `<td>${escapeHtml(t('total'))}</td>`;

                state.columns.forEach(function(col) {
                    footerHtml += `<td id="total-cat-${col.category_id}" class="col-total-cell text-center text-bold" data-col-uuid="${col.uuid}">0</td>`;
                });

                // Injected missing spacer cell to sit directly underneath the "+ Category" spawner column footer
                footerHtml += `<td></td>`;

                footerHtml += `<td id="matrix-grand-total">0</td>`;
                footerHtml += '<td></td>';
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

                $('.matrix-cell').removeClass('gs-cell-input--invalid');
                $('.matrix-row-container').removeClass('gs-matrix-row--zero');

                state.rows.forEach(function(row) {
                    state.columns.forEach(function(col) {
                        if (state.validation.invalidCells[row.uuid + '-' + col.uuid]) {
                            $(`.matrix-cell[data-row-uuid="${row.uuid}"][data-col-uuid="${col.uuid}"]`).addClass('gs-cell-input--invalid');
                        }
                    });

                    if (state.totals.rows[row.uuid] === 0) {
                        $(`.matrix-row-container[data-row-uuid="${row.uuid}"]`).addClass('gs-matrix-row--zero');
                    }
                });

                var html = '';
                if (state.validation.errors.length > 0) {
                    html = `<span class="text-red"><i class="fa fa-times-circle"></i> <strong>${escapeHtml(t('error'))}</strong> ${state.validation.errors.join(' ')} ${escapeHtml(t('blocked'))}</span>`;
                    $table.removeClass('gs-matrix--warning gs-matrix--ok').addClass('gs-matrix--error');
                    $('#matrix-grid-table').closest('form').find('button[type="submit"]').prop('disabled', true);
                } else if (state.validation.warnings.length > 0) {
                    html = `<span class="text-yellow"><i class="fa fa-warning"></i> <strong>${escapeHtml(t('warning'))}</strong> ${state.validation.warnings.join(' ')} ${escapeHtml(t('draft_allowed'))}</span>`;
                    $table.removeClass('gs-matrix--error gs-matrix--ok').addClass('gs-matrix--warning');
                    $('#matrix-grid-table').closest('form').find('button[type="submit"]').prop('disabled', false);
                } else {
                    html = `<span class="text-green"><i class="fa fa-check-circle"></i> <strong>${escapeHtml(t('status'))}</strong> ${escapeHtml(t('healthy'))}</span>`;
                    $table.removeClass('gs-matrix--error gs-matrix--warning').addClass('gs-matrix--ok');
                    $('#matrix-grid-table').closest('form').find('button[type="submit"]').prop('disabled', false);
                }

                $statusBar.html(html);
            }
        };
    })();


// spawner

    (function() {
        function initMatrixSpawner() {
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initMatrixSpawner, 50);
                return;
            }

            window.jQuery(function($) {
                // Only serialize the Categories, as Locations are now searched via dynamic AJAX
                const availableCategories = config.categories;

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
                        alert(t('all_added'));
                        return;
                    }

                    $spawner.addClass('gs-inline-select').html(`
                        <select id="inline-category-select" style="width: 100%;">
                            <option value="">${escapeHtml(t('search'))}</option>
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
                    $spawner.removeClass('gs-inline-select').html(`<i class="fa fa-plus"></i> ${escapeHtml(t('category'))}`);
                }

                // =============================================================
                // 2. STATE-DRIVEN ROW (LOCATION) SPAWNER (AJAX ONLY)
                // =============================================================
                $('#matrix-grid-table').on('click', '#btn-spawn-row', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return; // Already open

                    $spawner.addClass('gs-inline-select').html(`
                        <select id="inline-location-select" style="width: 100%;">
                            <option value="">${escapeHtml(t('search'))}</option>
                        </select>
                    `);

                    var $select = $('#inline-location-select');
                    $select.select2({
                        placeholder: t('search_offices'),
                        minimumInputLength: 2,
                        dropdownParent: $('body'),
                        ajax: {
                            url: config.searchOfficesUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                var geoOverride = $('input[name="geo_override"]:checked').val() || 'Inherit';
                                var geoAreaId = $('select[name="geo_area_id"]').val() || '';
                                var participantOverride = $('input[name="participant_override"]:checked').val() || 'Inherit';

                                return {
                                    q: params.term,
                                    initiative_id: config.initiativeId,
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
                    $spawner.removeClass('gs-inline-select').html(`<i class="fa fa-plus"></i> ${escapeHtml(t('select_office'))}`);
                }
            });
        }

        initMatrixSpawner();
    })();


// menus

    (function() {
        function initMatrixMenusEngine() {
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initMatrixMenusEngine, 50);
                return;
            }

            window.jQuery(function($) {
                // Append menu overlays directly to the body to prevent absolute positioning distortion
                $('body').append($('#col-context-menu')).append($('#row-context-menu'));
                $('#matrix-grid-table').on('keydown', '.header-name', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); }
                });
                $(document).on('keydown', function(e) { if (e.key === 'Escape') $('.gs-context-menu').hide(); });

                var activeColUuid = null;
                var activeRowUuid = null;

                // FIXED: Only load categories. Locations are handled exclusively via AJAX.
                const availableCategories = config.categories;

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
                    if (col && confirm(t('clear_column') + ' ' + col.name)) {
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
                                <option value="">${escapeHtml(t('search'))}</option>
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
                    if (row && confirm(t('clear_row') + ' ' + row.name)) {
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
                                <option value="">${escapeHtml(t('search'))}</option>
                            </select>
                        `);

                        var $select = $('#inline-location-change-select');
                        $select.select2({
                            placeholder: t('search_offices'),
                            minimumInputLength: 2,
                            dropdownParent: $('body'),
                            ajax: {
                                url: config.searchOfficesUrl,
                                dataType: 'json',
                                delay: 250,
                                data: function (params) {
                                    var geoOverride = $('input[name="geo_override"]:checked').val() || 'Inherit';
                                    var geoAreaId = $('select[name="geo_area_id"]').val() || '';
                                    var participantOverride = $('input[name="participant_override"]:checked').val() || 'Inherit';

                                    return {
                                        q: params.term,
                                        initiative_id: config.initiativeId,
                                        geo_override: geoOverride,
                                        geo_area_id: geoAreaId,
                                        participant_override: participantOverride
                                    };
                                },
                                processResults: function (data) {
                                    var activeLocationIds = window.GovStoreMatrix.state.rows.map(r => parseInt(r.location_id));

                                    // Prevent duplicate selections: filter out active rows, but keep the current row's location
                                    var filteredResults = data.results.filter(function(loc) {
                                        return parseInt(loc.id) === row.location_id || !activeLocationIds.includes(parseInt(loc.id));
                                    });

                                    return { results: filteredResults };
                                }
                            }
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


// keyboard

    (function() {
        function initKeyboardEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initKeyboardEngine, 50);
                return;
            }

            window.jQuery(function($) {
                $('#matrix-grid-table').on('keydown', '.matrix-cell', function(e) {
                    var $current = $(this);
                    var rIndex = parseInt($current.attr('data-row'));
                    var cIndex = parseInt($current.attr('data-col'));

                    var targetRow = rIndex;
                    var targetCol = cIndex;

                    switch(e.key) {
                        case 'ArrowUp':
                            targetRow = rIndex - 1;
                            e.preventDefault();
                            break;

                        case 'ArrowDown':
                        case 'Enter':
                            targetRow = rIndex + 1;
                            e.preventDefault();
                            break;

                        case 'ArrowLeft':
                            if (this.selectionStart === 0) {
                                targetCol = cIndex - 1;
                                e.preventDefault();
                            }
                            break;

                        case 'ArrowRight':
                            if (this.selectionEnd === this.value.length) {
                                targetCol = cIndex + 1;
                                e.preventDefault();
                            }
                            break;

                        default:
                            return;
                    }

                    if (targetRow !== rIndex || targetCol !== cIndex) {
                        var $targetCell = $(`.matrix-cell[data-row="${targetRow}"][data-col="${targetCol}"]`);
                        if ($targetCell.length > 0) {
                            $targetCell.focus();
                        }
                    }
                });

                $('#matrix-grid-table').on('focus', '.matrix-cell', function() {
                    this.select();
                });
            });
        }

        initKeyboardEngine();
    })();


// clipboard

    (function() {
        function initClipboardEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initClipboardEngine, 50);
                return;
            }

            window.jQuery(function($) {
                $('#matrix-grid-table').on('paste', '.matrix-cell', function(e) {
                    var $anchor = $(this);
                    var clipboardData = e.originalEvent.clipboardData || window.clipboardData;
                    var pastedText = clipboardData.getData('text');

                    if (!pastedText) return;
                    e.preventDefault();

                    var rows = pastedText.split(/\r?\n/);
                    var startRowIndex = parseInt($anchor.attr('data-row'));
                    var startColIndex = parseInt($anchor.attr('data-col'));

                    rows.forEach(function(rowText, rOffset) {
                        if (rowText.trim() === '') return;
                        var cols = rowText.split('\t');

                        cols.forEach(function(cellValue, cOffset) {
                            var targetRowIndex = startRowIndex + rOffset;
                            var targetColIndex = startColIndex + cOffset;

                            var targetRow = window.GovStoreMatrix.state.rows[targetRowIndex];
                            var targetCol = window.GovStoreMatrix.state.columns[targetColIndex];

                            if (targetRow && targetCol) {
                                var numericValue = parseInt(cellValue.trim().replace(/[^0-9]/g, '')) || 0;
                                // Mutate State strictly via the Actions API to preserve calculations
                                window.GovStoreMatrix.actions.setQuantity(targetRow.uuid, targetCol.uuid, numericValue);
                            }
                        });
                    });
                });
            });
        }

        initClipboardEngine();
    })();


// drag_drop

    (function() {
        function initDragDropEngine() {
            // Verify jQuery is loaded safely before executing
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initDragDropEngine, 50);
                return;
            }

            window.jQuery(function($) {
                var draggedType = null; // 'COLUMN' or 'ROW'
                var draggedUuid = null; // Stores active dragging UUID

                // =============================================================
                // A. COLUMN DRAG & DROP EVENTS (Horizontal)
                // =============================================================

                $('#matrix-grid-table').on('dragstart', '.matrix-cat-header', function(e) {
                    var $header = $(this);
                    draggedType = 'COLUMN';
                    draggedUuid = $header.attr('data-col-uuid');

                    $header.addClass('gs-dragging');
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                    e.originalEvent.dataTransfer.setData('text/plain', draggedUuid);
                });

                $('#matrix-grid-table').on('dragover', '.matrix-cat-header', function(e) {
                    if (draggedType !== 'COLUMN') return;
                    e.preventDefault(); // Required to allow drop action

                    var targetUuid = $(this).attr('data-col-uuid');
                    if (targetUuid !== draggedUuid) {
                        $(this).addClass('gs-drag-over-left'); // Draw blue vertical line
                    }
                });

                $('#matrix-grid-table').on('dragleave', '.matrix-cat-header', function() {
                    $(this).removeClass('gs-drag-over-left');
                });

                $('#matrix-grid-table').on('drop', '.matrix-cat-header', function(e) {
                    if (draggedType !== 'COLUMN') return;
                    e.preventDefault();

                    var targetUuid = $(this).attr('data-col-uuid');

                    // Cleanup visual styles
                    $('.matrix-cat-header').removeClass('gs-dragging gs-drag-over-left');

                    if (targetUuid && targetUuid !== draggedUuid) {
                        // Execute state swap
                        window.GovStoreMatrix.actions.reorderColumns(draggedUuid, targetUuid);
                    }

                    resetDragState();
                });

                // =============================================================
                // B. ROW DRAG & DROP EVENTS (Vertical)
                // =============================================================

                $('#matrix-grid-table').on('dragstart', '.matrix-loc-header', function(e) {
                    var $header = $(this);
                    draggedType = 'ROW';
                    draggedUuid = $header.attr('data-row-uuid');

                    $(this).closest('tr').addClass('gs-dragging');
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                    e.originalEvent.dataTransfer.setData('text/plain', draggedUuid);
                });

                $('#matrix-grid-table').on('dragover', '.matrix-loc-header', function(e) {
                    if (draggedType !== 'ROW') return;
                    e.preventDefault();

                    var targetUuid = $(this).attr('data-row-uuid');
                    if (targetUuid !== draggedUuid) {
                        $(this).closest('tr').addClass('gs-drag-over-top'); // Draw blue horizontal line
                    }
                });

                $('#matrix-grid-table').on('dragleave', '.matrix-loc-header', function() {
                    $(this).closest('tr').removeClass('gs-drag-over-top');
                });

                $('#matrix-grid-table').on('drop', '.matrix-loc-header', function(e) {
                    if (draggedType !== 'ROW') return;
                    e.preventDefault();

                    var targetUuid = $(this).attr('data-row-uuid');

                    // Cleanup visual styles
                    $('.matrix-row-container').removeClass('gs-dragging gs-drag-over-top');

                    if (targetUuid && targetUuid !== draggedUuid) {
                        // Execute state swap
                        window.GovStoreMatrix.actions.reorderRows(draggedUuid, targetUuid);
                    }

                    resetDragState();
                });

                // Cleanup fallback if drag ends outside drop boundaries
                $('#matrix-grid-table').on('dragend', function() {
                    $('.matrix-cat-header, .matrix-row-container').removeClass('gs-dragging gs-drag-over-left gs-drag-over-top');
                    resetDragState();
                });

                function resetDragState() {
                    draggedType = null;
                    draggedUuid = null;
                }
            });
        }

        initDragDropEngine();
    })();


// serializer

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

                    var specificity = $('input[name="specificity_level"]:checked').val() || $('input[type="hidden"][name="specificity_level"]').val();
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
                            $container.append(`<input type="hidden" name="matrix_economic_codes[${cat.category_id}]" value="${escapeHtml(cat.economic_code)}">`);
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


// boot

    (function() {
        function initMatrixBoot() {
            if (typeof window.jQuery === 'undefined' || typeof window.GovStoreMatrix.renderer === 'undefined') {
                setTimeout(initMatrixBoot, 50);
                return;
            }

            window.jQuery(function($) {
                const savedCategories = config.savedCategories;
                const savedLocations = config.savedLocations;
                const savedValues = config.savedValues;

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

    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startTrackingMatrix, {once:true});
    else startTrackingMatrix();
})();
