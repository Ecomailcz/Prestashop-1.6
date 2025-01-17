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

require dirname(__FILE__) . '/../../config/config.inc.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

$requestJson = Tools::file_get_contents('php://input');

if ($requestJson) {
    $request = json_decode($requestJson);

    $email = $request->payload->email;

    $customer = new Customer();

    $customers = $customer->getCustomersByEmail($email);

    if ($request->payload->status === 'SUBSCRIBED') {
        $newsletterStatus = '1';
    } else {
        $newsletterStatus = '0';
    }

    foreach ($customers as $customer) {
        $customerObject = new Customer($customer['id_customer']);

        if (Validate::isLoadedObject($customerObject)) {
            $shopId = (int) $customerObject->id_shop;

            if ($shopId) {
                if (Shop::getContext() != Shop::CONTEXT_SHOP || Shop::getContextShopID() != $shopId) {
                    Shop::setContext(Shop::CONTEXT_SHOP, $shopId);
                }

                $customerObject->newsletter = $newsletterStatus;
                if (!$customerObject->save()) {
                    PrestaShopLogger::addLog('Ecomail Webhook: Failed to save customer newsletter status for ID: ' . $customerObject->id, 3, null, 'Ecomail', null, true);
                }
            } else {
                PrestaShopLogger::addLog('Ecomail Webhook: Invalid shop ID for customer ID: ' . $customerObject->id, 3, null, 'Ecomail', null, true);
            }
        } else {
            PrestaShopLogger::addLog('Ecomail Webhook: Invalid customer object for ID: ' . $customer['id_customer'], 3, null, 'Ecomail', null, true);
        }
    }
}
