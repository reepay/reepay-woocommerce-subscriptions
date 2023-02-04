<?php
/**
 * @var string $title
 * @var string $type
 * @var array  $objects
 */

?>
<script type="text/template" id="tmpl-reepay-subscriptions-import-data-table-customers">
    <h3> <%= title %> </h3>
    <table class="wp-list-table widefat fixed striped table-view-list reepay-import-table js-reepay-import-table" data-type="customers">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                <input id="cb-select-all-1" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name">Name</th>
            <th scope="col" class="manage-column column-email">Email</th>
            <th scope="col" class="manage-column column-reepay-handle">Reepay Handle</th>
            <th scope="col" class="manage-column column-message">Import Message</th>
        </tr>
        </thead>

        <tbody>
        <% _(rows).forEach(function(data, handle) { %>
            <tr class="">
                <th scope="row" class="check-column">
                    <label class="screen-reader-text" for="cb-select-<%= handle %>">Select Simple product (access with woo mems)</label>
                    <input id="cb-select-<%= handle %>" type="checkbox" name="<%= handle %>" checked>
                </th>
                <td class="column-data column-name">
                    <%= data.first_name %> <%= data.last_name %>
                </td>
                <td class="column-data column-email">
                    <%= data.email %>
                </td>
                <td class="column-data column-reepay-handle">
                    <%= handle %>
                </td>
                <td class="column-data column-message js-column-message"></td>
            </tr>
        <% }); %>
        </tbody>

        <tfoot>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-2">Select All</label>
                <input id="cb-select-all-2" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name">Name</th>
            <th scope="col" class="manage-column column-email">Email</th>
            <th scope="col" class="manage-column column-reepay-handle">Reepay Handle</th>
            <th scope="col" class="manage-column column-message">Import Message</th>

        </tr>
        </tfoot>

    </table>
</script>