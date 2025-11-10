<?php
/**
 * Copyright (c) 2024 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2024 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use PrestaShop\PrestaShop\Adapter\Module\Module;

class SynchProductsToAiSmartTalk extends Module
{
    private $forceSync = false;
    private $productIds = [];

    public function __invoke($args = [])
    {
        foreach ($args as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $value;
        }

        return $this->sendProductsToApi();
    }

    private function sendProductsToApi()
    {
        $products = $this->getProductsToSynchronize();

        $baseLink = \Tools::getHttpHost(true) . __PS_BASE_URI__;

        // Get default currency information
        $defaultCurrencyId = (int)\Configuration::get('PS_CURRENCY_DEFAULT');
        $defaultCurrency = new \Currency($defaultCurrencyId);
        $currencySign = $defaultCurrency->sign ?? '€';

        $documentDatas = [];
        $synchronizedProductIds = [];
        foreach ($products as $product) {
            $psProduct = new \Product($product['id_product']);
            $productUrl = $baseLink . $product['link_rewrite'] . '/' . $product['id_product'] . '-' . $product['link_rewrite'] . '.html';

            $imageUrl = null;
            if (!empty($product['id_image'])) {
                $defaultLangId = \Configuration::get('PS_LANG_DEFAULT');
                $imageUrl = \Context::getContext()->link->getImageLink($psProduct->link_rewrite[$defaultLangId], $product['id_image']);
            }

            // Calculate final price considering specific prices (promotions)
            $finalPrice = $psProduct->getPrice();
            $hasSpecialPrice = !empty($product['specific_price']) || !empty($product['price_reduction']);
            
            // Format dates
            $priceFrom = !empty($product['price_from']) && $product['price_from'] !== '0000-00-00 00:00:00' ? $product['price_from'] : null;
            $priceTo = !empty($product['price_to']) && $product['price_to'] !== '0000-00-00 00:00:00' ? $product['price_to'] : null;

            $documentDatas[] = [
                'id' => $product['id_product'],
                'title' => $product['name'],
                'description' => strip_tags($product['description']),
                'description_short' => strip_tags($product['description_short']),
                'reference' => $product['reference'],
                'price' => $finalPrice,
                'currency' => $product['currency_code'] ?? 'EUR',
                'currency_sign' => $currencySign,
                'has_special_price' => $hasSpecialPrice,
                'price_from' => $priceFrom,
                'price_to' => $priceTo,
                'url' => $productUrl,
                'image_url' => $imageUrl,
            ];

            if (count($documentDatas) === 10) {
                if (!$this->postIfDataExists($documentDatas)) {
                    return false;
                }

                $documentDatas = [];
            }

            $synchronizedProductIds[] = $product['id_product'];
        }

        if (!$this->postIfDataExists($documentDatas)) {
            return false;
        }

        if (count($synchronizedProductIds) > 0) {
            $this->markProductsAsSynchronized($synchronizedProductIds);
        }

        return true;
    }

    private function postIfDataExists($documentDatas)
    {
        if (count($documentDatas) > 0 && false === $this->postToApi($documentDatas)) {
            return false;
        }
        return true;
    }

    public function markProductsAsSynchronized($productIds)
    {
        $ids = implode(',', array_map('intval', $productIds));
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'product SET aismarttalk_synch = 1 WHERE id_product IN (' . $ids . ')';
        return \Db::getInstance()->execute($sql);
    }

    private function postToApi($documentDatas)
    {
        $aiSmartTalkUrl = \Configuration::get('AI_SMART_TALK_URL');
        $chatModelId = \Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = \Configuration::get('CHAT_MODEL_TOKEN');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aiSmartTalkUrl . '/api/document/source');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'documentDatas' => $documentDatas,
            'chatModelId' => $chatModelId,
            'chatModelToken' => $chatModelToken,
            'source' => 'PRESTASHOP',
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        if ($result === false) {
            \Configuration::updateValue('AI_SMART_TALK_ERROR', curl_error($ch));
        } else {
            \Configuration::deleteByName('AI_SMART_TALK_ERROR');
        }

        curl_close($ch);

        $response = json_decode($result, true);
        if (isset($response['status']) && $response['status'] == 'error') {
            \Configuration::updateValue('AI_SMART_TALK_ERROR', $response['message']);
            return false;
        }

        return $result;
    }

    private function getProductsToSynchronize()
    {
        $defaultLangId = (int)\Configuration::get('PS_LANG_DEFAULT');
        $defaultShopId = (int)\Context::getContext()->shop->id;
        $defaultCurrencyId = (int)\Configuration::get('PS_CURRENCY_DEFAULT');
        
        $sql = 'SELECT p.id_product, pl.name, pl.description, pl.description_short,
                   p.reference, p.price, cl.link_rewrite, i.id_image,
                   sa.quantity as stock_quantity,
                   p.active,
                   p.available_date,
                   c.iso_code as currency_code,
                   sp.price as specific_price,
                   sp.from as price_from,
                   sp.to as price_to,
                   sp.reduction as price_reduction,
                   sp.reduction_type
            FROM ' . _DB_PREFIX_ . 'product p 
            JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product 
            JOIN ' . _DB_PREFIX_ . 'category_lang cl ON p.id_category_default = cl.id_category 
            LEFT JOIN ' . _DB_PREFIX_ . 'image i ON p.id_product = i.id_product AND i.cover = 1
            LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON p.id_product = sa.id_product 
                AND sa.id_product_attribute = 0 
                AND sa.id_shop = ' . $defaultShopId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'currency c ON c.id_currency = ' . $defaultCurrencyId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'specific_price sp ON p.id_product = sp.id_product 
                AND (sp.from = "0000-00-00 00:00:00" OR sp.from <= NOW()) 
                AND (sp.to = "0000-00-00 00:00:00" OR sp.to >= NOW())
                AND sp.id_shop = ' . $defaultShopId . '
            WHERE pl.id_lang = ' . $defaultLangId . ' AND cl.id_lang = ' . $defaultLangId . ' AND p.active = 1';

        $sql .= $this->forceSync === false ? ' AND p.aismarttalk_synch = 0' : '';
        $sql .= $this->productIds ? ' AND p.id_product IN (' . implode(',', $this->productIds) . ')' : '';
        $products = \Db::getInstance()->executeS($sql);

        return $products;
    }
}
