<div class="control-group setting-wide disable-overlay-wrap" id="select_default_category">
    {if fn_allowed_for("ULTIMATE") && !$runtime.company_id && !$runtime.simple_ultimate}
    <div class="disable-overlay" id="category_logo_disable_overlay"></div>
    {/if}
    <div class="control-group">
        <input type="hidden" name="category_settings[setting_name]" value="odoo_default_category" />
        <label class="control-label" for="sd_odoo_integration_select_default_category">{__("sd_odoo_integration.default_category")}:</label>
        <div class="controls" id="sd_odoo_integration_select_default_category">
            {include file="pickers/categories/picker.tpl"
            data_id="odoo_default_category"
            input_name="category_settings[setting_value]"
            display_input_id="odoo_default_category"
            item_ids=$default_category}

            {if fn_allowed_for("ULTIMATE") && !$runtime.company_id}
            <div class="right">
                {include file="buttons/update_for_all.tpl" display=true object_id="category_settings" name="category_settings[category_update_all_vendors]" hide_element="category_uploader"}
            </div>
            {/if}
            <p class="muted description">{__("sd_odoo_integration.default_category_tooltip")}</p>
        </div>
    </div>
</div>

<script type="text/javascript">
    Tygh.$(document).ready(function(){
    var $ = Tygh.$;
    $('.cm-update-for-all-icon[data-ca-hide-id=category_uploader]').on('click', function() {
        $('#category_uploader').toggleClass('disable-overlay-wrap');
        $('#category_logo_disable_overlay').toggleClass('disable-overlay');
    });
});
</script>
