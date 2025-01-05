<?php
/**
 * Copyright (c) 2024 AI SmartTalk
 * 
 * NOTICE OF LICENSE
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    AI SmartTalk
 * @copyright 2024
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace PrestaShop\AiSmartTalk;

// Make sure PrestaShop is loaded
if (!defined('_PS_VERSION_')) {
    exit;
}

// If your module folder is named "aismarttalk", you can do:
require_once _PS_MODULE_DIR_ . 'aismarttalk/vendor/autoload.php';

use Context;
use Configuration;
use PrestaShopException;
use PrestaShopLogger;
use Customer;
use Order;

/**
 * Class CustomerSync
 * Handles batch exporting of PrestaShop customers to AI SmartTalk
 */
class CustomerSync
{
    /** @var int Number of customers to export per batch */
    private $batchSize = 50;

    /** @var int Total count of customers to export */
    private $totalCustomers = 0;

    /** @var int Number of customers processed so far */
    private $processedCustomers = 0;

    /** @var Context PrestaShop context */
    private $context;

    /**
     * CustomerSync constructor.
     */
    public function __construct()
    {
        $this->context = Context::getContext();
    }

    /**
     * Export a batch of customers to AI SmartTalk.
     *
     * @param Customer[] $customers Array of PrestaShop Customer objects
     *
     * @return bool True on success, false otherwise
     */
    public function exportCustomerBatch(array $customers)
    {
        $aiSmartTalkUrl   = Configuration::get('AI_SMART_TALK_URL');
        $chatModelId      = Configuration::get('CHAT_MODEL_ID');
        $chatModelToken   = Configuration::get('CHAT_MODEL_TOKEN');

        // Map PrestaShop customer data to the expected AI SmartTalk format
        $customerData = array_map([$this, 'mapCustomerData'], $customers);

        // Prepare the payload
        $payload = [
            'customers'       => $customerData,
            'chatModelId'     => $chatModelId,
            'chatModelToken'  => $chatModelToken,
            'source'          => 'PRESTASHOP',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aiSmartTalkUrl . '/api/v1/crm/importCustomer');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Optional: set timeouts and SSL settings as needed
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $result   = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check for cURL execution errors
        if ($result === false || !empty($error)) {
            PrestaShopLogger::addLog(
                'AI SmartTalk customer sync cURL error: ' . $error,
                3,
                null,
                'Customer',
                null,
                true
            );
            return false;
        }

        // Check the HTTP status code (consider 2xx as success)
        if ($httpCode < 200 || $httpCode > 299) {
            PrestaShopLogger::addLog(
                'AI SmartTalk customer sync failed. HTTP code: ' . $httpCode . ' - Response: ' . $result,
                3,
                null,
                'Customer',
                null,
                true
            );
            return false;
        }

        // If we reach here, it's a success
        return true;
    }

    /**
     * Map a PrestaShop Customer object to AI SmartTalk format
     *
     * @param Customer $customer
     *
     * @return array
     */
    private function mapCustomerData(Customer $customer)
    {
        // Get addresses associated with this customer
        $addresses = $customer->getAddresses((int)$this->context->language->id);
        
        // Get the first address if available
        $firstAddress = !empty($addresses) ? reset($addresses) : null;
        
        $mappedData = [
            'email'      => $customer->email,
            'firstname'  => $customer->firstname,
            'lastname'   => $customer->lastname,
            'phone'      => $firstAddress ? $firstAddress['phone'] : null,
            'address'    => $firstAddress ? $firstAddress['address1'] : null,
            'city'       => $firstAddress ? $firstAddress['city'] : null,
            'country'    => $firstAddress ? $firstAddress['country'] : null,
            'postalCode' => $firstAddress ? $firstAddress['postcode'] : null,
        ];

        return $mappedData;
    }

    /**
     * Export all customers in batches to AI SmartTalk.
     * 
     * @return array Status of the export:
     *               [
     *                  'success' => bool,
     *                  'total' => int,
     *                  'processed' => int,
     *                  'errors' => array
     *               ]
     */
    public function exportAllCustomers()
    {
        try {
            // Retrieve all customers in PrestaShop
            $customers = Customer::getCustomers(true);
            $this->totalCustomers = count($customers);
            $this->processedCustomers = 0;
            $offset = 0;
            $errors = [];

            // Process in batches
            while ($customerBatch = array_slice($customers, $offset, $this->batchSize)) {
                // Convert each array-of-data to a Customer object
                // (Alternatively, getCustomers() already returns arrays; 
                //  you might need to re-instantiate Customer objects if required)
                $customerObjects = array_map(function ($cData) {
                    return new Customer((int)$cData['id_customer']);
                }, $customerBatch);

                if (!$this->exportCustomerBatch($customerObjects)) {
                    $errors[] = sprintf('Failed to export batch starting at offset %d', $offset);
                }

                $this->processedCustomers += count($customerBatch);
                $offset += $this->batchSize;
            }

            return [
                'success'   => empty($errors),
                'total'     => $this->totalCustomers,
                'processed' => $this->processedCustomers,
                'errors'    => $errors,
            ];
        } catch (PrestaShopException $e) {
            PrestaShopLogger::addLog(
                'AI SmartTalk customer sync exception: ' . $e->getMessage(),
                3,
                null,
                'Customer',
                null,
                true
            );
            return [
                'success' => false,
                'errors'  => [$e->getMessage()],
            ];
        } catch (\Exception $e) {
            // Catch any other exceptions
            PrestaShopLogger::addLog(
                'AI SmartTalk customer sync general exception: ' . $e->getMessage(),
                3,
                null,
                'Customer',
                null,
                true
            );
            return [
                'success' => false,
                'errors'  => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get current export progress in terms of total customers and processed customers.
     *
     * @return array [
     *   'total'      => int,
     *   'processed'  => int,
     *   'percentage' => float
     * ]
     */
    public function getProgress()
    {
        return [
            'total'      => $this->totalCustomers,
            'processed'  => $this->processedCustomers,
            'percentage' => $this->totalCustomers > 0
                ? round(($this->processedCustomers / $this->totalCustomers) * 100)
                : 0,
        ];
    }
}
