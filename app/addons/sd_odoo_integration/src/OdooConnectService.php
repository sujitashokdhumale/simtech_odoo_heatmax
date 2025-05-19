<?php
/***************************************************************************
*                                                                          *
*   © Simtech Development Ltd.                                             *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
***************************************************************************/

namespace Tygh\Addons\SdOdooIntegration;

use Tygh\Addons\SdOdooIntegration\Exceptions\OdooException;
use Tygh\Application;

class OdooConnectService
{
    public const CONNECTION_TIMEOUT = 900;

    protected $connection_pull = [];
    protected $condition_params;

    /**
     * @var \Tygh\Application
     */
    protected $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->condition_params = $this->getConnectionParams();
    }

    /**
     * Gets the current connection or creates a new one.
     *
     * @param int $company_id Company ID
     */
    public function getConnection($company_id)
    {
        if (empty($this->condition_params[$company_id])) {
            return false;
        }
        if (empty($this->connection_pull[$company_id])) {
            $this->connection_pull[$company_id] = new OdooConnection(
                $this->condition_params[$company_id]['odoo_username'],
                $this->condition_params[$company_id]['odoo_api_key'],
                $this->condition_params[$company_id]['odoo_database'],
                $this->condition_params[$company_id]['odoo_url'],
                $company_id
            );
        } else {
            $started = $this->connection_pull[$company_id]->getStartTime();
            if ((TIME - $started) > self::CONNECTION_TIMEOUT) {
                unset($this->connection_pull[$company_id]);
                $this->connection_pull[$company_id] = new OdooConnection(
                    $this->condition_params[$company_id]['odoo_username'],
                    $this->condition_params[$company_id]['odoo_api_key'],
                    $this->condition_params[$company_id]['odoo_database'],
                    $this->condition_params[$company_id]['odoo_url'],
                    $company_id
                );
            }
        }

        return $this->connection_pull[$company_id];
    }

    /**
     * Gets connection parameters for all companies.
     *
     * @return array
     */
    protected function getConnectionParams()
    {
        $fields = [
            '?:companies.company_id',
            '?:companies.odoo_url',
            '?:companies.odoo_database',
            '?:companies.odoo_username',
            '?:companies.odoo_api_key',
        ];
        $condition = '';
        $condition .= db_quote(
            ' AND ?:companies.odoo_url <> ?s AND ?:companies.odoo_database <> ?s AND ?:companies.odoo_username <> ?s AND ?:companies.odoo_api_key <> ?s',
            '', '', '', ''
        );

        return db_get_hash_array('SELECT ' . implode(', ', $fields) . ' FROM ?:companies WHERE 1 ?p', 'company_id', $condition);
    }
}
