<?php
/****************************************************************************
 *                                                                          *
 *   © Simtech Development Ltd.                                             *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 ***************************************************************************/

defined('BOOTSTRAP') or die('Access denied');

$schema = [
    // ODOO fields for import
    'state',
    'date_order',
    'partner_id',
    'partner_shipping_id',
    'order_line',
    'display_name',
    'invoice_status',
    'payment_state',
    'picking_ids',
    'write_date',
];

return $schema;
