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
use PrestaShop\AiSmartTalk\CustomerSync;

class AiSmartTalk extends Module
{
    public function __construct()
    {
        $this->name = 'aismarttalk';
        $this->tab = 'front_office_features';
        $this->version = '2.2.3';
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

        // Initialize URL configurations with defaults if not already set or empty
        $defaultUrl = 'https://aismarttalk.tech';
        $defaultCdn = 'https://cdn.aismarttalk.tech';
        
        $currentUrl = Configuration::get('AI_SMART_TALK_URL');
        $currentCdn = Configuration::get('AI_SMART_TALK_CDN');
        
        if (empty($currentUrl) || !filter_var($currentUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_URL', $defaultUrl);
        }
        if (empty($currentCdn) || !filter_var($currentCdn, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_CDN', $defaultCdn);
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

        // Set default URL configurations
        if (!Configuration::updateValue('AI_SMART_TALK_URL', 'https://aismarttalk.tech')) {
            return false;
        }

        if (!Configuration::updateValue('AI_SMART_TALK_CDN', 'https://cdn.aismarttalk.tech')) {
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
            'actionUpdateQuantity',
            'actionProductOutOfStock',
            'actionAuthentication',
            'actionCustomerLogout',
            'actionCustomerAccountAdd',
            'actionCustomerAccountUpdate',
            'actionCustomerDelete',
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
            && $this->unregisterHook('actionUpdateQuantity')
            && $this->unregisterHook('actionProductOutOfStock')
            && $this->unregisterHook('actionAuthentication')
            && $this->unregisterHook('actionCustomerLogout')
            && $this->removeSynchField()
            && Configuration::deleteByName('AI_SMART_TALK_ENABLED')
            && Configuration::deleteByName('AI_SMART_TALK_URL')
            && Configuration::deleteByName('AI_SMART_TALK_CDN')
            && Configuration::deleteByName('CHAT_MODEL_ID')
            && Configuration::deleteByName('CHAT_MODEL_TOKEN');
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

        // Ensure default URLs are always available
        $this->ensureDefaultUrls();

        if (Tools::getValue('resetConfiguration') === $this->name) {
            $this->resetConfiguration();
        }

        if (Tools::getValue('forceSync')) {
            $force = Tools::getValue('forceSync') === 'true';
            $output .= $this->sync($force, $output);
        }

        if (Tools::getValue('exportCustomers')) {
            $sync = new CustomerSync();
            $result = $sync->exportAllCustomers();
            
            if ($result['success']) {
                $output .= $this->displayConfirmation($this->l('Customers exported successfully!'));
            } else {
                $output .= $this->displayError($this->l('Failed to export customers. Please check the logs.'));
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $output .= $this->displayError($error);
                    }
                }
            }
        }

        if (Tools::getValue('clean')) {
            (new CleanProductDocuments())();
            $output .= $this->displayConfirmation($this->trans('Deleted and inactive products have been cleaned.', [], 'Modules.Aismarttalk.Admin'));
        }

        if (Tools::isSubmit('submitToggleChatbot')) {
            $chatbotEnabled = (bool) Tools::getValue('AI_SMART_TALK_ENABLED');
            Configuration::updateValue('AI_SMART_TALK_ENABLED', $chatbotEnabled);
            $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Modules.Aismarttalk.Admin'));
        }

        if (Tools::isSubmit('submitCustomerSync')) {
            $syncEnabled = (bool) Tools::getValue('AI_SMART_TALK_CUSTOMER_SYNC');
            Configuration::updateValue('AI_SMART_TALK_CUSTOMER_SYNC', $syncEnabled);
            $output .= $this->displayConfirmation($this->l('Customer sync settings updated.'));
        }

        if (Tools::isSubmit('submitWhiteLabel')) {
            $url = Tools::getValue('AI_SMART_TALK_URL');
            $cdn = Tools::getValue('AI_SMART_TALK_CDN');
            
            // Validate URLs and use defaults if invalid
            $defaultUrl = 'https://aismarttalk.tech';
            $defaultCdn = 'https://cdn.aismarttalk.tech';
            
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $url = $defaultUrl;
            }
            if (empty($cdn) || !filter_var($cdn, FILTER_VALIDATE_URL)) {
                $cdn = $defaultCdn;
            }
            
            Configuration::updateValue('AI_SMART_TALK_URL', $url);
            Configuration::updateValue('AI_SMART_TALK_CDN', $cdn);
            $output .= $this->displayConfirmation($this->trans('WhiteLabel settings updated.', [], 'Modules.Aismarttalk.Admin'));
        }

        $this->context->smarty->assign([
            'customerSyncEnabled' => Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC'),
            'currentIndex' => $this->context->link->getAdminLink('AdminAiSmartTalk'),
            'token' => Tools::getAdminTokenLite('AdminAiSmartTalk'),
        ]);

        $output .= $this->display(__FILE__, 'views/templates/admin/customer_sync.tpl');

        $output .= $this->handleForm();
        $output .= $this->getConcentInfoIfNotConfigured();
        
        // Always show the configuration form
        $output .= $this->displayForm();
        $output .= $this->displayWhiteLabelForm();
        
        // Show backoffice and chatbot toggle if configured
        if ($this->isConfigured()) {
            $output .= $this->displayBackOfficeIframe();
            $output .= $this->displayChatbotToggleForm();
        }

        return $output;
    }

    protected function displayChatbotToggleForm()
    {
        $this->context->smarty->assign([
            'formAction' => $_SERVER['REQUEST_URI'],
            'chatbotEnabled' => Configuration::get('AI_SMART_TALK_ENABLED'),
            'saveButtonText' => $this->trans('Save', [], 'Modules.Aismarttalk.Admin'),
            'enableChatbotText' => $this->trans('Enable Chatbot', [], 'Modules.Aismarttalk.Admin'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/chatbot-toggle.tpl');
    }

    public function displayForm()
    {
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
                    'value' => Configuration::get('CHAT_MODEL_ID'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Chat Model Token', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'CHAT_MODEL_TOKEN',
                    'size' => 64,
                    'required' => true,
                    'desc' => $this->trans('Token of the chat model', [], 'Modules.Aismarttalk.Admin'),
                    'value' => Configuration::get('CHAT_MODEL_TOKEN'),
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Modules.Aismarttalk.Admin'),
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
        $helper->fields_value['CHAT_MODEL_ID'] = Configuration::get('CHAT_MODEL_ID');
        $helper->fields_value['CHAT_MODEL_TOKEN'] = Configuration::get('CHAT_MODEL_TOKEN');

        return $helper->generateForm($fields_form);
    }

    public function displayWhiteLabelForm()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->trans('WhiteLabel Configuration', [], 'Modules.Aismarttalk.Admin'),
                'icon' => 'icon-cogs',
            ],
            'description' => $this->trans('These settings are primarily used for whitelabel implementations. Contact %s for information and support.', ['contact@aismarttalk.tech'], 'Modules.Aismarttalk.Admin'),
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('AI SmartTalk URL', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'AI_SMART_TALK_URL',
                    'required' => true,
                    'desc' => $this->trans('Base URL of AI SmartTalk API', [], 'Modules.Aismarttalk.Admin'),
                    'value' => Configuration::get('AI_SMART_TALK_URL'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('AI SmartTalk CDN URL', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'AI_SMART_TALK_CDN',
                    'required' => true,
                    'desc' => $this->trans('CDN URL for AI SmartTalk resources', [], 'Modules.Aismarttalk.Admin'),
                    'value' => Configuration::get('AI_SMART_TALK_CDN'),
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save WhiteLabel Settings', [], 'Modules.Aismarttalk.Admin'),
                'class' => 'btn btn-warning pull-right',
                'name' => 'submitWhiteLabel',
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitWhiteLabel';
        $helper->fields_value['AI_SMART_TALK_URL'] = Configuration::get('AI_SMART_TALK_URL');
        $helper->fields_value['AI_SMART_TALK_CDN'] = Configuration::get('AI_SMART_TALK_CDN');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayFooter($params)
    {
        if (!Configuration::get('AI_SMART_TALK_ENABLED')) {
            return '';
        }
        
        // Ensure CDN URL is valid
        $cdn = Configuration::get('AI_SMART_TALK_CDN');
        if (empty($cdn) || !filter_var($cdn, FILTER_VALIDATE_URL)) {
            $cdn = 'https://cdn.aismarttalk.tech';
            Configuration::updateValue('AI_SMART_TALK_CDN', $cdn);
        }
        
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $lang = $this->context->language->iso_code;

        $this->context->smarty->assign([
            'chatModelId' => $chatModelId,
            'CDN' => $cdn,
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

    public function hookActionUpdateQuantity($params)
    {
        if (!isset($params['id_product'])) {
            return;
        }

        $idProduct = $params['id_product'];
        
        // Récupérer la quantité actuelle (après mise à jour)
        $currentQuantity = (int) StockAvailable::getQuantityAvailableByProduct($idProduct);
        
        // Déterminer la quantité précédente
        // Vérifier différentes clés possibles pour le delta
        $deltaQuantity = 0;
        if (isset($params['delta_quantity'])) {
            $deltaQuantity = (int) $params['delta_quantity'];
        } elseif (isset($params['quantity'])) {
            // Si quantity est fourni, c'est le delta dans certains contextes
            $deltaQuantity = (int) $params['quantity'];
        }
        
        // Si on n'a pas de delta, on ne peut pas déterminer la transition, donc on skip
        if ($deltaQuantity === 0) {
            return;
        }

        // Synchroniser uniquement si on passe de 0 à >0 (réapprovisionnement)
        // Le passage à 0 est géré par hookActionProductOutOfStock
        if ($currentQuantity === 0 && $deltaQuantity > 0) {
            $api = new SynchProductsToAiSmartTalk();
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
            $now = new DateTime();
            Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'product SET aismarttalk_last_source = "' . $now->format('Y-m-d H:i:s') . '" WHERE id_product = ' . (int) $idProduct
            );
        }
    }

    public function hookActionProductOutOfStock($params)
    {
        if (!isset($params['id_product'])) {
            return;
        }die('out of stock');

        // Synchronisation quand le produit passe à 0
        $idProduct = $params['id_product'];
        $api = new SynchProductsToAiSmartTalk();
        $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
        $now = new DateTime();
        Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'product SET aismarttalk_last_source = "' . $now->format('Y-m-d H:i:s') . '" WHERE id_product = ' . (int) $idProduct);
    }

    private function getApiHost()
    {
        $url = Configuration::get('AI_SMART_TALK_URL');
        
        // Fallback to default if URL is empty or invalid
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = 'https://aismarttalk.tech';
            Configuration::updateValue('AI_SMART_TALK_URL', $url);
        }

        if (strpos($url, 'http://ai-toolkit-node:3000') !== false) {
            $url = str_replace('http://ai-toolkit-node:3000', 'http://localhost:3000', $url);
        }
        return $url;
    }

    private function ensureDefaultUrls()
    {
        $defaultUrl = 'https://aismarttalk.tech';
        $defaultCdn = 'https://cdn.aismarttalk.tech';
        
        $currentUrl = Configuration::get('AI_SMART_TALK_URL');
        $currentCdn = Configuration::get('AI_SMART_TALK_CDN');
        
        if (empty($currentUrl) || !filter_var($currentUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_URL', $defaultUrl);
        }
        if (empty($currentCdn) || !filter_var($currentCdn, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_CDN', $defaultCdn);
        }
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
            // Ensure URLs are valid before processing
            $this->ensureDefaultUrls();
            
            // Only update Chat Model ID and Token from main form
            // URLs are handled by the WhiteLabel form now
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
        Configuration::deleteByName('AI_SMART_TALK_URL');
        Configuration::deleteByName('AI_SMART_TALK_CDN');

        // Reset to default values
        Configuration::updateValue('AI_SMART_TALK_URL', 'https://aismarttalk.tech');
        Configuration::updateValue('AI_SMART_TALK_CDN', 'https://cdn.aismarttalk.tech');

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

    public function hookActionCustomerAccountAdd($params)
    {
        if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
            return;
        }
        
        $customer = $params['newCustomer'];
        $sync = new CustomerSync();
        $sync->exportCustomerBatch([$customer]);
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
            return;
        }
        
        $customer = $params['customer'];
        $sync = new CustomerSync();
        $sync->exportCustomerBatch([$customer]);
    }

    public function hookActionCustomerDelete($params)
    {
        // Implementation for customer deletion sync
        // This would call a different API endpoint to remove the customer from AI SmartTalk
    }
}
