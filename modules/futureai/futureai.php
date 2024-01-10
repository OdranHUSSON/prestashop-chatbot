<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\FutureAi\FutureAiApi;

class FutureAi extends Module
{
    public function __construct()
    {
        $this->name = 'futureai';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Future AI corp';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Future AI', [], 'Modules.Futureai.Admin');
        $this->description = $this->trans('Best chatbot ever.', [], 'Modules.Futureai.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Futureai.Admin');

        if (!Configuration::get('FUTUREAI_NAME')) {
            $this->warning = $this->trans('No name provided', [], 'Modules.Futureai.Admin');
        }
    }

    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $futureAiUrl = Tools::getValue('FUTURE_AI_URL');
            $chatModelId = Tools::getValue('CHAT_MODEL_ID');
            $chatModelToken = Tools::getValue('CHAT_MODEL_TOKEN');

            Configuration::updateValue('FUTURE_AI_URL', $futureAiUrl);
            Configuration::updateValue('CHAT_MODEL_ID', $chatModelId);
            Configuration::updateValue('CHAT_MODEL_TOKEN', $chatModelToken);

            $api = new FutureAiApi();
            $api();
            $output .= $this->displayConfirmation('Les produits ont été synchronisés avec l\'API.');
        }

        $output .= $this->displayBackOfficeIframe();

        return $output.$this->displayForm();
    }

    public function displayForm() {
        // Formulaires pour la configuration du module
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Paramètres'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Future AI URL'),
                    'name' => 'FUTURE_AI_URL',
                    'required' => true,
                    'desc' => $this->l('URL de l\'API Future AI'),
                    'value' => !empty(Configuration::get('FUTURE_AI_URL')) ? Configuration::get('FUTURE_AI_URL') : ''
                ],
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
        $helper->fields_value['FUTURE_AI_URL'] = Configuration::get('FUTURE_AI_URL');
        $helper->fields_value['CHAT_MODEL_ID'] = Configuration::get('CHAT_MODEL_ID');
        $helper->fields_value['CHAT_MODEL_TOKEN'] = Configuration::get('CHAT_MODEL_TOKEN');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayFooter($params) {
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $lang = $this->context->language->iso_code;

        $this->context->smarty->assign(array(
            'chatModelId' => $chatModelId,
            'CDN' => 'http://localhost:3001',
            'lang' => $lang,
        ));
    
        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    private function getApiHost() {
        $url = Configuration::get('FUTURE_AI_URL');

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
            'CDN' => 'http://localhost:3001',
            'iframeUrl' => $iframeUrl,
            'chatModelId' => $chatModelId,
            'lang' => $lang,
        ));

        if (!empty($chatModelId) && !empty($chatModelToken)) {
            return $this->display(__FILE__, 'views/templates/admin/backoffice.tpl');
        }
    }

    public function install() {
        return parent::install() &&
               $this->registerHook('displayFooter');
    }

}
