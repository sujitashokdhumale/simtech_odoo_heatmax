<div class="table-responsive-wrapper longtap-selection">
    <table width="100%" class="table table-middle table--relative table-responsive">
        <tbody>
            <tr>
                <td width="30%">
                    <a class="btn cm-ajax" disabled href="{"odoo.reset_status?company_id={$company_data.company_id}"|fn_url}">{__("sd_odoo_integration.reset_status_cron")}</a>
                </td>
            </tr>
            <tr>
                <td width="30%">
                    <a class="btn cm-ajax" data-ca-target-id="test-connection-result"
                        href="{"odoo.check_connect?company_id={$company_data.company_id}"|fn_url}">{__("sd_odoo_integration.check_connection_to_odoo")}</a>
                </td>
                <td class="left" width="70%">
                    <div id="test-connection-result">
                        <p class="muted description">{$checking_result}</p>
                    <!--test-connection-result--></div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
{if $statistic_cron}
    <div class="table-responsive-wrapper">
        <table width="100%" class="table table-middle table--relative table-responsive">
            <thead data-ca-bulkedit-default-object="true" data-ca-bulkedit-component="defaultObject">
                <tr>
                    <th>{__("sd_odoo_integration.entites")}</th>
                    <th>{__("sd_odoo_integration.last_succes_launch")}</th>
                    <th>{__("sd_odoo_integration.last_status")}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $odoo_entity_names as $key=>$item}
                    <tr>
                        <td>
                            {$item.name}
                        </td>
                        {if (!empty($statistic_cron[$key][0]))}
                            <td>
                                {if $statistic_cron[$key][0].last_launch != 0}
                                    {$statistic_cron[$key][0].last_launch|date_format:"%b %d, %Y  %H:%M"}
                                {else}
                                    {__("none")}
                                {/if}   
                            </td>
                            <td>
                                {$odoo_cron_status_names[$statistic_cron[$key][0].last_status].name}
                            </td>
                        {else}
                            <td>{__("sd_odoo_integration.no_data_cron")}</td>
                        {/if}
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{else}
    <p class="no-items">{__("no_data")}</p>
{/if}