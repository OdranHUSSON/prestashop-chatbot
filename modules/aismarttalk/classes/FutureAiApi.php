<?php

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use PrestaShop\PrestaShop\Adapter\Module\Module;
use Db;
use Configuration;
use Tools;
use Context;
use Product;


class FutureAiApi extends Module
{
    public function __invoke()
    {
        return $this->sendProductsToApi();
    }

    private function sendProductsToApi() {
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

        $baseLink = Tools::getHttpHost(true) . __PS_BASE_URI__;

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
                if (false === $this->postToApi($documentDatas)) {
                    return false;
                }

                $documentDatas = [];
            }
        }

        if (count($documentDatas) > 0) {
            if (false === $this->postToApi($documentDatas)) {
                return false;
            }
        }

        return true;
    }

    private function postToApi($documentDatas) {
        $aiSmartTalkUrl = Configuration::get('AI_SMART_TALK_URL');
        $chatModelId = Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = Configuration::get('CHAT_MODEL_TOKEN');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aiSmartTalkUrl .'/api/document/source');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'documentDatas' => $documentDatas,
            'chatModelId' => $chatModelId,
            'chatModelToken' => $chatModelToken
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        $result = curl_exec($ch);
        if($result === false) {
            // Debug: CURL error
            Configuration::updateValue('AI_SMART_TALK_CURL_ERROR', curl_error($ch));
        } else {
            Configuration::deleteByName('AI_SMART_TALK_CURL_ERROR');
        }

        curl_close($ch);

        return $result;
    }
}