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

        $this->displayName = $this->trans('AI SmartTalk', [], 'Modules.Futureai.Admin');
        $this->description = $this->trans('https://aismarttalk.tech/', [], 'Modules.Futureai.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Futureai.Admin');

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
            $output .= $this->displayConfirmation('Les produits supprimés et non actifs ont été nettoyés.');
        }

        $output .= $this->handleForm();
        $output .= $this->getConcentInfoIfNotConfigured();
        $output .= $this->displayForm();
        $output .= $this->displayBackOfficeIframe();
        $output .= $this->isConfigured() ? $this->displayButtons() : ''; // Afficher le bouton si configuré

        if (Tools::isSubmit('submitToggleChatbot')) {
            $chatbotEnabled = (bool) Tools::getValue('AI_SMART_TALK_ENABLED');
            Configuration::updateValue('AI_SMART_TALK_ENABLED', $chatbotEnabled);
            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        $output .= $this->displayChatbotToggleForm();

        return $output;
    }

    protected function displayChatbotToggleForm()
    {
        $form = '
            <form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
                <label for="AI_SMART_TALK_ENABLED">' . $this->l('Enable Chatbot:') . '</label>
                <input type="checkbox" name="AI_SMART_TALK_ENABLED" value="1" ' . (Configuration::get('AI_SMART_TALK_ENABLED') ? 'checked' : '') . ' />
                <input type="submit" name="submitToggleChatbot" value="' . $this->l('Save') . '" class="button" />
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

        // Formulaires pour la configuration du module
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Paramètres'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Chat Model ID'),
                    'name' => 'CHAT_MODEL_ID',
                    'required' => true,
                    'desc' => $this->l('ID du chat model'),
                    'value' => !empty(Configuration::get('CHAT_MODEL_ID')) ? Configuration::get('CHAT_MODEL_ID') : ''
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Chat Model Token'),
                    'name' => 'CHAT_MODEL_TOKEN',
                    'size' => 64,
                    'required' => true,
                    'desc' => $this->l('Token du chat model'),
                    'value' => !empty(Configuration::get('CHAT_MODEL_TOKEN')) ? Configuration::get('CHAT_MODEL_TOKEN') : ''
                ],
            ],
            'submit' => [
                'title' => $this->l('Synchroniser'),
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
            ? "<div class='alert alert-info'>
                    Veuillez renseigner les paramètres du chat model. <br>
                    Si vous n'avez pas encore de compte <a target='_blank' href='" . Configuration::get('AI_SMART_TALK_URL') . "'>AI SmartTalk</a>, vous pouvez en créer un <a target='_blank' href='" . Configuration::get('AI_SMART_TALK_URL') . "'>ici</a>
               </div>"
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
                $output .= $this->displayConfirmation('Tous les produits ont été synchronisés avec l\'API.');
            } else {
                $output .= $this->displayConfirmation('Les nouveaux produits ont été synchronisés avec l\'API.');
            }
        } else {
            $output .= $this->displayError('Une erreur est survenue lors de la synchronisation avec l\'API.');
            $output .= Configuration::get('AI_SMART_TALK_ERROR') ? $this->displayError(Configuration::get('AI_SMART_TALK_ERROR')) : '';
        }

        return $output;
    }
}