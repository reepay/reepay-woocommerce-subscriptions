<?php
/**
 * @var string $title
 * @var string $type
 * @var array  $objects
 */

?>
<script type="text/template" id="tmpl-reepay-subscriptions-import-data-table">
    <h3> <%= title %> </h3>
    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                <input id="cb-select-all-1" type="checkbox">
            </td>

            <% _(cols).forEach(function(title, key) { %>
                <th scope="col" id="<%- key %>" class="manage-column column-<%- key %>"><%= title %></th>
            <% }); %>
        </tr>
        </thead>

        <tbody id="the-list">
        <tr class="">
            <th scope="row" class="check-column">
                <label class="screen-reader-text" for="cb-select-11033">Select Simple product (access with woo mems)</label>
                <input id="cb-select-11033" type="checkbox" name="post[]" value="11033">
            </th>
            <td class="column-data">
                data
            </td>

        </tr>
        </tbody>

        <tfoot>
        <tr>
            <td id="cb" class="manage-column column-cb check-column">
                <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                <input id="cb-select-all-1" type="checkbox">
            </td>

            <% _(cols).forEach(function(title, key) { %>
            <th scope="col" id="<%- key %>" class="manage-column column-<%- key %>"><%= title %></th>
            <% }); %>
        </tr>
        </tfoot>

    </table>
</script>