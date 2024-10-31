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

<!-- views/templates/hook/footer.tpl -->
<iframe allowTransparency="true" id="chatbot-iframe" src="{$CDN|escape:'html':'UTF-8'}/chatbot/{$chatModelId|escape:'html':'UTF-8'}?lang={$lang|escape:'html':'UTF-8'}&initialColorMode=light{if isset($userToken)}&userToken={$userToken|escape:'html':'UTF-8'}&source=PRESTASHOP{/if}" style="width: 70px; height: 70px; position: fixed; bottom: 0; right: 0; border: 0; z-index: 1223;"></iframe>
<script
        type="text/javascript" 
        src="{$CDN|escape:'html':'UTF-8'}/latest.js"></script>
