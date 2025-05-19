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
use Tygh\Tygh;

/**
 * The service that handles deleting entities.
 */
class DeletingService
{
    public const ODOO_CHUNK_SIZE_DELETING = 1000;
    protected $company_id;

    /**
     * Price-list sync constructor.
     */
    public function __construct($company)
    {
        $this->company_id = $company['company_id'];
    }

    /**
     * The main function of deleting entities in cs-cart that have been deleted in the odoo.
     * 
     * @return boolean;
     */
    public function deletingEntities()
    {
        try {
            $this->deletingProducts();
            $this->deletingOrders();
            return true;
        } catch (OdooException $e) {
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.cron_odoo_error', [
                    '[company_id]' => $this->company_id,
                    '[error]' => $e->getMessage(),
                ]),
            ]);
        }
    }

    public function deletingProducts()
    {
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($this->company_id);
        $products_count = $current_connection->execute('product.product', 'search_count', [[]]);
        $count_chunk = ceil($products_count / self::ODOO_CHUNK_SIZE_DELETING);
        $chunk_index = 0;
        $all_odoo_id_product = [];
        while ($chunk_index < $count_chunk) {
            $offset = $chunk_index * self::ODOO_CHUNK_SIZE_DELETING;
            $product_odoo_ids = $current_connection->execute('product.product', 'search', [[]], ['offset' => $offset, 'limit' => self::ODOO_CHUNK_SIZE_DELETING, 'order' => 'id']);
            $all_odoo_id_product = array_merge($all_odoo_id_product, $product_odoo_ids);
            ++$chunk_index;
        }
        $products_for_deleting = self::getProductsNotInOdoo($all_odoo_id_product);
        if ($products_for_deleting) {
            foreach ($products_for_deleting as $product) {
                fn_delete_product($product);
            }
        }

        return true;
    }

    public function deletingOrders()
    {
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($this->company_id);
        $products_count = $current_connection->execute('sale.order', 'search_count', [[]]);
        $count_chunk = ceil($products_count / self::ODOO_CHUNK_SIZE_DELETING);
        $chunk_index = 0;
        $all_odoo_id_order = [];
        while ($chunk_index < $count_chunk) {
            $offset = $chunk_index * self::ODOO_CHUNK_SIZE_DELETING;
            $order_odoo_ids = $current_connection->execute('sale.order', 'search', [[]], ['offset' => $offset, 'limit' => self::ODOO_CHUNK_SIZE_DELETING, 'order' => 'id']);
            $all_odoo_order_ids = array_merge($all_odoo_id_order, $order_odoo_ids);
            ++$chunk_index;
        }
        $orders_for_deleting = self::getOrdersNotInOdoo($all_odoo_order_ids);
        if ($orders_for_deleting) {
            foreach ($orders_for_deleting as $order_id) {
                fn_delete_order($order_id);
            }
        }

        return true;
    }

    /**
     * Getting the ID of the products that were removed in the odoo.
     * 
     * @return array|boolean
     */
    public function getProductsNotInOdoo($product_odoo_ids)
    {
        if (!empty($product_odoo_ids)) {
            $product_ids = db_get_fields('SELECT product_id FROM ?:products WHERE company_id = ?i AND
            odoo_product_id <> ?s AND odoo_product_id NOT IN (?n)', $this->company_id, '', $product_odoo_ids);

            return $product_ids;
        }
        return false;
    }

      /**
     * Getting the ID of the orders that were removed in the odoo.
     * 
     * @return array|boolean
     */
    public function getOrdersNotInOdoo($order_odoo_ids)
    {
        if (!empty($order_odoo_ids)) {
            $order_ids = db_get_fields('SELECT order_id FROM ?:orders WHERE company_id = ?i AND
            odoo_order_id <> ?s AND odoo_order_id NOT IN (?n)', $this->company_id, '', $order_odoo_ids);

            return $order_ids;
        }
        return false;
    }
}
