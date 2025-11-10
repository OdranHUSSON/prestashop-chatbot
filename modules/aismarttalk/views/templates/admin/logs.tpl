{*
* Copyright (c) 2024 AI SmartTalk
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
*}

<div class="panel" id="aismarttalk-logs">
    <div class="panel-heading">
        <i class="icon-list-ul"></i> {l s='AI SmartTalk Debug Logs' mod='aismarttalk'}
        <span class="badge badge-info">{$logs|count}</span>
    </div>
    <div class="panel-body">
        <div class="alert alert-info">
            <p><strong>{l s='Debug Mode' mod='aismarttalk'}</strong></p>
            <p>{l s='These logs show all AI SmartTalk synchronization activities. Useful for debugging stock and product updates.' mod='aismarttalk'}</p>
        </div>

        <div class="form-group">
            <a href="{$clearLogsUrl}" class="btn btn-danger" onclick="return confirm('{l s='Are you sure you want to clear all AI SmartTalk logs?' mod='aismarttalk'}');">
                <i class="icon-trash"></i> {l s='Clear AI SmartTalk Logs' mod='aismarttalk'}
            </a>
            <a href="{$refreshUrl}" class="btn btn-primary">
                <i class="icon-refresh"></i> {l s='Refresh' mod='aismarttalk'}
            </a>
        </div>

        {if $logs|count > 0}
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table class="table table-striped table-hover">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                        <tr>
                            <th style="width: 150px;">{l s='Date' mod='aismarttalk'}</th>
                            <th style="width: 80px;">{l s='Severity' mod='aismarttalk'}</th>
                            <th style="width: 100px;">{l s='Product ID' mod='aismarttalk'}</th>
                            <th>{l s='Message' mod='aismarttalk'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$logs item=log}
                            <tr class="{if $log.severity == 3}danger{elseif $log.severity == 2}warning{else}info{/if}">
                                <td><small>{$log.date_add}</small></td>
                                <td>
                                    {if $log.severity == 1}
                                        <span class="label label-info">INFO</span>
                                    {elseif $log.severity == 2}
                                        <span class="label label-warning">WARNING</span>
                                    {elseif $log.severity == 3}
                                        <span class="label label-danger">ERROR</span>
                                    {else}
                                        <span class="label label-default">DEBUG</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $log.object_id}
                                        <a href="{$baseUrl}index.php?controller=AdminProducts&id_product={$log.object_id}&updateproduct&token={$productToken}" target="_blank">
                                            #{$log.object_id}
                                        </a>
                                    {else}
                                        -
                                    {/if}
                                </td>
                                <td style="word-break: break-all;">
                                    <code style="white-space: pre-wrap; display: block; max-height: 200px; overflow-y: auto;">{$log.message}</code>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-warning">
                <p>{l s='No logs found. Logs will appear here when products are synchronized.' mod='aismarttalk'}</p>
            </div>
        {/if}
    </div>
</div>

<style>
#aismarttalk-logs code {
    background: #f5f5f5;
    padding: 5px;
    border-radius: 3px;
    font-size: 12px;
}
#aismarttalk-logs .table-responsive::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
#aismarttalk-logs .table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
}
#aismarttalk-logs .table-responsive::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
#aismarttalk-logs .table-responsive::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

