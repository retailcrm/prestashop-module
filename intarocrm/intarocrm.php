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

        parent::__construct();
    }

    function install()
    {
        return (
            parent::install() &&
            $this->registerHook('newOrder') &&
            $this->registerHook('actionOrderStatusPostUpdate')
        );
    }

    function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('INTAROCRM_ADDRESS') &&
            Configuration::deleteByName('INTAROCRM_API_TOKEN') &&
            Configuration::deleteByName('INTAROCRM_API_STATUS') &&
            Configuration::deleteByName('INTAROCRM_API_DELIVERY') &&
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

        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

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

    public function hookActionOrderStatusPostUpdate($params)
    {
        $address_id = Address::getFirstCustomerAddressId($params['cart']->id_customer);
        $sql = 'SELECT * FROM '._DB_PREFIX_.'address WHERE id_address='.(int)$address_id;
        $address = Db::getInstance()->ExecuteS($sql);
        $address = $address[0];
        $delivery = json_decode(Configuration::get('INTAROCRM_API_DELIVERY'));
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
                error_log('customerCreate: connection error');
            }
            catch (\IntaroCrm\Exception\ApiException $e) {
                error_log('customerCreate: ' . $e->getMessage());
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
                        'deliveryType'    => $delivery->$dTypeKey,
                        'deliveryCost'    => $params['order']->total_shipping,
                        'status'          => 'new',
                        //'paymentType'     => 'cash',
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
                error_log('orderCreate: connection error');
            }
            catch (\IntaroCrm\Exception\ApiException $e) {
                error_log('orderCreate: ' . $e->getMessage());
            }

        }

        if (isset($params['newOrderStatus']) && !empty($params['newOrderStatus'])) {
            //var_dump($params['newOrderStatus']);
        }

    }

    protected function getApiDeliveryTypes($intaroCrm)
    {
        $crmDeliveryTypes = array();

        try {
            $deliveryTypes = $intaroCrm->deliveryTypesList();
        }
        catch (\IntaroCrm\Exception\CurlException $e) {
            error_log('deliveryTypesList: connection error');
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('deliveryTypesList: ' . $e->getMessage());
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
            error_log('statusTypesList: connection error');
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('statusTypesList: ' . $e->getMessage());
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
            error_log('paymentTypesList: connection error');
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('paymentTypesList: ' . $e->getMessage());
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
                    'name' => 'INTAROCRM_API_PAYMENT[' . $payment['id'] . ']',
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
                        'name' => $module->displayName
                    );
                }

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
}
