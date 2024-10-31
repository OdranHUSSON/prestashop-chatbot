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
      <div class="col-md-6">
          <div class="panel">
              <div class="panel-heading">
                  {l s='Synchronization' mod='aismarttalk'}
              </div>
              <div class="panel-body">
                  <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;forceSync=false" class="btn btn-default">
                      <i class="icon icon-refresh"></i> {l s='Synchronize New Products' mod='aismarttalk'}
                  </a>
                  <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;forceSync=true" class="btn btn-default">
                      <i class="icon icon-refresh"></i> {l s='Re-Synchronize All Products' mod='aismarttalk'}
                  </a>
              </div>
          </div>
      </div>
      <div class="col-md-6">
          <div class="panel">
              <div class="panel-heading">
                  {l s='Maintenance' mod='aismarttalk'}
              </div>
              <div class="panel-body">
                  <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;clean=true" class="btn btn-default">
                      <i class="icon icon-eraser"></i> {l s='Clean' mod='aismarttalk'}
                  </a>
                  <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;resetConfiguration={$module_name|escape:'html':'UTF-8'}" class="btn btn-default">
                      <i class="icon icon-cog"></i> {l s='Load Another Chat Model' mod='aismarttalk'}
                  </a>
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
<iframe src="{$iframeUrl|escape:'html':'UTF-8'}" width="100%" height="800px"></iframe>
<script type="text/javascript" src="{$CDN|escape:'html':'UTF-8'}/cdn?chatModelId={$chatModelId|escape:'html':'UTF-8'}" async></script>
<div id="chatbot">Loading ...</div>