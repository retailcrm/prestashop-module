<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

if (
    function_exists('date_default_timezone_set')
    &&
    function_exists('date_default_timezone_get')
) {
    date_default_timezone_set(@date_default_timezone_get());
}

require 'lib/vendor/Retailcrm.php';
require 'lib/vendor/Service.php';

if (file_exists('lib/custom/References.php')) {
    require 'lib/custom/References.php';
} else {
    require 'lib/classes/References.php';
}

class RetailCRM extends Module
{
    function __construct()
    {
        $this->name = 'retailcrm';
        $this->tab = 'market_place';
        $this->version = '1.1';
        $this->author = 'Retail Driver LCC';

        $this->displayName = $this->l('RetailCRM');
        $this->description = $this->l('Integration module for RetailCRM');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->apiUrl = Configuration::get('RETAILCRM_ADDRESS');
        $this->apiKey = Configuration::get('RETAILCRM_API_TOKEN');

        if (!empty($this->apiUrl) && !empty($this->apiKey)) {
            $this->api = new ApiClient(
                $this->apiUrl,
                $this->apiKey
            );
        }

        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');

        $this->response = array();
        $this->customerFix = array();
        $this->orderFix = array();

        $this->address_id = null;
        $this->customer_id = null;

        $this->customer = null;

        $this->ref = new References($this->api);

        parent::__construct();
    }

    function install()
    {
        return (
            parent::install() &&
            $this->registerHook('newOrder') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('actionCustomerAccountAdd')
        );
    }

    function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('RETAILCRM_ADDRESS') &&
            Configuration::deleteByName('RETAILCRM_API_TOKEN') &&
            Configuration::deleteByName('RETAILCRM_API_STATUS') &&
            Configuration::deleteByName('RETAILCRM_API_DELIVERY') &&
            Configuration::deleteByName('RETAILCRM_LAST_SYNC') &&
            Configuration::deleteByName('RETAILCRM_API_ADDR')
        ;
    }

    public function getContent()
    {
        $output = null;

        $address = Configuration::get('RETAILCRM_ADDRESS');
        $token = Configuration::get('RETAILCRM_API_TOKEN');

        if (!$address || $address == '') {
            $output .= $this->displayError( $this->l('Invalid or empty crm address') );
        } elseif (!$token || $token == '') {
            $output .= $this->displayError( $this->l('Invalid or empty crm api token') );
        } else {
            $output .= $this->displayConfirmation(
                $this->l('Timezone settings must be identical to both of your crm and shop') .
                " <a target=\"_blank\" href=\"$address/admin/settings#t-main\">$address/admin/settings#t-main</a>"
            );
        }

        if (Tools::isSubmit('submit'.$this->name))
        {
            $address = strval(Tools::getValue('RETAILCRM_ADDRESS'));
            $token = strval(Tools::getValue('RETAILCRM_API_TOKEN'));
            $delivery = json_encode(Tools::getValue('RETAILCRM_API_DELIVERY'));
            $status = json_encode(Tools::getValue('RETAILCRM_API_STATUS'));
            $payment = json_encode(Tools::getValue('RETAILCRM_API_PAYMENT'));
            $order_address = json_encode(Tools::getValue('RETAILCRM_API_ADDR'));

            if (!$address || empty($address) || !Validate::isGenericName($address)) {
                $output .= $this->displayError( $this->l('Invalid crm address') );
            } elseif (!$token || empty($token) || !Validate::isGenericName($token)) {
                $output .= $this->displayError( $this->l('Invalid crm api token') );
            } else {
                Configuration::updateValue('RETAILCRM_ADDRESS', $address);
                Configuration::updateValue('RETAILCRM_API_TOKEN', $token);
                Configuration::updateValue('RETAILCRM_API_DELIVERY', $delivery);
                Configuration::updateValue('RETAILCRM_API_STATUS', $status);
                Configuration::updateValue('RETAILCRM_API_PAYMENT', $payment);
                Configuration::updateValue('RETAILCRM_API_ADDR', $order_address);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        $this->display(__FILE__, 'retailcrm.tpl');

        return $output.$this->displayForm();
    }

    public function displayForm()
    {

        $this->displayConfirmation($this->l('Settings updated'));

        $default_lang = $this->default_lang;
        $intaroCrm = $this->api;

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
                    'name' => 'RETAILCRM_ADDRESS',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('CRM token'),
                    'name' => 'RETAILCRM_API_TOKEN',
                    'size' => 20,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );


        if (!empty($this->apiUrl) && !empty($this->apiKey)) {
            /*
             * Delivery
             */
            $fields_form[1]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Delivery'),
                ),
                'input' => $this->ref->getDeliveryTypes(),
            );

            /*
             * Order status
             */
            $fields_form[2]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Order statuses'),
                ),
                'input' => $this->ref->getStatuses(),
            );

            /*
             * Payment
             */
            $fields_form[3]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Payment types'),
                ),
                'input' => $this->ref->getPaymentTypes(),
            );
        }

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

        $helper->fields_value['RETAILCRM_ADDRESS'] = Configuration::get('RETAILCRM_ADDRESS');
        $helper->fields_value['RETAILCRM_API_TOKEN'] = Configuration::get('RETAILCRM_API_TOKEN');

        $deliverySettings = Configuration::get('RETAILCRM_API_DELIVERY');
        if (isset($deliverySettings) && $deliverySettings != '')
        {
            $deliveryTypes = json_decode($deliverySettings);
            foreach ($deliveryTypes as $idx => $delivery) {
                $name = 'RETAILCRM_API_DELIVERY[' . $idx . ']';
                $helper->fields_value[$name] = $delivery;
            }
        }

        $statusSettings = Configuration::get('RETAILCRM_API_STATUS');
        if (isset($statusSettings) && $statusSettings != '')
        {
            $statusTypes = json_decode($statusSettings);
            foreach ($statusTypes as $idx => $status) {
                $name = 'RETAILCRM_API_STATUS[' . $idx . ']';
                $helper->fields_value[$name] = $status;
            }
        }

        $paymentSettings = Configuration::get('RETAILCRM_API_PAYMENT');
        if (isset($paymentSettings) && $paymentSettings != '')
        {
            $paymentTypes = json_decode($paymentSettings);
            foreach ($paymentTypes as $idx => $payment) {
                $name = 'RETAILCRM_API_PAYMENT[' . $idx . ']';
                $helper->fields_value[$name] = $payment;
            }
        }

        $addressSettings = Configuration::get('RETAILCRM_API_ADDR');
        if (isset($addressSettings) && $addressSettings != '')
        {
            $addressTypes = json_decode($addressSettings);
            foreach ($addressTypes as $idx => $address) {
                $name = 'RETAILCRM_API_ADDR[' . $idx . ']';
                $helper->fields_value[$name] = $address;
            }
        }

        return $helper->generateForm($fields_form);
    }

    public function getAddressFields()
    {
        $addressFields = array();
        $address = explode(' ', str_replace("\n", ' ', AddressFormat::getAddressCountryFormat($this->context->country->id)));

        if (!empty($address)) {
            foreach ($address as $idx => $a) {
                if (!in_array($a, array('vat_number', 'phone_mobile', 'company'))) {
                    if (!strpos($a, ':')) {
                        $a = preg_replace('/_/', ' ', $a);
                        $a = preg_replace('/[\,\.]/', '', $a);
                        $addressFields[] = array(
                            'type' => 'select',
                            'label' => $this->l((string) ucfirst($a)),
                            'name' => 'RETAILCRM_API_ADDR[' . $idx . ']',
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
        }

        return $addressFields;
    }

    public function hookActionCustomerAccountAdd($params)
    {
        try {
            $this->api->customersCreate(
                array(
                    'externalId'      => $params['newCustomer']->id,
                    'firstName'       => $params['newCustomer']->firstname,
                    'lastName'        => $params['newCustomer']->lastname,
                    'email'           => $params['newCustomer']->email,
                    'createdAt'       => $params['newCustomer']->date_add
                )
            );
        }
        catch (CurlException $e) {
            error_log('customerCreate: connection error', 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
        }
        catch (InvalidJsonException $e) {
            error_log('customerCreate: ' . $e->getMessage(), 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
        }
    }

    public function hookNewOrder($params)
    {
        return $this->hookActionOrderStatusPostUpdate($params);
    }

    public function hookActionPaymentConfirmation($params)
    {
        $this->api->ordersEdit(
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
        $sql = 'SELECT * FROM '._DB_PREFIX_.'address WHERE id_address='.(int) $address_id;
        $address = Db::getInstance()->ExecuteS($sql);
        $address = $address[0];
        $delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'));
        $payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'));
        $inCart = $params['cart']->getProducts();

        if (isset($params['orderStatus'])) {
            try {
                $this->api->customersCreate(
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
            catch (CurlException $e) {
                error_log("customerCreate: connection error", 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
            }
            catch (InvalidJsonException $e) {
                error_log('customerCreate: ' . $e->getMessage(), 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
            }

            try {
                $items = array();
                foreach ($inCart as $item) {
                    $items[] = array(
                        'initialPrice' => (!empty($item['rate'])) ? $item['price'] + ($item['price'] * $item['rate'] / 100) : $item['price'],
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

                $this->api->ordersCreate(
                    array(
                        'externalId'      => $params['order']->id,
                        'orderType'       => 'eshop-individual',
                        'orderMethod'     => 'shopping-cart',
                        'status'          => 'new',
                        'customerId'      => $params['cart']->id_customer,
                        'firstName'       => $params['customer']->firstname,
                        'lastName'        => $params['customer']->lastname,
                        'phone'           => $address['phone'],
                        'email'           => $params['customer']->email,
                        'paymentType'     => $payment->$pTypeKey,
                        'delivery'        => array(
                            'code' => $delivery->$dTypeKey,
                            'cost' => $params['order']->total_shipping,
                            'address' => array(
                                'city'    => $address['city'],
                                'index'   => $address['postcode'],
                                'text'    => $address['address1'],
                            )
                        ),
                        'discount'        => $params['order']->total_discounts,
                        'items'           => $items,
                        'createdAt'       => $params['order']->date_add
                    )
                );
            }
            catch (CurlException $e) {
                error_log('orderCreate: connection error', 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
            }
            catch (InvalidJsonException $e) {
                error_log('orderCreate: ' . $e->getMessage(), 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
            }

        }

        if (!empty($params['newOrderStatus'])) {
            $statuses = OrderState::getOrderStates($this->default_lang);
            $aStatuses = json_decode(Configuration::get('RETAILCRM_API_STATUS'));
            foreach ($statuses as $status) {
                if ($status['name'] == $params['newOrderStatus']->name) {
                    $currStatus = $status['id_order_state'];
                    try {
                        $this->api->ordersEdit(
                            array(
                                'externalId'      => $params['id_order'],
                                'status'          => $aStatuses->$currStatus
                            )
                        );
                    }
                    catch (CurlException $e) {
                        error_log('orderStatusUpdate: connection error', 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
                    }
                    catch (InvalidJsonException $e) {
                        error_log('orderStatusUpdate: ' . $e->getMessage(), 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
                    }
                }
            }
        }
    }
}
