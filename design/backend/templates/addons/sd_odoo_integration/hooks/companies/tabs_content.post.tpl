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
  <div class="control-group">
    <label class="control-label">{__("sd_odoo_integration.odoo_pricelist_mapping")}</label>
    <div class="controls">
      <table class="table" id="odoo_pricelist_mapping_table">
        <thead>
          <tr>
            <th>{__("sd_odoo_integration.odoo_pricelist_name")}</th>
            <th>{__("usergroup")}</th>
            <th>&nbsp;</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$odoo_pricelist_mapping item="ug_id" key="pl_name"}
            <tr>
              <td><input type="text" name="odoo_pricelist_names[]" value="{$pl_name}" class="input-medium" /></td>
              <td>
                <select name="odoo_pricelist_usergroups[]">
                  {foreach from=$odoo_usergroups item="ug"}
                    <option value="{$ug.usergroup_id}" {if $ug.usergroup_id == $ug_id}selected="selected"{/if}>{$ug.usergroup}</option>
                  {/foreach}
                </select>
              </td>
              <td class="center"><button class="btn cm-delete-row" type="button"><i class="icon-trash"></i></button></td>
            </tr>
          {/foreach}
          <tr class="cm-row-clone hidden">
            <td><input type="text" name="odoo_pricelist_names[]" class="input-medium" /></td>
            <td>
              <select name="odoo_pricelist_usergroups[]">
                {foreach from=$odoo_usergroups item="ug"}
                  <option value="{$ug.usergroup_id}">{$ug.usergroup}</option>
                {/foreach}
              </select>
            </td>
            <td class="center"><button class="btn cm-delete-row" type="button"><i class="icon-trash"></i></button></td>
          </tr>
        </tbody>
      </table>
      <button class="btn" id="add_pricelist_mapping">{__("sd_odoo_integration.add_mapping_row")}</button>
    </div>
  </div>
  {include file="addons/sd_odoo_integration/views/manage_cron.tpl"}
</div>
<script>
  (function(_, $) {
    $('#add_pricelist_mapping').on('click', function(e) {
      e.preventDefault();
      var $row = $('#odoo_pricelist_mapping_table tbody tr.cm-row-clone').first().clone().removeClass('cm-row-clone hidden');
      $('#odoo_pricelist_mapping_table tbody').append($row);
    });
    $('#odoo_pricelist_mapping_table').on('click', '.cm-delete-row', function(e) {
      e.preventDefault();
      $(this).closest('tr').remove();
    });
  })(Tygh, Tygh.$);
</script>
