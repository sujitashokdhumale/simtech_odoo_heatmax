<?php
/***************************************************************************
*                                                                          *
*   © Simtech Development Ltd.                                             *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
***************************************************************************/

use Tygh\Addons\SdOdooIntegration\Helpers;
use Tygh\Tygh;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($mode == 'update') {
        if ($_REQUEST['addon'] == Helpers::SD_ODOO_INTEGRATION && !empty($_REQUEST['category_settings'])) {
            $default_category = isset($_REQUEST['category_settings']) ? $_REQUEST['category_settings'] : [];
            Helpers::updateDefaultCategorySettings($default_category);
        }
    }
}

if ($mode == 'update') {
    if ($_REQUEST['addon'] == Helpers::SD_ODOO_INTEGRATION) {
        Tygh::$app['view']->assign('default_category', Helpers::getDefaultCategorySettings());
    }
}
