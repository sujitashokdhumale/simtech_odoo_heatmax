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

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Tygh\Addons\SdOdooIntegration\HookHandlers\CartHookHandler;
use Tygh\Addons\SdOdooIntegration\HookHandlers\CustomerHookHandler;
use Tygh\Addons\SdOdooIntegration\HookHandlers\ProductHookHandler;
use Tygh\Application;

/**
 * Class ServiceProvider is intended to register services and components
 * of the inventory persistence status add-on to the application container.
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Container $app)
    {
        $app['addons.sd_odoo_integration.hook_handlers.product'] = static function (Application $app) {
            return new ProductHookHandler($app);
        };
        $app['addons.sd_odoo_integration.hook_handlers.cart'] = function (Application $app) {
            return new CartHookHandler($app);
        };
        $app['addons.sd_odoo_integration.hook_handlers.customer'] = function (Application $app) {
            return new CustomerHookHandler($app);
        };
        $app['addons.sd_odoo_integration.odoo_connect'] = function (Container $app) {
            return new OdooConnectService($app);
        };
    }
}
