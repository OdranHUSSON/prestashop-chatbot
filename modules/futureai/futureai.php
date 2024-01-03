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

    public function sendProductsToApi() {
        $sql = 'VOTRE REQUETE SQL ICI'; // Remplacez ceci par votre requête SQL
        $products = Db::getInstance()->executeS($sql);

        $csvData = $this->generateCSV($products);

        $userToken = Configuration::get('USER_TOKEN');
        $chatModelToken = Configuration::get('CHAT_MODEL_TOKEN');

        $this->postToApi($csvData, $userToken, $chatModelToken);
    }

    private function generateCSV($data) {
        $filePath = _PS_MODULE_DIR_.$this->name.'/products.csv';
        $file = fopen($filePath, 'w');

        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
        return $filePath;
    }

    private function postToApi($filePath, $userToken, $chatModelToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://future-ai:3000/api/source-chatbot');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($filePath),
            'user_token' => $userToken,
            'chat_model_token' => $chatModelToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $this->sendProductsToApi();
            $output .= $this->displayConfirmation('Les produits ont été synchronisés avec l\'API.');
        }

        return $output.$this->displayForm();
    }

    public function displayForm() {
        // Formulaires pour la configuration du module
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Paramètres'),
            ),
            'input' => array(
                // Ajouter vos champs de formulaire ici
                array(
                    'type' => 'text',
                    'label' => $this->l('User Token'),
                    'name' => 'USER_TOKEN',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Chat Model Token'),
                    'name' => 'CHAT_MODEL_TOKEN',
                    'size' => 20,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Synchroniser'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submit'.$this->name,
            )
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit'.$this->name;
        $helper->fields_value = array(); // Ajouter les valeurs par défaut ici

        return $helper->generateForm($fields_form);
    }

}
