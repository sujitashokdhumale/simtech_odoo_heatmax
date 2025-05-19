<?php
/***************************************************************************
*                                                                          *
*   © Simtech Development Ltd.                                             *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
***************************************************************************/

use Tygh\Tygh;

defined('BOOTSTRAP') or die('Access denied');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    return [CONTROLLER_STATUS_OK];
}

if ($mode === 'update') {
    if (!empty($_REQUEST['product_id'])) {
        Tygh::$app['view']->assign('vendor_read_only', true);
    }
}
if ($mode === 'manage' || $mode === 'p_subscr') {
    $selected_fields = Tygh::$app['view']->getTemplateVars('selected_fields');
    $key = array_search('company_id', array_column($selected_fields, 'field'));
    if ($key) {
        unset($selected_fields[$key]);
        Tygh::$app['view']->assign('selected_fields', $selected_fields);
    }
}