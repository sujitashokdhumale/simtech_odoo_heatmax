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
use Tygh\Addons\SdOdooIntegration\OdooCron;
use Tygh\Addons\SdOdooIntegration\Services\OrderSyncService;
use Tygh\Addons\SdOdooIntegration\Services\PricelistSyncService;
use Tygh\Addons\SdOdooIntegration\Services\ProductSyncService;
use Tygh\Addons\SdOdooIntegration\Services\TransferSyncService;
use Tygh\Addons\SdOdooIntegration\Services\DeletingService;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\SiteArea;
use Tygh\Enum\UserTypes;
use Tygh\NotificationsCenter\NotificationsCenter;
use Tygh\Registry;
use Tygh\Tygh;

defined('BOOTSTRAP') or die('Access denied');

if ($mode == 'import') {
    if (
        !isset($_REQUEST['cron_password']) || empty($_REQUEST['cron_password']) ||
        $_REQUEST['cron_password'] !== Registry::get('settings.Security.cron_password')
    ) {
        return [CONTROLLER_STATUS_DENIED];
    }
    Registry::set('odoo_import', true, true);
    $companies = Helpers::getCompaniesForImport();
    $default_category = Helpers::getDefaultCategorySettings();
    if (empty($default_category)) {
        fn_log_event('general', 'runtime', [
            'message' => 'Default category not configured.',
        ]);
        fn_set_notification('E', __('error'), 'Default category not configured.');

        return [CONTROLLER_STATUS_OK];
    }
    $start_time = time();
    foreach ($companies as $company) {
        date_default_timezone_set('UTC');

        $product_sync_service = new ProductSyncService($company, $start_time);
        try {
            $product_sync_service->syncProducts();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }

        $pricelist_sync_service = new PricelistSyncService($company, $start_time);
        try {
            $pricelist_sync_service->syncPricelist();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }

        $order_sync_service = new OrderSyncService($company, $start_time);
        try {
            $order_sync_service->syncOrders();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }

        $transfer_sync_service = new TransferSyncService($company, $start_time);
        try {
            $transfer_sync_service->syncTransfer();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }
    }
    fn_log_event('general', 'runtime', [
        'message' => __('sd_odoo_integration.successfully_completed'),
    ]);

    return [CONTROLLER_STATUS_NO_CONTENT];
} elseif ($mode == 'import_all_product') {
    @set_time_limit(0);
    $parts = 0;
    fn_set_progress('set_scale', 5);
    $default_category = Helpers::getDefaultCategorySettings();
    if (empty($default_category)) {
        $msg = __('sd_odoo_integration.cron_odoo_empty_default_category');
        fn_log_event('general', 'runtime', [
            'message' => $msg,
        ]);
        fn_set_notification('E', __('error'), $msg);

        return [CONTROLLER_STATUS_OK];
    }
    Registry::set('odoo_import', true, true);
    $companies = Helpers::getCompaniesForImport();
    $start_time = time();
    foreach ($companies as $company) {
        OdooCron::resetLastLaunch($company['company_id']);

        $product_sync_service = new ProductSyncService($company, $start_time);
        try {
            $product_sync_service->syncProducts();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }

        $pricelist_sync_service = new PricelistSyncService($company, $start_time);
        try {
            $pricelist_sync_service->syncPricelist();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }

        $order_sync_service = new OrderSyncService($company, $start_time);
        try {
            $order_sync_service->syncOrders();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }

        $transfer_sync_service = new TransferSyncService($company, $start_time);
        try {
            $transfer_sync_service->syncTransfer();
            fn_log_event('general', 'runtime', [
                'message' => 'Product sync completed for Company ID: ' . $company['company_id'],
            ]);
        } catch (\Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }

        fn_log_event('general', 'runtime', [
            'message' => __('sd_odoo_integration.successfully_completed'),
        ]);
    }

    return [CONTROLLER_STATUS_NO_CONTENT];
} elseif ($mode == 'deleting') {
    if (
        !isset($_REQUEST['cron_password']) || empty($_REQUEST['cron_password']) ||
        $_REQUEST['cron_password'] !== Registry::get('settings.Security.cron_password')
    ) {
        return [CONTROLLER_STATUS_DENIED];
    }
    $companies = Helpers::getCompaniesForImport();
    foreach ($companies as $company) {
        $deleting = new DeletingService($company);
        $deleting->deletingEntities();
    }
    return [CONTROLLER_STATUS_NO_CONTENT];
} elseif ($mode == 'reset_status') {
    if ($_REQUEST['company_id']) {
        OdooCron::resetStatusCron($_REQUEST['company_id']);
        return [CONTROLLER_STATUS_NO_CONTENT];
    }
} elseif ($mode == 'check_connect') {
    if (defined('AJAX_REQUEST')) {
        try {
            $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($_REQUEST['company_id']);

            if ($current_connection) {
                $uid = $current_connection->getUid();
                if (is_numeric($uid)) {
                    Tygh::$app['view']->assign('checking_result', __('OK'));
                } else {
                    Tygh::$app['view']->assign('checking_result', __('sd_odoo_integration.failed_connection'));
                }
            } else {
                Tygh::$app['view']->assign('checking_result', __('sd_odoo_integration.failed_connection'));
            }
        } catch (Exception $e) {
            fn_log_event('general', 'runtime', [
                'message' => 'Odoo connection test failed: ' . $e->getMessage()
            ]);
            Tygh::$app['view']->assign('checking_result', __('sd_odoo_integration.failed_connection'));
        }

        Tygh::$app['view']->display('addons/sd_odoo_integration/views/manage_cron.tpl');
        return [CONTROLLER_STATUS_NO_CONTENT];
    }
}
