<script type="text/template" id="tmpl-reepay-subscriptions-import-data-table-customers">
    <h3> <?php
		_e( 'Customers', 'reepay-subscriptions-for-woocommerce' ) ?> (<%= amount %>)</h3>
    <table class="wp-list-table widefat fixed striped table-view-list reepay-import-table js-reepay-import-table"
           data-type="customers">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1"><?php
					_e( 'Select All', 'reepay-subscriptions-for-woocommerce' ) ?></label>
                <input id="cb-select-all-1" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name"><?php
				_e( 'Name 1', 'reepay-subscriptions-for-woocommerce' ) ?></th>
            <th scope="col" class="manage-column column-email"><?php
				_e( 'Email', 'reepay-subscriptions-for-woocommerce' ) ?></th>
            <th scope="col" class="manage-column column-reepay-handle"><?php
				_e( 'Billwerk+ Optimize Handle', 'reepay-subscriptions-for-woocommerce' ) ?></th>

            <th scope="col" class="manage-column column-message"><?php
				_e( 'Import Message', 'reepay-subscriptions-for-woocommerce' ) ?></th>
        </tr>
        </thead>

        <tbody>
        <% if(!Object.keys(rows).length) { %>
        <tr class="no-items">
            <td class="colspanchange" colspan="5"><?php
				_e( 'No customers found', 'reepay-subscriptions-for-woocommerce' ) ?></td>
        </tr>
        <% } %>

        <% _(rows).forEach(function(data, handle) { %>
        <tr class="<% if(data.debug) { %>skipped<% } %>">
            <th scope="row" class="check-column">
                <input id="cb-select-<%= handle %>"
                       type="checkbox"
                       name="<%= handle %>"
                <% if(data.debug) { %>
                disabled
                <% } else { %>
                checked
                <% } %>
                >
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
            <td class="column-data column-message js-column-message">
                <% if(data.debug) { %>
                <%= data.debug_message %>
                <% } else { %>
				<?php
				_e( 'Ready to import', 'reepay-subscriptions-for-woocommerce' ) ?>
                <% } %>
            </td>
        </tr>
        <% }); %>
        </tbody>

        <tfoot>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-2"><?php
					_e( 'Select All', 'reepay-subscriptions-for-woocommerce' ) ?></label>
                <input id="cb-select-all-2" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-name"><?php
				_e( 'Name', 'reepay-subscriptions-for-woocommerce' ) ?></th>
            <th scope="col" class="manage-column column-email"><?php
				_e( 'Email', 'reepay-subscriptions-for-woocommerce' ) ?></th>
            <th scope="col" class="manage-column column-reepay-handle"><?php
				_e( 'Billwerk+ Optimize Handle', 'reepay-subscriptions-for-woocommerce' ) ?></th>

            <th scope="col" class="manage-column column-message"><?php
				_e( 'Import Message', 'reepay-subscriptions-for-woocommerce' ) ?></th>
        </tr>
        </tfoot>

    </table>
</script>