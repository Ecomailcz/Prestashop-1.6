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
    public function __construct()
    {
        $this->module_key = '3c90ebaffe6722aece11c7a66bc18bec';
        $this->name = 'ecomailemailmarketing';
        $this->tab = 'emailing';
        $this->version = '2.0.26';
        $this->author = 'Ecomail';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ecomail email marketing');
        $this->description = $this->l('Grow your business with an effective email marketing strategy. Ecomail will boost your sales immediately and save you precious time.');

        $this->confirmUninstall = $this->l('Do you really want to uninstall Ecomail?');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->setValues()
            && $this->setTab()
            && $this->setDatabase()
            && $this->setHooks()
            && $this->createWebserviceKey()
            && $this->clearCache();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->getAPI()->prestaUninstalled()
            && $this->unsetValues()
            && $this->unsetTab()
            && $this->unsetDatabase();
    }

    public function enable($force_all = false): bool
    {
        return parent::enable($force_all)
            && $this->setValues()
            && $this->setTab()
            && $this->setDatabase()
            && $this->setHooks()
            && $this->createWebserviceKey()
            && $this->clearCache();
    }

    // Config values
    public function setValues(): bool
    {
        $currentShopId = (int) Shop::getContextShopID();

        return
            Configuration::updateValue('ECOMAIL_SKIP_CONFIRM', 1, false, null, $currentShopId)
            && Configuration::updateValue('PS_WEBSERVICE', 1, false, null, $currentShopId)
            && Configuration::updateValue('PS_WEBSERVICE_CGI_HOST', 1, false, null, $currentShopId);
    }

    public function unsetValues(): bool
    {
        $configKeys = [
            'ECOMAIL_API_KEY',
            'ECOMAIL_APP_ID',
            'ECOMAIL_FORM_ID',
            'ECOMAIL_FORM_ACCOUNT',
            'ECOMAIL_WEBSERVICE_KEY',
            'ECOMAIL_LIST_ID',
        ];

        foreach ($configKeys as $key) {
            Configuration::deleteByName($key);
        }

        return true;
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
            $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('actionNewsletterRegistrationAfter')
            && $this->registerHook('actionCartSave')
            && $this->registerHook('actionSubmitCustomerAddressForm')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionObjectCustomerUpdateAfter')
            && $this->registerHook('addWebserviceResources');
    }

    public function getContent(): string
    {
        $output = null;
        $currentShopId = (int) Shop::getContextShopID();

        if (Tools::isSubmit('submit' . $this->name)) {
            $apiKey = Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_API_KEY', Tools::getValue('api_key'), false, null, $currentShopId);

            if (Tools::getValue('api_key') !== $apiKey) {
                if (!$this->getAPI()->isApiKeyValid()) {
                    $output .= $this->displayError($this->l('Invalid API key'));

                    $shopId = (int) Shop::getContextShopID();

                    if (version_compare(_PS_VERSION_, '1.7.7.0', '>=')) {
                        Configuration::deleteFromContext('ECOMAIL_API_KEY', null, $shopId);
                    } else {
                        $originalContext = Shop::getContext();
                        Shop::setContext(Shop::CONTEXT_SHOP, $shopId);

                        Configuration::deleteByName('ECOMAIL_API_KEY');

                        Shop::setContext($originalContext);
                    }
                }
            }

            Configuration::updateValue('ECOMAIL_APP_ID', Tools::getValue('app_id'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_FORM_ID', Tools::getValue('form_id'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_FORM_ACCOUNT', Tools::getValue('form_account'), false, null, $currentShopId);

            // Call initial sync
            if (
                Tools::getValue('sync_existing')
                && Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId)
                && (
                    (Tools::getValue('sync_existing') !== Configuration::get('ECOMAIL_SYNC_EXISTING', null, null, $currentShopId))
                    || (Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId) !== Tools::getValue('list_id'))
                )
            ) {
                PrestaShopLogger::addLog('Sync customers and orders');
                $this->syncCustomers(Tools::getValue('list_id'));
                $this->syncOrders();
                $output .= $this->displayConfirmation($this->l('Synchronisation of existing contacts and orders was successful.'));
            }

            Configuration::updateValue('ECOMAIL_LIST_ID', Tools::getValue('list_id'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_LOAD_ORDER_DATA', Tools::getValue('load_order_data'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_LOAD_NAME', Tools::getValue('load_name'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_LOAD_ADDRESS', Tools::getValue('load_address'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_LOAD_BIRTHDAY', Tools::getValue('load_birthday'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_LOAD_CART', Tools::getValue('load_cart'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_SKIP_CONFIRM', Tools::getValue('skip_confirmation'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_SYNC_EXISTING', Tools::getValue('sync_existing'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_LOAD_GROUP', Tools::getValue('load_group'), false, null, $currentShopId);
            Configuration::updateValue('ECOMAIL_TRIGGER_AUTORESPONDERS', Tools::getValue('trigger_autoresponders'), false, null, $currentShopId);
        }

        if (Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId) && !$this->getAPI()->getListsCollection()) {
            $output .= $this->displayError($this->l('Unable to connect to Ecomail. Please check your API key.'));
        }

        if (Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId) && $this->getAPI()->getListsCollection()) {
            $this->getAPI()->prestaInstalled();
            $output .= $this->displayConfirmation($this->l('Connection to Ecomail is active.'));

            $shopUrl = Context::getContext()->shop->getBaseURL(true, true);

            $output .= $this->displayConfirmation($this->l('The webhook for updating contacts is at ') . '<strong>' . $shopUrl . 'modules/ecomailemailmarketing/webhook.php</strong>');
        }

        return $output . $this->displayForm();
    }

    public function displayForm(): string
    {
        $currentShopId = (int) Shop::getContextShopID();
        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT', null, null, $currentShopId);

        if (Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId) && $this->getAPI()->getListsCollection()) {
            $options = [];
            if (Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId)) {
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
            $this->context->smarty->assign(
                [
                    'api_key_input' => (Configuration::get($this->name . '_api_key', null, null, $currentShopId) ? Configuration::get($this->name . '_api_key', null, null, $currentShopId) : null),
                ]
            );

            $ajax_link = $this->context->link->getModuleLink('ecomailemailmarketing', 'ajax', [], true);

            Media::addJsDef(
                [
                    'ajax_link' => $ajax_link,
                ]
            );

            return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
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
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name
                    . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        // Load current value
        $helper->fields_value['api_key'] = Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId);
        $helper->fields_value['app_id'] = Configuration::get('ECOMAIL_APP_ID', null, null, $currentShopId);
        $helper->fields_value['form_id'] = Configuration::get('ECOMAIL_FORM_ID', null, null, $currentShopId);
        $helper->fields_value['form_account'] = Configuration::get('ECOMAIL_FORM_ACCOUNT', null, null, $currentShopId);
        $helper->fields_value['list_id'] = Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId);
        $helper->fields_value['load_order_data'] = Configuration::get('ECOMAIL_LOAD_ORDER_DATA', null, null, $currentShopId);

        $helper->fields_value['load_name'] = Configuration::get('ECOMAIL_LOAD_NAME', null, null, $currentShopId);
        $helper->fields_value['load_address'] = Configuration::get('ECOMAIL_LOAD_ADDRESS', null, null, $currentShopId);
        $helper->fields_value['load_birthday'] = Configuration::get('ECOMAIL_LOAD_BIRTHDAY', null, null, $currentShopId);
        $helper->fields_value['load_cart'] = Configuration::get('ECOMAIL_LOAD_CART', null, null, $currentShopId);
        $helper->fields_value['skip_confirmation'] = Configuration::get('ECOMAIL_SKIP_CONFIRM', null, null, $currentShopId);
        $helper->fields_value['sync_existing'] = Configuration::get('ECOMAIL_SYNC_EXISTING', null, null, $currentShopId);
        $helper->fields_value['load_group'] = Configuration::get('ECOMAIL_LOAD_GROUP', null, null, $currentShopId);
        $helper->fields_value['trigger_autoresponders'] = Configuration::get('ECOMAIL_TRIGGER_AUTORESPONDERS', null, null, $currentShopId);

        return $helper->generateForm($fields_form);
    }

    protected function getAPI(): EcomailAPI
    {
        require_once __DIR__ . '/lib/api.php';

        $currentShopId = (int) Shop::getContextShopID();

        $obj = new EcomailAPI();
        $obj->setAPIKey(Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId));

        return $obj;
    }

    // customer registration, cart registration, order without registration
    public function hookActionCustomerAccountAdd(array $params): void
    {
        $newsletter = $params['newCustomer']->newsletter;
        $email = $params['newCustomer']->email;
        $currentShopId = (int) Shop::getContextShopID();

        if (Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId)) {
            $nameData = [];
            $birthdayData = [];

            if (Configuration::get('ECOMAIL_LOAD_NAME', null, null, $currentShopId)) {
                $firstname = $params['newCustomer']->firstname;
                $lastname = $params['newCustomer']->lastname;

                $nameData = [
                    'name' => $firstname,
                    'surname' => $lastname,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY', null, null, $currentShopId)) {
                $birthdayData = [
                    'birthday' => $params['newCustomer']->birthday,
                ];
            }

            $groupTags = [];

            if (Configuration::get('ECOMAIL_LOAD_GROUP', null, null, $currentShopId)) {
                $customer = new Customer($email);
                $groups = $customer->getGroups();

                foreach ($groups as $group) {
                    $group = new Group((int) $group);
                    $group = (array) $group->name;

                    if (count($group) === 0) {
                        continue;
                    }

                    $groupTags[] = $group[array_keys($group)[0]];
                }
            }

            $newsletterTags = $newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

            $this->getAPI()
                ->subscribeToList(
                    Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId),
                    array_merge(
                        ['email' => $email, 'source' => 'prestashop'],
                        $nameData,
                        $birthdayData,
                        ['tags' => array_merge($groupTags, $newsletterTags)]
                    ),
                    (bool) $newsletter
                );
        }
    }

    public function hookActionNewsletterRegistrationAfter(array $params): void
    {
        $currentShopId = (int) Shop::getContextShopID();

        if (Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId)) {
            $customer = Customer::getCustomersByEmail($params['email']);

            if ($customer) {
                $customer = end($customer);

                $nameData = [];
                $birthdayData = [];
                $addressData = [];

                if (Configuration::get('ECOMAIL_LOAD_NAME', null, null, $currentShopId)) {
                    $nameData = [
                        'name' => $customer['firstname'],
                        'surname' => $customer['lastname'],
                    ];
                }

                if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY', null, null, $currentShopId)) {
                    $birthdayData = [
                        'birthday' => $customer['birthday'],
                    ];
                }

                if (Configuration::get('ECOMAIL_LOAD_ADDRESS', null, null, $currentShopId)) {
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
                if (Configuration::get('ECOMAIL_LOAD_GROUP', null, null, $currentShopId)) {
                    $groups = $customer->getGroups();

                    foreach ($groups as $group) {
                        $group = new Group((int) $group);
                        $group = (array) $group->name;

                        if (count($group) === 0) {
                            continue;
                        }

                        $groupTags[] = $group[array_keys($group)[0]];
                    }
                }

                $newsletterTags = $customer->newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId),
                        array_merge(
                            ['email' => $customer['email'], 'source' => 'prestashop'],
                            $nameData,
                            $birthdayData,
                            $addressData,
                            ['tags' => array_merge($groupTags, $newsletterTags)],
                            ['custom_fields' => [
                                'PRESTA_LANGUAGE' => Language::getIsoById((int) $customer['id_lang']),
                            ]]
                        ),
                        (bool) $customer->newsletter
                    );
            } else {
                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId),
                        ['email' => $params['email'], 'source' => 'prestashop', 'tags' => ['prestashop', 'prestashop_newsletter']],
                        (bool) $customer->newsletter
                    );
            }
        }
    }

    // Order submitted
    public function hookActionValidateOrder(array $params): void
    {
        $currentShopId = (int) Shop::getContextShopID();

        if (Configuration::get('ECOMAIL_LOAD_ORDER_DATA', null, null, $currentShopId)) {
            $customer = new Customer($params['order']->id_customer);

            $nameData = [];
            $birthdayData = [];
            $addressData = [];

            if (Configuration::get('ECOMAIL_LOAD_NAME', null, null, $currentShopId)) {
                $nameData = [
                    'name' => $customer->firstname,
                    'surname' => $customer->lastname,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY', null, null, $currentShopId)) {
                $birthdayData = [
                    'birthday' => $customer->birthday,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_ADDRESS', null, null, $currentShopId)) {
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
            if (Configuration::get('ECOMAIL_LOAD_GROUP', null, null, $currentShopId)) {
                $groups = $customer->getGroups();

                foreach ($groups as $group) {
                    $group = new Group((int) $group);
                    $group = (array) $group->name;

                    if (count($group) === 0) {
                        continue;
                    }

                    $groupTags[] = $group[array_keys($group)[0]];
                }
            }

            $newsletterTags = $customer->newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

            $this->getAPI()
                ->subscribeToList(
                    Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId),
                    array_merge(
                        [
                            'email' => $customer->email,
                            'tags' => array_merge($newsletterTags, $groupTags),
                            'source' => 'prestashop',
                        ],
                        $nameData,
                        $birthdayData,
                        $addressData,
                        ['custom_fields' => [
                            'PRESTA_LANGUAGE' => Language::getIsoById((int) $customer->id_lang),
                        ]]
                    ),
                    (bool) $customer->newsletter
                );
        }

        $result = $this->getAPI()->createTransaction($params['order']);

        if (isset($result->errors)) {
            PrestaShopLogger::addLog('Ecomail failed: ' . json_encode($result), 1, null, 'Transaction', null, true);
        }
    }

    public function hookActionCartSave(array $params): void
    {
        $currentShopId = (int) Shop::getContextShopID();

        if (Configuration::get('ECOMAIL_LOAD_CART', null, null, $currentShopId)) {
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

            if (!$this->context->customer->email || count($products) === 0) {
                return;
            }

            $result = $this->getAPI()->sendBasket($this->context->customer->email, $products);

            if (isset($result->errors)) {
                PrestaShopLogger::addLog('Ecomail failed: ' . json_encode($result), 1, null, 'Cart', (int) $this->context->cart->id, true);
            }
        }
    }

    public function hookDisplayAfterBodyOpeningTag(array $params): string
    {
        $currentShopId = (int) Shop::getContextShopID();

        $this->context->smarty->assign(
            [
                'ECOMAIL_APP_ID' => Configuration::get('ECOMAIL_APP_ID', null, null, $currentShopId),
                'ECOMAIL_FORM_ID' => Configuration::get('ECOMAIL_FORM_ID', null, null, $currentShopId),
                'ECOMAIL_FORM_ACCOUNT' => Configuration::get('ECOMAIL_FORM_ACCOUNT', null, null, $currentShopId),
                'EMAIL' => $this->context->cookie->email,
                'PRODUCT_ID' => Tools::getValue('id_product'),
            ]
        );

        return $this->display(__FILE__, 'ecomail_scripts.tpl');
    }

    public function createWebserviceKey(): bool
    {
        $currentShopId = (int) Shop::getContextShopID();

        if ($currentShopId !== 0) {
            Shop::setContext(Shop::CONTEXT_SHOP, $currentShopId);
        }

        $apiAccess = new WebserviceKey();
        $apiAccess->key = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 32);
        $apiAccess->description = 'Ecomail webservice key' . ($currentShopId !== 0 ? ' for shopId ' . $currentShopId : '');
        $apiAccess->save();

        $permissions = [
            'customers' => ['GET' => 1],
            'orders' => ['GET' => 1],
            'products' => ['GET' => 1],
            'tags' => ['GET' => 1],
        ];

        WebserviceKey::setPermissionForAccount($apiAccess->id, $permissions);
        Configuration::updateValue('ECOMAIL_WEBSERVICE_KEY', $apiAccess->key, false, null, $currentShopId);

        return true;
    }

    public function syncCustomers(string $listId, int $offset = 0): void
    {
        $currentShopId = (int) Shop::getContextShopID();

        $webServiceUrl = Context::getContext()->shop->getBaseURL(true, true) . 'api/';

        $allCustomers = $this->requestGet($webServiceUrl, 'customers', $offset, 1000);

        $customersToImport = [];

        if (!$allCustomers || !isset($allCustomers['customers'])) {
            PrestaShopLogger::addLog('No customers to sync');

            return;
        }

        PrestaShopLogger::addLog(sprintf('Customers count: %s', count($allCustomers['customers'])));

        foreach ($allCustomers['customers'] as $customer) {
            if (!$this->getAPI()->isEmailValid($customer['email'])) {
                PrestaShopLogger::addLog(sprintf('Invalid email: %s', $customer['email'] ?? 'No email address'));

                continue;
            }

            $groupTags = [];

            if (Configuration::get('ECOMAIL_LOAD_GROUP', null, null, $currentShopId)) {
                if (isset($customer['associations']['groups'])) {
                    foreach ($customer['associations']['groups'] as $group) {
                        $group = new Group((int) $group['id']);
                        $group = (array) $group->name;
                        $groupTags[] = $group[array_keys($group)[0]];
                    }
                }
            }

            $newsletterTags = $customer['newsletter'] === '1' ? ['prestashop_newsletter', 'prestashop'] : ['prestashop'];

            $customerData = [
                'email' => $customer['email'],
                'tags' => array_merge($groupTags, $newsletterTags),
                'custom_fields' => [
                    'PRESTA_LANGUAGE' => Language::getIsoById((int) $customer['id_lang']),
                ],
                'source' => 'prestashop',
            ];

            if (Configuration::get('ECOMAIL_LOAD_NAME', null, null, $currentShopId)) {
                $customerData['name'] = $customer['firstname'];
                $customerData['surname'] = $customer['lastname'];
            }

            if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY', null, null, $currentShopId)) {
                $customerData['birthday'] = $customer['birthday'];
            }

            if (Configuration::get('ECOMAIL_LOAD_ADDRESS', null, null, $currentShopId)) {
                $customerForAddress = new Customer($customer['id']);
                $customerAddress = $customerForAddress->getAddresses($customerForAddress->id_lang);

                if (isset($customerAddress[0])) {
                    $country = new Country($customerAddress[0]['id_country']);

                    $customerData['city'] = $customerAddress[0]['city'];
                    $customerData['street'] = $customerAddress[0]['address1'];
                    $customerData['zip'] = $customerAddress[0]['postcode'];
                    $customerData['country'] = $country->iso_code;
                    $customerData['company'] = $customerAddress[0]['company'];
                    $customerData['phone'] = $customerAddress[0]['phone'];
                }
            }

            $customersToImport[] = $customerData;
        }

        if ($offset === 0 && count($customersToImport) > 0) {
            $firstCustomer = array_shift($customersToImport);
            $hasNewsletter = in_array('prestashop_newsletter', $firstCustomer['tags'], true);

            $result = $this->getAPI()->subscribeToList($listId, $firstCustomer, $hasNewsletter);

            if (isset($result->errors)) {
                PrestaShopLogger::addLog('Ecomail failed: ' . json_encode($result->errors), 1, null, 'subscribeToList', null, true);
            }
        }

        PrestaShopLogger::addLog('Customers processed - ready to import');

        if (count($customersToImport) > 0) {
            $result = $this->getAPI()->bulkSubscribeToList($listId, $customersToImport);

            if (isset($result->errors)) {
                PrestaShopLogger::addLog('Data contains invalid emails - retry: ' . json_encode($result->errors), 1, null, 'bulkSubscribeToList', null, true);

                foreach (array_keys((array) $result->errors) as $error) {
                    $key = explode('.', $error);

                    if (isset($key[1])) {
                        unset($customersToImport[$key[1]]);
                    }
                }

                $result = $this->getAPI()->bulkSubscribeToList($listId, $customersToImport);

                if (isset($result->errors)) {
                    PrestaShopLogger::addLog('Ecomail failed: ' . json_encode($result->errors), 1, null, 'bulkSubscribeToList', null, true);
                }
            }
        }

        if (count($allCustomers['customers']) === 1000) {
            $this->syncCustomers($listId, $offset + 1000);
        } else {
            PrestaShopLogger::addLog('Customers imported');
        }
    }

    public function requestGet(string $url, string $event, int $offset, int $limit): ?array
    {
        $currentShopId = (int) Shop::getContextShopID();

        $ch = curl_init();
        $display = '?display=full&output_format=JSON';
        $limit = sprintf('&limit=%d,%d', $offset, $limit);

        $url = $url . $event . $display . $limit;

        $headers = [
            'Authorization: Basic ' . base64_encode(Configuration::get('ECOMAIL_WEBSERVICE_KEY', null, null, $currentShopId) . ':'),
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response, true, JSON_UNESCAPED_SLASHES);
    }

    public function syncOrders(int $offset = 0): void
    {
        $webServiceUrl = Context::getContext()->shop->getBaseURL(true, true) . 'api/';

        $allOrders = $this->requestGet($webServiceUrl, 'orders', $offset, 500);

        $ordersToImport = [];

        if (!$allOrders || !isset($allOrders['orders'])) {
            PrestaShopLogger::addLog('No orders to sync - url: ' . $webServiceUrl);

            return;
        }

        PrestaShopLogger::addLog(sprintf('Orders count: %s', count($allOrders['orders'])));

        foreach ($allOrders['orders'] as $order) {
            if (!isset($order['associations']['order_rows'])) {
                continue;
            }

            $transaction = $this->getAPI()->buildTransaction($order);

            if (!$transaction) {
                continue;
            }

            $transactionItems = [];

            foreach ($order['associations']['order_rows'] as $item) {
                $transactionItems[] = $this->getAPI()->buildTransactionItems($item, strtotime($order['date_add']));
            }

            $ordersToImport[] = [
                'transaction' => $transaction,
                'transaction_items' => $transactionItems,
            ];
        }

        PrestaShopLogger::addLog('Orders processed - ready to import');

        if (count($ordersToImport) > 0) {
            $result = $this->getAPI()->bulkOrders($ordersToImport);

            if (isset($result->errors)) {
                PrestaShopLogger::addLog('Ecomail failed: ' . json_encode($result->errors), 1, null, 'bulkOrders', null, true);
            }
        }

        if (count($allOrders['orders']) === 500) {
            $this->syncOrders($offset + 500);
        } else {
            PrestaShopLogger::addLog('Orders imported');
        }
    }

    public function hookActionObjectCustomerUpdateAfter(array $params): void
    {
        $currentShopId = (int) Shop::getContextShopID();

        $customer = new Customer($params['customer']->id ?? $params['object']->id);

        $newsletter = $customer->newsletter;
        $email = $customer->email;

        if (Configuration::get('ECOMAIL_API_KEY', null, null, $currentShopId)) {
            $nameData = [];
            $birthdayData = [];

            if (Configuration::get('ECOMAIL_LOAD_NAME', null, null, $currentShopId)) {
                $firstname = $customer->firstname;
                $lastname = $customer->lastname;

                $nameData = [
                    'name' => $firstname,
                    'surname' => $lastname,
                ];
            }

            if (Configuration::get('ECOMAIL_LOAD_BIRTHDAY', null, null, $currentShopId)) {
                $birthday = $customer->birthday;

                $birthdayData = [
                    'birthday' => $birthday,
                ];
            }

            $groupTags = [];
            if (Configuration::get('ECOMAIL_LOAD_GROUP', null, null, $currentShopId)) {
                $groups = $customer->getGroups();

                foreach ($groups as $group) {
                    $group = new Group((int) $group);
                    $group = (array) $group->name;

                    if (count($group) === 0) {
                        continue;
                    }

                    $groupTags[] = $group[array_keys($group)[0]];
                }
            }

            $addressData = [];
            if (Configuration::get('ECOMAIL_LOAD_ADDRESS', null, null, $currentShopId)) {
                $customerAddress = $customer->getAddresses($customer->id_lang);

                if (isset($customerAddress[0])) {
                    $country = new Country($customerAddress[0]['id_country']);

                    $addressData = [
                        'city' => $customerAddress[0]['city'],
                        'street' => $customerAddress[0]['address1'],
                        'zip' => $customerAddress[0]['postcode'],
                        'country' => $country->iso_code,
                        'company' => $customerAddress[0]['company'],
                        'phone' => $customerAddress[0]['phone'],
                    ];
                }
            }

            $newsletterTags = $newsletter ? ['prestashop', 'prestashop_newsletter'] : ['prestashop'];

            $this->getAPI()
                ->updateSubscriberInList(
                    Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId),
                    array_merge(
                        ['email' => $email, 'source' => 'prestashop', 'status' => $newsletter ? '1' : '2'],
                        $nameData,
                        $birthdayData,
                        $addressData,
                        ['tags' => array_merge($groupTags, $newsletterTags)]
                    )
                );
        }
    }

    public function hookActionSubmitCustomerAddressForm(array $params): void
    {
        $currentShopId = (int) Shop::getContextShopID();

        if (Configuration::get('ECOMAIL_LOAD_ADDRESS', null, null, $currentShopId)) {
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
                    Configuration::get('ECOMAIL_LIST_ID', null, null, $currentShopId),
                    array_merge(
                        ['email' => $customer->email, 'source' => 'prestashop'],
                        $addressData
                    ),
                    (bool) $customer->newsletter
                );
        }
    }

    public function hookAddWebserviceResources(array $params): bool
    {
        return true;
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJquery();
            $this->context->controller->addJS($this->_path . 'views/js/save.js');
        }
    }

    public function clearCache(): bool
    {
        $cacheFile = _PS_CACHE_DIR_ . '/class_index.php';

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        return true;
    }
}
