<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\AiSmartTalk\FutureAiApi;

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
        $this->description = $this->trans('Best chatbot ever.', [], 'Modules.Futureai.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Futureai.Admin');

        Configuration::updateValue('AI_SMART_TALK_URL', 'https://aismarttalk.tech');
    }

    public function getContent() {
        $output = null;

        if (Tools::getValue('resetConfiguration') === $this->name) {
            $this->resetConfiguration();
        }

        $output .= $this->handleForm();
        $output .= $this->getConcentInfoIfNotConfigured();
        $output .= $this->displayForm();
        $output .= $this->displayBackOfficeIframe();
        $output .= $this->isConfigured() ? $this->displayResetButton() : ''; // Afficher le bouton si configuré

        return $output;
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
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $lang = $this->context->language->iso_code;

        $this->context->smarty->assign(array(
            'chatModelId' => $chatModelId,
            'CDN' => 'https://cdn.aismarttalk.tech',
            'lang' => $lang,
        ));
    
        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    private function getApiHost() {
        $url = Configuration::get('AI_SMART_TALK_URL');

        if (strpos($url, 'http://ai-toolkit-node:3000') !== false) {
            $url = str_replace('http://ai-toolkit-node:3000', 'http://localhost:3000', $url);
        }
        return $url;
    }

    public function displayBackOfficeIframe() {
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = Configuration::get('CHAT_MODEL_TOKEN');
        $lang = $this->context->language->iso_code;

        $iframeUrl = $this->getApiHost() .  "/$lang/embedded/$chatModelId/$chatModelToken";

        $this->context->smarty->assign(array(
            'CDN' => 'https://cdn.aismarttalk.tech',
            'iframeUrl' => $iframeUrl,
            'chatModelId' => $chatModelId,
            'lang' => $lang,
        ));

        if ($this->isConfigured()) {
            return $this->display(__FILE__, 'views/templates/admin/backoffice.tpl');
        }
    }

    public function install() {
        return parent::install() &&
               $this->registerHook('displayFooter');
    }

    private function getConcentInfoIfNotConfigured()
    {
        $output = '';
        if (!$this->isConfigured()) {
            $output .= "<div class='alert alert-info'>
                            Veuillez renseigner les paramètres du chat model. <br>
                            Si vous n'avez pas encore de compte <a target='_blank' href='".Configuration::get('AI_SMART_TALK_URL')."'>AI SmartTalk</a>, vous pouvez en créer un <a target='_blank' href='".Configuration::get('AI_SMART_TALK_URL')."'>ici</a>
                       </div>";
        }

        return $output;
    }

    private function isConfigured()
    {
        return !empty(Configuration::get('CHAT_MODEL_ID')) && !empty(Configuration::get('CHAT_MODEL_TOKEN')) && empty(Configuration::get('AI_SMART_TALK_ERROR'));
    }

    private function handleForm()
    {
        $output = '';
        if (Tools::isSubmit('submit'.$this->name)) {

            $chatModelId = Tools::getValue('CHAT_MODEL_ID');
            $chatModelToken = Tools::getValue('CHAT_MODEL_TOKEN');

            Configuration::updateValue('CHAT_MODEL_ID', $chatModelId);
            Configuration::updateValue('CHAT_MODEL_TOKEN', $chatModelToken);

            $api = new FutureAiApi();
            $isSynch = $api();

            if (true === $isSynch) {
                $output .= $this->displayConfirmation('Les produits ont été synchronisés avec l\'API.');
            } else {
                $output .= $this->displayError('Une erreur est survenue lors de la synchronisation avec l\'API.');
                $output .= Configuration::get('AI_SMART_TALK_ERROR') ? $this->displayError(Configuration::get('AI_SMART_TALK_ERROR')) : '';
            }
        }

        return $output;
    }

    private function displayResetButton() {
        // return a button to reset the module calling reset method then reload the current page
        return "<a href='".AdminController::$currentIndex.'&configure='.$this->name .'&resetConfiguration='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules')."' class='btn btn-default pull-left'>Charger un autre modèle de chat</a>";
    }

    private function resetConfiguration()
    {
        Configuration::deleteByName('CHAT_MODEL_ID');
        Configuration::deleteByName('CHAT_MODEL_TOKEN');
        // Additional reset logic here
        return true;
    }

}
