$(function () {
    const node = document.getElementById('storeops-rules-config');
    if (!node) return;
    const config = JSON.parse(node.textContent), text = config.labels;
    function failure() { window.alert(text.failure); }
    function loading(target) { $(target).empty().append($('<p>').addClass('text-muted').text(text.loading)); }
    $(document).on('submit', '.storeops-unassign', function (event) {
        if (!window.confirm(text.unassign_confirm)) event.preventDefault();
    });
    if (config.page === 'index') {
        const hub = $('#hub_dashboard_wrapper').prop('outerHTML');
        // Keep history in this page's memory; do not retain another administrator's target data.
        let recent = [];
        function remember(id, type, name) {
            recent = [{id,type,name}, ...recent.filter(item => !(item.id === id && item.type === type))].slice(0, 5);
            const list = $('#recent_targets_list').empty();
            recent.forEach(item => list.append($('<li>').addClass('sidebar-menu-item recent-item').data(item)
                .append($('<a>').attr('href','#').text(item.name))));
        }
        $('#recent_targets_list').empty().append($('<li>').addClass('text-muted').text(text.no_recent));
        let inspectorRequest;
        $(document).on('click', '.recent-item, .search-result-row, .direct-inspect-btn', function (event) {
            event.preventDefault();
            const item = $(this), id = item.data('id'), type = item.data('type'), name = item.data('name') || item.text().trim();
            $('.search-results-dropdown').hide();
            if (type === 'POLICY') { window.location.href = config.urls.edit.replace('__ID__', encodeURIComponent(id)); return; }
            remember(id,type,name);
            inspectorRequest?.abort(); loading('#workspace_pane');
            inspectorRequest = $.get(config.urls.inspector, {target_id:id,target_type:type})
                .done(html => $('#workspace_pane').html(html)).fail(xhr => { if (xhr.statusText !== 'abort') failure(); });
        });
        let timer, searchRequest;
        $(document).on('input', '#sidebarSearch, #centralSearchInput', function () {
            const input = $(this), dropdown = input.attr('id') === 'sidebarSearch' ? $('#sidebarDropdown') : $('#centralDropdown');
            const query = input.val().trim(); clearTimeout(timer); searchRequest?.abort();
            if (query.length < 2) { dropdown.empty().hide(); return; }
            timer = setTimeout(() => {
                searchRequest = $.get(config.urls.search, {q:query}).done(data => {
                    dropdown.empty(); let count = 0;
                    [['categories','CATEGORY'],['locations','LOCATION'],['policies','POLICY']].forEach(([group,type]) => {
                        if (!data[group]?.length) return;
                        dropdown.append($('<div>').addClass('dropdown-section-title').text(text[group]));
                        data[group].forEach(item => {
                            count++;
                            dropdown.append($('<a>').attr({href:'#',tabindex:0}).addClass('search-result-row')
                                .data({id:item.id,type,name:item.name}).text(item.name));
                        });
                    });
                    if (!count) dropdown.append($('<p>').addClass('text-muted').text(text.no_match));
                    dropdown.show();
                }).fail(xhr => { if (xhr.statusText !== 'abort') failure(); });
            },300);
        });
        $(document).on('click', function (event) {
            if (!$(event.target).closest('.sidebar-search, .hub-search-wrapper').length) $('.search-results-dropdown').hide();
        });
        $(document).on('click','#btn_back_to_hub', () => $('#workspace_pane').html(hub));
        $(document).on('click','#sidebar_categories_trigger, #card_categories', () => $('#workspace_pane').html($('#portal_categories_dir').html()));
        $(document).on('click','#sidebar_offices_trigger, #card_offices', () => $('#workspace_pane').html($('#portal_offices_dir').html()));
    }
    if (config.page === 'edit') {
        $('.behavior-radio').on('change', function () {
            $(this).closest('.rule-row').find('.config-panel').toggleClass('active',this.value === 'ENFORCE').toggle(this.value === 'ENFORCE');
        });
        $('#btn_trigger_publish').on('click', function () {
            const button = $(this), label = button.text(); button.text(text.analyzing).prop('disabled',true);
            $.get(config.urls.impact).done(data => {
                $('#impact_categories').text(data.categories_affected); $('#impact_drafts').text(data.drafts_affected);
                $('#risk_alert_panel').toggle(data.risk_level !== 'LOW').removeClass('alert-info alert-warning alert-danger')
                    .addClass(data.risk_level === 'HIGH' ? 'alert-danger' : 'alert-warning');
                $('#risk_desc').text(text.draft_impact); $('#publishModal').modal('show');
            }).fail(failure).always(() => button.text(label).prop('disabled',false));
        });
    }
    if (config.page === 'simulator') $('#simulatorForm').on('submit', function (event) {
        event.preventDefault();
        if (!$('[name="location_id"]').val() || !$('[name="category_id"]').val()) { window.alert(text.simulation_required); return; }
        loading('#simulation_results'); $.get(config.urls.simulator,$(this).serialize()).done(html => $('#simulation_results').html(html)).fail(failure);
    });
    if (config.page === 'create') {
        let step = 1;
        function show(value) {
            step = value; $('.wizard-step-pane').removeClass('active'); $('#pane_step_'+step).addClass('active');
            $('.step-node').removeClass('active complete');
            for (let i=1; i<=3; i++) $('#badge_step_'+i).addClass(i<step ? 'complete' : i===step ? 'active' : '');
            $('#btn_prev').css('visibility',step===1 ? 'hidden' : 'visible'); $('#btn_next').toggle(step!==3); $('#btn_submit').toggle(step===3);
        }
        $('#btn_next').on('click', () => {
            if (step===2 && !$('#input_rule_name')[0].reportValidity()) return;
            show(Math.min(step+1,3));
        });
        $('#btn_prev').on('click', () => show(Math.max(step-1,1)));
    }
});
