<?php
/**
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
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\AiSmartTalk\CleanProductDocuments;
use PrestaShop\AiSmartTalk\OAuthTokenHandler;
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;

class AiSmartTalk extends Module
{
    public function __construct()
    {
        $this->name = 'aismarttalk';
        $this->tab = 'front_office_features';
        $this->version = '2.2.1';
        $this->author = 'AI SmartTalk';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('AI SmartTalk', [], 'Modules.Aismarttalk.Admin');
        $this->description = $this->trans('https://aismarttalk.tech/', [], 'Modules.Aismarttalk.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Aismarttalk.Admin');

        // Check if the environment is production or development
        if ($_SERVER['HTTP_HOST'] !== 'prestashop') {
            // Production environment
            Configuration::updateValue('AI_SMART_TALK_URL', 'https://aismarttalk.tech');
            Configuration::updateValue('AI_SMART_TALK_CDN', 'https://cdn.aismarttalk.tech');
        } else {
            // Development environment
            Configuration::updateValue('AI_SMART_TALK_URL', 'http://ai-toolkit-node:3000');
            Configuration::updateValue('AI_SMART_TALK_CDN', 'http://localhost:3001');
        }

        $this->addSynchField();
        $this->registerAiSmartTalkHooks();
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!Configuration::updateValue('AI_SMART_TALK_ENABLED', false)) {
            return false;
        }

        return true;
    }

    public function registerAiSmartTalkHooks()
    {
        $hooks = [
            'displayFooter',
            'actionProductUpdate',
            'actionProductCreate',
            'actionProductDelete',
            'actionAuthentication',
            'actionCustomerLogout',
        ];

        foreach ($hooks as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                if (!$this->registerHook($hook)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->unregisterHook('displayFooter')
            && $this->unregisterHook('actionProductUpdate')
            && $this->unregisterHook('actionProductCreate')
            && $this->unregisterHook('actionProductDelete')
            && $this->unregisterHook('actionAuthentication')
            && $this->unregisterHook('actionCustomerLogout')
            && $this->removeSynchField()
            && Configuration::deleteByName('AI_SMART_TALK_ENABLED');
    }

    public function hookActionAuthentication($params)
    {
        $customer = $params['customer'];
        OAuthTokenHandler::setOAuthTokenCookie($customer);
    }

    public function hookActionCustomerLogout($params)
    {
        OAuthTokenHandler::unsetOAuthTokenCookie();
    }

    private function addSynchField()
    {
        $db = Db::getInstance();
        $tableName = _DB_PREFIX_ . 'product';

        // Check if aismarttalk_synch column exists before adding
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_synch'");
        if (empty($result)) {
            $db->execute("ALTER TABLE `$tableName` ADD COLUMN `aismarttalk_synch` TINYINT(1) NOT NULL DEFAULT 0");
        }

        // Check if aismarttalk_last_source column exists before adding
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_last_source'");
        if (empty($result)) {
            $db->execute("ALTER TABLE `$tableName` ADD COLUMN `aismarttalk_last_source` DATETIME NULL");
        }

        return true;
    }

    private function removeSynchField()
    {
        $db = Db::getInstance();
        $tableName = _DB_PREFIX_ . 'product';

        // Check if aismarttalk_synch column exists before dropping
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_synch'");
        if (!empty($result)) {
            $db->execute("ALTER TABLE `$tableName` DROP COLUMN `aismarttalk_synch`");
        }

        // Check if aismarttalk_last_source column exists before dropping
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_last_source'");
        if (!empty($result)) {
            $db->execute("ALTER TABLE `$tableName` DROP COLUMN `aismarttalk_last_source`");
        }

        return true;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::getValue('resetConfiguration') === $this->name) {
            $this->resetConfiguration();
        }

        if (Tools::getValue('forceSync')) {
            $force = Tools::getValue('forceSync') === 'true';
            $output .= $this->sync($force, $output);
        }

        if (Tools::getValue('clean')) {
            (new CleanProductDocuments())();
            $output .= $this->displayConfirmation($this->trans('Deleted and inactive products have been cleaned.', [], 'Modules.Aismarttalk.Admin'));
        }

        $output .= $this->handleForm();
        $output .= $this->getConcentInfoIfNotConfigured();
        $output .= $this->displayForm();
        $output .= $this->displayBackOfficeIframe();

        if (Tools::isSubmit('submitToggleChatbot')) {
            $chatbotEnabled = (bool) Tools::getValue('AI_SMART_TALK_ENABLED');
            Configuration::updateValue('AI_SMART_TALK_ENABLED', $chatbotEnabled);
            $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Modules.Aismarttalk.Admin'));
        }

        $output .= $this->displayChatbotToggleForm();

        return $output;
    }

    protected function displayChatbotToggleForm()
    {
        $this->context->smarty->assign([
            'formAction' => $_SERVER['REQUEST_URI'],
            'chatbotEnabled' => Configuration::get('AI_SMART_TALK_ENABLED'),
            'saveButtonText' => $this->trans('Save', [], 'Modules.Aismarttalk.Admin'),
            'enableChatbotText' => $this->trans('Enable Chatbot:', [], 'Modules.Aismarttalk.Admin'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/chatbot-toggle.tpl');
    }

    public function displayForm()
    {
        // If already configured, no need to display the form
        if ($this->isConfigured() && empty(Configuration::get('AI_SMART_TALK_ERROR'))) {
            return '';
        }

        // Forms for module configuration
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->trans('Parameters', [], 'Modules.Aismarttalk.Admin'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Chat Model ID', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'CHAT_MODEL_ID',
                    'required' => true,
                    'desc' => $this->trans('ID of the chat model', [], 'Modules.Aismarttalk.Admin'),
                    'value' => !empty(Configuration::get('CHAT_MODEL_ID')) ? Configuration::get('CHAT_MODEL_ID') : '',
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Chat Model Token', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'CHAT_MODEL_TOKEN',
                    'size' => 64,
                    'required' => true,
                    'desc' => $this->trans('Token of the chat model', [], 'Modules.Aismarttalk.Admin'),
                    'value' => !empty(Configuration::get('CHAT_MODEL_TOKEN')) ? Configuration::get('CHAT_MODEL_TOKEN') : '',
                ],
            ],
            'submit' => [
                'title' => $this->trans('Synchronize', [], 'Modules.Aismarttalk.Admin'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submit' . $this->name,
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit' . $this->name;
        $helper->fields_value['AI_SMART_TALK_URL'] = Configuration::get('AI_SMART_TALK_URL');
        $helper->fields_value['CHAT_MODEL_ID'] = Configuration::get('CHAT_MODEL_ID');
        $helper->fields_value['CHAT_MODEL_TOKEN'] = Configuration::get('CHAT_MODEL_TOKEN');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayFooter($params)
    {
        if (!Configuration::get('AI_SMART_TALK_ENABLED')) {
            return '';
        }
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $lang = $this->context->language->iso_code;

        $this->context->smarty->assign([
            'chatModelId' => $chatModelId,
            'CDN' => Configuration::get('AI_SMART_TALK_CDN'),
            'lang' => $lang,
            'source' => 'PRESTASHOP',
            'userToken' => $_COOKIE['ai_smarttalk_oauth_token'],
        ]);

        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        $lastTimeWeSynch = Db::getInstance()->getValue('SELECT aismarttalk_last_source FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . (int) $params['id_product']);

        $date = new DateTime();
        $date->modify('-3 seconds')->format('Y-m-d H:i:s');
        $lastTimeWeSynch = (new DateTime($lastTimeWeSynch));

        if (empty($lastTimeWeSynch) || ($date > $lastTimeWeSynch)) {
            $idProduct = $params['id_product'];
            $api = new SynchProductsToAiSmartTalk();
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
            $now = new DateTime();
            Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'product SET aismarttalk_last_source = "' . $now->format('Y-m-d H:i:s') . '" WHERE id_product = ' . (int) $params['id_product']);
        }
    }

    public function hookActionProductCreate($params)
    {
        $idProduct = $params['id_product'];
        $api = new SynchProductsToAiSmartTalk();
        $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
    }

    public function hookActionProductDelete($params)
    {
        $idProduct = $params['id_product'];
        $api = new CleanProductDocuments();
        $api(['productIds' => [(string) $idProduct]]);
    }

    private function getApiHost()
    {
        $url = Configuration::get('AI_SMART_TALK_URL');

        if (strpos($url, 'http://ai-toolkit-node:3000') !== false) {
            $url = str_replace('http://ai-toolkit-node:3000', 'http://localhost:3000', $url);
        }
        return $url;
    }

    public function displayBackOfficeIframe()
    {
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = Configuration::get('CHAT_MODEL_TOKEN');
        $lang = $this->context->language->iso_code;

        $backofficeUrl = $this->getApiHost() . '/admin/chatModel/' . $chatModelId;

        $this->context->smarty->assign([
            'CDN' => Configuration::get('AI_SMART_TALK_CDN'),
            'backofficeUrl' => $backofficeUrl,
            'chatModelId' => $chatModelId,
            'lang' => $lang,
        ]);

        if ($this->isConfigured()) {
            return $this->display(__FILE__, 'views/templates/admin/backoffice.tpl');
        }
    }

    private function getConcentInfoIfNotConfigured()
    {
        if (!$this->isConfigured()) {
            $this->context->smarty->assign([
                'aiSmartTalkUrl' => Configuration::get('AI_SMART_TALK_URL'),
                'enterParamsText' => $this->trans('Please enter the chat model parameters.', [], 'Modules.Aismarttalk.Admin'),
                'accountText' => $this->trans('If you don\'t have an %s account yet, you can create one %s.', [], 'Modules.Aismarttalk.Admin'),
                'hereText' => $this->trans('here', [], 'Modules.Aismarttalk.Admin'),
            ]);

            return $this->display(__FILE__, 'views/templates/admin/configuration-info.tpl');
        }
        return '';
    }

    private function isConfigured()
    {
        return !empty(Configuration::get('CHAT_MODEL_ID'))
            && !empty(Configuration::get('CHAT_MODEL_TOKEN'))
            && empty(Configuration::get('AI_SMART_TALK_ERROR'));
    }

    private function handleForm()
    {
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            Configuration::updateValue('CHAT_MODEL_ID', Tools::getValue('CHAT_MODEL_ID'));
            Configuration::updateValue('CHAT_MODEL_TOKEN', Tools::getValue('CHAT_MODEL_TOKEN'));

            $output = $this->sync(true, $output);
        }

        return $output;
    }

    private function displayButtons()
    {
        return '';
    }

    private function resetConfiguration()
    {
        Configuration::deleteByName('CHAT_MODEL_ID');
        Configuration::deleteByName('CHAT_MODEL_TOKEN');

        return true;
    }

    private function sync(bool $force = false, $output = '')
    {
        $api = new SynchProductsToAiSmartTalk();
        $isSynch = $api(['forceSync' => $force]);

        if (true === $isSynch) {
            if ($force) {
                $output .= $this->displayConfirmation($this->trans('All products have been synchronized with the API.', [], 'Modules.Aismarttalk.Admin'));
            } else {
                $output .= $this->displayConfirmation($this->trans('New products have been synchronized with the API.', [], 'Modules.Aismarttalk.Admin'));
            }
        } else {
            $output .= $this->displayError($this->trans('An error occurred during synchronization with the API.', [], 'Modules.Aismarttalk.Admin'));
            $output .= Configuration::get('AI_SMART_TALK_ERROR') ? $this->displayError(Configuration::get('AI_SMART_TALK_ERROR')) : '';
        }

        return $output;
    }
}
