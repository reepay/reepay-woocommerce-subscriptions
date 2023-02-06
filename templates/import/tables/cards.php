<?php
/**
 * @var string $title
 * @var string $type
 * @var array  $objects
 */

?>
<script type="text/template" id="tmpl-reepay-subscriptions-import-data-table-cards">
    <h3> Cards </h3>
    <table class="wp-list-table widefat fixed striped table-view-list reepay-import-table js-reepay-import-table" data-type="cards">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                <input id="cb-select-all-1" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name">Card</th>
            <th scope="col" class="manage-column column-type">Type</th>
            <th scope="col" class="manage-column column-status">Status</th>
            <th scope="col" class="manage-column column-expire">Expire at</th>
            <th scope="col" class="manage-column column-customer">Customer</th>

            <th scope="col" class="manage-column column-message">Import Message</th>
        </tr>
        </thead>

        <tbody>
        <% _(rows).forEach(function(data, card_id) { %>
            <tr class="">
                <th scope="row" class="check-column">
                    <label class="screen-reader-text" for="cb-select-<%= card_id %>">Select Simple product (access with woo mems)</label>
                    <input id="cb-select-<%= card_id %>" type="checkbox" name="<%= card_id %>" checked>
                </th>

                <td class="column-data column-name">
                    <%= data.masked_card %>
                </td>
                <td class="column-data column-type">
                    <%= data.transaction_card_type %>
                </td>
                <td class="column-data column-status">
                    <%= data.state %>
                </td>
                <td class="column-data column-expire">
                    <%= data.exp_date %>
                </td>
                <td class="column-data column-customer">
                    <%= data.customer_email %>
                    <br>
                    <%= data.customer %>
                </td>

                <td class="column-data column-message js-column-message"></td>
            </tr>
        <% }); %>
        </tbody>

        <tfoot>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                <input id="cb-select-all-1" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name">Card</th>
            <th scope="col" class="manage-column column-type">Type</th>
            <th scope="col" class="manage-column column-status">Status</th>
            <th scope="col" class="manage-column column-expire">Expire at</th>
            <th scope="col" class="manage-column column-customer">Customer</th>

            <th scope="col" class="manage-column column-message">Import Message</th>
        </tr>
        </tfoot>

    </table>
</script>