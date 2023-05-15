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
class EcomailAPI
{
    protected $APIKey;

    public function setAPIKey($arg): self
    {
        $this->APIKey = $arg;

        return $this;
    }

    public function getListsCollection()
    {
        return $this->call('lists');
    }

    public function subscribeToList(string $listId, array $customerData)
    {
        return $this->call(
            sprintf(
                'lists/%d/subscribe',
                urlencode($listId)
            ),
            'POST',
            [
                'subscriber_data' => $customerData,
                'resubscribe' => true,
                'update_existing' => true,
                'skip_confirmation' => (bool) Configuration::get('ECOMAIL_SKIP_CONFIRM'),
                'trigger_autoresponders' => (bool) Configuration::get('ECOMAIL_TRIGGER_AUTORESPONDERS'),
            ]
        );
    }

    public function sendBasket(string $email, array $products)
    {
        $data = json_encode([
            'data' => [
                'data' => [
                    'action' => 'Basket',
                    'products' => $products,
                ],
            ],
        ]);

        return $this->call(
            'tracker/events',
            'POST',
            [
                'event' => [
                    'email' => $email,
                    'category' => 'ue',
                    'action' => count($products) === 0 ? 'PrestaEmptyBasket' : 'PrestaBasket',
                    'label' => 'Basket',
                    'value' => "$data",
                ],
            ]
        );
    }

    public function createTransaction(Order $order)
    {
        $arr = [];

        foreach ($order->getProducts() as $orderProduct) {
            $arr[] = $this->buildTransactionItems($orderProduct, (int) $order->date_add);
        }

        return $this->call(
            'tracker/transaction',
            'POST',
            [
                'transaction' => $this->buildTransaction($order),
                'transaction_items' => $arr,
            ]
        );
    }

    protected function call(string $url, string $method = 'GET', ?array $data = null, ?bool $apiNew = false)
    {
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            sprintf('https://%s.ecomailapp.cz/%s', $apiNew ? 'apinew' : 'api2', $url)
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
                'Key: ' . $this->APIKey,
            ]
        );

        if (in_array($method, ['POST', 'PUT'])) {
            curl_setopt(
                $ch,
                CURLOPT_CUSTOMREQUEST,
                $method
            );

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode($data)
            );
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }

    public function bulkSubscribeToList(string $listId, array $data)
    {
        return $this->call(
            sprintf(
                'lists/%d/subscribe-bulk',
                urlencode($listId)
            ),
            'POST',
            [
                'subscriber_data' => $data,
                'resubscribe' => true,
                'update_existing' => true,
                'skip_confirmation' => (bool) Configuration::get('ECOMAIL_SKIP_CONFIRM'),
            ]
        );
    }

    public function bulkOrders(array $data)
    {
        return $this->call(
            'tracker/transaction-bulk',
            'POST',
            [
                'transaction_data' => $data,
            ]
        );
    }

    /**
     * @param array|Order $order
     *
     * @return array
     */
    public function buildTransaction($order): array
    {
        $addressData = [];

        if (Configuration::get('ECOMAIL_LOAD_ADDRESS')) {
            $addressDelivery = new Address($order->id_address_delivery);

            $addressDeliveryCountry = new Country($addressDelivery->id_country);
            $iso_code = $addressDeliveryCountry->iso_code;

            $addressData = [
                'city' => $addressDelivery->city,
                'country' => $iso_code,
            ];
        }

        if (is_array($order)) {
            $customer = new Customer($order['id_customer']);
        } else {
            $customer = $order->getCustomer();
            $order = (array) $order;
        }

        return array_merge(
            [
                'order_id' => 'presta_' . $order['id'],
                'email' => $customer->email,
                'shop' => 'prestashop',
                'amount' => round($order['total_paid_tax_incl'], 2),
                'tax' => round($order['total_paid_tax_incl'] - $order['total_paid_tax_excl'], 2),
                'shipping' => round($order['total_shipping'], 2),
                'timestamp' => strtotime($order['date_add']),
            ],
            $addressData
        );
    }

    public function buildTransactionItems(array $orderProduct, int $timestamp): array
    {
        $product = new Product($orderProduct['product_id']);
        $category = new Category($product->getDefaultCategory());

        return [
            'code' => $orderProduct['product_reference'],
            'title' => $orderProduct['product_name'],
            'category' => $category->getName(),
            'price' => round($orderProduct['unit_price_tax_incl'], 2),
            'amount' => $orderProduct['product_quantity'],
            'timestamp' => strtotime($timestamp),
        ];
    }

    public function isApiKeyValid(): bool
    {
        $response = (array) $this->call('account');

        return !(isset($response['message']) && $response['message'] === 'Wrong api key');
    }

    public function prestaInstalled(): void
    {
        $this->call('webhooks/prestashop-install', 'POST', null, true);
    }

    public function prestaUninstalled(): bool
    {
        $this->call('webhooks/prestashop-uninstall', 'POST', null, true);

        return true;
    }
}
