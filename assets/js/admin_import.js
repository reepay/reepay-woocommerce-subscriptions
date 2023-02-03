jQuery(function ($) {
    if (!window.reepayImport) {
        return;
    }

    if (!_ || !_.template) {
        console.error('Error with _.template function');
    }

    const config = window.reepayImport;
    config.sessionStorageKey = 'reepay_subscriptions_import_data';

    const $formTables = $('.form-table');

    const $importForm = $('.js-reepay-import-form');
    const $submitBtn = $importForm.find('input[type="submit"]');

    const $viewImportForm = $('.js-reepay-import-form-view');
    const $dataTableContainer = $viewImportForm.find('.js-reepay-import-table-container');
    const tableTemplate = _.template($('#tmpl-reepay-subscriptions-import-data-table').html());

    showImportTables();

    $formTables.on('change', '.reepay-import__row--main input', function () {
        const $this = $(this);
        const $formTable = $this.parents('.form-table');

        $formTable.find('.reepay-import__row--sub')
            .toggle(this.checked)
            .find('input')
            .prop('checked', false)
            .filter('input[name$="[all]"]')
            .prop('checked', this.checked);

        const $checkedCheckboxes = $formTables.find('.reepay-import__row--main input:checked');
        $submitBtn.prop('disabled', !$checkedCheckboxes.length);

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
            const $checkedCheckboxes = $formTable.find('input:checked');
            if ($checkedCheckboxes.length <= 1) {
                $checkedCheckboxes.prop('checked', false).trigger('change');
            }
        }
    });

    $importForm.on('submit', function (e) {
        e.preventDefault();

        const $this = $(this);

        $this.block();

        $.ajax({
            url: config.urls.get_items,
            data: $this.serialize(),
            method: 'GET',
            beforeSend: function (xhr) {

            },
            success: function (response) {
                showImportTables(response.data)
            },
            error: function (request, status, error) {
                alert('Request error. Try again')
            },
            complete: function () {
                $this.unblock();
            },
        })
    })

    $viewImportForm.on('click', 'input[name="submit"]', function (e) {
        e.preventDefault();

        showImportForm();
    })

    $viewImportForm.on('submit', function (e) {
        e.preventDefault();

        alert('NOT READY!!!');
        showImportForm();
    })

    function showImportForm() {
        clearObjectsToImport();

        $importForm.show();
        $viewImportForm.hide();
    }

    function showImportTables(data = undefined) {
        if (data) {
            saveObjectsToImport(data);
        } else {
            data = loadObjectsToImport()
        }

        if(!data) {
            return;
        }

        renderTables(data);

        $importForm.hide();
        $viewImportForm.show();
    }

    function saveObjectsToImport(data) {
        const serializedData = JSON.stringify(data);

        try {
            sessionStorage.setItem(config.sessionStorageKey, serializedData)
        } catch (e) {
            console.warn('Data can not be saved to session storage')
        }
    }

    function loadObjectsToImport() {
        const serializedData = sessionStorage.getItem(config.sessionStorageKey)

        if (!serializedData) {
            return false
        }

        return JSON.parse(serializedData);
    }

    function clearObjectsToImport() {
        sessionStorage.removeItem(config.sessionStorageKey)
    }

    function renderTables(data) {
        $dataTableContainer.html('');

        $dataTableContainer.append(tableTemplate({
            title: 'Items 1',
            cols: {
                data: 'Data',
                test: '<h1>Daaaata</h1>'
            }
        }))
    }

    $.blockUI.defaults = $.extend($.blockUI.defaults, {
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    })
});