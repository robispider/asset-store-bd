<!-- Load Dynamic Theme-Adaptive Stylesheet -->
@include('govtracking::tracking_codes.partials.styles._matrix_styles')

<div id="panel-level3" class="box box-solid" style="display: none;">
    <div class="box-header with-border">
        <h3 class="box-title text-purple"><i class="fa fa-table"></i> Exact Delivery Schedule Matrix</h3>
    </div>
    <div class="box-body">
        <div class="alert alert-info" style="background-color: #faf5ff !important; border-color: #d8b4fe !important; color: #581c87 !important;">
            <p><i class="fa fa-info-circle text-purple"></i> <strong>Interactive Spreadsheet Matrix:</strong></p>
            <ul style="margin-left: 15px; padding-left: 0; list-style-type: square;">
                <li>Click on any column or row header to trigger action menus (Move, Rename, Delete).</li>
                <li>Use standard arrow keys or Tab / Enter to navigate the grid cells exactly like Excel.</li>
                <li>You can copy tabular data from <strong>Excel</strong> or <strong>Google Sheets</strong> and paste it directly!</li>
                <li>You can **drag and drop** column and row headers to reorder them on-the-fly!</li>
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
                    <!-- Rendered dynamically by the State Engine -->
                </thead>
                <tbody id="matrix-grid-body">
                    <!-- Rendered dynamically by the State Engine -->
                </tbody>
                <tfoot>
                    <!-- Rendered dynamically by the State Engine -->
                </tfoot>
            </table>
        </div>

        <!-- Hidden serialization container populated before submit -->
        <div id="matrix-hidden-inputs"></div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- ENCODE DECOUPLED STATE-DRIVEN SCRIPT ENGINES -->
<!-- ========================================================================= -->
@include('govtracking::tracking_codes.partials.scripts._matrix_state')
@include('govtracking::tracking_codes.partials.scripts._matrix_renderer')
@include('govtracking::tracking_codes.partials.scripts._matrix_spawner')
@include('govtracking::tracking_codes.partials.scripts._matrix_menus')
@include('govtracking::tracking_codes.partials.scripts._matrix_keyboard')
@include('govtracking::tracking_codes.partials.scripts._matrix_clipboard')
@include('govtracking::tracking_codes.partials.scripts._matrix_drag_drop') <!-- Added Drag & Drop Controller -->
@include('govtracking::tracking_codes.partials.scripts._matrix_serializer')
@include('govtracking::tracking_codes.partials.scripts._matrix_validation')
@include('govtracking::tracking_codes.partials.scripts._matrix_boot')