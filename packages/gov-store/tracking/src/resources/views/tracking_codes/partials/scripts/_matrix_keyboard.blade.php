<!-- Excel Keyboard Navigation Engine -->
<script>
    (function() {
        function initKeyboardEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initKeyboardEngine, 50);
                return;
            }

            window.jQuery(function($) {
                $('#matrix-grid-body').on('keydown', '.matrix-cell', function(e) {
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

                $('#matrix-grid-body').on('focus', '.matrix-cell', function() {
                    this.select();
                });
            });
        }

        initKeyboardEngine();
    })();
</script>