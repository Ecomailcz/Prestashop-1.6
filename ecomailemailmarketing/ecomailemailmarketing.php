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

class ecomailemailmarketing extends Module
{
    protected $overridenModules = ['Ps_Emailsubscription'];

    public function __construct()
    {
        $this->module_key = '3c90ebaffe6722aece11c7a66bc18bec';
        $this->name = 'ecomailemailmarketing';
        $this->tab = 'emailing';
        $this->version = '2.0.0';
        $this->author = 'Ecomail';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => '8.0.1'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ecomail email marketing');
        $this->description = $this->l('Connection of the e-shop to Ecomail.');

        $this->confirmUninstall = $this->l('Do you really want to uninstall Ecomail?');
    }

    public function install(): bool
    {
        return parent::install() &&
            $this->setValues() &&
            $this->setTab() &&
            $this->setDatabase() &&
            $this->setHooks() &&
            $this->createWebserviceKey() &&
            unlink(_PS_CACHE_DIR_ . '/class_index.php');
    }

    public function uninstall(): bool
    {
        return parent::uninstall() &&
            $this->unsetValues() &&
            $this->unsetTab() &&
            $this->unsetDatabase();
    }

    // Config values
    public function setValues(): bool
    {
        return
            Configuration::updateValue('ECOMAIL_SKIP_CONFIRM', 1) &&
            Configuration::updateValue('PS_WEBSERVICE', 1) &&
            Configuration::updateValue('PS_WEBSERVICE_CGI_HOST', 1);
    }

    public function unsetValues(): bool
    {
        return
            Configuration::deleteByName('ECOMAIL_API_KEY') &&
            Configuration::deleteByName('ECOMAIL_APP_ID') &&
            Configuration::deleteByName('ECOMAIL_FORM_ID') &&
            Configuration::deleteByName('ECOMAIL_FORM_ACCOUNT') &&
            Configuration::deleteByName('ECOMAIL_WEBSERVICE_KEY') &&
            Configuration::deleteByName('ECOMAIL_LIST_ID');
    }

    // Admin tabs
    public function setTab(): bool
    {
        return true;
    }

    public function unsetTab(): bool
    {
        return true;
    }

    // DB tables
    public function setDatabase(): bool
    {
        return true;
    }

    public function unsetDatabase(): bool
    {
        return true;
    }

    // Hooks
    public function setHooks(): bool
    {
        return
            $this->registerHook('actionCustomerAccountAdd') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('displayFooter') &&
            $this->registerHook('actionCustomerNewsletterSubscribed') &&
            $this->registerHook('actionCartSave') &&
            $this->registerHook('actionCustomerAccountUpdate') &&
            $this->registerHook('actionSubmitCustomerAddressForm') &&
            $this->registerHook('addWebserviceResources');
    }

    public function getContent(): string
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $apiKey = Configuration::get('ECOMAIL_API_KEY');
            Configuration::updateValue('ECOMAIL_API_KEY', Tools::getValue('api_key'));

            if (Tools::getValue('api_key') !== $apiKey) {
                if (!$this->getAPI()->isApiKeyValid()) {
                    $output .= $this->displayError($this->l('Invalid API key'));
                    Configuration::deleteByName('ECOMAIL_API_KEY');
                }
            }

            Configuration::updateValue('ECOMAIL_APP_ID', Tools::getValue('app_id'));
            Configuration::updateValue('ECOMAIL_FORM_ID', Tools::getValue('form_id'));
            Configuration::updateValue('ECOMAIL_FORM_ACCOUNT', Tools::getValue('form_account'));

            // Call initial sync
            if (
                Tools::getValue('sync_existing') &&
                Configuration::get('ECOMAIL_API_KEY') &&
                (
                    (Tools::getValue('sync_existing') !== Configuration::get('ECOMAIL_SYNC_EXISTING')) ||
                    (Configuration::get('ECOMAIL_LIST_ID') !== Tools::getValue('list_id'))
                )
            ) {
                PrestaShopLogger::addLog('Sync customers and orders');
                $this->syncCustomers(Tools::getValue('list_id'));
                $this->syncOrders();
                $output .= $this->displayConfirmation($this->l('Synchronisation of existing contacts and orders was successful.'));
            }

            Configuration::updateValue('ECOMAIL_LIST_ID', Tools::getValue('list_id'));
            Configuration::updateValue('ECOMAIL_LOAD_ORDER_DATA', Tools::getValue('load_order_data'));
            Configuration::updateValue('ECOMAIL_LOAD_NAME', Tools::getValue('load_name'));
            Configuration::updateValue('ECOMAIL_LOAD_ADDRESS', Tools::getValue('load_address'));
            Configuration::updateValue('ECOMAIL_LOAD_BIRTHDAY', Tools::getValue('load_birthday'));
            Configuration::updateValue('ECOMAIL_LOAD_CART', Tools::getValue('load_cart'));
            Configuration::updateValue('ECOMAIL_SKIP_CONFIRM', Tools::getValue('skip_confirmation'));
            Configuration::updateValue('ECOMAIL_SYNC_EXISTING', Tools::getValue('sync_existing'));
            Configuration::updateValue('ECOMAIL_LOAD_GROUP', Tools::getValue('load_group'));
            Configuration::updateValue('ECOMAIL_TRIGGER_AUTORESPONDERS', Tools::getValue('trigger_autoresponders'));
        }

        if (Configuration::get('ECOMAIL_API_KEY') && !$this->getAPI()->getListsCollection()) {
            $output .= $this->displayError($this->l('Unable to connect to Ecomail. Please check your API key.'));
        }

        if (Configuration::get('ECOMAIL_API_KEY') && $this->getAPI()->getListsCollection()) {
            $output .= $this->displayConfirmation($this->l('Connection to Ecomail is active.'));
            $output .= $this->displayConfirmation($this->l('The webhook for updating contacts is at ') . '<strong>' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/modules/ecomailemailmarketing/webhook.php</strong>');
        }

        return $output . $this->displayForm();
    }

    public function displayForm(): string
    {
        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        if (Configuration::get('ECOMAIL_API_KEY') && $this->getAPI()->getListsCollection()) {
            $options = [];
            if (Configuration::get('ECOMAIL_API_KEY')) {
                $listsCollection = $this->getAPI()
                    ->getListsCollection();
                foreach ($listsCollection as $list) {
                    $options[] = [
                        'id_option' => $list->id,
                        'name' => $list->name,
                    ];
                }
            }

            // Init Fields form array
            $fields_form[0]['form'] = [
                'legend' => [
                    'title' => $this->l('Ecomail configuration'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Enter your API key'),
                        'name' => 'api_key',
                        'rows' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Select a contact list:'),
                        'desc' => $this->l(
                            'Select the contact list in which the new customers will be synchronised. If you have only just created it, it may take up to 30 minutes to connect to it.'
                        ),
                        'name' => 'list_id',
                        'required' => true,
                        'options' => [
                            'query' => $options,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Update data based on order data'),
                        'name' => 'load_order_data',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Send customer names'),
                        'name' => 'load_name',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Send customer addresses'),
                        'name' => 'load_address',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Send birthday of customers'),
                        'name' => 'load_birthday',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Send products in cart'),
                        'name' => 'load_cart',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Send customer groups to tags'),
                        'name' => 'load_group',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Skip double opt-in'),
                        'name' => 'skip_confirmation',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Trigger pipelines for new contacts'),
                        'name' => 'trigger_autoresponders',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'class' => 't',
                        'label' => $this->l('Synchronise existing contacts and orders (this operation may take a few minutes)'),
                        'name' => 'sync_existing',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                ],
                'warning' => $this->l('Click the save button to start synchronizing the data. Wait for the page to reload.'),
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ];

            $fields_form[1]['form'] = [
                'legend' => [
                    'title' => $this->l('Tracking'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Enter your appId'),
                        'desc' => $this->l(
                            'This information is used to activate the tracking code and track transactions - only for the Marketer+ tariff'
                        ),
                        'name' => 'app_id',
                        'rows' => 20,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ];

            $fields_form[2]['form'] = [
                'legend' => [
                    'title' => $this->l('Sign-up form'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Insert Ecomail Form ID'),
                        'desc' => $this->l(
                            'The js.id value in the form code. See the help for more information'
                        ),
                        'name' => 'form_id',
                        'rows' => 20,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Insert your Ecomail account name'),
                        'name' => 'form_account',
                        'rows' => 20,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ];
        } else {
            $fields_form[0]['form'] = [
                'legend' => [
                    'title' => $this->l('Ecomail configuration'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Enter your API key'),
                        'name' => 'api_key',
                        'rows' => 20,
                        'required' => true,
                        'desc' => $this->l('Once your API key is loaded correctly, you will select a list of contacts for your e-shop.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ];
        }

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        // Load current value
        $helper->fields_value['api_key'] = Configuration::get('ECOMAIL_API_KEY');
        $helper->fields_value['app_id'] = Configuration::get('ECOMAIL_APP_ID');
        $helper->fields_value['form_id'] = Configuration::get('ECOMAIL_FORM_ID');
        $helper->fields_value['form_account'] = Configuration::get('ECOMAIL_FORM_ACCOUNT');
        $helper->fields_value['list_id'] = Configuration::get('ECOMAIL_LIST_ID');
        $helper->fields_value['load_order_data'] = Configuration::get('ECOMAIL_LOAD_ORDER_DATA');

        $helper->fields_value['load_name'] = Configuration::get('ECOMAIL_LOAD_NAME');
        $helper->fields_value['load_address'] = Configuration::get('ECOMAIL_LOAD_ADDRESS');
        $helper->fields_value['load_birthday'] = Configuration::get('ECOMAIL_LOAD_BIRTHDAY');
        $helper->fields_value['load_cart'] = Configuration::get('ECOMAIL_LOAD_CART');
        $helper->fields_value['skip_confirmation'] = Configuration::get('ECOMAIL_SKIP_CONFIRM');
        $helper->fields_value['sync_existing'] = Configuration::get('ECOMAIL_SYNC_EXISTING');
        $helper->fields_value['load_group'] = Configuration::get('ECOMAIL_LOAD_GROUP');
        $helper->fields_value['trigger_autoresponders'] = Configuration::get('ECOMAIL_TRIGGER_AUTORESPONDERS');

        return $helper->generateForm($fields_form);
    }

    protected function getAPI(): EcomailAPI
    {
        require_once __DIR__ . '/lib/api.php';

        $obj = new EcomailAPI();
        $obj->setAPIKey(Configuration::get('ECOMAIL_API_KEY'));

        return $obj;
    }

    // customer registration, cart registration, order without registration
    public function hookActionCustomerAccountAdd(array $params): void
    {
        $newsletter = $params['newCustomer']->newsletter;
        $email = $params['newCustomer']->email;

        if (Configuration::get('ECOMAIL_API_KEY')) {
            $nameData = [];
            $birthdayData = [];

            if (Configuration::get('ECOMAIL_LOAD_NAME')) {
                $firstname = $params['newCustomer']->firstname;
                $lastname = $params['newCustomer']->lastname;

                $nameData = [
                    'name' => $firstname,
                    'surname' => $lastname,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY')) {
                $birthdayData = [
                    'birthday' => $params['newCustomer']->birthday,
                ];
            }

            $groupTags = [];
            if (Configuration::get('ECOMAIL_LOAD_GROUP')) {
                $customer = new Customer($email);
                $groups = $customer->getGroups();

                foreach ($groups as $group) {
                    $group = new Group((int) $group);
                    $group = (array) $group->name;

                    if (count($group) === 0) {
                        continue;
                    }

                    $groupTags[] = $group[array_key_first($group)];
                }
            }

            $newsletterTags = $newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

            $this->getAPI()
                ->subscribeToList(
                    Configuration::get('ECOMAIL_LIST_ID'),
                    array_merge(
                        ['email' => $email],
                        $nameData,
                        $birthdayData,
                        ['tags' => array_merge($groupTags, $newsletterTags)]
                    )
                );
        }
    }

    // blocknewsletter - customer subscribe
    public function hookActionCustomerNewsletterSubscribed(array $params): void
    {
        if (Configuration::get('ECOMAIL_API_KEY')) {
            $customer = Customer::getCustomersByEmail($params['email']);

            if ($customer) {
                $customer = end($customer);

                $nameData = [];
                $birthdayData = [];
                $addressData = [];

                if (Configuration::get('ECOMAIL_LOAD_NAME')) {
                    $nameData = [
                        'name' => $customer['firstname'],
                        'surname' => $customer['lastname'],
                    ];
                }

                if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY')) {
                    $birthdayData = [
                        'birthday' => $customer['birthday'],
                    ];
                }

                if (Configuration::get('ECOMAIL_LOAD_ADDRESS')) {
                    if (isset($params['order'])) {
                        $address = new Address($params['order']->id_address_invoice);
                        $country = new Country($address->id_country);

                        $addressData = [
                            'company' => $customer['company'],
                            'country' => $country->iso_code,
                        ];
                    }
                }

                $groupTags = [];
                if (Configuration::get('ECOMAIL_LOAD_GROUP')) {
                    $groups = $customer->getGroups();

                    foreach ($groups as $group) {
                        $group = new Group((int) $group);
                        $group = (array) $group->name;

                        if (count($group) === 0) {
                            continue;
                        }

                        $groupTags[] = $group[array_key_first($group)];
                    }
                }

                $newsletterTags = $customer->newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('ECOMAIL_LIST_ID'),
                        array_merge(
                            ['email' => $customer['email']],
                            $nameData,
                            $birthdayData,
                            $addressData,
                            ['tags' => array_merge($groupTags, $newsletterTags)]
                        )
                    );
            } else {
                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('ECOMAIL_LIST_ID'),
                        ['email' => $params['email']]
                    );
            }
        }
    }

    // Order submitted
    public function hookActionValidateOrder(array $params): void
    {
        if (Configuration::get('ECOMAIL_LOAD_ORDER_DATA')) {
            $customer = new Customer($params['order']->id_customer);

            $nameData = [];
            $birthdayData = [];
            $addressData = [];

            if (Configuration::get('ECOMAIL_LOAD_NAME')) {
                $nameData = [
                    'name' => $customer->firstname,
                    'surname' => $customer->lastname,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY')) {
                $birthdayData = [
                    'birthday' => $customer->birthday,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_ADDRESS')) {
                $address = new Address($params['order']->id_address_invoice);
                $country = new Country($address->id_country);

                $addressData = [
                    'city' => $address->city,
                    'street' => $address->address1,
                    'zip' => $address->postcode,
                    'country' => $country->iso_code,
                    'company' => $address->company,
                ];
            }

            $groupTags = [];
            if (Configuration::get('ECOMAIL_LOAD_GROUP')) {
                $groups = $customer->getGroups();

                foreach ($groups as $group) {
                    $group = new Group((int) $group);
                    $group = (array) $group->name;

                    if (count($group) === 0) {
                        continue;
                    }

                    $groupTags[] = $group[array_key_first($group)];
                }
            }

            $newsletterTags = $customer->newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

            $this->getAPI()
                ->subscribeToList(
                    Configuration::get('ECOMAIL_LIST_ID'),
                    array_merge(
                        [
                            'email' => $customer->email,
                            'tags' => array_merge($newsletterTags, $groupTags),
                        ],
                        $nameData,
                        $birthdayData,
                        $addressData
                    )
                );
        }

        $this->getAPI()->createTransaction($params['order']);
    }

    public function hookActionCartSave(array $params): void
    {
        if (Configuration::get('ECOMAIL_LOAD_CART')) {
            if (!isset($this->context->cart)) {
                return;
            }

            $cartProducts = $this->context->cart->getProducts();

            $products = [];
            foreach ($cartProducts as $p) {
                $productCat = new Category($p['id_category_default']);
                $products[] = [
                    'productId' => $p['id_product'],
                    'img_url' => $this->context->link->getImageLink($p['link_rewrite'], $p['id_image']),
                    'url' => $this->context->link->getProductLink((int) $p['id_product'], $p['link_rewrite'], $productCat->link_rewrite[$this->context->language->id]),
                    'name' => $p['name'],
                    'price' => $p['price_wt'],
                ];
            }

            if (!$this->context->customer->email) {
                return;
            }

            $result = $this->getAPI()->sendBasket($this->context->customer->email, $products);

            if (isset($result->errors)) {
                PrestaShopLogger::addLog('Ecomail failed: ' . json_encode($result), 1, null, 'Cart', (int) $this->context->cart->id, true);
            }
        }
    }

    public function hookDisplayFooter(array $params): string
    {
        $this->context->smarty->assign(
            [
                'ECOMAIL_APP_ID' => Configuration::get('ECOMAIL_APP_ID'),
                'ECOMAIL_FORM_ID' => Configuration::get('ECOMAIL_FORM_ID'),
                'ECOMAIL_FORM_ACCOUNT' => Configuration::get('ECOMAIL_FORM_ACCOUNT'),
                'EMAIL' => $this->context->cookie->email ?? 'empty',
            ]
        );

        return $this->display(__FILE__, 'ecomail_scripts.tpl');
    }

    public function createWebserviceKey(): bool
    {
        $apiAccess = new WebserviceKey();
        $apiAccess->key = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 32);
        $apiAccess->description = 'Ecomail webservice key';
        $apiAccess->save();

        $permissions = [
            'customers' => ['GET' => 1],
            'orders' => ['GET' => 1],
            'products' => ['GET' => 1],
            'tags' => ['GET' => 1],
        ];

        WebserviceKey::setPermissionForAccount($apiAccess->id, $permissions);
        Configuration::updateValue('ECOMAIL_WEBSERVICE_KEY', $apiAccess->key);

        return true;
    }

    public function syncCustomers(string $listId): void
    {
        $allCustomers = $this->requestGet(sprintf('%s%sapi/', _PS_BASE_URL_, __PS_BASE_URI__), 'customers');

        $customersToImport = [];

        if (!$allCustomers || !isset($allCustomers['customers'])) {
            return;
        }

        foreach ($allCustomers['customers'] as $customer) {
            $groupTags = [];

            if (isset($customer['associations']) && isset($customer['associations']['groups'])) {
                foreach ($customer['associations']['groups'] as $group) {
                    $group = new Group((int) $group['id']);
                    $group = (array) $group->name;
                    $groupTags[] = $group[array_key_first($group)];
                }
            }

            $newsletterTags = $customer['newsletter'] === '1' ? ['prestashop_newsletter', 'prestashop'] : ['prestashop'];

            $customersToImport[] = [
                'name' => $customer['firstname'],
                'surname' => $customer['lastname'],
                'email' => $customer['email'],
                'birthday' => $customer['birthday'],
                'tags' => array_merge($groupTags, $newsletterTags),
            ];
        }

        if (count($customersToImport) > 0) {
            $chunks = array_chunk($customersToImport, 3000);

            foreach ($chunks as $chunk) {
                $this->getAPI()->bulkSubscribeToList($listId, $chunk);
            }
        }
    }

    public function requestGet(string $url, string $event): ?array
    {
        $ch = curl_init();
        $display = '?display=full';
        $url = $url . $event . $display;

        $headers = [
            'output_format:JSON',
            'Authorization: Basic ' . base64_encode(Configuration::get('ECOMAIL_WEBSERVICE_KEY') . ':'),
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode(stripslashes($response), true, JSON_UNESCAPED_SLASHES);
    }

    public function syncOrders(): void
    {
        $allOrders = $this->requestGet(sprintf('%s%sapi/', _PS_BASE_URL_, __PS_BASE_URI__), 'orders');

        $ordersToImport = [];

        if (!$allOrders || !isset($allOrders['orders'])) {
            return;
        }

        foreach ($allOrders['orders'] as $order) {
            if (!isset($order['associations']['order_rows'])) {
                continue;
            }

            $transaction = $this->getAPI()->buildTransaction($order);
            $transactionItems = [];

            foreach ($order['associations']['order_rows'] as $item) {
                $transactionItems[] = $this->getAPI()->buildTransactionItems($item, $order['date_add']);
            }

            $ordersToImport[] = [
                'transaction' => $transaction,
                'transaction_items' => $transactionItems,
            ];
        }

        if (count($ordersToImport) > 0) {
            $chunks = array_chunk($ordersToImport, 1000);

            foreach ($chunks as $chunk) {
                $this->getAPI()->bulkOrders($chunk);
            }
        }
    }

    public function hookActionCustomerAccountUpdate(array $params): void
    {
        $newsletter = $params['customer']->newsletter;
        $email = $params['customer']->email;

        if (Configuration::get('ECOMAIL_API_KEY')) {
            $nameData = [];
            $birthdayData = [];

            if (Configuration::get('ECOMAIL_LOAD_NAME')) {
                $firstname = $params['customer']->firstname;
                $lastname = $params['customer']->lastname;

                $nameData = [
                    'name' => $firstname,
                    'surname' => $lastname,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY')) {
                $birthday = $params['customer']->birthday;

                $birthdayData = [
                    'birthday' => $birthday,
                ];
            }

            $groupTags = [];
            if (Configuration::get('ECOMAIL_LOAD_GROUP')) {
                $customer = new Customer($email);
                $groups = $customer->getGroups();

                foreach ($groups as $group) {
                    $group = new Group((int) $group);
                    $group = (array) $group->name;

                    if (count($group) === 0) {
                        continue;
                    }

                    $groupTags[] = $group[array_key_first($group)];
                }
            }

            $newsletterTags = $newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

            $this->getAPI()
                ->subscribeToList(
                    Configuration::get('ECOMAIL_LIST_ID'),
                    array_merge(
                        ['email' => $email],
                        $nameData,
                        $birthdayData,
                        ['tags' => array_merge($groupTags, $newsletterTags)]
                    )
                );
        }
    }

    public function hookActionSubmitCustomerAddressForm(array $params): void
    {
        if (Configuration::get('ECOMAIL_LOAD_ADDRESS')) {
            $country = new Country($params['address']->id_country);
            $customer = new Customer($params['address']->id_customer);

            $addressData = [
                'city' => $params['address']->city,
                'street' => $params['address']->address1,
                'zip' => $params['address']->postcode,
                'country' => $country->iso_code,
                'company' => $params['address']->company,
                'phone' => $params['address']->phone,
            ];

            $this->getAPI()
                ->subscribeToList(
                    Configuration::get('ECOMAIL_LIST_ID'),
                    array_merge(
                        ['email' => $customer->email],
                        $addressData,
                    )
                );
        }
    }

    public function hookAddWebserviceResources(array $params): bool
    {
        return true;
    }
}
