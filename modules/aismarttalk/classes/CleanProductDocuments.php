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
use PrestaShop\PrestaShop\Core\Configuration\Configuration;
use PrestaShop\PrestaShop\Core\Foundation\Database\Db;

class CleanProductDocuments extends Module
{
    private $productIds;

    public function __invoke($args = [])
    {
        foreach ($args as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $value;
        }
        $this->cleanProducts();
    }

    private function fetchAllProductIds()
    {
        $sql = 'SELECT id_product FROM ' . _DB_PREFIX_ . 'product WHERE active = 1';
        $products = Db::getInstance()->executeS($sql);

        return array_map(function ($product) {
            return (string) $product['id_product'];
        }, $products);
    }

    private function cleanProducts()
    {
        $productIds = $this->productIds ? $this->productIds : $this->fetchAllProductIds();
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
                'deleteFromIds' => [] !== $this->productIds ? true : false,
                'source' => 'PRESTASHOP',
            ],
        ));

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
