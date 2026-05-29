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

class WebserviceSpecificManagementEcomailSubscribers implements WebserviceSpecificManagementInterface
{
    protected $objOutput;
    protected $wsObject;

    /** @var string */
    protected $output = '';

    public function setObjectOutput($obj)
    {
        $this->objOutput = $obj;

        return $this;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function setWsObject($obj)
    {
        $this->wsObject = $obj;

        return $this;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function manage()
    {
        if ($this->wsObject->method !== 'GET') {
            $this->wsObject->setError(405, 'Method not allowed', 405);

            return $this;
        }

        if (!Module::isEnabled('ps_emailsubscription')) {
            $this->output = $this->renderJson([]);

            return $this;
        }

        $offsetAndLimit = explode(',', Tools::getValue('limit', '0,100'));

        $offset = (int) ($offsetAndLimit[0] ?? 0);
        $limit = (int) ($offsetAndLimit[1] ?? 100);
        $shopId = (int) Shop::getContextShopID();

        $query = new DbQuery();
        $query->select('s.`id`, s.`email`, s.`active`, s.`newsletter_date_add`, s.`id_shop`, s.`id_lang`');
        $query->from('emailsubscription', 's');
        $query->where('s.`active` = 1');
        if ($shopId) {
            $query->where('s.`id_shop` = ' . $shopId);
        }
        $query->limit($limit, $offset);

        $rows = Db::getInstance()->executeS($query) ?: [];
        $this->output = $this->renderJson($rows);

        return $this;
    }

    public function getContent()
    {
        return $this->output;
    }

    private function renderJson(array $rows)
    {
        return json_encode(['ecomail_subscribers' => $rows]);
    }
}
