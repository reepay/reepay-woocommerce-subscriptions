// Import SCSS entry file so that webpack picks up changes
import './index.scss';



/**
 * External dependencies
 */

import { addFilter } from '@wordpress/hooks';


addFilter(
    'woocommerce_admin_report_table',
    'woocommerce',
    ( reportTableData ) => {
        if ( reportTableData.endpoint !== 'customers' ) {
            return reportTableData;
        }

        reportTableData.headers = [
            ...reportTableData.headers,
            {
                label: 'Customer handle',
                key: 'customer_id',
            },
        ];

        if (
            ! reportTableData.items ||
            ! reportTableData.items.data ||
            ! reportTableData.items.data.length
        ) {
            return reportTableData;
        }

        const newRows = reportTableData.rows.map( ( row, index ) => {
            const customer = reportTableData.items.data[ index ];
            //console.log(customer);
            //console.log(Object.keys(roles_list));


            const newRow = [
                ...row,
                {
                    display: 'customer-' + customer.user_id,
                    value: 'customer-' + customer.user_id,
                },
            ];
            return newRow;
        } );

        reportTableData.rows = newRows;

        return reportTableData;
    }
);
