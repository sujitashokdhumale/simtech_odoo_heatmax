<?php
/***************************************************************************
 *                                                                          *
 *   © Simtech Development Ltd.                                             *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 ***************************************************************************/

namespace Tygh\Enum\Addons\SdOdooIntegration;

/**
 * Class type entities in cron job odoo.
 */
class OdooEntities
{
    public const PRODUCT = 'P';
    public const ORDER = 'O';
    public const PRICELIST = 'PL';
    public const TRANSFER = 'T';
    public const DELETING = 'DL';

    /**
     * @return array[]
     */
    public static function getAll()
    {
        return [
            self::PRODUCT     => [
                'name' => __('sd_odoo_integration.product'),
            ],
            self::ORDER => [
                'name' => __('sd_odoo_integration.order'),
            ],
            self::PRICELIST => [
                'name' => __('sd_odoo_integration.pricelist'),
            ],
            self::TRANSFER => [
                'name' => __('sd_odoo_integration.transfer'),
            ]
        ];
    }
}
