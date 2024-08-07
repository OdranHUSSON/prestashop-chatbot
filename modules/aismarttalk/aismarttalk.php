<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\AiSmartTalk\CleanProductDocuments;
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;

class AiSmartTalk extends Module
{
    public function __construct()
    {
        $this->name = 'aismarttalk';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
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
        if (!parent::install()
            || !$this->registerHook('displayFooter')
            || !$this->registerHook('actionProductUpdate')
            || !$this->registerHook('actionProductCreate')
            || !$this->registerHook('actionProductDelete')
            || !$this->registerHook('actionAuthentication')
            || !$this->registerHook('actionCustomerLogoutAfter')
            || !$this->addSynchField()
            || !Configuration::updateValue('AI_SMART_TALK_ENABLED', false)) {
                return false;
            }
        return true;
    }


    private function addSynchField()
    {
        // Check if the column already exists
        $columnExists = Db::getInstance()->execute(
            'SELECT COLUMN_NAME 
         FROM information_schema.COLUMNS 
         WHERE 
             TABLE_SCHEMA = "'._DB_PREFIX_.'product" AND 
             TABLE_NAME = "product" AND 
             COLUMN_NAME = "aismarttalk_synch"'
        );

        // If the column does not exist, add it
        if (empty($columnExists)) {
            $sql = 'ALTER TABLE '._DB_PREFIX_.'product ADD COLUMN aismarttalk_synch TINYINT(1) NOT NULL DEFAULT 0;';
            return Db::getInstance()->execute($sql);
        }

        return true;
    }


    public function getContent() {
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
            $chatbotEnabled = (bool)Tools::getValue('AI_SMART_TALK_ENABLED');
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

    public function displayForm() {
        // If already configured, no need to display the form
        if ($this->isConfigured() && empty(Configuration::get('AI_SMART_TALK_ERROR'))) {
            return '';
        }

        // Formulaires pour la configuration du module
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

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
                'name' => 'submit'.$this->name,
            ]
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit'.$this->name;
        $helper->fields_value['AI_SMART_TALK_URL'] = Configuration::get('AI_SMART_TALK_URL');
        $helper->fields_value['CHAT_MODEL_ID'] = Configuration::get('CHAT_MODEL_ID');
        $helper->fields_value['CHAT_MODEL_TOKEN'] = Configuration::get('CHAT_MODEL_TOKEN');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayFooter($params) {
        if (!Configuration::get('AI_SMART_TALK_ENABLED')) {
            return '';
        }
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $lang = $this->context->language->iso_code;

        $this->context->smarty->assign(array(
            'chatModelId' => $chatModelId,
            'CDN' => Configuration::get('AI_SMART_TALK_CDN'),
            'lang' => $lang,
        ));

        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function hookActionProductUpdate($params) {
        $product = $params['product'];
        $idProduct = $product->id;
        $api = new SynchProductsToAiSmartTalk();
        $api(['productIds' => [(string) $idProduct], "forceSync" => true]);
    }

    public function hookActionProductCreate($params) {
        $product = $params['product'];
        $idProduct = $product->id;
        $api = new SynchProductsToAiSmartTalk();
        $api(['productIds' => [(string) $idProduct], "forceSync" => true]);
    }

    public function hookActionProductDelete($params) {
        $product = $params['product'];
        $idProduct = $product->id;
        $api = new CleanProductDocuments();
        $api(['productIds' => [(string) $idProduct]]);
    }

    private function getApiHost() {
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
        $oauthToken = isset($_COOKIE['ai_smarttalk_oauth_token']) ? $_COOKIE['ai_smarttalk_oauth_token'] : '';

        $iframeUrl = $this->getApiHost() . "/$lang/embedded/$chatModelId/$chatModelToken?token=$oauthToken";

        $this->context->smarty->assign([
            'CDN' => Configuration::get('AI_SMART_TALK_CDN'),
            'iframeUrl' => $iframeUrl,
            'chatModelId' => $chatModelId,
            'lang' => $lang,
        ]);

        if ($this->isConfigured()) {
            return $this->display(__FILE__, 'views/templates/admin/backoffice.tpl');
        }
    }


    private function getConcentInfoIfNotConfigured()
    {
        return !$this->isConfigured()
            ? "<div class='alert alert-info'>
                    Veuillez renseigner les paramètres du chat model. <br>
                    Si vous n'avez pas encore de compte <a target='_blank' href='".Configuration::get('AI_SMART_TALK_URL')."'>AI SmartTalk</a>, vous pouvez en créer un <a target='_blank' href='".Configuration::get('AI_SMART_TALK_URL')."'>ici</a>
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
        if (Tools::isSubmit('submit'.$this->name)) {
            Configuration::updateValue('CHAT_MODEL_ID', Tools::getValue('CHAT_MODEL_ID'));
            Configuration::updateValue('CHAT_MODEL_TOKEN', Tools::getValue('CHAT_MODEL_TOKEN'));

            $output = $this->sync(true, $output);
        }

        return $output;
    }

    private function displayButtons() {
        $html = "";

        $html .= "<a href='".AdminController::$currentIndex.'&configure='.$this->name .'&forceSync=false&token='.Tools::getAdminTokenLite('AdminModules')."' class='btn btn-default pull-left'>Synchroniser les nouveaux produits</a>";
        $html .= "<a href='".AdminController::$currentIndex.'&configure='.$this->name .'&forceSync=true&token='.Tools::getAdminTokenLite('AdminModules')."' class='btn btn-default pull-left'>ReSynchroniser tous les produits</a>";
        $html .= "<a href='".AdminController::$currentIndex.'&configure='.$this->name .'&clean=true&token='.Tools::getAdminTokenLite('AdminModules')."' class='btn btn-default pull-left'>Nettoyer</a>";

        $html .= "<a href='".AdminController::$currentIndex.'&configure='.$this->name .'&resetConfiguration='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules')."' class='btn btn-default pull-left'>Charger un autre modèle de chat</a>";

        return $html;
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
        $isSynch = $api(['forceSync' =>$force]);

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

    public function hookActionCustomerLoginAfter($params)
    {
        $customer = $params['customer'];
        $this->setOAuthTokenCookie($customer->email);
    }

    public function hookActionCustomerLogoutAfter($params)
    {
        $this->unsetOAuthTokenCookie();
    }
    private function setOAuthTokenCookie($email)
    {
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = Configuration::get('CHAT_MODEL_TOKEN');
        $source = 'PRESTASHOP';
        
        $response = $this->fetchOAuthToken($chatModelId, $chatModelToken, $source, $email);

        if (!empty($response['token'])) {
            $loginCookieLifetime = time() + (int) Configuration::get('PS_COOKIE_LIFETIME_FO', 14 * 24 * 3600); // 14 days default
            setcookie('ai_smarttalk_oauth_token', $response['token'], $loginCookieLifetime, '/', null, false, true);
            $_COOKIE['ai_smarttalk_oauth_token'] = $response['token'];
        } else {
            PrestaShopLogger::addLog('No token found in response.', 3);
        }
    }

    private function unsetOAuthTokenCookie()
    {
        setcookie('ai_smarttalk_oauth_token', '', time() - 3600, '/', null, false, true);
        unset($_COOKIE['ai_smarttalk_oauth_token']);
    }

    private function fetchOAuthToken($chatModelId, $chatModelToken, $source, $email)
    {
        $url = Configuration::get('AI_SMART_TALK_URL') . '/api/oauth/integration';

        $response = $this->makePostRequest($url, [
            'chatModelId' => $chatModelId,
            'token' => $chatModelToken,
            'source' => $source,
            'email' => $email,
        ]);

        return json_decode($response, true);
    }

    private function makePostRequest($url, $data)
    {
        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
            ],
        ];
        $context  = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }

}
