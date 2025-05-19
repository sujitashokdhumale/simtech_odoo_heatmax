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

class OdooOrderStatuses
{
    public const STATE_DRAFT = 'draft';
    public const STATE_SENT = 'sent';
    public const STATE_SALE = 'sale';
    public const STATE_DONE = 'done';
    public const STATE_CANCEL = 'cancel';

    /**
     *  Get all statuses odoo sale order.
     *
     * @return array
     */
    public static function getVariants()
    {
        return [
            self::STATE_DRAFT => 'sd_odoo_integration.odoo_state_draft',
            self::STATE_SENT => 'sd_odoo_integration.odoo_state_sent',
            self::STATE_SALE => 'sd_odoo_integration.odoo_state_sale',
            self::STATE_DONE => 'sd_odoo_integration.odoo_state_done',
            self::STATE_CANCEL => 'sd_odoo_integration.odoo_state_cancel',
        ];
    }
}
