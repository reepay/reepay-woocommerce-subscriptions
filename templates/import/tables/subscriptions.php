<script type="text/template" id="tmpl-reepay-subscriptions-import-data-table-subscriptions">
    <h3> <?php _e('Customers', 'reepay-subscriptions-for-woocommerce') ?>Subscriptions (<%= amount %>)</h3>
    <table class="wp-list-table widefat fixed striped table-view-list reepay-import-table js-reepay-import-table" data-type="subscriptions">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'reepay-subscriptions-for-woocommerce') ?></label>
                <input id="cb-select-all-1" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-subscription-handle"><?php _e('Subscription handle', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-customer"><?php _e('Customer', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-plan"><?php _e('Plan', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-status"><?php _e('Status', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-dates"><?php _e('Dates', 'reepay-subscriptions-for-woocommerce') ?></th>

            <th scope="col" class="manage-column column-message"><?php _e('Import Message', 'reepay-subscriptions-for-woocommerce') ?></th>
        </tr>
        </thead>

        <tbody>
        <% if(!Object.keys(rows).length) { %>
            <tr class="no-items"><td class="colspanchange" colspan="7"><?php _e('No subscriptions found', 'reepay-subscriptions-for-woocommerce') ?></td></tr>
        <% } %>

        <% _(rows).forEach(function(data, handle) { %>
            <tr class="">
                <th scope="row" class="check-column">
                    <input id="cb-select-<%= handle %>" type="checkbox" name="<%= handle %>" checked>
                </th>

                <td class="column-data column-subscription-handle">
                    <%= handle %>
                </td>

                <td class="column-data column-customer">
                    <%= data.customer %>

                    <br>

                    <% if (data.customer_email !== undefined) { %>
                    <%= data.customer_email %>
                    <% } else { %>
                    <strong><?php _e('Customer does not exist in this store', 'reepay-subscriptions-for-woocommerce') ?></strong>
                    <% } %>
                </td>

                <td class="column-data column-plan">
                    <%= data.plan %>
                </td>

                <td class="column-data column-status">
                    <% if (data.is_cancelled) { %>
	                <?php _e('Status', 'reepay-subscriptions-for-woocommerce') ?>: cancelled
                    <% } else { %>
	                <?php _e('Status', 'reepay-subscriptions-for-woocommerce') ?>: <%= data.state %>
                    <% } %>

                    <% if (data.dunning_invoices) { %>
                    <br>
	                <?php _e('Dunning invoices', 'reepay-subscriptions-for-woocommerce') ?>: <%= data.dunning_invoices %>
                    <% } %>
                </td>

                <td class="column-data column-dates">
	                <?php _e('Created', 'reepay-subscriptions-for-woocommerce') ?>: <%= data.created %>
                    <br>
                    <hr>
	                <?php _e('Activated', 'reepay-subscriptions-for-woocommerce') ?>: <%= data.activated %>
                    <br>
                    <hr>
	                <?php _e('Start', 'reepay-subscriptions-for-woocommerce') ?>: <%= data.start_date %>
                    <br>
                    <hr>
	                <?php _e('First period start', 'reepay-subscriptions-for-woocommerce') ?>: <%= data.first_period_start %>
                    <br>
                    <hr>
	                <?php _e('Next period start', 'reepay-subscriptions-for-woocommerce') ?>: <%= data.next_period_start %>
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

            <th scope="col" class="manage-column column-subscription-handle"><?php _e('Subscription handle', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-customer"><?php _e('Customer', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-plan"><?php _e('Plan', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-status"><?php _e('Status', 'reepay-subscriptions-for-woocommerce') ?></th>
            <th scope="col" class="manage-column column-dates"><?php _e('Dates', 'reepay-subscriptions-for-woocommerce') ?></th>

            <th scope="col" class="manage-column column-message"><?php _e('Import Message', 'reepay-subscriptions-for-woocommerce') ?></th>
        </tr>
        </tfoot>

    </table>
</script>