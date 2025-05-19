<?php
/***************************************************************************
*                                                                          *
*   © Simtech Development Ltd.                                             *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
***************************************************************************/

declare(strict_types=1);

namespace Tygh\Addons\SdOdooIntegration\HookHandlers;

use Tygh\Addons\SdOdooIntegration\Exceptions\OdooException;
use Tygh\Addons\SdOdooIntegration\Services\OrderSyncService;
use Tygh\Application;
use Tygh\Enum\Addons\SdOdooIntegration\OdooOrderStatuses;
use Tygh\Enum\Addons\SdOdooIntegration\OdooTransferStatuses;
use Tygh\Enum\OrderStatuses;
use Tygh\Enum\YesNo;
use Tygh\Registry;
use Tygh\Tygh;

/**
 * This class describes the hook handlers related to cart.
 */
class CartHookHandler
{
    /** @var Application */
    protected $app;

    /**
     * CartHookHandler constructor.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 'pre_get_cart_product_data' hook handler.
     *
     * @param string                          $hash             Unique product HASH
     * @param array<string, int|string|array> $product          Product data
     * @param bool                            $skip_promotion   Skip promotion calculation
     * @param array<string, int|string|array> $cart             Array of cart content and user information necessary for purchase
     * @param array<string, int|string|array> $auth             Array with authorization data
     * @param int                             $promotion_amount Amount of product in promotion (like Free products, etc)
     * @param array<string, string>           $fields           SQL query fields
     * @param string                          $join             JOIN statement
     * @param array<string, array>            $params           Array of additional params
     *
     * @see fn_add_product_to_cart()
     */
    public function preGetCartProductData($hash, $product, &$skip_promotion, $cart, $auth, $promotion_amount, &$fields, $join, $params): void
    {
        $fields[] = '?:products.odoo_product_id';
    }

    /**
     * 'get_cart_product_data_post' hook handler.
     *
     * @param string $hash             Unique product HASH
     * @param array  $product          Product data
     * @param bool   $skip_promotion   Skip promotion calculation
     * @param array  $cart             Array of cart content and user information necessary for purchase
     * @param array  $auth             Array with authorization data
     * @param int    $promotion_amount Amount of product in promotion (like Free products, etc)
     * @param array  $_pdata           Product data
     * @param string $lang_code        Two-letter language code
     *
     * @see fn_add_product_to_cart()
     */
    public function getCartProductDataPost($hash, &$product, $skip_promotion, &$cart, $auth, $promotion_amount, $_pdata, $lang_code)
    {
        $cart['products'][$hash]['odoo_product_id'] = $_pdata['odoo_product_id'] ?? null;
        $product['odoo_product_id'] = $_pdata['odoo_product_id'] ?? null;
    }

    /**
     * 'create_order' hook handler.
     *
     * @see fn_update_order()
     */
    public function createOrder(&$order): void
    {
        if (Registry::get('odoo_import')) {
            return;
        }
        if ($order['is_parent_order'] == YesNo::NO) {
            $odoo_order_id = OrderSyncService::createOrderInOdoo($order);
            if ($odoo_order_id) {
                $order['odoo_order_id'] = $odoo_order_id;
            }
        }
    }

    /**
     * 'update_order' hook handler.
     *
     * @see fn_update_order()
     */
    public function updateOrder(&$order, $order_id): void
    {
        if (Registry::get('odoo_import')) {
            return;
        }
        if ($order['is_parent_order'] == YesNo::NO && !empty($order_id)) {
            $odoo_order_id = db_get_field('SELECT odoo_order_id FROM ?:orders WHERE order_id = ?i', $order_id);
            if (!empty($odoo_order_id)) {
                OrderSyncService::updateOrderInOdoo($order, $odoo_order_id);
            } else {
                $odoo_order_id = OrderSyncService::createOrderInOdoo($order);
                if ($odoo_order_id) {
                    $order['odoo_order_id'] = $odoo_order_id;
                }
            }
        }
    }

    /**
     * 'get_status_params_definition' hook handler.
     *
     * @param array  $status_params
     * @param string $type
     *
     * @see fn_get_status_params_definition()
     */
    public function getStatusParamsDefinition(&$status_params, $type)
    {
        if ($type == STATUSES_ORDER) {
            $status_params['odoo_status'] = [
                'type' => 'select',
                'label' => 'sd_odoo_integration.odoo_statuses',
                'variants' => OdooOrderStatuses::getVariants(),
            ];
        } elseif ($type == STATUSES_SHIPMENT) {
            $status_params['transfer_status'] = [
                'type' => 'select',
                'label' => 'sd_odoo_integration.odoo_transfer_statuses',
                'variants' => OdooTransferStatuses::getVariants(),
            ];
        }
    }

    /**
     * 'delete_order' hook handler.
     *
     * @param int $order_id Order ID
     */
    public function deleteOrder($order_id)
    {
        $order_data = db_get_row('SELECT odoo_order_id, company_id FROM ?:orders WHERE order_id = ?i', $order_id);
        if (!empty($order_data['odoo_order_id'])) {
            try {
                $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($order_data['company_id']);
                if (!$current_connection) {
                    return;
                }
                $isset_order_in_odoo = $current_connection->execute('sale.order', 'search', [[['id', '=', (int) $order_data['odoo_order_id']]]]);
                if ($isset_order_in_odoo) {
                    $success = $current_connection->execute('sale.order', 'unlink', [[(int) $order_data['odoo_order_id']]]);
                }
            } catch (OdooException $e) {
                fn_log_event('general', 'runtime', [
                    'message' => __('sd_odoo_integration.odoo_error_delete_order', [
                        '[error]' => $e->getMessage(),
                    ]),
                ]);
            }
        }
    }

    /**
     * 'change_order_status_post' hook handler.
     *
     * @param int    $order_id           Order identifier
     * @param string $status_to          New order status (one char)
     * @param string $status_from        Old order status (one char)
     * @param array  $force_notification Array with notification rules
     * @param bool   $place_order        True, if this function have been called inside of fn_place_order function
     * @param array  $order_info         Order information
     * @param array  $edp_data           Downloadable products data
     *
     * @see fn_change_order_status()
     */
    public function changeOrderStatusPost($order_id, $status_to, $status_from, $force_notification, $place_order, $order_info, $edp_data)
    {
        if (
            !$place_order 
            && $status_to !== OrderStatuses::INCOMPLETED
            && $status_from !== OrderStatuses::INCOMPLETED 
            && $order_info['is_parent_order'] == YesNo::NO
            && !empty($order_info['odoo_order_id'])
        ) {
            $result = OrderSyncService::updateStatusOrderInOdoo($order_info, $status_to);

            return $result;
        }
    }

    /**
     * 'get_statuses_post' hook handler.
     *
     * @param array  $statuses            An array of statuses
     * @param string $join                The parameters of joining with other tables
     * @param string $condition           The condition of the selection
     * @param string $type                One-letter status type
     * @param array  $status_to_select    Array of statuses that should be retrieved. If empty, all statuses will be
     * @param bool   $additional_statuses Flag that determines whether additional (hidden) statuses should be
     *                                    retrieved
     * @param bool   $exclude_parent      Flag that determines whether parent statuses should be excluded
     * @param string $lang_code           Language code
     * @param int    $company_id          Company identifier
     * @param string $order               The fields by which sorting must be performed
     *
     * @see fn_get_statuses()
     */
    public function getStatusesPost(
        &$statuses,
        $join,
        $condition,
        $type,
        $status_to_select,
        $additional_statuses,
        $exclude_parent,
        $lang_code,
        $company_id,
        $order
    ) {
        if (defined('IMPORT_ORDER_ODOO')) {
            foreach ($statuses as &$status) {
                $status['params']['inventory'] = 'P';
            }
        }
    }
}
