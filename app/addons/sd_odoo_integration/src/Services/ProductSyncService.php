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
use Tygh\Enum\FileUploadTypes;
use Tygh\Enum\OutOfStockActions;
use Tygh\Enum\ProductFeatures;
use Tygh\Registry;
use Tygh\Tygh;

/**
 * The service that handles syncing product of data odoo.
 */
class ProductSyncService
{
    public const ODOO_IMPORT_CHUNK_SIZE = 10;
    protected $odoo_cron;
    protected $company_id;
    protected $last_launch;
    protected $start_time;

    /**
     * Product sync constructor.
     */
    public function __construct($company, $start_time)
    {
        $this->company_id = $company['company_id'];
        $this->odoo_cron = new OdooCron($company['company_id'], OdooEntities::PRODUCT);
        $this->last_launch = $this->odoo_cron->last_launch;
        $this->start_time = $start_time;
    }

    /**
     * The main function of receiving products from odoo.
     *
     * @return bool
     */
    public function syncProducts()
    {
        $result = true;
        try {
            if ($this->odoo_cron->getActiveState($this->company_id, OdooEntities::PRODUCT, $this->start_time)) {
                return;
            }
            $this->odoo_cron->updateState($this->company_id, OdooEntities::PRODUCT, OdooCronStatus::IN_PROGRESS, $this->start_time);
            $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($this->company_id);
            date_default_timezone_set('UTC');
            $filter_date = date('Y-m-d H:i:s', $this->last_launch);
            $products_count = $current_connection->execute('product.product', 'search_count', [[['write_date', '>', $filter_date], ['sale_ok', '=', 1]]]);
            $product_fields_mapping = fn_get_schema('odoo', 'product_mapping');
            $count_chunk = ceil($products_count / self::ODOO_IMPORT_CHUNK_SIZE);
            if (!defined('CONSOLE')) {
                fn_set_progress('parts', $count_chunk);
            }
            $products_count_check = 0;
            $products = $products_data = $add_products = $update_products = [];
            for ($chunk_index = 0; $chunk_index < $count_chunk; $chunk_index++) {
                $products_chunk_count_check = 0;
                $offset = $chunk_index * self::ODOO_IMPORT_CHUNK_SIZE;
                $products = $current_connection->execute('product.product', 'search', [[['write_date', '>', $filter_date], ['sale_ok', '=', 1]]], ['offset' => $offset, 'limit' => self::ODOO_IMPORT_CHUNK_SIZE, 'order' => 'id']);
                $products_data = $current_connection->execute('product.product', 'read', [$products], ['fields' => array_keys($product_fields_mapping)]);
                $add_products = $update_products = [];
                if (!empty($products_data)) {
                    $products_data_map = array_map(function ($v) use ($product_fields_mapping) {
                        $data = array_combine($product_fields_mapping, $v);
                        if (isset($data['product'])) {
                            $data['odoo_product_name'] = $data['product'];
                        }

                        return $data;
                    }, $products_data);
                    list($add_products, $update_products) = Helpers::splitProductsForUpdateAndAdd($products_data_map, $this->company_id);
                }
                if (!empty($add_products)) {
                    foreach ($add_products as $a_product) {
                        if (!empty($a_product['product_template_attribute_value_ids'])) {
                            $attribute_data = $current_connection->execute('product.template.attribute.value', 'read', [$a_product['product_template_attribute_value_ids']]);
                            $a_product['product_features'] = self::syncAttribute($attribute_data);
                        }
                        if ($a_product['type_product'] !== 'product') {
                            $a_product['out_of_stock_actions'] = OutOfStockActions::BUY_IN_ADVANCE;
                        }
                        $a_product['company_id'] = $this->company_id;
                        $a_product['category_ids'][] = Helpers::getDefaultCategorySettings();
                        if (!empty($a_product['image'])) {
                            self::addImageFromOdoo($a_product['image'], $a_product['product_code']);
                        }
                        $is_success = fn_execute_as_company(
                            static function () use ($a_product) {
                                return fn_update_product($a_product);
                            },
                            $this->company_id
                        );
                        if (!empty($a_product['image'])) {
                            unset($_REQUEST['product_main_image_data'], $_REQUEST['file_product_main'], $_REQUEST['type_product_main_image_detailed']);
                        }
                        if (!$is_success) {
                            fn_log_event('general', 'runtime', [
                                'message' => __('sd_odoo_integration.cron_odoo_error_add_product', [
                                    '[odoo_product_id]' => $a_product['odoo_product_id'],
                                ]),
                            ]);
                            $result = false;
                        } else {
                            ++$products_chunk_count_check;
                        }
                    }
                }
                if (!empty($update_products)) {
                    foreach ($update_products as $u_product) {
                        if (isset($u_product['product'])) {
                            unset($u_product['product']);
                        }
                        if (isset($u_product['odoo_product_name'])) {
                            unset($u_product['odoo_product_name']);
                        }
                        if (!empty($u_product['product_template_attribute_value_ids'])) {
                            $attribute_data = $current_connection->execute('product.template.attribute.value', 'read', [$u_product['product_template_attribute_value_ids']]);
                            $u_product['product_features'] = self::syncAttribute($attribute_data);
                        }
                        $u_product['company_id'] = $this->company_id;
                        if (isset($u_product['cs_cart_product_id']) && !empty($u_product['cs_cart_product_id'])) {
                            if (!empty($u_product['image'])) {
                                $products_images = fn_get_image_pairs($u_product['cs_cart_product_id'], 'product', 'M', true, true, DEFAULT_LANGUAGE);
                                    if (!empty($products_images)) {
                                        fn_delete_image_pair($products_images['pair_id']);
                                    }
                                self::addImageFromOdoo($u_product['image'], $u_product['product_code']);
                            }
                            if ($u_product['type_product'] !== 'product') {
                                $u_product['out_of_stock_actions'] = OutOfStockActions::BUY_IN_ADVANCE;
                            }
                            $is_success = fn_execute_as_company(
                                static function () use ($u_product) {
                                    return fn_update_product($u_product, $u_product['cs_cart_product_id']);
                                },
                                $this->company_id
                            );
                            if (!empty($u_product['image'])) {
                                unset($_REQUEST['product_main_image_data'], $_REQUEST['file_product_main'], $_REQUEST['type_product_main_image_detailed']);
                            }
                            if (!$is_success) {
                                fn_log_event('general', 'runtime', [
                                    'message' => __('sd_odoo_integration.cron_odoo_error_update_product', [
                                        '[product_id]' => $u_product['cs_cart_product_id'],
                                    ]),
                                ]);
                                $result = false;
                            } else {
                                ++$products_chunk_count_check;
                            }
                        }
                    }
                }
                if ($products_chunk_count_check < count($products)) {
                    fn_log_event('general', 'runtime', [
                        'message' => 'ERROR ODOO CRON: '. implode(",", $products),
                    ]);
                    $result = false;
                    throw new OdooException('The number of products in a chunk is different from the amount of data');
                }
                if (!defined('CONSOLE')) {
                    fn_set_progress('echo', __('importing_data'));
                }
            }
            $this->odoo_cron->updateState($this->company_id, OdooEntities::PRODUCT, OdooCronStatus::SUCCESS, $this->start_time);
        } catch (OdooException $e) {
            \Tygh\Tools\ErrorHandler::handleException($e);
            $this->odoo_cron->updateState($this->company_id, OdooEntities::PRODUCT, OdooCronStatus::FAIL, $this->last_launch);
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.cron_odoo_error', [
                    '[company_id]' => $this->company_id,
                    '[error]' => $e->getMessage(),
                ]),
            ]);
        }

        return $result;
    }

    /**
     * Sync attribute from odoo.
     *
     * @return array|bool
     */
    public function syncAttribute(array $attribute_data)
    {
        $product_attribute_data = [];
        foreach ($attribute_data as $item) {
            $find_feature = self::sdFindFeature($item['attribute_id'][1], ProductFeatures::TEXT_SELECTBOX, 0, $lang_code = CART_LANGUAGE);
            if ($find_feature) {
                $value_feature = self::sdFindFeatureValue($find_feature['feature_id'], $item['name']);
                if ($value_feature) {
                    $product_attribute_data += [
                        $find_feature['feature_id'] => $value_feature,
                    ];
                } else {
                    $variant_id = fn_add_feature_variant($find_feature['feature_id'], ['variant' => $item['name']]);
                    if ($variant_id) {
                        $product_attribute_data += [
                            $find_feature['feature_id'] => $variant_id,
                        ];
                    }
                }
            } else {
                $add_feature_data = [
                    'internal_name' => $item['attribute_id'][1],
                    'description' => $item['attribute_id'][1],
                    'company_id' => '0',
                    'purpose' => 'group_catalog_item',
                    'feature_style' => 'dropdown',
                    'filter_style' => 'checkbox',
                    'feature_type' => 'S',
                    'status' => 'A',
                    'variants' => [
                        [
                            'position' => '0',
                            'color' => '#ffffff',
                            'variant' => $item['name'],
                        ],
                    ],
                ];
                $feature_id = fn_update_product_feature($add_feature_data, 0);
                if ($feature_id) {
                    $value_feature = self::sdFindFeatureValue($feature_id, $item['name']);
                    if ($value_feature) {
                        $product_attribute_data += [
                            $feature_id => $value_feature,
                        ];
                    }
                }
            }
        }

        return $product_attribute_data;
    }

    /**
     * Find feature by name.
     *
     * @param string   $name       Product feature name
     * @param string   $type       Product feature type
     * @param int      $group_id   Product feature group identification
     * @param string   $lang_code  Language code
     * @param int|null $company_id Company identifier
     * @param string   $field_name Field name of features name: internal_name or description
     *
     * @return array
     */
    public function sdFindFeature($name, $type, $group_id, $lang_code, $company_id = null, $field_name = 'internal_name')
    {
        $current_company_id = Registry::get('runtime.company_id');
        $is_simple_ultimate = Registry::get('runtime.simple_ultimate');

        if (!$is_simple_ultimate && $company_id !== null) {
            Registry::set('runtime.company_id', $company_id);
        }

        $condition = db_quote('WHERE ?p = ?s AND lang_code = ?s AND feature_type = ?s', $field_name, $name, $lang_code, $type);
        $condition .= db_quote(' AND parent_id = ?i', $group_id);

        if (fn_allowed_for('ULTIMATE')) {
            $condition .= fn_get_company_condition('?:product_features.company_id');
        }

        $result = db_get_row(
            'SELECT pf.feature_id, pf.feature_code, pf.feature_type, pf.categories_path, pf.parent_id, pf.status, pf.company_id' .
            ' FROM ?:product_features as pf ' .
            ' LEFT JOIN ?:product_features_descriptions ON pf.feature_id = ?:product_features_descriptions.feature_id ' . $condition
        );

        if (!$is_simple_ultimate && $company_id !== null) {
            Registry::set('runtime.company_id', $current_company_id);
        }

        return $result;
    }

    /**
     * Find value feature by name.
     *
     * @param string $id_feature Feature id
     * @param string $name_value Name value
     *
     * @return int
     */
    public function sdFindFeatureValue($id_feature, $name_value)
    {
        $join = db_quote(
            ' LEFT JOIN ?:product_feature_variant_descriptions'
            . ' ON ?:product_feature_variant_descriptions.variant_id = ?:product_feature_variants.variant_id'
            . ' AND ?:product_feature_variant_descriptions.lang_code = ?s',
            DESCR_SL
        );
        $condition = db_quote(' ?:product_feature_variants.feature_id = ?i', $id_feature);

        $variant_id = db_get_field(
            'SELECT ?:product_feature_variants.variant_id'
            . ' FROM ?:product_feature_variants ?p'
            . ' WHERE ?p AND ?:product_feature_variant_descriptions.variant = ?l'
            . ' LIMIT ?i',
            $join,
            $condition,
            $name_value,
            1
        );

        return $variant_id;
    }

    /**
     * Convert and add image to REQUEST for adding to product.
     *
     * @param string $image Image in (base64)
     * @param string $code  Id product code
     *
     * @return void
     */
    public function addImageFromOdoo($image, $code)
    {
        $image_file = Helpers::base64ToJpeg($image, $code . '_image.jpg');
        $image_data = [
            'product_main_image_data' => [
                [
                    'detailed_alt' => '',
                    'type' => 'M',
                    'position' => '0',
                    'is_new' => true,
                ],
            ],
            'file_product_main_image_detailed' => [$image_file],
            'type_product_main_image_detailed' => [FileUploadTypes::UPLOADED],
        ];
        $_REQUEST = array_merge($_REQUEST, $image_data);
    }
}
