<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\AiSmartTalk\CleanProductDocuments;
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;
use PrestaShop\AiSmartTalk\OAuthTokenHandler;

class AiSmartTalk extends Module
{
    public function __construct()
    {
        $this->name = 'aismarttalk';
        $this->tab = 'front_office_features';
        $this->version = '2.0.0';
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
    }

    public function install()
    {
        if (
            !parent::install()
            || !$this->registerHook('displayFooter')
            || !$this->registerHook('actionProductUpdate')
            || !$this->registerHook('actionProductCreate')
            || !$this->registerHook('actionProductDelete')
            || !$this->registerHook('actionAuthentication')
            || !$this->registerHook('actionCustomerLogout')
            || !$this->addSynchField()
            || !Configuration::updateValue('AI_SMART_TALK_ENABLED', false)
        ) { // Add default configuration for enabling/disabling the chatbot
            return false;
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
            Db::getInstance()->execute('ALTER TABLE ' . _DB_PREFIX_ . 'product ADD COLUMN aismarttalk_synch TINYINT(1) NOT NULL DEFAULT 0');
        
        return true;
    }
    
    private function removeSynchField()
    {
            Db::getInstance()->execute('ALTER TABLE ' . _DB_PREFIX_ . 'product DROP COLUMN aismarttalk_synch');
        
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
        $form = '
            <form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
                <label for="AI_SMART_TALK_ENABLED">' . $this->trans('Enable Chatbot:', [], 'Modules.Aismarttalk.Admin') . '</label>
                <input type="checkbox" name="AI_SMART_TALK_ENABLED" value="1" ' . (Configuration::get('AI_SMART_TALK_ENABLED') ? 'checked' : '') . ' />
                <input type="submit" name="submitToggleChatbot" value="' . $this->trans('Save', [], 'Modules.Aismarttalk.Admin') . '" class="button" />
            </form>
        ';

        return $form;
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
                    'value' => !empty(Configuration::get('CHAT_MODEL_ID')) ? Configuration::get('CHAT_MODEL_ID') : ''
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Chat Model Token', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'CHAT_MODEL_TOKEN',
                    'size' => 64,
                    'required' => true,
                    'desc' => $this->trans('Token of the chat model', [], 'Modules.Aismarttalk.Admin'),
                    'value' => !empty(Configuration::get('CHAT_MODEL_TOKEN')) ? Configuration::get('CHAT_MODEL_TOKEN') : ''
                ],
            ],
            'submit' => [
                'title' => $this->trans('Synchronize', [], 'Modules.Aismarttalk.Admin'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submit' . $this->name,
            ]
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

        $this->context->smarty->assign(array(
            'chatModelId' => $chatModelId,
            'CDN' => Configuration::get('AI_SMART_TALK_CDN'),
            'lang' => $lang,
            'source' => 'PRESTASHOP',
            'userToken' => $_COOKIE['ai_smarttalk_oauth_token'],
        ));

        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        $idProduct = $params['id_product'];
        $api = new SynchProductsToAiSmartTalk();
        $api(['productIds' => [(string) $idProduct], "forceSync" => true]);
    }

    public function hookActionProductCreate($params)
    {
        $idProduct = $params['id_product'];
        $api = new SynchProductsToAiSmartTalk();
        $api(['productIds' => [(string) $idProduct], "forceSync" => true]);
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

        $backofficeUrl = $this->getApiHost() . "/admin/chatModel/" . $chatModelId;

        $this->context->smarty->assign(array(
            'CDN' => Configuration::get('AI_SMART_TALK_CDN'),
            'backofficeUrl' => $backofficeUrl,
            'chatModelId' => $chatModelId,
            'lang' => $lang,
        ));

        if ($this->isConfigured()) {
            return $this->display(__FILE__, 'views/templates/admin/backoffice.tpl');
        }
    }

    private function getConcentInfoIfNotConfigured()
    {
        return !$this->isConfigured()
            ? "<div class='alert alert-info'>" .
                $this->trans('Please enter the chat model parameters.', [], 'Modules.Aismarttalk.Admin') . "<br>" .
                sprintf($this->trans('If you don\'t have an %s account yet, you can create one %s.', [], 'Modules.Aismarttalk.Admin'),
                    '<a target="_blank" href="' . Configuration::get('AI_SMART_TALK_URL') . '">AI SmartTalk</a>',
                    '<a target="_blank" href="' . Configuration::get('AI_SMART_TALK_URL') . '">' . $this->trans('here', [], 'Modules.Aismarttalk.Admin') . '</a>'
                ) .
              "</div>"
            : '';
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

    private function sync(bool $force = false, $output = "")
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