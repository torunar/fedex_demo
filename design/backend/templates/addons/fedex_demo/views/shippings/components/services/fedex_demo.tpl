{$f_package_types = [
    "FEDEX_10KG_BOX", "FEDEX_25KG_BOX", "FEDEX_BOX", "FEDEX_ENVELOPE",
    "FEDEX_EXTRA_LARGE_BOX", "FEDEX_LARGE_BOX", "FEDEX_MEDIUM_BOX", "FEDEX_PAK",
    "FEDEX_SMALL_BOX", "FEDEX_TUBE", "YOUR_PACKAGING"
]}

{$f_drop_off_types = [
    "BUSINESS_SERVICE_CENTER", "DROP_BOX", "REGULAR_PICKUP", "REQUEST_COURIER", "STATION"
]}

<fieldset>

<div class="control-group">
    <label class="control-label" for="user_key">{__("authentication_key")}</label>
    <div class="controls">
    <input id="user_key" type="text" name="shipping_data[service_params][user_key]" size="30" value="{$shipping.service_params.user_key}"/>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="user_key_password">{__("authentication_password")}</label>
    <div class="controls">
    <input id="user_key_password" type="text" name="shipping_data[service_params][user_key_password]" size="30" value="{$shipping.service_params.user_key_password}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="account_number">{__("account_number")}</label>
    <div class="controls">
    <input id="account_number" type="text" name="shipping_data[service_params][account_number]" size="30" value="{$shipping.service_params.account_number}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="ship_fedex_meter_number">{__("ship_fedex_meter_number")}</label>
    <div class="controls">
    <input id="ship_fedex_meter_number" type="text" name="shipping_data[service_params][meter_number]" size="30" value="{$shipping.service_params.meter_number}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="test_mode">{__("test_mode")}</label>
    <div class="controls">
    <input type="hidden" name="shipping_data[service_params][test_mode]" value="N" />
    <input id="test_mode" type="checkbox" name="shipping_data[service_params][test_mode]" value="Y" {if $shipping.service_params.test_mode == "Y"}checked="checked"{/if} />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="package_type">{__("package_type")}</label>
    <div class="controls">
    <select id="package_type" name="shipping_data[service_params][package_type]">
        {foreach $f_package_types as $f_package_type}
            <option value="{$f_package_type}"{if $shipping.service_params.package_type == $f_package_type} selected="selected"{/if}>{__("ship_fedex_package_type_{$f_package_type|lower}")}</option>
        {/foreach}
    </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="ship_fedex_drop_off_type">{__("ship_fedex_drop_off_type")}</label>
    <div class="controls">
    <select id="ship_fedex_drop_off_type" name="shipping_data[service_params][drop_off_type]">
        {foreach $f_drop_off_types as $f_drop_off_type}
            <option value="{$f_drop_off_type}"{if $shipping.service_params.drop_off_type == $f_drop_off_type} selected="selected"{/if}>{__("ship_fedex_drop_off_type_{$f_drop_off_type|lower}")}</option>
        {/foreach}
    </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="max_weight">{__("max_box_weight")}</label>
    <div class="controls">
    <input id="max_weight" type="text" name="shipping_data[service_params][max_weight_of_box]" size="30" value="{$shipping.service_params.max_weight_of_box|default:0}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="ship_fedex_height">{__("ship_fedex_height")}</label>
    <div class="controls">
    <input id="ship_fedex_height" type="text" name="shipping_data[service_params][height]" size="30" value="{$shipping.service_params.height}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="ship_fedex_width">{__("ship_fedex_width")}</label>
    <div class="controls">
    <input id="ship_fedex_width" type="text" name="shipping_data[service_params][width]" size="30" value="{$shipping.service_params.width}"/>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="ship_fedex_length">{__("ship_fedex_length")}</label>
    <div class="controls">
    <input id="ship_fedex_length" type="text" name="shipping_data[service_params][length]" size="30" value="{$shipping.service_params.length}"/>
    </div>
</div>

</fieldset>