jQuery(function ($) {
    const $table = $('.form-table');

    $table.on('change', '.reepay-import__row--main input', function () {
        const $this = $(this);
        let $tr = $this.parents('tr').next();

        do {
            $tr.toggle(this.checked);
            const $checkbox = $tr.find('input');

            if (this.checked) {
                if ($checkbox.attr('name').includes('[all]')) {
                    $checkbox.prop('checked', true);
                }
            } else {
                $checkbox.prop('checked', false);
            }

            $tr = $tr.next();
        } while ($tr.length)
    }).find('.reepay-import__row--main input').trigger('change');

    $table.on('change', '.reepay-import__row--sub input', function () {
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