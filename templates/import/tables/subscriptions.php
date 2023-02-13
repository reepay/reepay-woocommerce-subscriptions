<script type="text/template" id="tmpl-reepay-subscriptions-import-data-table-subscriptions">
    <h3> Subscriptions (<%= amount %>)</h3>
    <table class="wp-list-table widefat fixed striped table-view-list reepay-import-table js-reepay-import-table" data-type="subscriptions">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                <input id="cb-select-all-1" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-subscription-handle">Subscription handle</th>
            <th scope="col" class="manage-column column-customer">Customer</th>
            <th scope="col" class="manage-column column-plan">Plan</th>
            <th scope="col" class="manage-column column-status">Status</th>
            <th scope="col" class="manage-column column-dates">Dates</th>

            <th scope="col" class="manage-column column-message">Import Message</th>
        </tr>
        </thead>

        <tbody>
        <% if(!Object.keys(rows).length) { %>
            <tr class="no-items"><td class="colspanchange" colspan="7">No subscriptions found</td></tr>
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
                    <strong>Customer does not exist in this store</strong>
                    <% } %>
                </td>

                <td class="column-data column-plan">
                    <%= data.plan %>
                </td>

                <td class="column-data column-status">
                    <% if (data.is_cancelled) { %>
                    Status: cancelled
                    <% } else { %>
                    Status: <%= data.state %>
                    <% } %>

                    <% if (data.dunning_invoices) { %>
                    <br>
                    Dunning invoices: <%= data.dunning_invoices %>
                    <% } %>
                </td>

                <td class="column-data column-dates">
                    Created: <%= data.created %>
                    <br>
                    <hr>
                    Activated: <%= data.activated %>
                    <br>
                    <hr>
                    Start: <%= data.start_date %>
                    <br>
                    <hr>
                    First period start: <%= data.first_period_start %>
                    <br>
                    <hr>
                    Next period start: <%= data.next_period_start %>
                </td>

                <td class="column-data column-message js-column-message">Ready to import</td>
            </tr>
        <% }); %>
        </tbody>

        <tfoot>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-2">Select All</label>
                <input id="cb-select-all-2" type="checkbox" checked>
            </td>

            <th scope="col" class="manage-column column-subscription-handle">Subscription handle</th>
            <th scope="col" class="manage-column column-customer">Customer</th>
            <th scope="col" class="manage-column column-plan">Plan</th>
            <th scope="col" class="manage-column column-status">Status</th>
            <th scope="col" class="manage-column column-dates">Dates</th>

            <th scope="col" class="manage-column column-message">Import Message</th>
        </tr>
        </tfoot>

    </table>
</script>