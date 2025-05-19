{if $product_data.odoo_product_name}
    <div class="control-group">
        <label class="control-label">{__("odoo_product_name")}</label>
        <div class="controls">
            <input type="text" value="{$product_data.odoo_product_name|escape}" readonly class="input-large" />
            <p class="muted description">{__("odoo_product_name_hint")}</p>
        </div>
    </div>
{/if}
