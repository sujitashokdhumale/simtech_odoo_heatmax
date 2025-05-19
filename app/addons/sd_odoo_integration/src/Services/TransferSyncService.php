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

use Tygh\Addons\SdOdooIntegration\Exceptions\OdooException;
use Tygh\Addons\SdOdooIntegration\OdooCron;
use Tygh\Addons\SdOdooIntegration\Services\OrderSyncService;
use Tygh\Enum\Addons\SdOdooIntegration\OdooCronStatus;
use Tygh\Enum\Addons\SdOdooIntegration\OdooEntities;
use Tygh\Tygh;

/**
 * The service that handles syncing transfers of data odoo.
 */
class TransferSyncService
{
    public const ODOO_IMPORT_CHUNK_SIZE = 50;
    protected $odoo_cron;
    protected $company_id;
    protected $last_launch;
    protected $start_time;
    protected $default_shippment_id;

    /**
     * Transfers sync constructor.
     */
    public function __construct($company, $start_time)
    {
        $this->company_id = $company['company_id'];
        $this->odoo_cron = new OdooCron($company['company_id'], OdooEntities::TRANSFER);
        $this->last_launch = $this->odoo_cron->last_launch;
        $this->start_time = $start_time;
        $this->default_shippment_id = $company['odoo_shipment_import_id'];
    }

    /**
     * The main function of receiving pricelist from odoo.
     *
     * @return true
     */
    public function syncTransfer()
    {
        try {
            $result = false;
            if ($this->odoo_cron->getActiveState($this->company_id, OdooEntities::TRANSFER, $this->start_time)) {
                return false;
            }
            $current_connection = Tygh::$app['addons.sd_odoo_integration.odoo_connect']->getConnection($this->company_id);
            if (!$current_connection) {
                return false;
            }
            $force_notification = fn_get_notification_rules([], true);
            $this->odoo_cron->updateState($this->company_id, OdooEntities::TRANSFER, OdooCronStatus::IN_PROGRESS, $this->start_time);
            date_default_timezone_set('UTC');
            $filter_date = date('Y-m-d H:i:s', $this->last_launch);
            $transfer_count = $current_connection->execute('stock.picking', 'search_count', [[['write_date', '>', $filter_date], ['sale_id', '!=', false], ['picking_type_code', '=', 'outgoing']]]);
            $count_chunk = ceil($transfer_count / self::ODOO_IMPORT_CHUNK_SIZE);
            $fields_transfers = fn_get_schema('odoo', 'transfer_fields');
            $chunk_index = 0;
            while ($chunk_index < $count_chunk) {
                $offset = $chunk_index * self::ODOO_IMPORT_CHUNK_SIZE;
                $transfers = $current_connection->execute('stock.picking', 'search', [[['write_date', '>', $filter_date], ['sale_id', '!=', false], ['picking_type_code', '=', 'outgoing']]], ['offset' => $offset, 'limit' => self::ODOO_IMPORT_CHUNK_SIZE, 'order' => 'id']);
                $transfers_data = $current_connection->execute('stock.picking', 'read', [$transfers], ['fields' => $fields_transfers]);
                foreach ($transfers_data as $item) {
                    if (!(empty($item['date_done']))) {
                        $date = fn_date_to_timestamp($item['date_done']);
                    } else {
                        $date = fn_date_to_timestamp($item['date']);
                    }
                    $order_id_cscart = OrderSyncService::issetOrderInCscart($item['sale_id'][0], $this->company_id);
                    $order_id = is_array($order_id_cscart) ? reset($order_id_cscart) : false;
                    $params['order_id'] = $order_id;
                    $params['advanced_info'] = false;
                    list($shipments,) = fn_get_shipments_info($params);
                    $data = reset($shipments);
                    if (empty($data)) {
                        $shipment_data_odoo = [
                            'timestamp' => $date,
                            'order_id' => $params['order_id'],
                            'shipping_id' => $this->default_shippment_id,
                            'tracking_number' => $item['id'],
                            'status' => self::getStatusByOdooTransferStatus($item['state']),
                        ];
                        $result = fn_update_shipment($shipment_data_odoo, 0, 0, true, $force_notification);
                    } else {
                        $data['status'] = self::getStatusByOdooTransferStatus($item['state']);
                        $date['timestamp'] = $date;
                        $result = fn_update_shipment($data, $data['shipment_id']);
                    }
                }
                ++$chunk_index;
            }
            $this->odoo_cron->updateState($this->company_id, OdooEntities::TRANSFER, OdooCronStatus::SUCCESS, $this->start_time);
            return $result;
        } catch (OdooException $e) {
            $this->odoo_cron->updateState($this->company_id, OdooEntities::TRANSFER, OdooCronStatus::FAIL, $this->last_launch);
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.cron_odoo_error', [
                    '[company_id]' => $this->company_id,
                    '[error]' => $e->getMessage(),
                ]),
            ]);
        }
    }


    /**
     * Get shipment status by odoo transfer status
     * 
     * @param string $status_from_odoo
     *
     * @return string
     */
    public function getStatusByOdooTransferStatus($status_from_odoo)
    {
        return db_get_field('SELECT statuses.status FROM ?:statuses AS statuses '
        . 'LEFT JOIN ?:status_data AS data ON statuses.status_id = data.status_id WHERE '
        . 'data.param = ?s AND data.value = ?s LIMIT 1', 'transfer_status', $status_from_odoo);
    }
}
