{* 
 * Copyright (c) 2024 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2024 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}
 
<div class="panel">
  <h3><i class="icon icon-cogs"></i> {l s='AI SmartTalk Configuration' mod='aismarttalk'}</h3>
  <div class="row">
      <div class="col-md-12">
          <div class="panel">
              <div class="panel-heading">
                  <h4>{l s='Customer Synchronization' mod='aismarttalk'}</h4>
              </div>
              <div class="panel-body">
                  <form method="post" class="form-horizontal">
                      <div class="form-group">
                          <label class="control-label col-lg-4">
                              {l s='Auto-sync customers with AI SmartTalk' mod='aismarttalk'}
                          </label>
                          <div class="col-lg-8">
                              <div class="switch prestashop-switch">
                                  <input type="radio" name="AI_SMART_TALK_CUSTOMER_SYNC" id="AI_SMART_TALK_CUSTOMER_SYNC_on" value="1" {if $customerSyncEnabled}checked="checked"{/if}>
                                  <label for="AI_SMART_TALK_CUSTOMER_SYNC_on">{l s='Yes' mod='aismarttalk'}</label>
                                  <input type="radio" name="AI_SMART_TALK_CUSTOMER_SYNC" id="AI_SMART_TALK_CUSTOMER_SYNC_off" value="0" {if !$customerSyncEnabled}checked="checked"{/if}>
                                  <label for="AI_SMART_TALK_CUSTOMER_SYNC_off">{l s='No' mod='aismarttalk'}</label>
                                  <a class="slide-button btn"></a>
                              </div>
                          </div>
                      </div>
                      <div class="form-group">
                          <div class="col-lg-8 col-lg-offset-4">
                              <button type="submit" name="submitCustomerSync" class="btn btn-success">
                                  <i class="icon icon-save"></i> {l s='Save Settings' mod='aismarttalk'}
                              </button>
                              <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;exportCustomers=1" class="btn btn-info">
                                  <i class="icon icon-upload"></i> {l s='Export Customers' mod='aismarttalk'}
                              </a>
                          </div>
                      </div>
                  </form>
              </div>
          </div>
      </div>
  </div>
  <div class="row">
      <div class="col-md-12">
          <div class="panel">
              <div class="panel-heading">
                  {l s='AI SmartTalk Backoffice' mod='aismarttalk'}
              </div>
              <div class="panel-body">
                  <a href="{$backofficeUrl|escape:'html':'UTF-8'}" target="_blank" class="btn btn-primary">
                      <i class="icon icon-external-link"></i> {l s='Open AI SmartTalk Backoffice' mod='aismarttalk'}
                  </a>
              </div>
          </div>
      </div>
  </div>
</div>

<!-- views/templates/admin/backoffice.tpl -->
<script>
    window.chatbotSettings = {
      lang: "{$lang|escape:'javascript':'UTF-8'}"
    };
</script>
<script type="text/javascript" src="{$CDN|escape:'html':'UTF-8'}/cdn?chatModelId={$chatModelId|escape:'html':'UTF-8'}" async></script>
<div id="chatbot">Loading ...</div>

<script type="text/javascript">
$(document).ready(function() {
    $('#export-customers').click(function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.ajax({
            url: '{$currentIndex|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}&action=exportCustomers',
            method: 'POST',
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('{l s='Customers exported successfully!' mod='aismarttalk'}');
                } else {
                    showErrorMessage('{l s='Export failed. Please check the logs.' mod='aismarttalk'}');
                }
            },
            error: function() {
                showErrorMessage('{l s='An error occurred during export.' mod='aismarttalk'}');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>