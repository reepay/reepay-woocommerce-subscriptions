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
    const $dataTablesContainer = $viewImportForm.find('.js-reepay-import-table-container');
    const tableTemplates = config.objects.reduce(
        (templates, objectName) => {
            templates[objectName] = _.template($(`#tmpl-reepay-subscriptions-import-data-table-${objectName}`).html())
            return templates;
        }, {})

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
            url: config.urls.get_objects,
            data: $this.serialize(),
            method: 'GET',
            beforeSend: function (xhr) {

            },
            success: function (response) {
                if (response.success) {
                    showImportTables(response.data)
                } else {
                    alert(JSON.stringify(response.data));
                }
            },
            error: function (request, status, error) {
                alert('Request error. Try again')
            },
            complete: function () {
                $this.unblock();
            },
        })
    })

    $viewImportForm.on('click', 'p.submit a.js-back', function (e) {
        e.preventDefault();

        showImportForm();
    })

    $viewImportForm.on('submit', function (e) {
        e.preventDefault();

        const $this = $(this);

        $this.block();

        const data = {
            selected: serializeImportTables()
        }

        $.ajax({
            url: config.urls.save_objects,
            data: data,
            method: 'POST',
            beforeSend: function (xhr) {

            },
            success: function (response) {
                if (response.success) {
                    finishImport(response.data)
                } else {
                    alert(response.data.error);
                }
            },
            error: function (request, status, error) {
                alert('Request error. Try again')
            },
            complete: function () {
                $this.unblock();
            },
        })
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

        if (!data) {
            return;
        }

        renderTables(data);

        $importForm.hide();
        $viewImportForm.show();
        $viewImportForm.find('input[type="submit"]').show();
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
        $dataTablesContainer.html('');

        Object.entries(data).forEach(([objectType, data]) => {
            if (tableTemplates[objectType]) {
                $dataTablesContainer.append(tableTemplates[objectType]({
                    rows: data
                }))
            } else {
                console.warn('Wrong object type in tables render', objectType);
            }
        })
    }

    function serializeImportTables() {
        const data = {};

        $.each($('.js-reepay-import-table[data-type]'), function () {
            const $this = $(this);
            const objectType = $this.attr('data-type');

            data[objectType] = []

            $.each($this.find('input:checked'), function () {
                if (this.name) {
                    data[objectType].push(this.name)
                }
            })
        })

        return data;
    }

    function finishImport(importedObjects) {
        // $viewImportForm.find('input[type="submit"]').hide();

        Object.entries(importedObjects).forEach(([objectType, importResults]) => {
            const $table = $(`.js-reepay-import-table[data-type="${objectType}"]`)

            Object.entries(importResults)
                .forEach(([handle, status]) => {
                        const $tr = $table
                            .find(`input[name="${handle}"]`)
                            .parents('tr');

                        const $colMessage = $tr.find('.js-column-message');

                        if (status === true) {
                            $tr.addClass( 'success' );
                            $colMessage.html( 'Successfully imported' )
                        } else {
                            $tr.addClass( 'error' );
                            $colMessage.html( status )
                        }
                    }
                )
        })
    }

    $.blockUI.defaults = $.extend($.blockUI.defaults, {
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    })
});