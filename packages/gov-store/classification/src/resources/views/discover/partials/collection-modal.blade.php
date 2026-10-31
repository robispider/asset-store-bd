<!-- Reusable Add to Collection Modal -->
<div class="modal fade gs-discover-modal" id="collectionMembershipModal" tabindex="-1" role="dialog" aria-labelledby="collectionModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div id="collection-modal-form">
                <div class="gs-discover-modal__head">
                    <div>
                        <h2 class="gs-discover-modal__title" id="collectionModalLabel">{{ __('classification::discover.modal.title') }}</h2>
                        <p class="gs-discover-muted">{{ __('classification::discover.modal.lead') }}</p>
                    </div>
                    <button class="gs-discover-iconbtn gs-discover-iconbtn--plain" type="button" data-dismiss="modal" aria-label="{{ __('classification::discover.modal.close') }}"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <div class="gs-discover-modal__body">
                    <p class="gs-discover-modal__selected">{{ __('classification::discover.modal.selected') }}: <strong id="collection-modal-count">0</strong></p>
                    <fieldset class="gs-discover-modal__fieldset">
                        <legend>{{ __('classification::discover.modal.choose') }}</legend>
                        <div class="gs-discover-input">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <label for="collection-modal-search" class="gs-sr-only">{{ __('classification::discover.modal.search') }}</label>
                            <input id="collection-modal-search" type="search" autocomplete="off" placeholder="{{ __('classification::discover.modal.search') }}">
                        </div>
                        <div class="gs-discover-modal__options" id="collection-options" aria-live="polite">
                            <p class="gs-discover-muted">{{ __('classification::discover.modal.loading') }}</p>
                        </div>
                    </fieldset>
                    <p class="gs-discover-note"><i class="fas fa-info-circle" aria-hidden="true"></i> <span>{{ __('classification::discover.modal.note') }}</span></p>
                    <div class="gs-discover-modal__error" id="collection-modal-error" role="alert" hidden></div>
                </div>
                <div class="gs-discover-modal__foot">
                    <button type="button" class="gs-btn gs-btn--secondary gs-btn--outline" data-dismiss="modal" id="btn-collection-cancel">{{ __('classification::discover.modal.cancel') }}</button>
                    <button type="button" class="gs-btn gs-btn--primary" id="btn-collection-save" disabled>{{ __('classification::discover.modal.save') }}</button>
                </div>
            </div>
            <div class="gs-discover-modal__done" id="collection-modal-done" role="status" hidden>
                <span class="gs-discover-modal__done-icon" aria-hidden="true"><i class="fas fa-check"></i></span>
                <h2 class="gs-discover-modal__title">{{ __('classification::discover.modal.success_title') }}</h2>
                <p class="gs-discover-muted" id="collection-modal-done-body"></p>
                <button type="button" class="gs-btn gs-btn--primary" id="btn-collection-done">{{ __('classification::discover.modal.done') }}</button>
            </div>
        </div>
    </div>
</div>

@php
    $collectionModalText = [
        'none' => __('classification::discover.modal.none'),
        'loadError' => __('classification::discover.modal.load_error'),
        'noMatch' => __('classification::discover.modal.no_match'),
        'save' => __('classification::discover.modal.save'),
        'saving' => __('classification::discover.modal.saving'),
        'error' => __('classification::discover.modal.error'),
        'empty' => __('classification::discover.modal.empty'),
        'count' => __('classification::collections.count', ['count' => ':count']),
        'success' => __('classification::discover.modal.success_body', ['added' => ':added', 'total' => ':total']),
    ];
@endphp
<script>
    let collectionCodesQueue = [];
    const collectionModalText = @json($collectionModalText);

    function renderCollectionOptions(collections) {
        const box = document.getElementById('collection-options');
        box.innerHTML = '';
        if (!collections.length) {
            const empty = document.createElement('p');
            empty.className = 'gs-discover-muted';
            empty.textContent = collectionModalText.none;
            box.appendChild(empty);
            return;
        }
        collections.forEach(function (col) {
            const option = document.createElement('label');
            option.className = 'gs-discover-option';
            option.dataset.filterText = String(col.name).toLowerCase();
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'collection_id';
            radio.value = col.id;
            const text = document.createElement('span');
            text.className = 'gs-discover-option__text';
            const name = document.createElement('span');
            name.className = 'gs-discover-option__name';
            name.textContent = col.name;
            const meta = document.createElement('span');
            meta.className = 'gs-discover-option__meta';
            meta.textContent = collectionModalText.count.replace(':count', col.count ?? 0);
            text.append(name, meta);
            option.append(radio, text);
            box.appendChild(option);
        });
        const noMatch = document.createElement('p');
        noMatch.className = 'gs-discover-muted';
        noMatch.id = 'collection-options-no-match';
        noMatch.textContent = collectionModalText.noMatch;
        noMatch.hidden = true;
        box.appendChild(noMatch);
    }

    function triggerAddToCollection(codes) {
        collectionCodesQueue = Array.isArray(codes) ? codes : [codes];

        if (collectionCodesQueue.length === 0) {
            alert(collectionModalText.empty);
            return;
        }

        document.getElementById('collection-modal-error').hidden = true;
        document.getElementById('collection-modal-form').hidden = false;
        document.getElementById('collection-modal-done').hidden = true;
        document.getElementById('collection-modal-search').value = '';
        const save = document.getElementById('btn-collection-save');
        save.disabled = true;
        save.textContent = collectionModalText.save;
        document.getElementById('btn-collection-cancel').disabled = false;
        document.getElementById('collection-modal-count').textContent = collectionCodesQueue.length;

        jQuery('#collectionMembershipModal').modal('show');

        jQuery.ajax({
            url: '{{ route("gov.catalog.discover.collections.api.list") }}',
            type: 'GET',
            success: function (res) {
                renderCollectionOptions(res.success ? res.collections : []);
            },
            error: function () {
                const box = document.getElementById('collection-options');
                box.innerHTML = '';
                const failed = document.createElement('p');
                failed.className = 'gs-discover-muted';
                failed.textContent = collectionModalText.loadError;
                box.appendChild(failed);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const options = document.getElementById('collection-options');
        const save = document.getElementById('btn-collection-save');

        options.addEventListener('change', function () {
            save.disabled = !options.querySelector('input[name="collection_id"]:checked');
        });

        document.getElementById('collection-modal-search').addEventListener('input', function (event) {
            const text = event.target.value.trim().toLowerCase();
            let shown = 0;
            options.querySelectorAll('.gs-discover-option').forEach(function (option) {
                option.hidden = text && option.dataset.filterText.indexOf(text) === -1;
                if (!option.hidden) shown++;
            });
            const noMatch = document.getElementById('collection-options-no-match');
            if (noMatch) noMatch.hidden = shown > 0;
        });

        document.getElementById('btn-collection-done').addEventListener('click', function () {
            window.location.reload();
        });

        save.addEventListener('click', function () {
            const checked = options.querySelector('input[name="collection_id"]:checked');
            if (!checked) return;

            save.disabled = true;
            save.textContent = collectionModalText.saving;
            document.getElementById('btn-collection-cancel').disabled = true;
            document.getElementById('collection-modal-error').hidden = true;

            jQuery.ajax({
                url: '{{ route("gov.catalog.discover.collections.api.add-nodes") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    collection_id: checked.value,
                    codes: collectionCodesQueue
                },
                success: function (res) {
                    document.getElementById('collection-modal-done-body').textContent = collectionModalText.success
                        .replace(':added', res.added_count ?? 0)
                        .replace(':total', res.total_expanded ?? 0);
                    document.getElementById('collection-modal-form').hidden = true;
                    document.getElementById('collection-modal-done').hidden = false;
                },
                error: function (xhr) {
                    save.disabled = false;
                    save.textContent = collectionModalText.save;
                    document.getElementById('btn-collection-cancel').disabled = false;
                    const error = document.getElementById('collection-modal-error');
                    error.textContent = (xhr.responseJSON && xhr.responseJSON.message) || collectionModalText.error;
                    error.hidden = false;
                }
            });
        });
    });
</script>
