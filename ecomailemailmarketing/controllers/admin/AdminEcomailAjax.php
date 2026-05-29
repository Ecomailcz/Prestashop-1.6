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

class AdminEcomailAjaxController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->ajax = true;
    }

    private function respond(string $json): void
    {
        header('Content-Type: application/json');
        echo $json;
        exit;
    }

    public function postProcess()
    {
        if (Tools::getValue('action') === 'saveApi') {
            $this->ajaxProcessSaveApi();
        }
    }

    protected function ajaxProcessSaveApi()
    {
        $apikey = Tools::getValue('apikey');

        require_once __DIR__ . '/../../lib/api.php';
        $api = new EcomailAPI();
        $api->setAPIKey($apikey);

        if (!$api->isApiKeyValid()) {
            $this->respond(json_encode(['error' => 'Invalid API key']));

            return;
        }

        Configuration::updateValue('ECOMAIL_API_KEY', $apikey, false, null, (int) Shop::getContextShopID());
        $this->respond(json_encode(true));
    }
}
