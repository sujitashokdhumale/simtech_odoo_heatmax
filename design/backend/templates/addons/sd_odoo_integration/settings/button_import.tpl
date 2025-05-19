<div class="control-group">
    <div class="control">
        <a class="cm-ajax cm-comet btn btn-primary" href={fn_url("odoo.import_all_product")}>
            {__("sd_odoo_integration.import_all_product_from_odoo")}</a>
        <a class="cm-ajax cm-comet btn btn-primary"
            href={fn_url("odoo.reset_status")}>{__("sd_odoo_integration.clear_status_in_cron")}</a>
    </div>
</div>