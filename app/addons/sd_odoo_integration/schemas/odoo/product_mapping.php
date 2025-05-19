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
    // ODOO fields => CSCART fields
    'id' => 'odoo_product_id',
    'name' => 'product',
    'default_code' => 'product_code',
    'list_price' => 'price',
    'weight' => 'weight',
    'website_description' => 'full_description',
    'free_qty' => 'amount',
    'product_template_attribute_value_ids' => 'product_template_attribute_value_ids',
    'image_1920' => 'image',
    'type' => 'type_product',
];

return $schema;
