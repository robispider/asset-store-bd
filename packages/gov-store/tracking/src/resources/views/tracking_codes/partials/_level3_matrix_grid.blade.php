<div id="panel-level3" class="box box-solid" style="{{ (isset($trackingCode) && $trackingCode->specificity_level === '3_MATRIX') ? 'display: block;' : 'display: none;' }}">
    <div class="box-header with-border">
        <h3 class="box-title text-purple"><i class="fa fa-table"></i> Exact Delivery Schedule Matrix</h3>
    </div>
    <div class="box-body">
        <div class="alert alert-info">
            <p><i class="fa fa-info-circle"></i> <strong>Spreadsheet Matrix Instructions:</strong></p>
            <ol style="margin-left: 15px; padding-left: 0;">
                <li>Click <strong>[+ Add Category Column]</strong> to select the items you are procuring.</li>
                <li>Click <strong>[+ Add Office Row]</strong> to select the receiving warehouses.</li>
                <li>Enter the authorized distribution quantities directly inside the spreadsheet cells.</li>
            </ol>
        </div>

        <div class="margin-bottom-15">
            <button type="button" class="btn btn-primary btn-sm" id="btn-add-column"><i class="fa fa-columns"></i> + Add Category Column</button>
            <button type="button" class="btn btn-warning btn-sm" id="btn-add-row" style="margin-left: 5px;"><i class="fa fa-building"></i> + Add Office Row</button>
        </div>

        <div class="table-responsive" style="margin-top: 15px; border: 1px solid #ddd; border-radius: 4px;">
            <table class="table table-bordered table-striped" id="matrix-grid-table" style="margin-bottom: 0;">
                <thead>
                    <tr id="matrix-header-row">
                        <th width="300" style="background-color: #f4f4f4;">Participating Office (Location)</th>
                        <th width="80" id="col-actions-header" style="background-color: #f4f4f4; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="matrix-grid-body">
                    <!-- Dynamic rows will spawn here -->
                </tbody>
                <tfoot>
                    <tr id="matrix-footer-row" style="background-color: #f9f9f9; font-weight: bold;">
                        <td>TOTAL COMPONENT GOAL</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-matrix-column" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Select Category Column</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Asset Category</label>
                    <select id="matrix-col-select" class="form-control select2" style="width: 100%;">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-confirm-column">Add Column</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-matrix-row" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Select Office Row</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Office Location</label>
                    <select id="matrix-row-select" class="form-control select2" style="width: 100%;">
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="btn-confirm-row">Add Row</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let activeCategories = [];
        let rowIndex = 0;

        // --- PRE-POPULATION MATRICES (Drawn dynamically on EDIT, empty on CREATE) ---
        const savedCategories = @json(isset($trackingCode) && $trackingCode->specificity_level === '3_MATRIX' ? $trackingCode->targets->map(fn($t) => ['id' => $t->category_id, 'name' => $t->category->name, 'econ' => $t->economic_code]) : []);
        const savedLocations = @json(isset($trackingCode) && $trackingCode->specificity_level === '3_MATRIX' ? $trackingCode->targets->flatMap->allocations->map(fn($a) => ['id' => $a->location_id, 'name' => $a->location->name])->unique('id')->values() : []);
        const savedValues = @json($savedMatrixValues ?? []);

        // Modal Controls
        $('#btn-add-column').click(() => $('#modal-matrix-column').modal('show'));
        $('#btn-add-row').click(() => $('#modal-matrix-row').modal('show'));

        function addColumn(catId, catName, econCode = '') {
            activeCategories.push(catId);
            const headerHtml = `
                <th class="matrix-cat-col" data-cat-id="${catId}" style="text-align: center; background-color: #f4f4f4;">
                    ${catName}
                    <input type="hidden" name="matrix_categories[]" value="${catId}">
                    <br><small class="text-muted"><input type="text" name="matrix_economic_codes[${catId}]" class="form-control input-sm text-center" value="${econCode}" placeholder="Econ Code" style="margin-top: 5px; width: 100px; display:inline-block;"></small>
                </th>
            `;
            $('#col-actions-header').before(headerHtml);

            $('#matrix-grid-body tr').each(function() {
                const rowId = $(this).data('row-index');
                const locId = $(this).find('.matrix-row-loc-input').val();
                const prefilledVal = (savedValues[locId] && savedValues[locId][catId]) ? savedValues[locId][catId] : 0;

                const cellHtml = `
                    <td class="text-center cell-${catId}">
                        <input type="number" name="matrix_values[${rowId}][${catId}]" class="form-control text-center matrix-cell" data-cat-id="${catId}" value="${prefilledVal}" min="0" required style="width: 80px; display: inline-block;">
                    </td>
                `;
                $(this).find('.cell-actions').before(cellHtml);
            });

            const footerHtml = `<td id="total-cat-${catId}" class="text-center text-bold" style="background-color: #f9f9f9;">0</td>`;
            $('#matrix-footer-row').find('td:last').before(footerHtml);
        }

        function addRow(locId, locName) {
            const tr = document.createElement('tr');
            tr.setAttribute('data-row-index', rowIndex);
            tr.setAttribute('class', 'matrix-row');

            let cellsHtml = `
                <td>
                    <strong>${locName}</strong>
                    <input type="hidden" name="matrix_locations[${rowIndex}]" class="matrix-row-loc-input" value="${locId}">
                </td>
            `;

            activeCategories.forEach(catId => {
                const prefilledVal = (savedValues[locId] && savedValues[locId][catId]) ? savedValues[locId][catId] : 0;
                cellsHtml += `
                    <td class="text-center cell-${catId}">
                        <input type="number" name="matrix_values[${rowIndex}][${catId}]" class="form-control text-center matrix-cell" data-cat-id="${catId}" value="${prefilledVal}" min="0" required style="width: 80px; display: inline-block;">
                    </td>
                `;
            });

            cellsHtml += `
                <td class="text-right cell-actions">
                    <button type="button" class="btn btn-xs btn-danger remove-matrix-row"><i class="fa fa-trash"></i></button>
                </td>
            `;

            tr.innerHTML = cellsHtml;
            document.getElementById('matrix-grid-body').appendChild(tr);
            rowIndex++;
        }

        // Trigger pre-population if values were passed from edit controller
        if (savedCategories.length > 0) {
            savedCategories.forEach(cat => addColumn(cat.id.toString(), cat.name, cat.econ ?? ''));
            savedLocations.forEach(loc => addRow(loc.id.toString(), loc.name));
            calculateTotals();
        }

        // Manual Add Controls
        $('#btn-confirm-column').click(function() {
            const select = document.getElementById('matrix-col-select');
            addColumn(select.value, select.options[select.selectedIndex].text);
            $('#modal-matrix-column').modal('hide');
            calculateTotals();
        });

        $('#btn-confirm-row').click(function() {
            const select = document.getElementById('matrix-row-select');
            addRow(select.value, select.options[select.selectedIndex].text);
            $('#modal-matrix-row').modal('hide');
            calculateTotals();
        });

        $('#matrix-grid-body').on('click', '.remove-matrix-row', function() {
            $(this).closest('tr').remove();
            calculateTotals();
        });

        $('#matrix-grid-body').on('input', '.matrix-cell', () => calculateTotals());

        function calculateTotals() {
            activeCategories.forEach(catId => {
                let colSum = 0;
                $(`.matrix-cell[data-cat-id="${catId}"]`).each(function() {
                    colSum += parseInt($(this).val()) || 0;
                });
                $(`#total-cat-${catId}`).text(colSum);
            });
        }
    });
</script>