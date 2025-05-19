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
 * Class status in cron job odoo.
 */
class OdooCronStatus
{
    public const NOOP = 'X';
    public const IN_PROGRESS = 'P';
    public const SUCCESS = 'S';
    public const FAIL = 'F';

    /**
     * @return array[]
     */
    public static function getAll()
    {
        return [
            self::NOOP     => [
                'name' => __('sd_odoo_integration.noop'),
            ],
            self::IN_PROGRESS => [
                'name' => __('sd_odoo_integration.in_progress'),
            ],
            self::SUCCESS => [
                'name' => __('sd_odoo_integration.success'),
            ],
            self::FAIL => [
                'name' => __('sd_odoo_integration.fail'),
            ]
        ];
    }
}
