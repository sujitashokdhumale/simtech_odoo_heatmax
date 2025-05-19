<?php
/****************************************************************************
*                                                                          *
*   © Simtech Development Ltd.                                             *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
***************************************************************************/

namespace Tygh\Addons\SdOdooIntegration;

use Tygh\Settings;
use Tygh\Storage;

/**
 * Class Helpers.
 */
class Helpers
{
    const SD_ODOO_INTEGRATION = 'sd_odoo_integration';
    
    /**
     * Gets default category ID.
     *
     * @return string ID of default category
     */
    public static function getDefaultCategorySettings()
    {
        $default_category = '';
        $settings = Settings::instance()->getValues('sd_odoo_integration', 'ADDON');
        if (!empty($settings['general']['odoo_default_category'])) {
            $default_category = $settings['general']['odoo_default_category'];
        }

        return $default_category;
    }

    /**
     * Update default category ID.
     *
     * @return int ID of default category
     */
    public static function updateDefaultCategorySettings($setting)
    {
        if (isset($setting)) {
            Settings::instance()->updateValue($setting['setting_name'], $setting['setting_value']);
        }
    }

    /**
     * Getting vendor settings to connect to odoo companies.
     *
     * @return array
     */
    public static function getCompaniesForImport()
    {
        $fields = [
            '?:companies.company_id',
            '?:companies.odoo_url',
            '?:companies.odoo_database',
            '?:companies.odoo_username',
            '?:companies.odoo_api_key',
            '?:companies.odoo_discount_product',
            '?:companies.odoo_shipment_import_id',
        ];
        $condition = '';
        $condition .= db_quote(' AND ?:companies.odoo_url <> ?s AND ?:companies.odoo_database <> ?s 
            AND ?:companies.odoo_username <> ?s AND ?:companies.odoo_api_key <> ?s', '', '', '', '');
        $companies = db_get_array('SELECT ' . implode(', ', $fields) . ' FROM ?:companies WHERE 1 ?p', $condition);

        return $companies;
    }

    /**
     * Splitting an array of products to update and add products.
     *
     * @param array  $products
     * @param string $company_id
     *
     * @return array
     */
    public static function splitProductsForUpdateAndAdd($products, $company_id)
    {
        $sku_list = array_column($products, 'product_code');
        $products_for_update_key = db_get_array('SELECT product_code, product_id FROM ?:products WHERE product_code IN(?a) AND product_code <> ?s AND company_id =?i', $sku_list, '', $company_id);
        $products_for_update_key_hash = db_get_hash_single_array('SELECT product_code, product_id FROM ?:products WHERE product_code IN(?a) AND product_code <> ?s AND company_id =?i', ['product_code', 'product_id'], $sku_list, '', $company_id);
        if (empty($products_for_update_key)) {
            return [$products, []];
        }
        $products_for_add_key = array_diff_key($sku_list, $products_for_update_key);
        $products_for_add = array_intersect_key($products, $products_for_add_key);
        $products_for_update = array_intersect_key($products, $products_for_update_key);
        foreach ($products_for_update as &$item) {
            if ($item['product_code']) {
                $index = $item['product_code'];
                $item['cs_cart_product_id'] = $products_for_update_key_hash[$index] ?? null;
            } else {
                unset($item);
            }
        }
        unset($item);

        return [$products_for_add, $products_for_update];
    }

    /**
     * Convert string base64 to jpg file in tmp dir.
     *
     * @return array
     */
    public static function base64ToJpeg($base64_string, $image_name)
    {
        $images_path = Storage::instance('custom_files')->getAbsolutePath('');
        $image_output = $images_path . $image_name;
        $ifp = fopen($image_output, 'wb');
        fwrite($ifp, base64_decode($base64_string));
        fclose($ifp);

        return $image_output;
    }

    /**
     * Get products mapping ID odoo_product_id => product_id.
     *
     * @param array $products_odoo_id
     *
     * @return array
     */
    public static function getProductsIdMappingOdooId($odoo_products_id)
    {
        $list_mapping_id = db_get_hash_single_array('SELECT odoo_product_id, product_id FROM ?:products WHERE odoo_product_id IN(?a)', ['odoo_product_id', 'product_id'], $odoo_products_id);

        return $list_mapping_id;
    }

    /**
     * Get odoo product id 
     *
     * @param string $product_id
     *
     * @return int
     */
    public static function getOdooIdProduct($product_id)
    {
        $odoo_product_id = db_get_field('SELECT odoo_product_id FROM ?:products WHERE product_id = ?s', $product_id);

        return $odoo_product_id ?? 0;
    }

    /**
     * Get discount SKU product in odoo by company id 
     *
     * @param string $company_id
     *
     * @return int
     */
    public static function getDiscountSKUVendor($company_id)
    {
        $disc_odoo_product_sku = db_get_field('SELECT odoo_discount_product FROM ?:companies WHERE company_id = ?s', $company_id);

        return $disc_odoo_product_sku ?? false;
    }

    /**
     * Get shipping method ID in odoo by company id 
     *
     * @param string $company_id
     *
     * @return int
     */
    public static function getShippingIdOdooVendor($company_id)
    {
        $odoo_shipment_method_id = db_get_field('SELECT odoo_shipment_method_id FROM ?:companies WHERE company_id = ?s', $company_id);

        return (int) $odoo_shipment_method_id ?? false;
    }

    /**
     * Get shipping ID default cs-cart for import orders 
     *
     * @param string $company_id
     *
     * @return int
     */
    public static function getDefaultShippingForImport($company_id)
    {
        $shipment_id = db_get_field('SELECT odoo_shipment_import_id FROM ?:companies WHERE company_id = ?s', $company_id);

        return (int) $shipment_id ?? false;
    }
}
