<?php
/***************************************************************************
*                                                                          *
*   © Simtech Development Ltd.                                             *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
***************************************************************************/

namespace Tygh\Addons\SdOdooIntegration\HookHandlers;

use Tygh\Addons\SdOdooIntegration\Exceptions\OdooException;
use Tygh\Application;
use Tygh\Registry;
use Tygh\Tygh;

/**
 * This class describes the hook handlers related to products.
 */
class ProductHookHandler
{
    /** @var Application */
    protected $app;

    /** @var \Tygh\Database\Connection */
    protected $db;

    /**
     * DestinationHookHandler constructor.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = $app['db'];
    }

    /**
     * Saved odoo_id and company_id in registry (run before product is deleted).
     *
     * @param int  $product_id Product identifier
     * @param bool $status     Flag determines if product can be deleted, if false product is not deleted
     */
    public function deleteProductPre($product_id, $status)
    {
        $odoo_product_data = db_get_row('SELECT odoo_product_id, company_id FROM ?:products WHERE product_id = ?i', $product_id);
        if (!empty($odoo_product_data['odoo_product_id'])) {
            Registry::set('odoo_product_id_' . $product_id, $odoo_product_data);
        }
    }

    /**
     * 'delete_product_post' hook handler.
     *
     * @param int  $product_id      Product identifier
     * @param bool $product_deleted True if product was deleted successfully, false otherwise
     *
     * @see fn_delete_product()
     */
    public function deleteProductPost(int $product_id, bool $product_deleted): void
    {
        if (!$product_deleted) {
            return;
        }

        $odoo_product_data = Registry::get('odoo_product_id_' . $product_id);
        if ($odoo_product_data) {
            try {
                $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($odoo_product_data['company_id']);
                if (!$current_connection) {
                    return;
                }
                $success = $current_connection->execute('product.product', 'unlink', [[(int) $odoo_product_data['odoo_product_id']]]);
                Registry::del('odoo_product_id_' . $product_id);
            } catch (OdooException $e) {
                fn_set_notification('E', __('warning'), __('sd_odoo_integration.odoo_error_delete_product', [
                    '[error]' => $e->getMessage(),
                ]));
                fn_log_event('general', 'runtime', [
                    'message' => __('sd_odoo_integration.odoo_error', [
                        '[error]' => $e->getMessage(),
                    ]),
                ]);
            }
        }
    }

    /**
     * The "update_product_post" hook handler.
     *
     * @param array<string, string|int> $product_data Product data
     * @param int                       $product_id   Product ID
     * @param string                    $lang_code    Two-letter language code (e.g. 'en', 'ru', etc.)
     * @param bool                      $create       flag determines if product was created (true) or just updated (false)
     *
     * @see fn_update_product
     */
    public function onUpdateProductPost(array $product_data, $product_id, $lang_code, $create)
    {
        if (
            (isset($product_data['company_id'])
                && ($product_data['company_id'] == 0))
            || Registry::get('odoo_import')
            || Registry::get('runtime.mode') == 'm_update'
        ) {
            return;
        }
        try {
            $odoo_product_data = db_get_row('SELECT odoo_product_id, company_id FROM ?:products WHERE product_id = ?i', $product_id);
            $company_id = $product_data['company_id'] ?? $odoo_product_data['company_id'];
            $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
            if (!$current_connection) {
                return;
            }
            $image = fn_get_image_pairs($product_id, 'product', 'M', false, true);
            $base64image = '';
            if ($image) {
                $path = $image['detailed']['absolute_path'];
                $data = file_get_contents($path);
                $base64image = base64_encode($data);
            }
            $fields_to_odoo = [
                'name' => $product_data['product'],
                'lst_price' => $product_data['price'],
                'default_code' => $product_data['product_code'],
                'image_1920' => $base64image,
                'weight' => $product_data['weight'],
                'website_description' => $product_data['full_description'],
            ];
            if ($odoo_product_data['odoo_product_id']) {
                // UPDATE data in odoo
                if (!empty($product_data['product_features'])) {
                    list($features) = fn_get_product_features(['product_id' => $product_id, 'variants' => true, 'variants_selected_only' => true]);
                    $tmpl_data = $current_connection->execute('product.product', 'read', [(int) $odoo_product_data['odoo_product_id']], ['fields' => ['product_tmpl_id']]);
                    $product_tmpl_id = $tmpl_data[0]['product_tmpl_id'][0];
                    $attribute_lines = [];
                    foreach ($features as $feature) {
                        $attribute_id = $attribute_value = '';
                        if (!empty($feature['variant_id']) || !empty($feature['value'])) {
                            $attribute_id = $current_connection->execute('product.attribute', 'search', [[['name', '=', $feature['internal_name']]]]);
                            if ($attribute_id && $feature['variant_id']) {
                                $attribute_value = $current_connection->execute('product.attribute.value', 'search', [[['name', '=', $feature['variants'][$feature['variant_id']]['variant']], ['attribute_id', '=', $attribute_id]]]);
                            } elseif ($attribute_id && $feature['value']) {
                                $attribute_value = $current_connection->execute('product.attribute.value', 'search', [[['name', '=', $feature['value']], ['attribute_id', '=', $attribute_id]]]);   
                            } 
                        }
                        if (isset($attribute_id[0]) && isset($attribute_value[0])) {
                            $attribute_lines[] = [
                                'product_tmpl_id' => $product_tmpl_id,
                                'attribute_id' => $attribute_id[0],
                                'value_ids' => [
                                    [6, 0, [$attribute_value[0]]],
                                ]
                            ];

                        }
                    }
                    if (!empty($attribute_lines)) {
                        $id_lines = $current_connection->execute('product.template.attribute.line','search', [[['product_tmpl_id', '=', $product_tmpl_id]]]);
                        $delete_old_attribute = $current_connection->execute('product.template.attribute.line','unlink', [$id_lines]);
                        $product_attribute_line = $current_connection->execute('product.template.attribute.line','create', [$attribute_lines]);
                    }
                }
                $success = $current_connection->execute(
                    'product.product',
                    'write',
                    [
                        [(int) $odoo_product_data['odoo_product_id']],
                        $fields_to_odoo,
                    ]
                );
            } else {
                $has_product_odoo = $current_connection->execute('product.product', 'search', [[['default_code', '=', $product_data['product_code']]]]);
                if (empty($has_product_odoo)) {
                    // Create product in odoo
                    $id = $current_connection->execute(
                        'product.product',
                        'create',
                        [
                            [
                                'name' => $product_data['product'],
                                'detailed_type' => 'product',
                                'lst_price' => $product_data['price'],
                                'default_code' => $product_data['product_code'],
                                'image_1920' => $base64image,
                                'weight' => $product_data['weight'],
                                'website_description' => $product_data['full_description'],
                            ]
                        ]
                    );
                } elseif (count($has_product_odoo) > 1) {
                    fn_set_notification('E', __('error'), __('sd_odoo_integration.odoo_error_create_product_double_sku'));

                    return;
                } else {
                    $success = $current_connection->execute(
                        'product.product',
                        'write',
                        [
                            [(int) $has_product_odoo[0]],
                            $fields_to_odoo,
                        ]
                    );
                    $id = $has_product_odoo[0];
                }
                if ($id) {
                    db_query('UPDATE ?:products SET odoo_product_id = ?i WHERE product_id = ?i', $id, $product_id);
                }
            }
        } catch (OdooException $e) {
            fn_set_notification('E', __('warning'), __('sd_odoo_integration.odoo_error_update_product'));
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.odoo_error', [
                    '[error]' => $e->getMessage(),
                ]),
            ]);
        }
    }

     /**
     * The "clone_product_data" hook handler.
     *
     * @param int   $product_id             Product identifier
     * @param array $data                   Product data
     * @param bool  $is_cloning_allowed     If 'false', the product can't be cloned
     *
     * @see fn_clone_product
     */
    public function cloneProductData($product_id, &$data, $is_cloning_allowed) {
        unset($data['odoo_product_id']);
    }
}
