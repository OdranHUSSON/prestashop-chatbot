<!-- views/templates/admin/backoffice.tpl -->
<script>
    window.chatbotSettings = {
      lang: "{$lang}"
    };
</script>
<iframe src="{$iframeUrl}" width="100%" height="800px"></iframe>
<script type="text/javascript" src="{$CDN}/cdn?chatModelId={$chatModelId}" async></script>
<div id="chatbot">Loading ...</div>
