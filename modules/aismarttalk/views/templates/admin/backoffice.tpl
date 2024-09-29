<div class="panel">
  <h3><i class="icon icon-cogs"></i> {l s='AI SmartTalk Configuration' mod='aismarttalk'}</h3>
  <div class="row">
      <div class="col-md-6">
          <div class="panel">
              <div class="panel-heading">
                  {l s='Chatbot Settings' mod='aismarttalk'}
              </div>
              <div class="panel-body">
                  <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}" method="post">
                      <div class="form-group">
                          <label>{l s='Enable Chatbot:' mod='aismarttalk'}</label>
                          <span class="switch prestashop-switch fixed-width-lg">
                              <input type="radio" name="AI_SMART_TALK_ENABLED" id="AI_SMART_TALK_ENABLED_on" value="1" {if $AI_SMART_TALK_ENABLED}checked="checked"{/if}>
                              <label for="AI_SMART_TALK_ENABLED_on">{l s='Yes' mod='aismarttalk'}</label>
                              <input type="radio" name="AI_SMART_TALK_ENABLED" id="AI_SMART_TALK_ENABLED_off" value="0" {if !$AI_SMART_TALK_ENABLED}checked="checked"{/if}>
                              <label for="AI_SMART_TALK_ENABLED_off">{l s='No' mod='aismarttalk'}</label>
                              <a class="slide-button btn"></a>
                          </span>
                      </div>
                      <button type="submit" name="submitToggleChatbot" class="btn btn-default">
                          {l s='Save' mod='aismarttalk'}
                      </button>
                  </form>
              </div>
          </div>
      </div>
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
  </div>
  <div class="row">
      <div class="col-md-6">
          <div class="panel">
              <div class="panel-heading">
                  {l s='Maintenance' mod='aismarttalk'}
              </div>
              <div class="panel-body">
                  <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;clean=true" class="btn btn-default">
                      <i class="icon icon-eraser"></i> {l s='Clean' mod='aismarttalk'}
                  </a>
                  <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;resetConfiguration={$module_name}" class="btn btn-default">
                      <i class="icon icon-cog"></i> {l s='Load Another Chat Model' mod='aismarttalk'}
                  </a>
              </div>
          </div>
      </div>
      <div class="col-md-6">
          <div class="panel">
              <div class="panel-heading">
                  {l s='AI SmartTalk Backoffice' mod='aismarttalk'}
              </div>
              <div class="panel-body">
                  <a href="{$backofficeUrl}" target="_blank" class="btn btn-primary">
                      <i class="icon icon-external-link"></i> {l s='Open AI SmartTalk Backoffice' mod='aismarttalk'}
                  </a>
              </div>
          </div>
      </div>
  </div>
</div>
