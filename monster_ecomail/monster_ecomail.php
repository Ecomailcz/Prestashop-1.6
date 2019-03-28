<?php
if (!defined('_PS_VERSION_'))
    exit;

class monster_ecomail extends Module
{

    protected $overridenModules = array( 'blocknewsletter', 'Ps_Emailsubscription');

    public function __construct()
    {
        $this->name = 'monster_ecomail';
        $this->tab = 'emailing';
        $this->version = '1.9.1';
        $this->author = 'MONSTER MEDIA, s.r.o.';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5.1', 'max' => '1.7.999');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ecomail');
        $this->description = $this->l('Napojení e-shopu na Ecomail.');

        $this->confirmUninstall = $this->l('Opravdu si přejete odinstalovat napojení na Ecomail?');
    }

    public function install()
    {
        return parent::install() &&
            $this->setValues() &&
            $this->setTab() &&
            $this->setDatabase() &&
            $this->setHooks() &&
            unlink(_PS_CACHE_DIR_.'/class_index.php');
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            $this->unsetValues() &&
            $this->unsetTab() &&
            $this->unsetDatabase();
    }

    //hodnoty v configu
    public function setValues(){
        return Configuration::updateValue('MONSTER_ECOMAIL_SKIP_CONFIRMATION', 1);
    }

    public function unsetValues(){
        return
            Configuration::deleteByName('MONSTER_ECOMAIL_API_KEY') &&
            Configuration::deleteByName('MONSTER_ECOMAIL_APP_ID') &&
            Configuration::deleteByName('MONSTER_ECOMAIL_LIST_ID');

    }

    //taby v adminu
    public function setTab(){
        return true;
    }

    public function unsetTab(){
        return true;
    }

    //tabulky v DB
    public function setDatabase(){
        return true;
    }

    public function unsetDatabase(){
        return true;
    }

    //hooky
    public function setHooks(){

        return
            $this->registerHook( 'actionCustomerAccountAdd' ) &&
            $this->registerHook( 'actionValidateOrder' ) &&
            $this->registerHook( 'displayFooter' ) &&
            $this->registerHook( 'actionCustomerNewsletterSubscribed' ) &&
            $this->registerHook( 'actionCartSave');
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name))
        {
            Configuration::updateValue('MONSTER_ECOMAIL_API_KEY', Tools::getValue('api_key'));
            Configuration::updateValue('MONSTER_ECOMAIL_APP_ID', Tools::getValue('app_id'));
            Configuration::updateValue('MONSTER_ECOMAIL_LIST_ID', Tools::getValue('list_id'));

            Configuration::updateValue('MONSTER_ECOMAIL_LOAD_ORDER_DATA', Tools::getValue('load_order_data'));

            Configuration::updateValue('MONSTER_ECOMAIL_LOAD_NAME', Tools::getValue('load_name'));
            Configuration::updateValue('MONSTER_ECOMAIL_LOAD_ADDRESS', Tools::getValue('load_address'));
            Configuration::updateValue('MONSTER_ECOMAIL_LOAD_BIRTHDAY', Tools::getValue('load_birthday'));
            Configuration::updateValue('MONSTER_ECOMAIL_SKIP_CONFIRMATION', Tools::getValue('skip_confirmation'));

        }

        if(Configuration::get( 'MONSTER_ECOMAIL_API_KEY' ) && !$this->getAPI()->getListsCollection()){
            $output .= $this->displayError("Nepodařilo se spojit se službou Ecomail. Zkontrolujte prosím svůj API klíč.");
        }

        if(Configuration::get( 'MONSTER_ECOMAIL_API_KEY' ) && $this->getAPI()->getListsCollection()){
            $output .= $this->displayConfirmation($this->l('Spojení se službou Ecomail je aktivní.'));
            $output .= $this->displayConfirmation($this->l('Webhook pro zpětnou aktualizaci kontaktů je na adrese ')."<strong>".(isset($_SERVER['HTTPS']) ? "https" : "http") . "://".$_SERVER['HTTP_HOST']."/modules/monster_ecomail/webhook.php</strong>");
        }

        if(_PS_VERSION_ < 1.6){
            $output .= $this->displayError("Návod k aktivaci odesílání subscribů z modulu 'Blok Odběr novinek [Newsletter block]' do systému Ecomail pro vaší verzi PrestaShopu najdete <a terget='_blank' href='/modules/monster_ecomail/readme_1.5.html'>zde</a>.");
        }

        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        if(Configuration::get( 'MONSTER_ECOMAIL_API_KEY' ) && $this->getAPI()->getListsCollection()){

        $options = array();
        if( Configuration::get( 'MONSTER_ECOMAIL_API_KEY' ) ) {
            $listsCollection = $this->getAPI()
                ->getListsCollection();
            foreach( $listsCollection as $list ) {
                $options[] = array(
                    'id_option' => $list->id,
                    'name'      => $list->name
                );
            }
        }

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Konfigurace Ecomail'),
            ),
            'input' => array(
                array(
                    'type'     => 'text',
                    'label'    => $this->l( 'Vložte Váš API klíč' ),
                    'name'     => 'api_key',
                    'rows'     => 20,
                    'required' => true
                ),
                array(
                    'type'     => 'select',
                    'label'    => $this->l( 'Vyberte seznam kontaktů:' ),
                    'desc'     => $this->l(
                        'Vyberte list do kterého budou zapsáni noví zákazníci. Pokud jste jej vytvořili teprve nyní, může trvat až 30 minut, než bude možné se na něj napojit.'
                    ),
                    'name'     => 'list_id',
                    'required' => true,
                    'options'  => array(
                        'query' => $options,
                        'id'    => 'id_option',
                        'name'  => 'name'
                    )
                ),
                array(
                    'type' => (_PS_VERSION_ > 1.5) ? 'switch' : 'radio',
                    'class' => 't',
                    'label' => $this->l('Aktualizovat údaje na základě dat z objednávek'),
                    'name' => 'load_order_data',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ),
                array(
                    'type' => (_PS_VERSION_ > 1.5) ? 'switch' : 'radio',
                    'class' => 't',
                    'label' => $this->l('Odesílat jména zákazníků'),
                    'name' => 'load_name',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ),
                array(
                    'type' => (_PS_VERSION_ > 1.5) ? 'switch' : 'radio',
                    'class' => 't',
                    'label' => $this->l('Odesílat adresy zákazníků'),
                    'name' => 'load_address',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ),
                array(
                    'type' => (_PS_VERSION_ > 1.5) ? 'switch' : 'radio',
                    'class' => 't',
                    'label' => $this->l('Odesílat data narození zákazníků'),
                    'name' => 'load_birthday',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ),
                array(
                    'type' => (_PS_VERSION_ > 1.5) ? 'switch' : 'radio',
                    'class' => 't',
                    'label' => $this->l('Přeskočit double opt-in'),
                    'name' => 'skip_confirmation',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Marketer+'),
            ),
            'input' => array(
                array(
                    'type'  => 'text',
                    'label' => $this->l( 'Vložte Vaše appId' ),
                    'desc'  => $this->l(
                        'Tento údaj slouží pro aktivaci trackovacího kódu a sledování transakcí - pouze pro tarif Marketer+'
                    ),
                    'name'  => 'app_id',
                    'rows'  => 20
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        }else{
            $fields_form[0]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Konfigurace Ecomail'),
                ),
                'input' => array(
                    array(
                        'type'     => 'text',
                        'label'    => $this->l( 'Vložte Váš API klíč' ),
                        'name'     => 'api_key',
                        'rows'     => 20,
                        'required' => true,
                        'desc'  => $this->l('Po správném načtení vašeho API klíče si zvolíte seznam kontaktů pro váš e-shop.')
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                )
            );
        }

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['api_key'] = Configuration::get('MONSTER_ECOMAIL_API_KEY');
        $helper->fields_value['app_id'] = Configuration::get('MONSTER_ECOMAIL_APP_ID');
        $helper->fields_value['list_id'] = Configuration::get('MONSTER_ECOMAIL_LIST_ID');
        $helper->fields_value['load_order_data'] = Configuration::get('MONSTER_ECOMAIL_LOAD_ORDER_DATA');

        $helper->fields_value['load_name'] = Configuration::get('MONSTER_ECOMAIL_LOAD_NAME');
        $helper->fields_value['load_address'] = Configuration::get('MONSTER_ECOMAIL_LOAD_ADDRESS');
        $helper->fields_value['load_birthday'] = Configuration::get('MONSTER_ECOMAIL_LOAD_BIRTHDAY');
        $helper->fields_value['skip_confirmation'] = Configuration::get('MONSTER_ECOMAIL_SKIP_CONFIRMATION');

        return $helper->generateForm($fields_form);
    }

    protected function getAPI() {
        require_once __DIR__ . '/lib/api.php';
        $obj = new EcomailAPI();
        $obj->setAPIKey( Configuration::get('MONSTER_ECOMAIL_API_KEY') );
        return $obj;
    }

    //registrace zákazníka, registrace v košíku, objednávka bez registrace
    public function hookActionCustomerAccountAdd( $params ) {

        if(_PS_VERSION_ < 1.7){
            $newsletter = $params['_POST']['newsletter'];
            $email = $params['_POST']['email'];
        }else{
            $newsletter = $params['newCustomer']->newsletter;
            $email = $params['newCustomer']->email;
        }

        if( $newsletter ) {

            if( Configuration::get('MONSTER_ECOMAIL_API_KEY') ) {

                $nameData = array();
                $birthdayData = array();
                $addressData = array();

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_NAME')){

                    $firstname = (_PS_VERSION_ < 1.7) ? $params['_POST']['customer_firstname'] : $params['newCustomer']->firstname;
                    $lastname = (_PS_VERSION_ < 1.7) ? $params['_POST']['customer_lastname'] : $params['newCustomer']->lastname;

                    $nameData = array(
                        'name'  => $firstname,
                        'surname' => $lastname
                    );
                }

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_BIRTHDAY')){

                    if(_PS_VERSION_ < 1.7){
                        $birthday = (empty($params['_POST']['years']) ? '' : (int)$params['_POST']['years'].'-'.(int)$params['_POST']['months'].'-'.(int)$params['_POST']['days']);
                        if (!Validate::isBirthDate($birthday)) {
                            $birthday = "";
                        }
                    }else{
                        $birthday = $params['newCustomer']->birthday;
                    }

                    $birthdayData = array(
                        'birthday' => $birthday
                    );
                }

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_ADDRESS')) {

                    //pokud probíhá nákup bez registrace nebo registrace v košíku
                    if(_PS_VERSION_ < 1.7) {
                        if ($params['_POST']['id_country']) {
                            $country = new Country($params['_POST']['id_country']);

                            $addressData = array(
                                "company" => $params['_POST']['company'],
                                "city" => $params['_POST']['city'],
                                "street" => $params['_POST']['address1'],
                                "zip" => $params['_POST']['postcode'],
                                "country" => $country->iso_code
                            );
                        }
                    }else{

                    }
                }

                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('MONSTER_ECOMAIL_LIST_ID'),
                        array_merge(
                            array('email' => $email),$nameData,$birthdayData,$addressData)
                    );
            }
        }
    }

    //subscribe zákazníka v modulu blocknewsletter
    public function hookActionCustomerNewsletterSubscribed( $params ) {

        $monsterLogWS = new FileLogger(0);
        $monsterLogWS->setFilename(_PS_ROOT_DIR_ . "/log/ecomail.log");
        $monsterLogWS->logDebug("executing hook");

        if( Configuration::get('MONSTER_ECOMAIL_API_KEY') ) {

            $monsterLogWS->logDebug("api ok");

            $customer = Customer::getCustomersByEmail( $params['email'] );

            $monsterLogWS->logDebug("email = ".$params['email'] );

            //uživatel existuje v eshopu
            if($customer){

                $monsterLogWS->logDebug("customer existuje v eshopu");

                $customer = end($customer); //po registraci už není možné vytvářet další účty, takže bude mít nejvíc dat

                $nameData = array();
                $birthdayData = array();
                $addressData = array();

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_NAME')){
                    $nameData = array(
                        'name'  => $customer['firstname'],
                        'surname' => $customer['lastname']
                        //'gender' => male/female - ecomail si dopočítává automaticky podle jmen
                    );
                }

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_BIRTHDAY')){
                    $birthdayData = array(
                        'birthday' => $customer['birthday']
                    );
                }

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_ADDRESS')){
                    if(isset($params['order'])){
                        $address = new Address($params['order']->id_address_invoice);
                        $country = new Country($address->id_country);

                        $addressData = array(
                            'company' => $customer['company']
                        );
                    }
                }

                $monsterLogWS->logDebug("subscribeToList");

                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('MONSTER_ECOMAIL_LIST_ID'),
                        array_merge(array('email' => $customer['email']),$nameData,$birthdayData,$addressData)

                );
            }else{//uživatel neexistuje v eshopu - je to visitor

                $monsterLogWS->logDebug("uživatel neexistuje v eshopu");
                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('MONSTER_ECOMAIL_LIST_ID'),
                        array('email' => $params['email'])
                    );

            }
        }
    }

    //dokončení objednávky
    public function hookActionValidateOrder( $params ) {

        //aktualizace stávajících údajů + subscribe uživatele, který se registroval dříve
        if(Configuration::get('MONSTER_ECOMAIL_LOAD_ORDER_DATA')){
            $customer = new Customer($params['order']->id_customer);

            //pokud zákazník nesouhlasil s newsletterem, neřešíme ho ani zde
            if($customer->newsletter){
                $nameData = array();
                $birthdayData = array();
                $addressData = array();

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_NAME')){
                    $nameData = array(
                        'name'  => $customer->firstname,
                        'surname' => $customer->lastname

                    );
                }

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_BIRTHDAY')){
                    $birthdayData = array(
                        'birthday' => $customer->birthday

                    );
                }

                if(Configuration::get('MONSTER_ECOMAIL_LOAD_ADDRESS')){
                    $address = new Address($params['order']->id_address_invoice);
                    $country = new Country($address->id_country);

                    $addressData = array(
                        "city" => $address->city,
                        "street" => $address->address1,
                        "zip" => $address->postcode,
                        "country" => $country->iso_code,
                        "company" => $address->company
                    );
                }

                $this->getAPI()
                    ->subscribeToList(
                        Configuration::get('MONSTER_ECOMAIL_LIST_ID'),
                        array_merge(array('email' => $customer->email),$nameData,$birthdayData,$addressData)
                    );
            }
        }

        //přenos transakce pro tarif Marketer+
        $this->getAPI()
            ->createTransaction( $params['order'] );
    }

    public function hookActionCartSave($params)
    {
        //products in cart temporarily disabled - waiting for API
        return;

        if (!isset($this->context->cart))
            return;

        $cart_products = $this->context->cart->getProducts();

        $products = array();
        foreach($cart_products as $p){
            $products[] = array(
                "productId" => $p["id_product"],
                "img_url" => $this->context->link->getImageLink($p['link_rewrite'], $p["id_image"]),
                "url" => $this->context->link->getProductLink((int)$p["id_product"], $p['link_rewrite'], new Category($p["id_category_default"])),
                "name" => $p["name"],
                "price" => $p["price_wt"],
                "description" => $p["description_short"]
            );
        }

        $this->context->cookie->monster_ecomail_cart = json_encode($products);
    }

    public function hookDisplayFooter($params) {

        $output = '
<!-- Ecomail starts growing -->
<script type="text/javascript">
;(function(p,l,o,w,i,n,g){if(!p[i]){p.GlobalSnowplowNamespace=p.GlobalSnowplowNamespace||[];
p.GlobalSnowplowNamespace.push(i);p[i]=function(){(p[i].q=p[i].q||[]).push(arguments)
};p[i].q=p[i].q||[];n=l.createElement(o);g=l.getElementsByTagName(o)[0];n.async=1;
n.src=w;g.parentNode.insertBefore(n,g)}}(window,document,"script","//d3hgrlqjaqd5ry.cloudfront.net/sp/2.4.2/sp.js","ecotrack"));
window.ecotrack(\'newTracker\', \'cf\', \'d2dpiwfhf3tz0r.cloudfront.net\', { // Initialise a tracker
appId: \''.Configuration::get('MONSTER_ECOMAIL_APP_ID').'\'
});
window.ecotrack(\'setUserIdFromLocation\', \'ecmid\');
window.ecotrack(\'trackPageView\');
';

        if(isset($this->context->cookie->email)){
            $output .= 'window.ecotrack(\'setUserId\', \''.$this->context->cookie->email.'\');
            ';
        }

        if(isset($this->context->cookie->monster_ecomail_cart)){
            $output .= "window.ecotrack('trackUnstructEvent', {
            schema: '',
                data: {
                action: 'Basket',
                products: ".$this->context->cookie->monster_ecomail_cart."
                }
            })
        ";

            $this->context->cookie->__unset('monster_ecomail_cart');

        }
        $output .= '
</script>
<!-- Ecomail stops growing -->';

        return $output;
    }


}
