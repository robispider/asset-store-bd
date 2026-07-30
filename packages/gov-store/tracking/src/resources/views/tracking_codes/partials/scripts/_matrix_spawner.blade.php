<!-- Inline Select2 Spawning Engine -->
<script>
    (function() {
        function initMatrixSpawner() {
            // Verify jQuery and Select2 are fully loaded before executing
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                setTimeout(initMatrixSpawner, 50);
                return;
            }

            window.jQuery(function($) {
                window.activeCategories = window.activeCategories || [];
                var rowIndex = 0;

                const availableCategories = @json($categories->map(fn($c) => ['id' => $c->id, 'text' => $c->name]));
                const availableLocations = @json($locations->map(fn($l) => ['id' => $l->id, 'text' => $l->name]));

                // Column Spawner
                $('#btn-spawn-column').on('click', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return;

                    $spawner.addClass('gs-inline-select').html(`
                        <select id="inline-category-select" style="width: 100%;">
                            <option value="">-- Search --</option>
                        </select>
                    `);

                    var $select = $('#inline-category-select');
                    $select.select2({
                        data: availableCategories,
                        dropdownParent: $spawner,
                        minimumResultsForSearch: 0
                    }).select2('open');

                    $select.on('select2:select', function(e) {
                        var catId = e.params.data.id;
                        var catName = e.params.data.text;

                        if (window.activeCategories.includes(parseInt(catId))) {
                            alert('This Category Column has already been added to the matrix.');
                            resetColumnSpawner($spawner);
                            return;
                        }

                        window.activeCategories.push(parseInt(catId));

                        var headerColHtml = `
                            <th class="matrix-cat-col" data-cat-id="${catId}" style="text-align: center; background-color: #f4f4f4;">
                                ${catName}
                                <input type="hidden" name="matrix_categories[]" value="${catId}">
                                <br><small class="text-muted"><input type="text" name="matrix_economic_codes[${catId}]" class="form-control input-sm text-center" placeholder="Econ Code" style="margin-top: 5px; width: 100px; display:inline-block; height: 26px; padding: 2px 6px;"></small>
                            </th>
                        `;
                        $spawner.before(headerColHtml);

                        $('#matrix-grid-body tr.matrix-row').each(function() {
                            var rIndex = $(this).data('row-index');
                            var cellHtml = `
                                <td class="text-center cell-${catId}">
                                    <input type="number" name="matrix_values[${rIndex}][${catId}]" class="gs-cell-input matrix-cell" data-cat-id="${catId}" data-row="${rIndex}" data-col="${window.activeCategories.length - 1}" value="0" min="0" required>
                                </td>
                            `;
                            $(this).find('.row-total-cell').before(cellHtml);
                        });

                        var footerSpacerHtml = `<td class="spacer-${catId}" style="background-color: #f8fafc; border-bottom: 1px solid #cbd5e1;"></td>`;
                        $('#matrix-spawner-spacer').before(footerSpacerHtml);

                        var footerTotalHtml = `<td id="total-cat-${catId}" class="text-center text-bold" style="background-color: #f1f5f9; font-weight: bold; border-top: 2px solid #cbd5e1;">0</td>`;
                        $('#matrix-grand-total').before(footerTotalHtml);

                        resetColumnSpawner($spawner);
                        $(document).trigger('matrix:structure_changed');
                    });

                    $select.on('select2:close', function() {
                        setTimeout(() => resetColumnSpawner($spawner), 100);
                    });
                });

                function resetColumnSpawner($spawner) {
                    $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Category');
                }

                // Row Spawner
                $('#btn-spawn-row').on('click', function() {
                    var $spawner = $(this);
                    if ($spawner.hasClass('gs-inline-select')) return;

                    $spawner.addClass('gs-inline-select').html(`
                        <select id="inline-location-select" style="width: 100%;">
                            <option value="">-- Search --</option>
                        </select>
                    `);

                    var $select = $('#inline-location-select');
                    $select.select2({
                        data: availableLocations,
                        dropdownParent: $spawner,
                        minimumResultsForSearch: 0
                    }).select2('open');

                    $select.on('select2:select', function(e) {
                        var locId = e.params.data.id;
                        var locName = e.params.data.text;

                        var duplicate = false;
                        $('.matrix-row-loc-input').each(function() {
                            if ($(this).val() == locId) duplicate = true;
                        });

                        if (duplicate) {
                            alert('This Office Row has already been added to the matrix.');
                            resetRowSpawner($spawner);
                            return;
                        }

                        var tr = document.createElement('tr');
                        tr.setAttribute('data-row-index', rowIndex);
                        tr.setAttribute('class', 'matrix-row');

                        var cellsHtml = `
                            <td>
                                <strong>${locName}</strong>
                                <input type="hidden" name="matrix_locations[${rowIndex}]" class="matrix-row-loc-input" value="${locId}">
                            </td>
                        `;

                        window.activeCategories.forEach((catId, colIndex) => {
                            cellsHtml += `
                                <td class="text-center cell-${catId}">
                                    <input type="number" name="matrix_values[${rowIndex}][${catId}]" class="gs-cell-input matrix-cell" data-cat-id="${catId}" data-row="${rowIndex}" data-col="${colIndex}" value="0" min="0" required>
                                </td>
                            `;
                        });

                        cellsHtml += `
                            <td class="row-total-cell text-center text-bold" style="background-color: #f1f5f9; font-weight: bold; border-right: 2px solid #cbd5e1; line-height: 36px;">0</td>
                            <td class="text-right cell-actions" style="background-color: #f8fafc; padding: 5px 12px; line-height: 26px;">
                                <button type="button" class="btn btn-xs btn-danger remove-matrix-row"><i class="fa fa-trash"></i></button>
                            </td>
                        `;

                        tr.innerHTML = cellsHtml;
                        document.getElementById('matrix-grid-body').appendChild(tr);

                        rowIndex++;
                        resetRowSpawner($spawner);
                        $(document).trigger('matrix:structure_changed');
                    });

                    $select.on('select2:close', function() {
                        setTimeout(() => resetRowSpawner($spawner), 100);
                    });
                });

                function resetRowSpawner($spawner) {
                    $spawner.removeClass('gs-inline-select').html('<i class="fa fa-plus"></i> Select Office...');
                }

                $('#matrix-grid-body').on('click', '.remove-matrix-row', function() {
                    $(this).closest('tr').remove();
                    $(document).trigger('matrix:structure_changed');
                });
            });
        }

        initMatrixSpawner();
    })();
</script>