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

<form action="{$formAction|escape:'html':'UTF-8'}" method="post">
    <label for="AI_SMART_TALK_ENABLED">{$enableChatbotText|escape:'html':'UTF-8'}</label>
    <input type="checkbox" name="AI_SMART_TALK_ENABLED" value="1" {if $chatbotEnabled}checked{/if} />
    <input type="submit" name="submitToggleChatbot" value="{$saveButtonText|escape:'html':'UTF-8'}" class="button" />
</form>