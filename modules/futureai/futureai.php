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
                   p.reference, p.price, p.active, cl.link_rewrite AS category, i.id_image,
                   CONCAT(cl.link_rewrite, '/', p.id_product, '-', pl.link_rewrite, '.html') AS product_url,
                   CONCAT('/img/p/', i.id_image, '.jpg') AS image_url
            FROM " . _DB_PREFIX_ . "product p 
            JOIN " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product 
            JOIN " . _DB_PREFIX_ . "category_lang cl ON p.id_category_default = cl.id_category 
            LEFT JOIN " . _DB_PREFIX_ . "image i ON p.id_product = i.id_product AND i.cover = 1
            WHERE pl.id_lang = 1 AND cl.id_lang = 1 AND p.active = 1";

        $products = Db::getInstance()->executeS($sql);

        $baseLink = Tools::getHttpHost(true).__PS_BASE_URI__;

        $documentDatas = [];
        foreach ($products as $product) {
            $productUrl = $baseLink . $product['category'] . '/' . $product['id_product'] . '-' . $product['link_rewrite'] . '.html';

            if (!empty($product['image_url'])) {
                $psProduct = new Product($product['id_product']);
                $defaultLangId = Configuration::get('PS_LANG_DEFAULT');
                $imageId = Product::getCover($psProduct)['id_image'];
                $imageUrl = Context::getContext()->link->getImageLink($psProduct->link_rewrite[$defaultLangId], $imageId);            }

            $documentDatas[] = [
                'id' => $product['id_product'],
                'name' => $product['name'],
                'description' => $product['description'],
                'description_short' => $product['description_short'],
                'reference' => $product['reference'],
                'price' => $product['price'],
                'active' => $product['active'],
                'category' => $product['category'],
                'product_url' => $productUrl,
                'image_url' => $imageUrl
            ];

            if (count($documentDatas) === 100) {
                $this->postToApi($documentDatas, $futureAiUrl, $chatModelId);
                $documentDatas = [];
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
            $token = Tools::getValue('FUTURE_AI_TOKEN');        

            Configuration::updateValue('CHAT_MODEL_ID', $chatModelId);
            Configuration::updateValue('FUTURE_AI_URL', $futureAiUrl);
            Configuration::updateValue('FUTURE_AI_TOKEN', $token);

            $this->sendProductsToApi($futureAiUrl, $chatModelId);
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
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Chat Model ID'),
                    'name' => 'CHAT_MODEL_ID',
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Token'),
                    'name' => 'FUTURE_AI_TOKEN',
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
        $helper->fields_value['FUTURE_AI_URL'] = Configuration::get('FUTURE_AI_URL');
        $helper->fields_value['CHAT_MODEL_ID'] = Configuration::get('CHAT_MODEL_ID');        
        $helper->fields_value['FUTURE_AI_TOKEN'] = Configuration::get('FUTURE_AI_TOKEN');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayFooter($params) {
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
    
        $this->context->smarty->assign(array(
            'chatModelId' => $chatModelId,
        ));
    
        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function displayBackOfficeIframe() {
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $token = Configuration::get('FUTURE_AI_TOKEN');
        $url = Configuration::get('FUTURE_AI_URL');

        // DEV ENVIROMENT REWRITE URL TO localhost:3000 from http://ai-toolkit-node:3000
        if (strpos($url, 'http://ai-toolkit-node:3000') !== false) {
            $url = str_replace('http://ai-toolkit-node:3000', 'http://localhost:3000', $url);
        }        
    
        $iframeUrl = $url . "/embedded/$chatModelId/$token";
        
    
        $this->context->smarty->assign(array(
            'iframeUrl' => $iframeUrl,
        ));
    
        return $this->display(__FILE__, 'views/templates/admin/backoffice.tpl');
    }

    public function install() {
        return parent::install() &&
               $this->registerHook('displayFooter');
    }
    

}
