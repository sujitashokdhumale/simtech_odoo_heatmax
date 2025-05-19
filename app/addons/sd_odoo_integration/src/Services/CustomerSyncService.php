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

use Tygh\Tygh;

/**
 * The service that handles syncing product of data odoo.
 */
class CustomerSyncService
{
    /**
     * Create customer in odoo from cs-cart.
     *
     * @param int   $company_id
     * @param array $data_customer
     *
     * @return int|false
     */
    public static function createCustomerInOdoo($company_id, $data_customer)
    {
        // TODO: need refactorign
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
        if (!$current_connection) {
            return false;
        }
        $id = $current_connection->execute('res.partner', 'create', [['name' => 'New Partner']]);

        return $id ?? false;
    }

    /**
     * Search customer in odoo by email.
     *
     * @param int   $company_id
     * @param array $data_order
     *
     * @return int|false
     */
    public static function issetCustomerInOdoo($company_id, $data_order)
    {
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
        // TODO: need refactorign
        if (!$current_connection) {
            return false;
        }
        $result = $current_connection->execute('res.partner', 'search', [[['email_normalized', '=', $data_order['email']]]]);
        if (count($result) == 1) {
            return reset($result);
        } elseif (empty($result)) {
            $name = $data_order['firstname'] . ' ' . $data_order['lastname'];
            if ($data_order['ship_to_another']) {
                $b_name = $data_order['b_firstname'] . ' ' . $data_order['b_lastname'];
                $b_country_id = $current_connection->execute('res.country', 'search', [[['code', '=', $data_order['b_country']]]]);
                $b_state_id = $current_connection->execute('res.country.state', 'search', [[['code', '=', $data_order['b_state']], ['country_id', '=', $b_country_id[0]]]]);
                $id_billing = $current_connection->execute(
                    'res.partner',
                    'create',
                    [[
                        'name' => $b_name,
                        'phone' => $data_order['phone'],
                        'email' => $data_order['email'],
                        'street' => $data_order['b_address'],
                        'zip' => $data_order['b_zipcode'],
                        'city' => $data_order['b_city'],
                        'country_id' => $b_country_id[0] ?? '',
                        'state_id' => $b_state_id[0] ?? '',
                        'type' => 'invoice',
                    ]]
                );
            } 
            $country_id = $current_connection->execute('res.country', 'search', [[['code', '=', $data_order['s_country']]]]);
            $state_id = $current_connection->execute('res.country.state', 'search', [[['code', '=', $data_order['s_state']], ['country_id', '=', $country_id[0]]]]);
            $shipping_arr = [
                'name' => empty($name) ? $data_order['email'] : $name,
                'phone' => $data_order['phone'],
                'email' => $data_order['email'],
                'street' => $data_order['s_address'],
                'zip' => $data_order['s_zipcode'],
                'city' => $data_order['s_city'],
                'country_id' => $country_id[0] ?? '',
                'state_id' => $state_id[0] ?? '',
            ];
            if (isset($id_billing)) {
                $shipping_arr += [
                    'child_ids' => [$id_billing],
                ];
            }
            $id = $current_connection->execute(
                'res.partner',
                'create',
                [$shipping_arr]
            );

            return $id ?? false;
        } else {
            return false;
        }
    }

    /**
     * Search customer in odoo by id odoo.
     *
     * @param int $company_id
     * @param int $id_customer
     *
     * @return array|false
     */
    public static function getCustomerFromOdooById($company_id, $id_customer)
    {
        $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($company_id);
        // TODO: need refactorign
        if (!$current_connection) {
            return false;
        }
        $result = $current_connection->execute('res.partner', 'search_read', [[['id', '=', $id_customer]]]);

        return is_array($result) ? $result[0] : false;
    }
}
