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

use \Ripcord\Ripcord;
use Ripcord\Client\Transport\Stream;
use Tygh\Addons\SdOdooIntegration\Exceptions\OdooException;

class OdooConnection
{
    protected $client;
    protected $start_time;
    protected $login;
    protected $password;
    protected $db;
    protected $uid;
    protected $company_id;

    /**
     * OdooConnection constructor.
     */
    public function __construct($login, $password, $db, $url, $company_id)
    {
        $this->password = $password;
        $this->db = $db;

        $stream = new Stream(['http' => ['timeout' => 30]]);

        $common = Ripcord::client("$url/xmlrpc/2/common", null, $stream);
        $this->uid = $common->authenticate($db, $login, $password, []);
        $this->client = Ripcord::client("$url/xmlrpc/2/object", null, $stream);
        $this->start_time = TIME;
        $this->company_id = $company_id;
    }

    /**
     * Executes a query to the odoo system.
     *
     * @param string $model
     * @param string $action
     * @param array  $params
     * @param array  $params2
     *
     * @return array
     */
    public function execute($model, $action, $params, $params2 = [])
    {
        $result = $this->client->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            $model,
            $action,
            $params,
            $params2
        );

        if (isset($result['faultCode'])) {
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.cron_odoo_error', [
                    '[company_id]' => $this->company_id,
                    '[error]' => $result['faultString'],
                ]),
            ]);
            throw new OdooException($result['faultString'], $result['faultCode']);
        }

        return $result;
    }

    /**
     * Get start time current conection.
     *
     * @return int
     */
    public function getStartTime()
    {
        return $this->start_time;
    }

    /**
     * Get uid current conection.
     *
     * @return int
     */
    public function getUid()
    {
        return $this->uid;
    }
}
