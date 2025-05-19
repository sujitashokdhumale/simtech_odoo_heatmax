<?php
/***************************************************************************
 *                                                                          *
 *   © Simtech Development Ltd.                                             *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 ***************************************************************************/

namespace Tygh\Addons\SdOdooIntegration\Services;

use Tygh\Addons\SdOdooIntegration\Exceptions\OdooException;
use Tygh\Addons\SdOdooIntegration\Helpers;
use Tygh\Addons\SdOdooIntegration\OdooCron;
use Tygh\Enum\Addons\SdOdooIntegration\OdooCronStatus;
use Tygh\Enum\Addons\SdOdooIntegration\OdooEntities;
use Tygh\Enum\Addons\SdOdooIntegration\OdooOrderStatuses;
use Tygh\Enum\YesNo;
use Tygh\Registry;
use Tygh\Settings;
use Tygh\Tygh;

/**
 * The service that handles syncing product of data odoo.
 */
class OrderSyncService
{
    public const ODOO_IMPORT_CHUNK_SIZE = 20;

    protected $odoo_cron;
    protected $company_id;
    protected $last_launch;
    protected $start_time;
    protected $odoo_default_discount_product;
    protected $import_new_orders;

    /**
     * Orders sync constructor.
     */
    public function __construct($company, $start_time)
    {
        $this->company_id = $company['company_id'];
        $this->odoo_cron = new OdooCron($company['company_id'], OdooEntities::ORDER);
        $this->last_launch = $this->odoo_cron->last_launch;
        $this->start_time = $start_time;
        $this->odoo_default_discount_product = $company['odoo_discount_product'];
        $settings = Settings::instance()->getValues('sd_odoo_integration', 'ADDON');
        $this->import_new_orders = $settings['general']['import_new_odoo_orders'] ?? YesNo::YES;
    }

    /**
     * The main function of receiving orders from odoo.
     *
     * @throws \OdooException
     */
    public function syncOrders()
    {
        try {
            if ($this->odoo_cron->getActiveState($this->company_id, OdooEntities::ORDER, $this->start_time)) {
                return false;
            }
            $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($this->company_id);
            if (!$current_connection) {
                return false;
            }
            $this->odoo_cron->updateState($this->company_id, OdooEntities::ORDER, OdooCronStatus::IN_PROGRESS, $this->start_time);
            fn_define('ORDER_MANAGEMENT', true);
            $company_id = (int) $this->company_id;
            date_default_timezone_set('UTC');
            $filter_date = date('Y-m-d H:i:s', $this->last_launch);
            $orders_count = $current_connection->execute(
                'sale.order',
                'search_count',
                [[['write_date', '>', $filter_date]]]
            );
            $count_chunk = ceil($orders_count / self::ODOO_IMPORT_CHUNK_SIZE);
            if (!defined('CONSOLE')) {
                fn_set_progress('parts', $count_chunk);
            }
            $orders_fields = fn_get_schema('odoo', 'odoo_order_fields');
            $order_line_fields = ['product_id', 'product_uom_qty', 'price_unit'];
            $orders_count_check = 0;
            for ($chunk_index = 0; $chunk_index < $count_chunk; $chunk_index++) {
                $offset = $chunk_index * self::ODOO_IMPORT_CHUNK_SIZE;
                $orders = $current_connection->execute(
                    'sale.order',
                    'search',
                    [[['write_date', '>', $filter_date]]],
                    ['offset' => $offset, 'limit' => self::ODOO_IMPORT_CHUNK_SIZE, 'order' => 'id desc']
                );
                $orders_data = $current_connection->execute(
                    'sale.order',
                    'read',
                    [$orders],
                    ['fields' => $orders_fields]
                );
                foreach ($orders_data as $item) {
                    $order_exists = self::issetOrderInCscart($item['id'], $this->company_id);
                    if ($order_exists) {
                        fn_define('IMPORT_ORDER_ODOO', false);
                        $this->updateOrderInCscart($item);
                        ++$orders_count_check;
                        continue;
                    }
                    if ($this->import_new_orders === YesNo::NO) {
                        continue;
                    }
                    fn_define('IMPORT_ORDER_ODOO', true);
                    $cart = [
                        'products' => [],
                        'recalculate' => false,
                        'payment_id' => 0,
                    ];

                    $data_customers = CustomerSyncService::getCustomerFromOdooById($this->company_id, $item['partner_id'][0]);
                    $params['phone'] = $data_customers['phone'] ?? '';
                    $firstname = explode(' ', $data_customers['name'])[0] ?? '';
                    $lastname = explode(' ', $data_customers['name'])[1] ?? '';
                    $cart['user_data']['email'] = $data_customers['email_normalized'] ?? $item['partner_id'][0] . '@odooimport_' . $this->company_id;
                    if (!empty($firstname) && strpos($firstname, ' ')) {
                        list($firstname, $lastname) = explode(' ', $firstname);
                    }
                    $cart['user_data']['firstname'] = $firstname;
                    $cart['user_data']['b_firstname'] = $firstname;
                    $cart['user_data']['s_firstname'] = $firstname;
                    $cart['user_data']['lastname'] = $lastname;
                    $cart['user_data']['b_lastname'] = $lastname;
                    $cart['user_data']['s_lastname'] = $lastname;
                    $cart['user_data']['phone'] = $params['phone'];
                    $cart['user_data']['b_phone'] = $params['phone'];
                    $cart['user_data']['s_phone'] = $params['phone'];
                    $address_keys = [
                        'b_address', 's_address', 'b_city', 's_city', 'b_country', 's_country', 'b_state',
                        's_state',
                    ];
                    foreach ($address_keys as $key) {
                        if (!isset($cart['user_data'][$key])) {
                            $cart['user_data'][$key] = ' ';
                        }
                    }
                    $cart['user_data']['company_id'] = $this->company_id;
                    $order_lines = $current_connection->execute(
                        'sale.order.line',
                        'read',
                        [$item['order_line']],
                        ['fields' => $order_line_fields]
                    );
                    $product_data = [];
                    $products_odoo = array_column($order_lines, 'product_id');
                    $products_odoo_id = [];
                    foreach ($products_odoo as $product_item) {
                        $products_odoo_id[] = $product_item[0];
                    }
                    if (empty($products_odoo_id)) {
                        continue;
                    }
                    $products_id_mapping = Helpers::getProductsIdMappingOdooId($products_odoo_id);
                    foreach ($order_lines as $line) {
                        $product_data[$products_id_mapping[$line['product_id'][0]]] = [
                            'product_id' => (int) $products_id_mapping[$line['product_id'][0]],
                            'amount' => (int) $line['product_uom_qty'],
                            'stored_price' => YesNo::YES,
                            'price' => $line['price_unit'],
                        ];
                    }
                    $auth = Tygh::$app['session']['auth'];
                    $cart['timestamp'] = strtotime($item['date_order']);
                    fn_add_product_to_cart($product_data, $cart, $auth);
                    fn_calculate_cart_content($cart, $auth, 'S', false, 'S', false);
                    if ($cart['shipping_required']) {
                        $default_shipping_id = Helpers::getDefaultShippingForImport($this->company_id);
                        $cart['product_groups'][0]['chosen_shippings'][0] = $cart['product_groups'][0]['shippings'][$default_shipping_id];
                        $cart['shipping'][$default_shipping_id] = $cart['product_groups'][0]['shippings'][$default_shipping_id];
                        $cart['shipping_failed'] = false;
                        $cart['company_shipping_failed'] = false;
                        $cart['chosen_shippings'] = [$default_shipping_id];
                    }
                    $is_success = fn_execute_as_company(
                        static function () use ($cart, $auth) {
                            return fn_place_order($cart, $auth);
                        },
                        $this->company_id
                    );
                    if ($is_success[0] && $is_success[1] == true) {
                        db_query('UPDATE ?:orders SET odoo_order_id = ?i WHERE order_id = ?i', $item['id'], $is_success[0]);
                        ++$orders_count_check;
                    }
                }
                if (!defined('CONSOLE')) {
                    fn_set_progress('echo', __('importing_data'));
                }
            }
            $this->odoo_cron->updateState($this->company_id, OdooEntities::ORDER, OdooCronStatus::SUCCESS, $this->start_time);
        } catch (OdooException $e) {
            $this->odoo_cron->updateState($this->company_id, OdooEntities::ORDER, OdooCronStatus::FAIL, $this->last_launch);
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.cron_odoo_error', [
                    '[company_id]' => $this->company_id,
                    '[error]' => $e->getMessage(),
                ]),
            ]);
        }
    }

    /**
     * Create order in odoo from cs-cart.
     *
     * @param array $data_order
     *
     * @return int|false
     */
    public static function createOrderInOdoo($data_order)
    {
        if (!isset($data_order['company_id'])) {
            return false;
        }
        $company_id = $data_order['company_id'];
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
        if (!$current_connection) {
            return false;
        }
        $customer_id = CustomerSyncService::issetCustomerInOdoo($company_id, $data_order);
        $order_statuses = fn_get_statuses(STATUSES_ORDER);
        $odoo_order_status = $order_statuses[$data_order['status']]['params']['odoo_status'] ?? OdooOrderStatuses::STATE_DRAFT;
        $data_order_for_odoo =
            [
                [
                    'partner_id' => $customer_id,
                    'state' => $odoo_order_status,
                ],
            ];
        if (!empty($data_order['shipping'])) {
            $shipping_odoo_product_sku = Helpers::getShippingIdOdooVendor($company_id);
            $shipping_odoo_product_id = $current_connection->execute('delivery.carrier', 'read', [$shipping_odoo_product_sku], ['fields' => ['product_id']]);
            $data_order_for_odoo[0] += [
                'carrier_id' => $shipping_odoo_product_sku,
            ];
        }
        $create_order_id = $current_connection->execute('sale.order', 'create', $data_order_for_odoo);
        $product_for_odoo = [];
        foreach ($data_order['products'] as $product) {
            if (empty($product['odoo_product_id'])) {
                continue;
            }
            $product_for_odoo[] = [
                'order_id' => $create_order_id,
                'product_id' => (int) $product['odoo_product_id'],
                'product_uom_qty' => $product['amount'],
                'price_unit' => $product['price'],
            ];
        }
        if (!empty($data_order['subtotal_discount'])) {
            $disc_odoo_product_sku = Helpers::getDiscountSKUVendor($company_id);
            $disc_odoo_product_id = $current_connection->execute('product.product', 'search', [[['default_code', '=', $disc_odoo_product_sku]]]);
            if (
                is_array($disc_odoo_product_id)
                && !empty($disc_odoo_product_id)
            ) {
                $product_for_odoo[] = [
                    'order_id' => (int) $create_order_id,
                    'product_id' => (int) reset($disc_odoo_product_id),
                    'product_uom_qty' => floatval($data_order['subtotal_discount']),
                ];
            }
        }
        if (!empty($shipping_odoo_product_id)) {
            $product_for_odoo[] = [
                'order_id' => (int) $create_order_id,
                'product_id' => (int) reset($shipping_odoo_product_id[0]['product_id']),
                'product_uom_qty' => 1,
                'price_unit' => floatval($data_order['shipping_cost']),
                'is_delivery' => 1,
            ];
        }
        $order_line = $current_connection->execute(
            'sale.order.line',
            'create',
            [$product_for_odoo]
        );

        return $create_order_id ?? false;
    }

    /**
     * Update order in odoo from cs-cart.
     *
     * @param array  $data_order
     * @param string $odoo_order_id
     *
     * @return int|false
     */
    public static function updateOrderInOdoo($data_order, $odoo_order_id)
    {
        if (!isset($data_order['company_id'])) {
            return false;
        }
        $company_id = $data_order['company_id'];
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
        if (!$current_connection) {
            return false;
        }
        $customer_id = CustomerSyncService::issetCustomerInOdoo($company_id, $data_order);
        $order_statuses = fn_get_statuses(STATUSES_ORDER);
        $odoo_order_status = $order_statuses[$data_order['status']]['params']['odoo_status'] ?? OdooOrderStatuses::STATE_DRAFT;
        $data_order_for_odoo =
            [
                [(int) $odoo_order_id],
                [
                    'partner_id' => $customer_id,
                    'state' => $odoo_order_status,
                ],
            ];
        if (!empty($data_order['shipping'])) {
            $odoo_shipment_method_id = Helpers::getShippingIdOdooVendor($company_id);
            $shipping_odoo_product_id = $current_connection->execute('delivery.carrier',  'read', [$odoo_shipment_method_id], ['fields' => ['product_id']]);
            $data_order_for_odoo[1] += [
                'carrier_id' => $odoo_shipment_method_id,
            ];
        }
        $update_order_id = $current_connection->execute('sale.order', 'write', $data_order_for_odoo);
        $old_sale_order_line = $current_connection->execute(
            'sale.order.line',
            'search',
            [[['order_id', '=', (int) $odoo_order_id]]]
        );
        $delete_line = $current_connection->execute(
            'sale.order.line',
            'unlink',
            [$old_sale_order_line]
        );

        if ($delete_line) {
            $product_for_odoo = [];
            foreach ($data_order['products'] as $product) {
                $product_for_odoo[] = [
                    'order_id' => (int) $odoo_order_id,
                    'product_id' => (int) $product['odoo_product_id'],
                    'product_uom_qty' => $product['amount'],
                    'price_unit' => $product['price'],
                ];
            }
            if (!empty($data_order['subtotal_discount'])) {
                $disc_odoo_product_sku = Helpers::getDiscountSKUVendor($company_id);
                $disc_odoo_product_id = $current_connection->execute('product.product', 'search', [[['default_code', '=', $disc_odoo_product_sku]]]);
                if (
                    is_array($disc_odoo_product_id)
                    && !empty($disc_odoo_product_id)
                ) {
                    $product_for_odoo[] = [
                        'order_id' => (int) $odoo_order_id,
                        'product_id' => (int) reset($disc_odoo_product_id),
                        'product_uom_qty' => floatval($data_order['subtotal_discount']),
                    ];
                }
            }
            if (!empty($shipping_odoo_product_id)) {
                $product_for_odoo[] = [
                    'order_id' => (int) $odoo_order_id,
                    'product_id' => (int) reset($shipping_odoo_product_id[0]['product_id']),
                    'product_uom_qty' => 1,
                    'price_unit' => floatval($data_order['shipping_cost']),
                    'is_delivery' => 1,
                ];
            }
            $order_line = $current_connection->execute(
                'sale.order.line',
                'create',
                [$product_for_odoo]
            );
        }

        return $update_order_id ?? false;
    }

    /**
     * Update order in CS-Cart by data from Odoo.
     *
     * @param array $odoo_order
     *
     * @return bool
     */
    public function updateOrderInCscart(array $odoo_order)
    {
        $order_id_data = self::issetOrderInCscart($odoo_order['id'], $this->company_id);
        $order_id = is_array($order_id_data) ? reset($order_id_data) : $order_id_data;

        if (!$order_id) {
            return false;
        }

        $status = $this->getStatusByOdooOrderStatus($odoo_order['state']);
        if ($status) {
            fn_change_order_status($order_id, $status, '', fn_get_notification_rules([], true));
        }

        if (!empty($odoo_order['payment_state'])) {
            db_query('UPDATE ?:orders SET payment_state = ?s WHERE order_id = ?i', $odoo_order['payment_state'], $order_id);
        }

        if (!empty($odoo_order['picking_ids'])) {
            $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($this->company_id);
            if ($current_connection) {
                $fields_transfers = fn_get_schema('odoo', 'transfer_fields');
                $transfers_data = $current_connection->execute('stock.picking', 'read', [$odoo_order['picking_ids']], ['fields' => $fields_transfers]);
                $force_notification = fn_get_notification_rules([], true);
                foreach ($transfers_data as $transfer) {
                    $date = !empty($transfer['date_done']) ? fn_date_to_timestamp($transfer['date_done']) : fn_date_to_timestamp($transfer['date']);
                    $params = ['order_id' => $order_id, 'advanced_info' => false];
                    list($shipments,) = fn_get_shipments_info($params);
                    $shipment = reset($shipments);
                    $shipment_status = $this->getStatusByOdooTransferStatus($transfer['state']);
                    if (empty($shipment)) {
                        $shipment_data = [
                            'timestamp' => $date,
                            'order_id' => $order_id,
                            'shipping_id' => Helpers::getDefaultShippingForImport($this->company_id),
                            'tracking_number' => $transfer['id'],
                            'status' => $shipment_status,
                        ];
                        fn_update_shipment($shipment_data, 0, 0, true, $force_notification);
                    } else {
                        $shipment['status'] = $shipment_status;
                        $shipment['timestamp'] = $date;
                        fn_update_shipment($shipment, $shipment['shipment_id']);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Get order status by odoo order status.
     *
     * @param string $status_from_odoo
     *
     * @return string|null
     */
    protected function getStatusByOdooOrderStatus($status_from_odoo)
    {
        return db_get_field('SELECT statuses.status FROM ?:statuses AS statuses'
            . ' LEFT JOIN ?:status_data AS data ON statuses.status_id = data.status_id'
            . ' WHERE statuses.type = ?s AND data.param = ?s AND data.value = ?s LIMIT 1',
            STATUSES_ORDER, 'odoo_status', $status_from_odoo);
    }

    /**
     * Get shipment status by odoo transfer status.
     *
     * @param string $status_from_odoo
     *
     * @return string|null
     */
    protected function getStatusByOdooTransferStatus($status_from_odoo)
    {
        return db_get_field('SELECT statuses.status FROM ?:statuses AS statuses'
            . ' LEFT JOIN ?:status_data AS data ON statuses.status_id = data.status_id WHERE '
            . 'data.param = ?s AND data.value = ?s LIMIT 1', 'transfer_status', $status_from_odoo);
    }

    /**
     * Update only status order in odoo from cs-cart.
     *
     * @param array  $data_order
     * @param string $status_to
     *
     * @return int|false
     */
    public static function updateStatusOrderInOdoo($data_order, $status_to)
    {
        $order_statuses = fn_get_statuses(STATUSES_ORDER);
        $odoo_order_status = $order_statuses[$status_to]['params']['odoo_status'] ?? false;
        if (!isset($data_order['company_id']) && !$odoo_order_status) {
            return false;
        }
        $company_id = $data_order['company_id'];
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
        if (!$current_connection) {
            return false;
        }
        switch ($odoo_order_status) {
            case OdooOrderStatuses::STATE_SALE:
                $update_order_id = $current_connection->execute(
                    'sale.order',
                    'action_confirm',
                    [[(int) $data_order['odoo_order_id']]]
                );
                break;
            case OdooOrderStatuses::STATE_CANCEL:
                $update_order_id = $current_connection->execute(
                    'sale.order',
                    'action_cancel',
                    [[(int) $data_order['odoo_order_id']]]
                );
                break;
            default:
                if (!$odoo_order_status) {
                    return true;
                }
                $data_order_for_odoo =
                    [
                        [(int) $data_order['odoo_order_id']],
                        ['state' => $odoo_order_status],
                    ];
                $update_order_id = $current_connection->execute(
                    'sale.order',
                    'write',
                    $data_order_for_odoo
                );
        }

        return $update_order_id ?? false;
    }

    /**
     * Search order in cs-cart by odoo_order_id.
     *
     * @param array $odoo_order_id
     * @param int   $company_id
     *
     * @return int|false order_id
     */
    public static function issetOrderInCscart($odoo_order_id, $company_id)
    {
        if (
            !empty($odoo_order_id)
            && !empty($company_id)
        ) {
            $order_id = db_get_row('SELECT order_id FROM ?:orders WHERE odoo_order_id = ?i AND company_id = ?i', $odoo_order_id, $company_id);

            return $order_id ?? false;
        }
    }
}
