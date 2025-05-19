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
use Tygh\Tygh;

/**
 * The service that handles syncing product of data odoo.
 */
class PricelistSyncService
{
    public const ODOO_IMPORT_CHUNK_SIZE = 50;
    protected $odoo_cron;
    protected $company_id;
    protected $last_launch;
    protected $start_time;

    /**
     * Price-list sync constructor.
     */
    public function __construct($company, $start_time)
    {
        $this->company_id = $company['company_id'];
        $this->odoo_cron = new OdooCron($company['company_id'], OdooEntities::PRICELIST);
        $this->last_launch = $this->odoo_cron->last_launch;
        $this->start_time = $start_time;
    }

    /**
     * The main function of receiving pricelist from odoo.
     *
     * @param string $company
     * @param int    $last_launch
     */
    public function syncPricelist()
    {
        try {
            if ($this->odoo_cron->getActiveState($this->company_id, OdooEntities::PRICELIST, $this->start_time)) {
                return;
            }
            $company_id = $this->company_id;
            $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
            $mapping = Helpers::getPricelistMappingVendor($company_id);
            $this->odoo_cron->updateState($this->company_id, OdooEntities::PRICELIST, OdooCronStatus::IN_PROGRESS, $this->start_time);
            date_default_timezone_set('UTC');
            $filter_date = date('Y-m-d H:i:s', $this->last_launch);
            $pricelist_count = $current_connection->execute('product.pricelist.item', 'search_count', [[['write_date', '>', $filter_date], ['active', '=', true]]]);
            $count_chunk = ceil($pricelist_count / self::ODOO_IMPORT_CHUNK_SIZE);
            if (!defined('CONSOLE')) {
                fn_set_progress('parts', $count_chunk);
            }
            $chunk_index = 0;
            while ($chunk_index < $count_chunk) {
                $offset = $chunk_index * self::ODOO_IMPORT_CHUNK_SIZE;
                $pricelists = $current_connection->execute('product.pricelist.item', 'search', [[['write_date', '>', $filter_date], ['active', '=', true]]], ['offset' => $offset, 'limit' => self::ODOO_IMPORT_CHUNK_SIZE, 'order' => 'id']);
                $fields_pricelist = ['product_tmpl_id', 'product_id', 'min_quantity', 'fixed_price', 'compute_price', 'percent_price', 'pricelist_id', 'applied_on'];
                $pricelists_data = $current_connection->execute('product.pricelist.item', 'read', [$pricelists], ['fields' => $fields_pricelist]);
                $product_data_for_update_price = [];
                foreach ($pricelists_data as $item) {
                    if ($item['applied_on'] !== 'product') {
                        continue;
                    }
                    $min_quantity = $item['min_quantity'] == 0 ? 1 : $item['min_quantity'];
                    $usergroup_id = $mapping[$item['pricelist_id'][0]] ?? 0;
                    if (empty($item['product_id'][0])) {
                        $products_variant = $current_connection->execute('product.template', 'read',
                            [$item['product_tmpl_id'][0]], ['fields' => ['product_variant_ids']]);
                        foreach ($products_variant[0]['product_variant_ids'] as $variant) {
                            $product_data_for_update_price[] = [
                                'odoo_product_id' => $variant,
                                'price' => $item['fixed_price'],
                                'lower_limit' => $min_quantity,
                                'percentage_discount' => $item['compute_price'] == 'percentage' ? $item['percent_price'] : 0,
                                'usergroup_id' => $usergroup_id,
                            ];
                        }
                    } else {
                        $product_data_for_update_price[] = [
                            'odoo_product_id' => $item['product_id'][0],
                            'price' => $item['fixed_price'],
                            'lower_limit' => $min_quantity,
                            'percentage_discount' => $item['compute_price'] == 'percentage' ? $item['percent_price'] : 0,
                            'usergroup_id' => $usergroup_id,
                        ];
                    }
                }
                $products_id_mapping = Helpers::getProductsIdMappingOdooId(array_column($product_data_for_update_price, 'odoo_product_id'));
                foreach ($product_data_for_update_price as $key => $item) {
                    $product_id = $products_id_mapping[$item['odoo_product_id']] ?? 0;
                    if (!$product_id) {
                        unset($product_data_for_update_price[$key]);
                        continue;
                    }
                    $product_data_for_update_price[$key]['product_id'] = $product_id;
                    unset($product_data_for_update_price[$key]['odoo_product_id']);
                }

                $grouped = [];
                foreach ($product_data_for_update_price as $price_data) {
                    $product_id = $price_data['product_id'];
                    $usergroup_id = $price_data['usergroup_id'];
                    $grouped[$product_id][$usergroup_id][] = $price_data;
                }

                foreach ($grouped as $product_id => $groups) {
                    foreach ($groups as $usergroup_id => $items) {
                        $has_base = false;
                        foreach ($items as $i) {
                            if ($i['lower_limit'] == 1) {
                                $has_base = true;
                                break;
                            }
                        }
                        if ($has_base) {
                            db_query('DELETE FROM ?:product_prices WHERE product_id = ?i AND usergroup_id = ?i', $product_id, $usergroup_id);
                        } else {
                            db_query('DELETE FROM ?:product_prices WHERE product_id = ?i AND usergroup_id = ?i AND lower_limit > 1', $product_id, $usergroup_id);
                        }
                        db_replace_into('product_prices', $items, true);
                    }
                }
                ++$chunk_index;
                if (!defined('CONSOLE')) {
                    fn_set_progress('echo', __('importing_data'));
                }
            }
            $this->odoo_cron->updateState($this->company_id, OdooEntities::PRICELIST, OdooCronStatus::SUCCESS, $this->start_time);
        } catch (OdooException $e) {
            $this->odoo_cron->updateState($this->company_id, OdooEntities::PRICELIST, OdooCronStatus::FAIL, $this->last_launch);
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.cron_odoo_error', [
                    '[company_id]' => $company_id,
                    '[error]' => $e->getMessage(),
                ]),
            ]);
        }
    }
}
