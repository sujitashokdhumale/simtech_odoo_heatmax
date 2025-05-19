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

use Tygh\Enum\Addons\SdOdooIntegration\OdooCronStatus;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\SiteArea;
use Tygh\Enum\UserTypes;
use Tygh\NotificationsCenter\NotificationsCenter;
use Tygh\Registry;
use Tygh\Tygh;

class OdooCron
{
    /** @var int */
    public $last_launch;

    /** @var string */
    protected $company_id;

    /** @var bool */
    protected $has_record;

    /**
     * Manager constructor.
     *
     * @param string $company_id
     * @param string $type_entity
     */
    public function __construct(
        $company_id,
        $type_entity
    ) {
        $last_state = $this->getLastState($company_id, $type_entity);
        $this->last_launch = $last_state['last_launch'] ?? 0;
        $this->has_record = $last_state['last_status'] ?? false;
    }

    /**
     * Get active state.
     *
     * @param int    $company_id
     * @param string $type_entity
     * @param int    $start_time
     *
     * @return bool
     */
    public function getActiveState($company_id, $type_entity, $start_time)
    {
        $result = db_get_field(
            'SELECT last_launch FROM ?:cron_odoo_states'
            . ' WHERE current_status = ?s AND company_id = ?s AND type_entity = ?s',
            OdooCronStatus::IN_PROGRESS,
            $company_id,
            $type_entity
        );

        if (!empty($result) || $result == '0') {
            fn_log_event('general', 'runtime', [
                'message' => __('sd_odoo_integration.running_another_cron', [
                    '[start_time]' => $start_time,
                ]),
            ]);
            $delta_time = $start_time - $this->last_launch;
            if ($delta_time > 3600) {
                /** @var \Tygh\NotificationsCenter\NotificationsCenter $notifications_center */
                $notifications_center = Tygh::$app['notifications_center'];
                $force_notification = [
                    UserTypes::ADMIN => true,
                ];
                $title = __('sd_odoo_integration.cron_is_broken');
                $notifications_center->add([
                    'user_id' => 1,
                    'title' => $title,
                    'message' => __('sd_odoo_integration.cron_is_broken', [
                        '[company_id]' => $company_id,
                    ]),
                    'severity' => NotificationSeverity::INFO,
                    'area' => SiteArea::ADMIN_PANEL,
                    'section' => NotificationsCenter::SECTION_ADMINISTRATION,
                    'tag' => NotificationsCenter::TAG_OTHER,
                    'language_code' => Registry::get('settings.Appearance.backend_default_language'),
                    'pinned' => true,
                    'remind' => false,
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * Update status and run time cron.
     *
     * @param string @company_id
     * @param string @type_entity
     * @param string @status
     * @param int @time
     *
     * @return bool result of updating state
     */
    public function updateState($company_id, $type_entity, $status, $time)
    {
        $data = [
            'type_entity' => $type_entity,
            'company_id' => $company_id,
        ];
        switch ($status) {
            case OdooCronStatus::SUCCESS:
                $data += [
                    'last_launch' => $time,
                    'last_status' => $status,
                    'current_status' => '',
                ];
                break;
            case OdooCronStatus::FAIL:
                $data += [
                    'last_launch' => $this->last_launch,
                    'last_status' => $status,
                    'current_status' => '',
                ];
                break;
            case OdooCronStatus::IN_PROGRESS:
                $data += [
                    'current_status' => $status,
                ];
                break;
        }

        return (bool) db_replace_into('cron_odoo_states', $data);
    }

    /**
     * Getting the latest cron run time and status.
     *
     * @param string $company_id
     * @param string $type_entity
     *
     * @return array|bool
     */
    public function getLastState($company_id, $type_entity)
    {
        $result = db_get_row(
            'SELECT last_launch, last_status FROM ?:cron_odoo_states'
            . ' WHERE company_id = ?s AND type_entity = ?s',
            $company_id,
            $type_entity
        );

        return $result;
    }

    /**
     * Getting the latest cron run time and status.
     *
     * @param string $company_id
     * @param string $type_entity
     *
     * @return int
     */
    public function getLastLaunch($company_id, $type_entity)
    {
        $result = db_get_field(
            'SELECT last_launch FROM ?:cron_odoo_states'
            . ' WHERE company_id = ?s AND type_entity = ?s',
            $company_id,
            $type_entity
        );

        return $result;
    }

    /**
     * Clears all cron statuses that are in progress.
     *
     * @param string $company_id
     *
     * @return int
     */
    public static function resetStatusCron($company_id)
    {
        $result = db_query('UPDATE ?:cron_odoo_states SET current_status = ?s WHERE company_id = ?s AND current_status = ?s', '', $company_id, OdooCronStatus::IN_PROGRESS);

        return $result;
    }

    /**
     * Сlears all last_launch timestamps by organization.
     *
     * @param string $company_id
     *
     * @return int
     */
    public static function resetLastLaunch($company_id)
    {
        $result = db_query('UPDATE ?:cron_odoo_states SET last_launch = ?i WHERE company_id = ?s', 0, $company_id);

        return $result;
    }

    /**
     * Get cron job statistics by organization.
     *
     * @param string $company_id
     *
     * @return array
     */
    public static function getStatisticsCronJob($company_id)
    {
        $result = db_get_hash_multi_array('SELECT * FROM ?:cron_odoo_states WHERE company_id = ?s', ['type_entity'], $company_id);

        return $result;
    }
}
