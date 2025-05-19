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

use Tygh\Application;

/**
 * Hook handlers related to companies.
 */
class CompanyHookHandler
{
    /** @var Application */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * "update_company_pre" hook handler.
     *
     * @param array<string,string|int> $company_data Company data
     * @param int $company_id Company ID
     * @param string $lang_code Language code
     * @param string $action Action
     */
    public function onUpdateCompanyPre(&$company_data, $company_id, $lang_code, $action)
    {
        if (!isset($_REQUEST['odoo_pricelist_names'], $_REQUEST['odoo_pricelist_usergroups'])) {
            return;
        }

        $names = (array) $_REQUEST['odoo_pricelist_names'];
        $usergroups = (array) $_REQUEST['odoo_pricelist_usergroups'];
        $mapping = [];
        foreach ($names as $k => $name) {
            $name = trim($name);
            $ug_id = isset($usergroups[$k]) ? (int) $usergroups[$k] : 0;
            if ($name === '' || !$ug_id) {
                continue;
            }
            $mapping[$name] = $ug_id;
        }

        $company_data['odoo_pricelist_mapping'] = json_encode($mapping);
    }
}
