{{-- Small progressive enhancements shared by the membership screens. Every form also works without them. --}}
<script>
$(function () {
    // Hand over a role: show the chosen office's colleagues; keep the other lists hidden and disabled.
    var $handover = $('#govmem-handover');
    function openHandover(office, role, roleName, roleDesc, officeName) {
        $handover.find('[data-govmem-field=office]').val(office);
        $handover.find('[data-govmem-field=role]').val(role);
        $handover.find('[data-govmem-text=role-name]').text(roleName || '');
        $handover.find('[data-govmem-text=role-desc]').text(roleDesc || '');
        $handover.find('[data-govmem-text=office-name]').text(officeName || '');
        var $lists = $handover.find('[data-govmem-office]').prop('hidden', true).prop('disabled', true);
        var $list = $lists.filter('[data-govmem-office="' + office + '"]');
        $list.prop('hidden', false).prop('disabled', false);
        $handover.find('[data-govmem-nobody]').prop('hidden', $list.find('input[type=radio]:not(:disabled)').length > 0);
        $handover.modal('show');
    }
    $(document).on('click', '[data-govmem-handover]', function () {
        var d = this.dataset;
        openHandover(d.office, d.role, d.roleName, d.roleDesc, d.officeName);
    });
    if ($handover.is('[data-govmem-reopen]')) {
        var $source = $('[data-govmem-handover][data-office="' + $handover.data('office') + '"][data-role="' + $handover.data('role') + '"]').first();
        var s = $source.length ? $source[0].dataset : {};
        openHandover($handover.data('office'), $handover.data('role'), s.roleName, s.roleDesc, s.officeName);
    }

    // Type to narrow a list of people.
    $(document).on('input', '[data-govmem-filter]', function () {
        var needle = this.value.trim().toLowerCase();
        $(this).closest('fieldset, .modal-body').find('[data-govmem-name]').each(function () {
            this.hidden = needle !== '' && this.dataset.govmemName.indexOf(needle) === -1;
        });
    });

    // Sections that belong to one choice (data-govmem-when="field=value") show only when it is chosen.
    function syncChoices() {
        $('[data-govmem-when]').each(function () {
            var rule = this.dataset.govmemWhen.split('='), $checked = $('input[name="' + rule[0] + '"]:checked');
            var on = $checked.length > 0 && $checked.val() === rule[1];
            this.hidden = !on;
            $(this).find(':input').prop('disabled', !on);
        });
    }
    $(document).on('change', 'input[type=radio]', syncChoices);
    syncChoices();

    // Copy a code to the clipboard.
    $(document).on('click', '[data-govmem-copy]', function () {
        var button = this, text = this.dataset.govmemCopy;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () { button.textContent = button.dataset.copied; });
        }
    });

    // Fill a shared dialog from the button that opens it (data-govmem-dialog="#id", data-action, data-name).
    $(document).on('click', '[data-govmem-dialog]', function () {
        var $dialog = $(this.dataset.govmemDialog);
        $dialog.find('form').attr('action', this.dataset.action);
        $dialog.find('[data-govmem-text=name]').text(this.dataset.name || '');
        $dialog.modal('show');
    });

    // A dialog whose form failed validation opens again with its message.
    $('.modal[data-govmem-reopen]').not($handover).first().modal('show');
});
</script>
