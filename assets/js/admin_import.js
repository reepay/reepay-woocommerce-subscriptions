jQuery(function ($) {
    const $formTables = $('.form-table');

    $formTables.on('change', '.reepay-import__row--main input', function () {
        const $this = $(this);
        const $formTable = $this.parents('.form-table');

        $formTable.find('.reepay-import__row--sub')
            .toggle(this.checked)
            .find('input')
            .prop('checked', false)
            .filter('input[name$="[all]"]')
            .prop('checked', this.checked);

    }).find('.reepay-import__row--main input').trigger('change');

    $formTables.on('change', '.reepay-import__row--sub input', function () {
        const $this = $(this);
        const $formTable = $this.parents('.form-table');

        if (this.checked) { //may be active either option all or others
            if ($this.attr('name').includes('[all]')) {
                let $tr = $this.parents('tr').next();

                do {
                    const $checkbox = $tr.find('input');

                    $checkbox.prop('checked', false);

                    $tr = $tr.next();
                } while ($tr.length)
            } else {
                $formTable.find('input[name$="[all]"]').prop('checked', false);
            }
        } else { //disable group if options not selected
            const checkedCheckboxes = $formTable.find('input:checked');
            if (checkedCheckboxes.length <= 1) {
                checkedCheckboxes.prop('checked', false).trigger('change');
            }
        }
    });
});