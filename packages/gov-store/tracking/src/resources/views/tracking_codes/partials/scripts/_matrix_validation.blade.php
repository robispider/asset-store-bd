<!-- Spreadsheet Validation & Compliance Engine -->
<script>
    (function() {
        function initValidationEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initValidationEngine, 50);
                return;
            }

            window.jQuery(function($) {
                $('#matrix-grid-body').on('input', '.matrix-cell', function() {
                    runDiagnostics();
                });

                $(document).on('matrix:structure_changed', function() {
                    runDiagnostics();
                });

                function runDiagnostics() {
                    var errors = [];
                    var warnings = [];

                    $('.matrix-cell').css('background-color', '');
                    $('.matrix-row').css('border-left', '');

                    $('.matrix-cell').each(function() {
                        var val = parseInt($(this).val()) || 0;
                        if (val < 0) {
                            $(this).css('background-color', '#fee2e2');
                            if (!errors.includes('Negative quantities are not allowed.')) {
                                errors.push('Negative quantities are not allowed.');
                            }
                        }
                    });

                    $('#matrix-grid-body tr.matrix-row').each(function() {
                        var rowSum = 0;
                        $(this).find('.matrix-cell').each(function() {
                            rowSum += parseInt($(this).val()) || 0;
                        });

                        if (rowSum === 0) {
                            $(this).css('border-left', '4px solid #f59e0b');
                            if (!warnings.includes('Some participating offices have zero items allocated.')) {
                                warnings.push('Some participating offices have zero items allocated.');
                            }
                        }
                    });

                    updateStatusBar(errors, warnings);
                }

                function updateStatusBar(errors, warnings) {
                    var $statusBar = $('#matrix-status-text');
                    var html = '';

                    if (errors.length > 0) {
                        html = `<span class="text-red"><i class="fa fa-times-circle"></i> <strong>Spreadsheet Error:</strong> ${errors.join(' ')} (Saving blocked)</span>`;
                        $('#matrix-grid-table').css('border-color', '#ef4444');
                        $('button[type="submit"]').prop('disabled', true);
                    } else if (warnings.length > 0) {
                        html = `<span class="text-yellow"><i class="fa fa-warning"></i> <strong>Operational Warning:</strong> ${warnings.join(' ')} (Draft saving allowed)</span>`;
                        $('#matrix-grid-table').css('border-color', '#f59e0b');
                        $('button[type="submit"]').prop('disabled', false);
                    } else {
                        html = `<span class="text-green"><i class="fa fa-check-circle"></i> <strong>Spreadsheet Status:</strong> Healthy (All allocations conform to planning rules)</span>`;
                        $('#matrix-grid-table').css('border-color', '#cbd5e1');
                        $('button[type="submit"]').prop('disabled', false);
                    }

                    $statusBar.html(html);
                }

                runDiagnostics();
            });
        }

        initValidationEngine();
    })();
</script>