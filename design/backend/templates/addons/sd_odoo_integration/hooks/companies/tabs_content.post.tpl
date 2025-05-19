<div id="content_odoo" class="hidden">
  <div class="control-group">
    <label for="elm_company_odoo_url" class="control-label">{__("sd_odoo_integration.odoo_url")}:</label>
    <div class="controls">
      <input type="text" name="company_data[odoo_url]" id="elm_company_odoo_url" value="{$company_data.odoo_url}"
        class="input-large" />
    </div>
  </div>
  <div class="control-group">
    <label for="elm_company_odoo_database" class="control-label">{__("sd_odoo_integration.odoo_database")}:</label>
    <div class="controls">
      <input type="text" name="company_data[odoo_database]" id="elm_company_odoo_database"
        value="{$company_data.odoo_database}" class="input-large" />
    </div>
  </div>
  <div class="control-group">
    <label for="elm_company_odoo_user_email" class="control-label">{__("sd_odoo_integration.odoo_user_email")}:</label>
    <div class="controls">
      <input type="text" name="company_data[odoo_username]" id="elm_company_odoo_user_email"
        value="{$company_data.odoo_username}" class="input-large" />
    </div>
  </div>
  <div class="control-group">
    <label for="elm_company_odoo_api_key" class="control-label">{__("sd_odoo_integration.odoo_api_key")}:</label>
    <div class="controls">
      <input type="text" name="company_data[odoo_api_key]" id="elm_company_odoo_api_key"
        value="{$company_data.odoo_api_key}" class="input-large" />
    </div>
  </div>
  <div class="control-group">
    <label for="elm_company_odoo_discount_product"
      class="control-label">{__("sd_odoo_integration.odoo_discount_product")}:</label>
    <div class="controls">
      <input type="text" name="company_data[odoo_discount_product]" id="elm_company_odoo_discount_product"
        value="{$company_data.odoo_discount_product}" />
    </div>
  </div>
  <div class="control-group">
    <label class="control-label"> {__("sd_odoo_integration.odoo_shipment_import_sku")}:</label>
    {if $available_shippings}
      <div class="controls">
        <select name="company_data[odoo_shipment_import_id]">
          {foreach from=$available_shippings item="shipping" key="code"}
            <option value="{$code}" {if $company_data.odoo_shipment_import_id == {$code}}selected="selected" {/if}>
              {$shipping}</option>
          {/foreach}
        </select>
      </div>
    {else}
      <div class="controls">
        <p class="muted description">{__("sd_odoo_integration.not_shipments_available")}</p>
      </div>
    {/if}
  </div>
  <div class="control-group">
    <label for="elm_company_odoo_shipment_method_id"
      class="control-label">{__("sd_odoo_integration.odoo_shipment_method_id")}:</label>
    <div class="controls">
      <input type="text" name="company_data[odoo_shipment_method_id]" id="elm_company_odoo_shipment_method_id"
        value="{$company_data.odoo_shipment_method_id}" />
    </div>
  </div>
  {include file="addons/sd_odoo_integration/views/manage_cron.tpl"}
</div>