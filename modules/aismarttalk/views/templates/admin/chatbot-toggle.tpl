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
    <div class="panel-heading">
        {l s='Chatbot Activation' mod='aismarttalk'}
    </div>
    <div class="panel-body">
        <form action="{$formAction|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {$enableChatbotText|escape:'html':'UTF-8'}
                </label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="AI_SMART_TALK_ENABLED" id="AI_SMART_TALK_ENABLED_on" value="1" {if $chatbotEnabled}checked="checked"{/if}>
                        <label for="AI_SMART_TALK_ENABLED_on">{l s='Yes' mod='aismarttalk'}</label>
                        <input type="radio" name="AI_SMART_TALK_ENABLED" id="AI_SMART_TALK_ENABLED_off" value="0" {if !$chatbotEnabled}checked="checked"{/if}>
                        <label for="AI_SMART_TALK_ENABLED_off">{l s='No' mod='aismarttalk'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            <div class="panel-footer">
                <button type="submit" name="submitToggleChatbot" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {$saveButtonText|escape:'html':'UTF-8'}
                </button>
            </div>
        </form>
    </div>
</div>