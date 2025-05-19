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

use Tygh\Core\ApplicationInterface;
use Tygh\Core\BootstrapInterface;
use Tygh\Core\HookHandlerProviderInterface;

class Bootstrap implements BootstrapInterface, HookHandlerProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function boot(ApplicationInterface $app)
    {
        $app->register(new ServiceProvider());
    }

    /**
     * {@inheritDoc}
     */
    public function getHookHandlerMap(): array
    {
        $hooks = [
            'delete_product_post' => [
                'addons.sd_odoo_integration.hook_handlers.product',
                'deleteProductPost',
            ],
            'update_product_post' => [
                'addons.sd_odoo_integration.hook_handlers.product',
                'onUpdateProductPost',
            ],
            'delete_product_pre' => [
                'addons.sd_odoo_integration.hook_handlers.product',
                'deleteProductPre',
            ],
            'clone_product_data' => [
                'addons.sd_odoo_integration.hook_handlers.product',
                'cloneProductData',
            ],
            'get_cart_product_data_post' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'getCartProductDataPost',
            ],
            'create_order' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'createOrder',
            ],
            'update_order' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'updateOrder',
            ],
            'pre_get_cart_product_data' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'preGetCartProductData',
            ],
            'get_status_params_definition' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'getStatusParamsDefinition',
            ],
            'change_order_status_post' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'changeOrderStatusPost',
            ],
            'delete_order' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'deleteOrder',
            ],
            'get_statuses_post' => [
                'addons.sd_odoo_integration.hook_handlers.cart',
                'getStatusesPost',
            ],
        ];

        return $hooks;
    }
}
