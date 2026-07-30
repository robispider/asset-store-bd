<!-- Reactive Spreadsheet Calculation Engine -->
<script>
    (function() {
        function initCalculatorEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initCalculatorEngine, 50);
                return;
            }

            window.jQuery(function($) {
                $('#matrix-grid-body').on('input', '.matrix-cell', function() {
                    calculateMatrix();
                });

                $(document).on('matrix:structure_changed', function() {
                    calculateMatrix();
                });

                function calculateMatrix() {
                    calculateHorizontalRowTotals();
                    calculateVerticalColumnTotals();
                    calculateGrandTotal();
                }

                function calculateHorizontalRowTotals() {
                    $('#matrix-grid-body tr.matrix-row').each(function() {
                        var rowSum = 0;
                        $(this).find('.matrix-cell').each(function() {
                            rowSum += parseInt($(this).val()) || 0;
                        });
                        $(this).find('.row-total-cell').text(rowSum);
                    });
                }

                function calculateVerticalColumnTotals() {
                    if (!window.activeCategories) return;

                    window.activeCategories.forEach(function(catId) {
                        var colSum = 0;
                        $(`.matrix-cell[data-cat-id="${catId}"]`).each(function() {
                            colSum += parseInt($(this).val()) || 0;
                        });
                        $(`#total-cat-${catId}`).text(colSum);
                    });
                }

                function calculateGrandTotal() {
                    var grandSum = 0;
                    $('.row-total-cell').each(function() {
                        grandSum += parseInt($(this).text()) || 0;
                    });
                    $('#matrix-grand-total').text(grandSum);
                }

                calculateMatrix();
            });
        }

        initCalculatorEngine();
    })();
</script>