<?php
/***************************************************************************
 *                                                                          *
 *   © Simtech Development Ltd.                                             *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 ***************************************************************************/

use Tygh\Addons\SdOdooIntegration\OdooCron;
use Tygh\Enum\Addons\SdOdooIntegration\OdooCronStatus;
use Tygh\Enum\Addons\SdOdooIntegration\OdooEntities;
use Tygh\Registry;
use Tygh\Tygh;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    return [CONTROLLER_STATUS_OK];
}

if ($mode == 'update' || $mode == 'add') {
    Registry::set('navigation.tabs.odoo', [
        'title' => 'Odoo API',
        'js' => true,
    ]);
}

if (
    $mode == 'update'
    && !empty($_REQUEST['company_id'])
) {
    $odoo_entity_names = OdooEntities::getAll();
    $odoo_cron_status_names = OdooCronStatus::getAll();
    $available_shippings = fn_get_shippings_names($_REQUEST['company_id']);
    $statistic_cron = OdooCron::getStatisticsCronJob($_REQUEST['company_id']);
    Tygh::$app['view']->assign([
        'odoo_entity_names' => $odoo_entity_names,
        'odoo_cron_status_names' => $odoo_cron_status_names,
        'available_shippings' => $available_shippings,
        'statistic_cron' => $statistic_cron,
    ]);
}
