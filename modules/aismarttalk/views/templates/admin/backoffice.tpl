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
                  <a href="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;resetConfiguration={$module_name}" class="btn btn-default">
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
                  <a href="{$backofficeUrl}" target="_blank" class="btn btn-primary">
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
      lang: "{$lang}"
    };
</script>
<iframe src="{$iframeUrl}" width="100%" height="800px"></iframe>
<script type="text/javascript" src="{$CDN}/cdn?chatModelId={$chatModelId}" async></script>
<div id="chatbot">Loading ...</div>