{include 
    file = "common/widget_copy.tpl"
    widget_copy_title = __("tip")
    widget_copy_text = __("sd_odoo_integration.cron_delete_info")
    widget_copy_code_text = "php {$smarty.const.DIR_ROOT}"|fn_get_console_command:$config.admin_index:[
        "dispatch" => "odoo.deleting",
        "cron_password" => $settings.Security.cron_password
    ]
}
