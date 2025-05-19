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

class OdooTransferStatuses
{
    public const STATE_DRAFT = 'assigned';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_DONE = 'done';
    public const STATE_CANCEL = 'cancel';

    /**
     *  Get all statuses odoo transfers.
     *
     * @return array
     */
    public static function getVariants()
    {
        return [
            self::STATE_DRAFT => 'sd_odoo_integration.odoo_state_assigned',
            self::STATE_CONFIRMED => 'sd_odoo_integration.odoo_state_confirmed',
            self::STATE_DONE => 'sd_odoo_integration.odoo_state_done',
            self::STATE_CANCEL => 'sd_odoo_integration.odoo_state_cancel',
        ];
    }
}
