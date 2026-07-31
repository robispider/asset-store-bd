<!-- Form Serialization Compiler -->
<script>
    (function() {
        function initSerializerEngine() {
            if (typeof window.jQuery === 'undefined') {
                setTimeout(initSerializerEngine, 50);
                return;
            }

            window.jQuery(function($) {
                var $table = $('#matrix-grid-table');
                var $form = $table.closest('form');

                $form.on('submit', function() {
                    var $container = $('#matrix-hidden-inputs');
                    $container.empty(); 

                    var specificity = $('input[name="specificity_level"]:checked').val();
                    if (specificity !== '3_MATRIX') {
                        return; 
                    }

                    var state = window.GovStoreMatrix.state;

                    // Serialize Columns
                    state.columns.forEach(function(col) {
                        $container.append(`<input type="hidden" name="matrix_categories[]" value="${col.category_id}">`);
                        if (col.economic_code) {
                            $container.append(`<input type="hidden" name="matrix_economic_codes[${col.category_id}]" value="${col.economic_code}">`);
                        }
                    });

                    // Serialize Rows
                    state.rows.forEach(function(row, rIndex) {
                        $container.append(`<input type="hidden" name="matrix_locations[${rIndex}]" value="${row.location_id}">`);

                        state.columns.forEach(function(col) {
                            var cellVal = state.values[row.uuid][col.uuid] || 0;
                            $container.append(`<input type="hidden" name="matrix_values[${rIndex}][${col.category_id}]" value="${cellVal}">`);
                        });
                    });
                });
            });
        }

        initSerializerEngine();
    })();
</script>