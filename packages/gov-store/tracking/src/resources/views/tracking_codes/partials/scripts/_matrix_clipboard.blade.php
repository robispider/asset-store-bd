<!-- Excel Clipboard Sync Engine -->
<script>
    (function() {
        function initClipboardEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initClipboardEngine, 50);
                return;
            }

            window.jQuery(function($) {
                $('#matrix-grid-body').on('paste', '.matrix-cell', function(e) {
                    var $anchor = $(this);
                    var clipboardData = e.originalEvent.clipboardData || window.clipboardData;
                    var pastedText = clipboardData.getData('text');

                    if (!pastedText) return;
                    e.preventDefault();

                    var rows = pastedText.split(/\r?\n/);
                    var startRow = parseInt($anchor.attr('data-row'));
                    var startCol = parseInt($anchor.attr('data-col'));

                    rows.forEach(function(rowText, rOffset) {
                        if (rowText.trim() === '') return;
                        var cols = rowText.split('\t');

                        cols.forEach(function(cellValue, cOffset) {
                            var targetRow = startRow + rOffset;
                            var targetCol = startCol + cOffset;
                            var $targetInput = $(`.matrix-cell[data-row="${targetRow}"][data-col="${targetCol}"]`);

                            if ($targetInput.length > 0) {
                                var numericValue = parseInt(cellValue.trim().replace(/[^0-9]/g, '')) || 0;
                                $targetInput.val(numericValue);
                            }
                        });
                    });

                    $(document).trigger('matrix:structure_changed');
                });
            });
        }

        initClipboardEngine();
    })();
</script>