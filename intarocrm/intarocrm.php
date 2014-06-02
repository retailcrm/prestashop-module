<?php

require 'classes/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class IntaroCRM extends Module
{
    function __construct()
    {
        $this->name = 'intarocrm';
        $this->tab = 'market_place';
        $this->version = '0.1';
        $this->author = 'Intaro Ltd.';

        $this->displayName = $this->l('IntaroCRM');
        $this->description = $this->l('Integration module for IntaroCRM');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->intaroCRM = new \IntaroCrm\RestApi(
            Configuration::get('INTAROCRM_ADDRESS'),
            Configuration::get('INTAROCRM_API_TOKEN')
        );

        $this->default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');

        $this->response = array();
        $this->customerFix = array();
        $this->orderFix = array();

        $this->address_id = null;
        $this->customer_id = null;

        $this->customer = null;

        parent::__construct();
    }

    function install()
    {
        return (
            parent::install() &&
            $this->registerHook('newOrder') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionPaymentConfirmation')

        );
    }

    function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('INTAROCRM_ADDRESS') &&
            Configuration::deleteByName('INTAROCRM_API_TOKEN') &&
            Configuration::deleteByName('INTAROCRM_API_STATUS') &&
            Configuration::deleteByName('INTAROCRM_API_DELIVERY') &&
            Configuration::deleteByName('INTAROCRM_LAST_SYNC') &&
            Configuration::deleteByName('INTAROCRM_API_ADDR')
        ;
    }

    public function getContent()
    {
        $output = null;

        $address = Configuration::get('INTAROCRM_ADDRESS');
        $token = Configuration::get('INTAROCRM_API_TOKEN');

        if (!$address || $address == '') {
            $output .= $this->displayError( $this->l('Invalid crm address') );
        } elseif (!$token || $token == '') {
            $output .= $this->displayError( $this->l('Invalid crm api token') );
        } else {
            $output .= $this->displayConfirmation(
                $this->l('Timezone settings must be identical to both of your crm and shop') .
                " <a target=\"_blank\" href=\"$address/admin/settings#t-main\">$address/admin/settings#t-main</a>"
            );
        }

        if (Tools::isSubmit('submit'.$this->name))
        {
            $address = strval(Tools::getValue('INTAROCRM_ADDRESS'));
            $token = strval(Tools::getValue('INTAROCRM_API_TOKEN'));
            $delivery = json_encode(Tools::getValue('INTAROCRM_API_DELIVERY'));
            $status = json_encode(Tools::getValue('INTAROCRM_API_STATUS'));
            $payment = json_encode(Tools::getValue('INTAROCRM_API_PAYMENT'));
            $order_address = json_encode(Tools::getValue('INTAROCRM_API_ADDR'));

            if (!$address || empty($address) || !Validate::isGenericName($address)) {
                $output .= $this->displayError( $this->l('Invalid crm address') );
            } elseif (!$token || empty($token) || !Validate::isGenericName($token)) {
                $output .= $this->displayError( $this->l('Invalid crm api token') );
            } else {
                Configuration::updateValue('INTAROCRM_ADDRESS', $address);
                Configuration::updateValue('INTAROCRM_API_TOKEN', $token);
                Configuration::updateValue('INTAROCRM_API_DELIVERY', $delivery);
                Configuration::updateValue('INTAROCRM_API_STATUS', $status);
                Configuration::updateValue('INTAROCRM_API_PAYMENT', $payment);
                Configuration::updateValue('INTAROCRM_API_ADDR', $order_address);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        $this->display(__FILE__, 'intarocrm.tpl');

        return $output.$this->displayForm();
    }

    public function displayForm()
    {

        $this->displayConfirmation($this->l('Settings updated'));

        $default_lang = $this->default_lang;
        $intaroCrm = $this->intaroCRM;

        /*
         * Network connection form
         */
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Network connection'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('CRM address'),
                    'name' => 'INTAROCRM_ADDRESS',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('CRM token'),
                    'name' => 'INTAROCRM_API_TOKEN',
                    'size' => 20,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        /*
         * Delivery
         */
        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Delivery'),
            ),
            'input' => $this->getDeliveryTypes($default_lang, $intaroCrm),
        );

        /*
         * Order status
         */
        $fields_form[2]['form'] = array(
            'legend' => array(
                'title' => $this->l('Order statuses'),
            ),
            'input' => $this->getStatusTypes($default_lang, $intaroCrm),
        );

        /*
         * Payment
         */
        $fields_form[3]['form'] = array(
            'legend' => array(
                'title' => $this->l('Payment types'),
            ),
            'input' => $this->getPaymentTypes($intaroCrm),
        );

        /*
         * Address fields
         */
        $fields_form[4]['form'] = array(
            'legend' => array(
                'title' => $this->l('Address'),
            ),
            'input' => $this->getAddressFields()
        );


        /*
         * Diplay forms
         */

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

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

        $helper->fields_value['INTAROCRM_ADDRESS'] = Configuration::get('INTAROCRM_ADDRESS');
        $helper->fields_value['INTAROCRM_API_TOKEN'] = Configuration::get('INTAROCRM_API_TOKEN');

        $deliverySettings = Configuration::get('INTAROCRM_API_DELIVERY');
        if (isset($deliverySettings) && $deliverySettings != '')
        {
            $deliveryTypes = json_decode($deliverySettings);
            foreach ($deliveryTypes as $idx => $delivery) {
                $name = 'INTAROCRM_API_DELIVERY[' . $idx . ']';
                $helper->fields_value[$name] = $delivery;
            }
        }

        $statusSettings = Configuration::get('INTAROCRM_API_STATUS');
        if (isset($statusSettings) && $statusSettings != '')
        {
            $statusTypes = json_decode($statusSettings);
            foreach ($statusTypes as $idx => $status) {
                $name = 'INTAROCRM_API_STATUS[' . $idx . ']';
                $helper->fields_value[$name] = $status;
            }
        }

        $paymentSettings = Configuration::get('INTAROCRM_API_PAYMENT');
        if (isset($paymentSettings) && $paymentSettings != '')
        {
            $paymentTypes = json_decode($paymentSettings);
            foreach ($paymentTypes as $idx => $payment) {
                $name = 'INTAROCRM_API_PAYMENT[' . $idx . ']';
                $helper->fields_value[$name] = $payment;
            }
        }

        $addressSettings = Configuration::get('INTAROCRM_API_ADDR');
        if (isset($addressSettings) && $addressSettings != '')
        {
            $addressTypes = json_decode($addressSettings);
            foreach ($addressTypes as $idx => $address) {
                $name = 'INTAROCRM_API_ADDR[' . $idx . ']';
                $helper->fields_value[$name] = $address;
            }
        }

        return $helper->generateForm($fields_form);
    }

    public function hookNewOrder($params)
    {
        return $this->hookActionOrderStatusPostUpdate($params);
    }

    public function hookActionPaymentConfirmation($params)
    {
        $this->intaroCRM->orderEdit(
            array(
                'externalId'      => $params['id_order'],
                'paymentStatus'   => 'paid',
                'createdAt'       => $params['cart']->date_upd
            )
        );
        return $this->hookActionOrderStatusPostUpdate($params);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $address_id = Address::getFirstCustomerAddressId($params['cart']->id_customer);
        $sql = 'SELECT * FROM '._DB_PREFIX_.'address WHERE id_address='.(int)$address_id;
        $address = Db::getInstance()->ExecuteS($sql);
        $address = $address[0];
        $delivery = json_decode(Configuration::get('INTAROCRM_API_DELIVERY'));
        $payment = json_decode(Configuration::get('INTAROCRM_API_PAYMENT'));
        $inCart = $params['cart']->getProducts();

        if (isset($params['orderStatus'])) {
            try {
                $crmCustomerId = $this->intaroCRM->customerCreate(
                    array(
                        'externalId' => $params['cart']->id_customer,
                        'lastName'   => $params['customer']->lastname,
                        'firstName'  => $params['customer']->firstname,
                        'email'      => $params['customer']->email,
                        'phones'     =>  array(
                            array(
                                'number' => $address['phone'],
                                'type'   => 'mobile'
                            ),
                            array(
                                'number' => $address['phone_mobile'],
                                'type'   => 'mobile'
                            )
                        ),
                        'createdAt'  => $params['customer']->date_add
                    )
                );
            }
            catch (\IntaroCrm\Exception\CurlException $e) {
                error_log("customerCreate: connection error", 3, "intarocrm.log");
            }
            catch (\IntaroCrm\Exception\ApiException $e) {
                error_log('customerCreate: ' . $e->getMessage(), 3, "intarocrm.log");
            }

            try {
                $items = array();
                foreach ($inCart as $item) {
                    $items[] = array(
                        'initialPrice' => $item['price'],
                        'quantity'      => $item['quantity'],
                        'productId'     => $item['id_product'],
                        'productName'   => $item['name'],
                        'createdAt'     => $item['date_add']
                    );
                }

                $dTypeKey = $params['cart']->id_carrier;
                if (Module::getInstanceByName('advancedcheckout') === false) {
                    $pTypeKey = $params['order']->module;
                } else {
                    $pTypeKey = $params['order']->payment;
                }
                $this->intaroCRM->orderCreate(
                    array(
                        'externalId'      => $params['order']->id,
                        'orderType'       => 'eshop-individual',
                        'orderMethod'     => 'shopping-cart',
                        'customerId'      => $params['cart']->id_customer,
                        'firstName'       => $params['customer']->firstname,
                        'lastName'        => $params['customer']->lastname,
                        'phone'           => $address['phone'],
                        'email'           => $params['customer']->email,
                        'paymentStatus'   => 'not-paid',
                        'paymentType'     => $payment->$pTypeKey,
                        'deliveryType'    => $delivery->$dTypeKey,
                        'deliveryCost'    => $params['order']->total_shipping,
                        'status'          => 'new',
                        'deliveryAddress' => array(
                            'city'    => $address['city'],
                            'index'   => $address['postcode'],
                            'text'    => $address['address1'],
                        ),
                        'discount'        => $params['order']->total_discounts,
                        'items'           => $items,
                        'createdAt'       => $params['order']->date_add
                    )
                );
            }
            catch (\IntaroCrm\Exception\CurlException $e) {
                error_log('orderCreate: connection error', 3, "intarocrm.log");
            }
            catch (\IntaroCrm\Exception\ApiException $e) {
                error_log('orderCreate: ' . $e->getMessage(), 3, "intarocrm.log");
            }

        }

        if (isset($params['newOrderStatus']) && !empty($params['newOrderStatus'])) {
            $statuses = OrderState::getOrderStates($this->default_lang);
            $aStatuses = json_decode(Configuration::get('INTAROCRM_API_STATUS'));
            foreach ($statuses as $status) {
                if ($status['name'] == $params['newOrderStatus']->name) {
                    $currStatus = $status['id_order_state'];
                    try {
                        $this->intaroCRM->orderEdit(
                            array(
                                'externalId'      => $params['id_order'],
                                'status'          => $aStatuses->$currStatus,
                                'createdAt'       => $params['cart']->date_upd
                            )
                        );
                    }
                    catch (\IntaroCrm\Exception\CurlException $e) {
                        error_log('orderStatusUpdate: connection error', 3, "intarocrm.log");
                    }
                    catch (\IntaroCrm\Exception\ApiException $e) {
                        error_log('orderStatusUpdate: ' . $e->getMessage(), 3, "intarocrm.log");
                    }
                }
            }
        }
    }

    protected function getApiDeliveryTypes($intaroCrm)
    {
        $crmDeliveryTypes = array();

        try {
            $deliveryTypes = $intaroCrm->deliveryTypesList();
        }
        catch (\IntaroCrm\Exception\CurlException $e) {
            error_log('deliveryTypesList: connection error', 3, "intarocrm.log");
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('deliveryTypesList: ' . $e->getMessage(), 3, "intarocrm.log");
        }

        if (!empty($deliveryTypes)) {
            $crmDeliveryTypes[] = array();
            foreach ($deliveryTypes as $dType) {
                $crmDeliveryTypes[] = array(
                    'id_option' => $dType['code'],
                    'name' => $dType['name'],
                );
            }
        }

        return $crmDeliveryTypes;

    }

    protected function getDeliveryTypes($default_lang, $intaroCrm)
    {
        $deliveryTypes = array();

        $carriers = Carrier::getCarriers(
            $default_lang, true, false, false,
            null, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE
        );

        if (!empty($carriers)) {
            foreach ($carriers as $carrier) {
                $deliveryTypes[] = array(
                    'type' => 'select',
                    'label' => $carrier['name'],
                    'name' => 'INTAROCRM_API_DELIVERY[' . $carrier['id_carrier'] . ']',
                    'required' => false,
                    'options' => array(
                        'query' => $this->getApiDeliveryTypes($intaroCrm),
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                );
            }
        }

        return $deliveryTypes;
    }

    protected function getApiStatuses($intaroCrm)
    {
        $crmStatusTypes = array();

        try {
            $statusTypes = $intaroCrm->orderStatusesList();
        }
        catch (\IntaroCrm\Exception\CurlException $e) {
            error_log('statusTypesList: connection error', 3, "intarocrm.log");
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('statusTypesList: ' . $e->getMessage(), 3, "intarocrm.log");
        }

        if (!empty($statusTypes)) {
            $crmStatusTypes[] = array();
            foreach ($statusTypes as $sType) {
                $crmStatusTypes[] = array(
                    'id_option' => $sType['code'],
                    'name' => $sType['name']
                );
            }
        }

        return $crmStatusTypes;
    }

    protected function getStatusTypes($default_lang, $intaroCrm)
    {
        $statusTypes = array();
        $states = OrderState::getOrderStates($default_lang, true);

        if (!empty($states)) {
            foreach ($states as $state) {
                if ($state['name'] != ' ') {
                    $statusTypes[] = array(
                        'type' => 'select',
                        'label' => $state['name'],
                        'name' => 'INTAROCRM_API_STATUS[' . $state['id_order_state'] . ']',
                        'required' => false,
                        'options' => array(
                            'query' => $this->getApiStatuses($intaroCrm),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    );
                }
            }
        }

        return $statusTypes;
    }

    protected function getApiPaymentTypes($intaroCrm)
    {
        $crmPaymentTypes = array();

        try {
            $paymentTypes = $intaroCrm->paymentTypesList();
        }
        catch (\IntaroCrm\Exception\CurlException $e) {
            error_log('paymentTypesList: connection error', 3, "intarocrm.log");
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('paymentTypesList: ' . $e->getMessage(), 3, "intarocrm.log");
        }

        if (!empty($paymentTypes)) {
            $crmPaymentTypes[] = array();
            foreach ($paymentTypes as $pType) {
                $crmPaymentTypes[] = array(
                    'id_option' => $pType['code'],
                    'name' => $pType['name']
                );
            }
        }

        return $crmPaymentTypes;
    }

    protected function getPaymentTypes($intaroCrm)
    {
        $payments = $this->getSystemPaymentModules();
        $paymentTypes = array();

        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $paymentTypes[] = array(
                    'type' => 'select',
                    'label' => $payment['name'],
                    'name' => 'INTAROCRM_API_PAYMENT[' . $payment['code'] . ']',
                    'required' => false,
                    'options' => array(
                        'query' => $this->getApiPaymentTypes($intaroCrm),
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                );
            }
        }

        return $paymentTypes;
    }

    protected function getSystemPaymentModules()
    {
        $shop_id = Context::getContext()->shop->id;

        /* Get all modules then select only payment ones */
        $modules = Module::getModulesOnDisk(true);
        /*
         * Hack for knivesland
         */
        if (Module::getInstanceByName('advancedcheckout') === false) {
            foreach ($modules as $module) {
                if ($module->tab == 'payments_gateways')
                {
                    if ($module->id)
                    {
                        if (!get_class($module) == 'SimpleXMLElement')
                            $module->country = array();
                        $countries = DB::getInstance()->executeS('
                        SELECT id_country
                        FROM '._DB_PREFIX_.'module_country
                        WHERE id_module = '.(int)$module->id.' AND `id_shop`='.(int)$shop_id
                        );
                        foreach ($countries as $country)
                            $module->country[] = $country['id_country'];

                        if (!get_class($module) == 'SimpleXMLElement')
                            $module->currency = array();
                        $currencies = DB::getInstance()->executeS('
                        SELECT id_currency
                        FROM '._DB_PREFIX_.'module_currency
                        WHERE id_module = '.(int)$module->id.' AND `id_shop`='.(int)$shop_id
                        );
                        foreach ($currencies as $currency)
                            $module->currency[] = $currency['id_currency'];

                        if (!get_class($module) == 'SimpleXMLElement')
                            $module->group = array();
                        $groups = DB::getInstance()->executeS('
                        SELECT id_group
                        FROM '._DB_PREFIX_.'module_group
                        WHERE id_module = '.(int)$module->id.' AND `id_shop`='.(int)$shop_id
                        );
                        foreach ($groups as $group)
                            $module->group[] = $group['id_group'];
                    }
                    else
                    {
                        $module->country = null;
                        $module->currency = null;
                        $module->group = null;
                    }

                    if ($module->active != 0) {
                        $this->payment_modules[] = array(
                            'id' => $module->id,
                            'code' => $module->name,
                            'name' => $module->displayName
                        );
                    }

                }
            }
        } else {
            require_once(dirname(__FILE__) . '/../advancedcheckout/classes/Payment.php');
            $modules = Payment::getPaymentMethods();
            foreach ($modules as $module) {
                $this->payment_modules[] = array(
                    'id' => $module['id_payment'],
                    'code' => $module['name'],
                    'name' => $module['name']
                );
            }
        }

        return $this->payment_modules;
    }

    protected function getAddressFields()
    {
        $addressFields = array();
        $address = explode(' ', str_replace("\n", ' ', AddressFormat::getAddressCountryFormat($this->context->country->id)));

        if (!empty($address)) {
            foreach ($address as $idx => $a) {
                if (!strpos($a, ':')) {
                    $addressFields[] = array(
                        'type' => 'select',
                        'label' => $this->l((string)$a),
                        'name' => 'INTAROCRM_API_ADDR[' . $idx . ']',
                        'required' => false,
                        'options' => array(
                            'query' => array(
                                array(
                                    'name' => '',
                                    'id_option' => ''
                                ),
                                array(
                                    'name' => $this->l('FIRST_NAME'),
                                    'id_option' => 'first_name'
                                ),
                                array(
                                    'name' => $this->l('LAST_NAME'),
                                    'id_option' => 'last_name'
                                ),
                                array(
                                    'name' => $this->l('PHONE'),
                                    'id_option' => 'phone'
                                ),
                                array(
                                    'name' => $this->l('EMAIL'),
                                    'id_option' => 'email'
                                ),
                                array(
                                    'name' => $this->l('ADDRESS'),
                                    'id_option' => 'address'
                                ),
                                array(
                                    'name' => $this->l('COUNTRY'),
                                    'id_option' => 'country'
                                ),
                                array(
                                    'name' => $this->l('REGION'),
                                    'id_option' => 'region'
                                ),
                                array(
                                    'name' => $this->l('CITY'),
                                    'id_option' => 'city'
                                ),
                                array(
                                    'name' => $this->l('ZIP'),
                                    'id_option' => 'index'
                                ),
                                array(
                                    'name' => $this->l('STREET'),
                                    'id_option' => 'street'
                                ),
                                array(
                                    'name' => $this->l('BUILDING'),
                                    'id_option' => 'building'
                                ),
                                array(
                                    'name' => $this->l('FLAT'),
                                    'id_option' => 'flat'
                                ),
                                array(
                                    'name' => $this->l('INTERCOMCODE'),
                                    'id_option' => 'intercomcode'
                                ),
                                array(
                                    'name' => $this->l('FLOOR'),
                                    'id_option' => 'floor'
                                ),
                                array(
                                    'name' => $this->l('BLOCK'),
                                    'id_option' => 'block'
                                ),
                                array(
                                    'name' => $this->l('HOUSE'),
                                    'ID' => 'house'
                                )
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    );
                }
            }
        }

        return $addressFields;
    }

    public function exportCatalog()
    {
        global $smarty;
        $shop_url = (Configuration::get('PS_SSL_ENABLED') ? _PS_BASE_URL_SSL_ : _PS_BASE_URL_);
        $id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        if ($currency->iso_code == 'RUB') {
            $currency->iso_code = 'RUR';
        }

        // Get currencies
        $currencies = Currency::getCurrencies();

        // Get categories
        $categories = Category::getCategories($id_lang, true, false);

        // Get products
        $products = Product::getProducts($id_lang, 0, 0, 'name', 'asc');
        foreach ($products AS $product)
        {
            // Check for home category
            $category = $product['id_category_default'];
            if ($category == Configuration::get('PS_HOME_CATEGORY')) {
                $temp_categories = Product::getProductCategories($product['id_product']);

                foreach ($temp_categories AS $category) {
                    if ($category != Configuration::get('PS_HOME_CATEGORY'))
                        break;
                }

                if ($category == Configuration::get('PS_HOME_CATEGORY')) {
                    continue;
                }

            }
            $link = new Link();
            $cover = Image::getCover($product['id_product']);

            $picture = 'http://' . $link->getImageLink($product['link_rewrite'], $product['id_product'].'-'.$cover['id_image'], 'large_default');
            if (!(substr($picture, 0, strlen($shop_url)) === $shop_url))
                $picture = rtrim($shop_url,"/") . $picture;
            $crewrite = Category::getLinkRewrite($product['id_category_default'], $id_lang);
            $url = $link->getProductLink($product['id_product'], $product['link_rewrite'], $crewrite);
            $version = substr(_PS_VERSION_, 0, 3);
            if ($version == "1.3")
                $available_for_order  = $product['active'] && $product['quantity'];
            else {
                $prod = new Product($product['id_product']);
                $available_for_order = $product['active'] && $product['available_for_order'] && $prod->checkQty(1);
            }
            $items[] = array('id_product' => $product['id_product'],
                             'available_for_order' => $available_for_order,
                             'price' => $product['price'],
                             'purchase_price' => $product['wholesale_price'],
                             'name' => htmlspecialchars(strip_tags($product['name'])),
                             'article' => htmlspecialchars($product['reference']),
                             'id_category_default' => $category,
                             'picture' => $picture,
                             'url' => $url
            );
        }

        foreach ($this->custom_attributes as $i => $value) {
            $attr = Configuration::get($value);
            $smarty->assign(strtolower($value), $attr);
        }

        $smarty->assign('currencies', $currencies);
        $smarty->assign('currency', $currency->iso_code);
        $smarty->assign('categories', $categories);
        $smarty->assign('products', $items);
        $smarty->assign('shop_name', Configuration::get('PS_SHOP_NAME'));
        $smarty->assign('company', Configuration::get('PS_SHOP_NAME'));
        $smarty->assign('shop_url', $shop_url . __PS_BASE_URI__);
        return $this->display(__FILE__, 'export.tpl');
    }

    public function orderHistory()
    {
        /*
         * get last sync date
         */
        $lastSync = Configuration::get('INTAROCRM_LAST_SYNC');

        $startFrom = ($lastSync === false) ? null : $lastSync;
        $endTime = date('Y-m-d H:i:s');

        $data = array();
        $counter = 0;

        /*
         * retrive orders from crm since last update
         */
        do {
            try {
                $this->response = $this->intaroCRM->orderHistory(
                    $startDate = $startFrom, $endDate = $endTime,
                    $limit = 250, $offset = $counter
                );
                $data = array_merge($data, $this->response);
                $counter += 250;
            }
            catch (\IntaroCrm\Exception\CurlException $e) {
                error_log('orderHistory: connection error', 3, "intarocrm.log");
            }
            catch (\IntaroCrm\Exception\ApiException $e) {
                error_log('orderHistory: ' . $e->getMessage(), 3, "intarocrm.log");
            }
        } while (!empty($response));

        /*
         * store recieved data into shop database
         */
        if (!empty($data)) {
            $toUpdate = array();

            /*
             * Customer object. Will be used for further updates.
             */
            $this->customer = new Customer();

            $statuses = array_flip((array)json_decode(Configuration::get('INTAROCRM_API_STATUS')));
            $deliveries = array_flip((array)json_decode(Configuration::get('INTAROCRM_API_DELIVERY')));
            $payments = array_flip((array)json_decode(Configuration::get('INTAROCRM_API_PAYMENT')));

            foreach ($data as $order) {
                if (!array_key_exists('externalId', $order)) {
                    /*
                     * create customer if not exist
                     */
                    $this->customer->getByEmail($order['customer']['email']);

                    if (!array_key_exists('externalId', $order['customer'])) {
                        if (Validate::isEmail($order['customer']['email'])) {

                            if (!$this->customer->id)
                            {
                                $this->customer->firstname = $order['customer']['firstName'];
                                $this->customer->lastname = $order['customer']['lastName'];
                                $this->customer->email = $order['customer']['email'];
                                $this->customer->passwd = substr(str_shuffle(strtolower(sha1(rand() . time()))),0, 5);

                                if($this->customer->add()) {

                                    /*
                                     * create customer address for delivery data
                                     */

                                    $this->customer->getByEmail($order['customer']['email']);
                                    $this->customer_id = $this->customer->id;

                                    $address = new Address();
                                    $address->id_customer = $this->customer->id;
                                    $address->id_country = $this->default_country;
                                    $address->lastname = $this->customer->lastname;
                                    $address->firstname = $this->customer->firstname;
                                    $address->alias = 'default';
                                    $address->postcode = $order['deliveryAddress']['index'];
                                    $address->city = $order['deliveryAddress']['city'];
                                    $address->address1 = $order['deliveryAddress']['text'];
                                    $address->phone = $order['phone'];
                                    $address->phone_mobile = $order['phone'];

                                    $address->add();

                                    /*
                                     * store address record id for handle order data
                                     */
                                    $addr = $this->customer->getAddresses($this->default_lang);
                                    $this->address_id = $addr[0]['id_address'];
                                }
                            } else {
                                $addresses =  $this->customer->getAddresses($this->default_lang);
                                $this->address_id = $addresses[0]['id_address'];
                                $this->customer_id = $this->customer->id;
                            }

                            /*
                             * collect customer ids for single fix request
                             */
                            array_push(
                                $this->customerFix,
                                array(
                                    'id' => $order['customer']['id'],
                                    'externalId' => $this->customer_id
                                )
                            );
                        }
                    } else {
                        $addresses =  $this->customer->getAddresses($this->default_lang);
                        $this->address_id = $addresses[0]['id_address'];
                        $this->customer_id = $order['customer']['externalId'];
                    }

                    $delivery = $order['deliveryType'];
                    $payment = $order['paymentType'];
                    $state = $order['status'];

                    $cart = new Cart();
                    $cart->id_currency = $this->default_currency;
                    $cart->id_lang = $this->default_lang;
                    $cart->id_customer = $this->customer_id;
                    $cart->id_address_delivery = (int)$this->address_id;
                    $cart->id_address_invoice = (int)$this->address_id;
                    $cart->id_carrier = (int)$deliveries[$delivery];

                    $cart->add();

                    $products = array();
                    if(!empty($order['items'])) {
                        foreach ($order['items'] as $item) {
                            $product = array();
                            $product['id_product'] = (int)$item['offer']['externalId'];
                            $product['quantity'] = $item['quantity'];
                            $product['id_address_delivery'] = (int)$this->address_id;
                            $products[] = $product;
                        }
                    }

                    $cart->setWsCartRows($products);
                    $cart->update();

                    /*
                     * Create order
                     */

                    $newOrder = new Order();
                    $newOrder->id_address_delivery = (int)$this->address_id;
                    $newOrder->id_address_invoice = (int)$this->address_id;
                    $newOrder->id_cart = (int)$cart->id;
                    $newOrder->id_currency = $this->default_currency;
                    $newOrder->id_lang = $this->default_lang;
                    $newOrder->id_customer = (int)$this->customer_id;
                    $newOrder->id_carrier = (int)$deliveries[$delivery];
                    $newOrder->payment =  $payments[$payment];
                    $newOrder->module = (Module::getInstanceByName('advancedcheckout') === false)
                        ? $payments[$payment]
                        : 'advancedcheckout'
                        ;
                    $newOrder->total_paid = $order['summ'] + $order['deliveryCost'];
                    $newOrder->total_paid_tax_incl = $order['summ'] + $order['deliveryCost'];
                    $newOrder->total_paid_tax_excl = $order['summ'] + $order['deliveryCost'];
                    $newOrder->total_paid_real = $order['summ'] + $order['deliveryCost'];
                    $newOrder->total_products = $order['summ'];
                    $newOrder->total_products_wt = $order['summ'];
                    $newOrder->total_shipping = $order['deliveryCost'];
                    $newOrder->total_shipping_tax_incl = $order['deliveryCost'];
                    $newOrder->total_shipping_tax_excl = $order['deliveryCost'];
                    $newOrder->conversion_rate = 1.000000;
                    $newOrder->current_state = (int)$statuses[$state];
                    $newOrder->delivery_date = $order['deliveryDate'];
                    $newOrder->date_add = $order['createdAt'];
                    $newOrder->date_upd = $order['createdAt'];
                    $newOrder->valid = 1;
                    $newOrder->secure_key = md5(time());

                    if (isset($order['discount']))
                    {
                        $newOrder->total_discounts = $order['discount'];
                    }

                    $newOrder->add(false, false);

                    /*
                     * collect order ids for single fix request
                     */
                    array_push(
                        $this->orderFix,
                        array(
                            'id' => $order['id'],
                            'externalId' => $newOrder->id
                        )
                    );

                    /*
                     * Create order details
                     */
                    $product_list = array();
                    foreach ($order['items'] as $item) {
                        $product = new Product((int)$item['offer']['externalId'], false, $this->default_lang);
                        $qty = $item['quantity'];
                        $product_list[] = array('product' =>$product, 'quantity' => $qty);
                    }

                    $query = 'INSERT `'._DB_PREFIX_.'order_detail`
                        (
                            `id_order`, `id_order_invoice`, `id_shop`, `product_id`, `product_attribute_id`,
                            `product_name`, `product_quantity`, `product_quantity_in_stock`, `product_price`,
                            `product_reference`, `total_price_tax_excl`, `total_price_tax_incl`,
                            `unit_price_tax_excl`, `unit_price_tax_incl`, `original_product_price`
                        )

                        VALUES';

                    foreach ($product_list as $product) {
                        $query .= '('
                            .(int)$newOrder->id.',
                            0,
                            '. $this->context->shop->id.',
                            '.(int)$product['product']->id.',
                            0,
                            '.implode('', array('\'', $product['product']->name, '\'')).',
                            '.(int)$product['quantity'].',
                            '.(int)$product['quantity'].',
                            '.$product['product']->price.',
                            '.implode('', array('\'', $product['product']->reference, '\'')).',
                            '.$product['product']->price.',
                            '.$product['product']->price.',
                            '.$product['product']->price.',
                            '.$product['product']->price.',
                            '.$product['product']->price.'
                        ),';
                    }

                    Db::getInstance()->execute(rtrim($query, ','));

                    try {
                        $this->intaroCRM->customerFixExternalIds($this->customerFix);
                        $this->intaroCRM->orderFixExternalIds($this->orderFix);
                    }
                    catch (\IntaroCrm\Exception\CurlException $e) {
                        error_log('fixExternalId: connection error', 3, "intarocrm.log");
                        continue;
                    }
                    catch (\IntaroCrm\Exception\ApiException $e) {
                        error_log('fixExternalId: ' . $e->getMessage(), 3, "intarocrm.log");
                        continue;
                    }

                } else {
                    if (!in_array($order['id'], $toUpdate))
                    {
                        /*
                         * take last order update only
                         */
                        $toUpdate[] = $order['id'];
                        if ($order['paymentType'] != null && $order['deliveryType'] != null && $order['status'] != null) {
                            $orderToUpdate = new Order((int)$order['externalId']);

                            /*
                             * check status
                             */
                            $stype = $order['status'];
                            if ($statuses[$stype] != null) {
                                if ($statuses[$stype] != $orderToUpdate->current_state) {
                                    Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'orders`
                                        SET `current_state` = \''.$statuses[$stype].'\'
                                        WHERE `id_order` = '.(int)$order['externalId']);
                                }
                            }

                            /*
                             * check delivery type
                             */
                            $dtype = $order['deliveryType'];
                            if ($deliveries[$dtype] != null) {
                                if ($deliveries[$dtype] != $orderToUpdate->id_carrier) {
                                    Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'orders`
                                        SET `id_carrier` = \''.$deliveries[$dtype].'\'
                                        WHERE `id_order` = '.(int)$order['externalId']);
                                    Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'order_carrier`
                                        SET `id_carrier` = \''.$deliveries[$dtype].'\'
                                        WHERE `id_order` = \''.$orderToUpdate->id.'\'');
                                }
                            }

                            /*
                             * check payment type
                             */
                            $ptype = $order['paymentType'];
                            if ($payments[$ptype] != null) {
                                if ($payments[$ptype] != $orderToUpdate->payment) {
                                    Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'orders`
                                        SET `payment` = \''.$payments[$ptype].'\'
                                        WHERE `id_order` = '.(int)$order['externalId']);
                                    Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'order_payment`
                                        SET `payment_method` = \''.$payments[$ptype].'\'
                                        WHERE `order_reference` = \''.$orderToUpdate->reference.'\'');

                                }
                            }

                            /*
                             * check items
                             */

                            /*
                             * Clean deleted
                             */
                            foreach ($order['items'] as $key => $item) {
                                if (isset($item['deleted']) && $item['deleted'] == true) {
                                    Db::getInstance()->execute('
                                        DELETE FROM `'._DB_PREFIX_.'order_detail`
                                        WHERE `id_order` = '. $orderToUpdate->id .'
                                        AND `product_id` = '.$item['id']);

                                    unset($order['items'][$key]);
                                }
                            }

                            /*
                             * check quantity
                             */

                            foreach ($orderToUpdate->getProductsDetail() as $orderItem) {
                                foreach ($order['items'] as $key => $item) {
                                    if ($item['offer']['externalId'] == $orderItem['product_id']) {
                                        if (isset($item['quantity']) && $item['quantity'] != $orderItem['product_quantity']) {
                                            Db::getInstance()->execute('
                                                UPDATE `'._DB_PREFIX_.'order_detail`
                                                SET `product_quantity` = '.$item['quantity'].',
                                                `product_quantity_in_stock` = '.$item['quantity'].'
                                                WHERE `id_order_detail` = '.$orderItem['id_order_detail']);
                                        }

                                        unset($order['items'][$key]);
                                    }
                                }
                            }

                            /*
                             * check new items
                             */
                            if (!empty($order['items'])) {
                                foreach ($order['items'] as $key => $newItem) {
                                    $product = new Product((int)$newItem['offer']['externalId'], false, $this->default_lang);
                                    $qty = $newItem['quantity'];
                                    $product_list[] = array('product' =>$product, 'quantity' => $qty);
                                }


                                $query = 'INSERT `'._DB_PREFIX_.'order_detail`
                                (
                                    `id_order`, `id_order_invoice`, `id_shop`, `product_id`, `product_attribute_id`,
                                    `product_name`, `product_quantity`, `product_quantity_in_stock`, `product_price`,
                                    `product_reference`, `total_price_tax_excl`, `total_price_tax_incl`,
                                    `unit_price_tax_excl`, `unit_price_tax_incl`, `original_product_price`
                                )

                                VALUES';

                                foreach ($product_list as $product) {
                                    $query .= '('
                                        .(int)$orderToUpdate->id.',
                                        0,
                                        '. $this->context->shop->id.',
                                        '.(int)$product['product']->id.',
                                        0,
                                        '.implode('', array('\'', $product['product']->name, '\'')).',
                                        '.(int)$product['quantity'].',
                                        '.(int)$product['quantity'].',
                                        '.$product['product']->price.',
                                        '.implode('', array('\'', $product['product']->reference, '\'')).',
                                        '.$product['product']->price.',
                                        '.$product['product']->price.',
                                        '.$product['product']->price.',
                                        '.$product['product']->price.',
                                        '.$product['product']->price.'
                                    ),';
                                }

                                Db::getInstance()->execute(rtrim($query, ','));
                                unset($order['items'][$key]);
                            }

                            /*
                             * Fix prices & discounts
                             * Discounts only for whole order
                             */
                            $orderDiscout = null;
                            $orderTotal = $order['summ'];

                            if (isset($order['discount']) && $order['discount'] > 0) {
                                if ($order['discount'] != $orderToUpdate->total_discounts) {
                                    $orderDiscout = ($orderDiscout == null) ? $order['discount'] : $order['discount'] + $orderDiscout;
                                }
                            }

                            if (isset($order['discountPercent']) && $order['discountPercent'] > 0) {
                                $percent = ($order['summ'] * $order['discountPercent'])/100;
                                if ($percent != $orderToUpdate->total_discounts) {
                                    $orderDiscout = ($orderDiscout == null) ? $percent : $percent + $orderDiscout;
                                }
                            }

                            $totalDiscount = ($orderDiscout == null) ? $orderToUpdate->total_discounts : $orderDiscout;

                            if ($totalDiscount != $orderToUpdate->total_discounts || $orderTotal != $orderToUpdate->total_paid) {
                                Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'orders`
                                        SET `total_discounts` = '.$totalDiscount.',
                                        `total_discounts_tax_incl` = '.$totalDiscount.',
                                        `total_discounts_tax_excl` = '.$totalDiscount.',
                                        `total_paid` = '.$orderTotal.',
                                        `total_paid_tax_incl` = '.$orderTotal.',
                                        `total_paid_tax_excl` = '.$orderTotal.'
                                        WHERE `id_order` = '.(int)$order['externalId']);
                            }
                        }
                    }
                }
            }

            /*
             * Update last sync timestamp
             */
            try {
                Configuration::updateValue(
                    'INTAROCRM_LAST_SYNC',
                    date_format($this->intaroCRM->getGeneratedAt(), 'Y-m-d H:i:s')
                );
            }
            catch (\IntaroCrm\Exception\CurlException $e) {
                error_log('getLastSync: connection error', 3, "intarocrm.log");
            }
            catch (\IntaroCrm\Exception\ApiException $e) {
                error_log('getLastSync: ' . $e->getMessage(), 3, "intarocrm.log");
            }

            return count($data) . " records was synced";

        } else {
            return 'Nothing to sync';
        }

    }
}
