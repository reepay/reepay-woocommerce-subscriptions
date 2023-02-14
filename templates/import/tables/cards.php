<script type="text/template" id="tmpl-reepay-subscriptions-import-data-table-cards">
    <h3> <?php _e('Cards', 'reepay-subscriptions-for-woocommerce') ?> (<%= amount %>)</h3>
    <table class="wp-list-table widefat fixed striped table-view-list reepay-import-table js-reepay-import-table" data-type="cards">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'reepay-subscriptions-for-woocommerce') ?></label>
                <input id="cb-select-all-1" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name"><?php _e('Card', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-type"><?php _e('Type', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-status"><?php _e('Status', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-expire"><?php _e('Expire at', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-customer"><?php _e('Customer', 'reepay-subscriptions-for-woocommerce') ?></th>

            <th scope="col" class="manage-column column-message"><?php _e('Import Message', 'reepay-subscriptions-for-woocommerce') ?></th>
        </tr>
        </thead>

        <tbody>
        <% if(!Object.keys(rows).length) { %>
            <tr class="no-items"><td class="colspanchange" colspan="7"><?php _e('No cards found', 'reepay-subscriptions-for-woocommerce') ?></td></tr>
        <% } %>

        <% _(rows).forEach(function(data, card_id) { %>
            <tr class="">
                <th scope="row" class="check-column">
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

                <td class="column-data column-message js-column-message"><?php _e('Ready to import', 'reepay-subscriptions-for-woocommerce') ?></td>
            </tr>
        <% }); %>
        </tbody>

        <tfoot>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-2"><?php _e('Select All', 'reepay-subscriptions-for-woocommerce') ?></label>
                <input id="cb-select-all-2" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name"><?php _e('Card', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-type"><?php _e('Type', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-status"><?php _e('Status', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-expire"><?php _e('Expire at', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-customer"><?php _e('Customer', 'reepay-subscriptions-for-woocommerce') ?></th>

            <th scope="col" class="manage-column column-message"><?php _e('Import Message', 'reepay-subscriptions-for-woocommerce') ?></th>
        </tr>
        </tfoot>

    </table>
</script>