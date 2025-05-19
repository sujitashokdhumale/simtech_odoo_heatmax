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
    // ODOO fields transfers
    'state',
    'sale_id',
    'carrier_tracking_ref',
    'date_done',
    'date',
];

return $schema;
