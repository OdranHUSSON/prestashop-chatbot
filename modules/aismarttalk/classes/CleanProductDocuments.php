<?php

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use PrestaShop\PrestaShop\Adapter\Module\Module;
use Db;
use Configuration;

class CleanProductDocuments extends Module
{
    public function __invoke()
    {
        $productIds = $this->fetchAllProductIds();
        if (!empty($productIds)) {
            $this->postProductIdsToApi($productIds);
        }
    }

    private function fetchAllProductIds()
    {
        $sql = "SELECT id_product FROM " . _DB_PREFIX_ . "product WHERE active = 1";
        $products = Db::getInstance()->executeS($sql);

        return array_map(function($product) {
            return (string) $product['id_product'];
        }, $products);
    }

    private function postProductIdsToApi($productIds)
    {
        $aiSmartTalkUrl = Configuration::get('AI_SMART_TALK_URL');
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = Configuration::get('CHAT_MODEL_TOKEN');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aiSmartTalkUrl . '/api/document/clean');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(
                [
                    'productIds' => $productIds,
                    'chatModelId' => $chatModelId,
                    'chatModelToken' => $chatModelToken,
                    'source' => 'PRESTASHOP'
                ]
            )
        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        if ($result === false) {
            Configuration::updateValue('CLEAN_PRODUCT_DOCUMENTS_ERROR', curl_error($ch));
        } else {
            $response = json_decode($result, true);
            if (isset($response['status']) && $response['status'] == 'error') {
                Configuration::updateValue('CLEAN_PRODUCT_DOCUMENTS_ERROR', $response['message']);
            } else {
                Configuration::deleteByName('CLEAN_PRODUCT_DOCUMENTS_ERROR');
            }
        }

        curl_close($ch);
    }
}
