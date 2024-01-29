<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class EcomailemailmarketingAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();
    }

    public function displayAjaxSaveApi()
    {
        $apikey = Tools::getValue('apikey');

        if (!$this->isApiKeyValid($apikey)) {
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        echo json_encode($this->saveApi($apikey));
        exit;
    }

    public function saveApi(string $apikey): bool
    {
        Configuration::updateValue('ECOMAIL_API_KEY', $apikey);

        return true;
    }

    protected function isApiKeyValid(string $apiKey): bool
    {
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://api2.ecomailapp.cz/account'
        );
        curl_setopt(
            $ch,
            CURLOPT_RETURNTRANSFER,
            true
        );
        curl_setopt(
            $ch,
            CURLOPT_HEADER,
            false
        );
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Key: ' . $apiKey,
            ]
        );

        $response = curl_exec($ch);
        curl_close($ch);

        $response = (array) json_decode($response);

        return !(isset($response['message']) && $response['message'] === 'Wrong api key');
    }
}
