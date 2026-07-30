<!-- Isolated CSS Spreadsheet Styling -->
<style>
    .gs-grid-container {
        position: relative;
        max-height: 500px;
        overflow: auto;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background-color: #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    #matrix-grid-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        margin-bottom: 0;
        font-size: 13px;
        color: #334155;
    }

    /* --- LOCKED TOP HEADER ROW --- */
    #matrix-grid-table thead th {
        position: sticky;
        top: 0;
        background-color: #f8fafc;
        z-index: 20;
        border-bottom: 2px solid #cbd5e1;
        border-right: 1px solid #e2e8f0;
        padding: 8px 12px;
        font-weight: 600;
        text-align: center;
        height: 40px;
    }

    /* --- LOCKED LEFT COLUMN (OFFICES) --- */
    #matrix-grid-table tbody td:first-child,
    #matrix-grid-table tfoot td:first-child {
        position: sticky;
        left: 0;
        background-color: #f8fafc;
        z-index: 15;
        border-right: 2px solid #cbd5e1;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        padding: 10px 15px;
    }

    /* --- THE CORNER INTERSECT CELL (TOP-LEFT) --- */
    #matrix-grid-table thead th:first-child {
        position: sticky;
        left: 0;
        top: 0;
        z-index: 30;
        border-right: 2px solid #cbd5e1;
        border-bottom: 2px solid #cbd5e1;
        background-color: #f1f5f9;
        text-align: left;
    }

    /* --- GENERAL SPREADSHEET CELL STYLING --- */
    #matrix-grid-table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0;
        height: 36px;
    }

    /* --- FLAT SPREADSHEET CELL INPUTS --- */
    .gs-cell-input {
        width: 100%;
        height: 36px;
        border: none;
        outline: none;
        text-align: center;
        padding: 0 8px;
        background-color: transparent;
        transition: all 0.15s ease-in-out;
    }

    .gs-cell-input:focus {
        background-color: #ffffff;
        box-shadow: inset 0 0 0 2px #3b82f6;
        z-index: 5;
        position: relative;
    }

    #matrix-grid-table tbody tr:hover td {
        background-color: #f1f5f9;
    }

    /* --- FOOTER TOTALS ROW --- */
    #matrix-grid-table tfoot tr td {
        position: sticky;
        bottom: 0;
        background-color: #f8fafc;
        z-index: 10;
        border-top: 2px solid #cbd5e1;
        font-weight: 700;
        padding: 10px 12px;
        text-align: center;
    }

    #matrix-grid-table tfoot td:first-child {
        z-index: 25;
    }

    /* --- INLINE SPAWNER ELEMENTS --- */
    .gs-inline-spawner {
        background-color: #f8fafc !important;
        cursor: pointer;
        color: #3b82f6;
        font-weight: 600 !important;
        text-align: center;
        transition: background-color 0.15s;
    }

    .gs-inline-spawner:hover {
        background-color: #eff6ff !important;
        color: #2563eb;
    }

    .gs-inline-select .select2-container--default .select2-selection--single {
        border: none !important;
        background-color: transparent !important;
        height: 34px !important;
    }
</style>

<div id="panel-level3" class="box box-solid" style="display: none;">
    <div class="box-header with-border">
        <h3 class="box-title text-purple"><i class="fa fa-table"></i> Exact Delivery Schedule Matrix</h3>
    </div>
    <div class="box-body">
        <div class="alert alert-info" style="background-color: #faf5ff !important; border-color: #d8b4fe !important; color: #581c87 !important;">
            <p><i class="fa fa-info-circle text-purple"></i> <strong>Interactive Spreadsheet Matrix:</strong></p>
            <ul style="margin-left: 15px; padding-left: 0; list-style-type: square;">
                <li>Click the <strong>[+ Category]</strong> column header to dynamically insert category targets.</li>
                <li>Click the <strong>[+ Select Office...]</strong> row header to dynamically insert receiving warehouses.</li>
                <li>Use standard arrow keys or Tab / Enter to navigate the grid cells exactly like Excel.</li>
                <li>You can copy tabular data from <strong>Excel</strong> or <strong>Google Sheets</strong> and paste it directly!</li>
            </ul>
        </div>

        <!-- Dynamic Spreadsheet Real-Time Status Bar -->
        <div id="matrix-status-bar" class="margin-bottom-15" style="font-size: 14px; padding: 10px; background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;">
            <span id="matrix-status-text">
                <span class="text-green"><i class="fa fa-check-circle"></i> <strong>Spreadsheet Status:</strong> Healthy (All allocations conform to planning rules)</span>
            </span>
        </div>

        <!-- The Spreadsheet Container -->
        <div class="gs-grid-container">
            <table class="table" id="matrix-grid-table">
                <thead>
                    <tr id="matrix-header-row">
                        <th width="320">Office / Warehouse Location</th>
                        <!-- Dynamic category headers spawn here -->
                        
                        <!-- Inline Column Spawner -->
                        <th width="150" class="gs-inline-spawner" id="btn-spawn-column" style="vertical-align: middle;">
                            <i class="fa fa-plus"></i> Category
                        </th>
                        
                        <!-- Static Row Totals Header Column -->
                        <th width="120" id="col-row-total-header" style="background-color: #f1f5f9; font-weight: bold; border-right: 2px solid #cbd5e1;">ROW TOTAL</th>
                        <th width="80" style="text-align: right; background-color: #f8fafc;">Action</th>
                    </tr>
                </thead>
                <tbody id="matrix-grid-body">
                    <!-- Dynamic rows spawn here -->
                </tbody>
                <tfoot>
                    <!-- Inline Row Spawner -->
                    <tr id="matrix-spawner-row">
                        <td class="gs-inline-spawner" id="btn-spawn-row" style="text-align: left;">
                            <i class="fa fa-plus"></i> Select Office...
                        </td>
                        <!-- Spacer cells spawn here dynamically on column creations -->
                        <td id="matrix-spawner-spacer"></td>
                        <td style="background-color: #f8fafc; border-right: 2px solid #cbd5e1;"></td>
                        <td></td>
                    </tr>
                    
                    <!-- Grand Totals Row -->
                    <tr id="matrix-footer-row">
                        <td>TOTAL ALLOCATIONS</td>
                        <!-- Dynamic totals spawn here -->
                        <td id="matrix-grand-total" style="background-color: #f1f5f9; font-weight: bold; border-right: 2px solid #cbd5e1;">0</td>
                        <td style="background-color: #f8fafc;"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Include Dynamic UI Javascript Engines (Including Step 6 Validation Engine) -->
@include('govtracking::tracking_codes.partials.scripts._matrix_spawner')
@include('govtracking::tracking_codes.partials.scripts._matrix_keyboard')
@include('govtracking::tracking_codes.partials.scripts._matrix_calculator')
@include('govtracking::tracking_codes.partials.scripts._matrix_clipboard')
@include('govtracking::tracking_codes.partials.scripts._matrix_validation')