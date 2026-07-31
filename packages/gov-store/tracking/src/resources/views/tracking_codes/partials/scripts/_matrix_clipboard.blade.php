<!-- Excel Clipboard Sync Engine -->
<script>
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
</script>