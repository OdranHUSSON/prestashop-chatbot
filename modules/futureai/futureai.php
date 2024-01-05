<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

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

    private function sendProductsToApi($futureAiUrl, $chatModelId) {
        $sql = "SELECT p.id_product, pl.name, pl.description, pl.description_short, 
                   p.reference, p.price, p.active, cl.link_rewrite AS category,
                   CONCAT('http://yourdomain.com/', cl.link_rewrite, '/', p.id_product, '-', pl.link_rewrite, '.html') AS product_url 
            FROM " . _DB_PREFIX_ . "product p 
            JOIN " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product 
            JOIN " . _DB_PREFIX_ . "category_lang cl ON p.id_category_default = cl.id_category 
            WHERE pl.id_lang = 1 AND cl.id_lang = 1 AND p.active = 1";

        $products = Db::getInstance()->executeS($sql);

        $documentDatas = [];
        foreach ($products as $product) {
            $documentDatas[] = [
                'id' => $product['id_product'],
                'name' => $product['name'],
                'description' => $product['description'],
                'description_short' => $product['description_short'],
                'reference' => $product['reference'],
                'price' => $product['price'],
                'active' => $product['active'],
                'category' => $product['category'],
                'product_url' => $product['product_url']
            ];

            if (count($documentDatas) === 100) {
                $this->postToApi($documentDatas, $futureAiUrl, $chatModelId);
                $dataToSend = [];
            }
        }

        if (count($documentDatas) > 0) {
            $this->postToApi($documentDatas, $futureAiUrl, $chatModelId);
        }
    }

    private function postToApi($documentDatas, $futureAiUrl, $chatModelId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $futureAiUrl .'/api/document/source');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'documentDatas' => $documentDatas,
            'chatModelId' => $chatModelId
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        $result = curl_exec($ch);
        if($result === false) {
            // Debug: CURL error
             var_dump('Curl error: ' . curl_error($ch));
        } else {
            // Debug: Successful result
             var_dump('CURL execution result:', $result);
        }

        curl_close($ch);

        return $result;
    }


    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $futureAiUrl = Tools::getValue('FUTURE_AI_URL');
            $chatModelId = Tools::getValue('CHAT_MODEL_ID');

            Configuration::updateValue('CHAT_MODEL_ID', $chatModelId);
            Configuration::updateValue('FUTURE_AI_URL', $futureAiUrl);

            $this->sendProductsToApi($futureAiUrl, $chatModelId);
            $output .= $this->displayConfirmation('Les produits ont été synchronisés avec l\'API.');
        }

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
                // Ajouter vos champs de formulaire ici
                [
                    'type' => 'text',
                    'label' => $this->l('Future AI URL'),
                    'name' => 'FUTURE_AI_URL',
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Chat Model ID'),
                    'name' => 'CHAT_MODEL_ID',
                    'size' => 20,
                    'required' => true
                ]
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
        $helper->fields_value = []; // Ajouter les valeurs par défaut ici

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayFooter($params) {
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
    
        $this->context->smarty->assign(array(
            'chatModelId' => $chatModelId,
        ));
    
        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function install() {
        return parent::install() &&
               $this->registerHook('displayFooter');
    }
    

}
