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
    if (isset($_REQUEST['odoo_pricelist_names'], $_REQUEST['odoo_pricelist_usergroups'])) {
        $mapping = array_combine((array) $_REQUEST['odoo_pricelist_names'], (array) $_REQUEST['odoo_pricelist_usergroups']);
        $mapping = array_filter($mapping, static function ($ug_id, $name) {
            return $name !== '' && $ug_id !== '';
        }, ARRAY_FILTER_USE_BOTH);
        $_REQUEST['company_data']['odoo_pricelist_mapping'] = json_encode($mapping);
    }

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

    $mapping_json = db_get_field('SELECT odoo_pricelist_mapping FROM ?:companies WHERE company_id = ?i', $_REQUEST['company_id']);
    $odoo_pricelist_mapping = json_decode($mapping_json, true);
    if (!is_array($odoo_pricelist_mapping)) {
        $odoo_pricelist_mapping = [];
    }
    $odoo_usergroups = db_get_array("SELECT usergroup_id, usergroup FROM ?:usergroups WHERE type = 'C'");

    Tygh::$app['view']->assign([
        'odoo_pricelist_mapping' => $odoo_pricelist_mapping,
        'odoo_usergroups' => $odoo_usergroups,
    ]);
}
