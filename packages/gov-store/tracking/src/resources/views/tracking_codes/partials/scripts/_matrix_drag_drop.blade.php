<!-- Drag-and-Drop Event Controller (Zero Dependencies) -->
<script>
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
</script>