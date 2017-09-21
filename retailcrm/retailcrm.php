<?php
/**
 * @author Retail Driver LCC
 * @copyright RetailCRM
 * @license GPL
 * @version 2.1.1
 * @link https://retailcrm.ru
 *
 */

if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
    date_default_timezone_set(@date_default_timezone_get());
}

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/bootstrap.php');

class RetailCRM extends Module
{

    public function __construct()
    {
        $this->name = 'retailcrm';
        $this->tab = 'export';
        $this->version = '2.1.1';
        $this->author = 'Retail Driver LCC';
        $this->displayName = $this->l('RetailCRM');
        $this->description = $this->l('Integration module for RetailCRM');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');
        $this->apiUrl = Configuration::get('RETAILCRM_ADDRESS');
        $this->apiKey = Configuration::get('RETAILCRM_API_TOKEN');
        $this->apiVersion = Configuration::get('RETAILCRM_API_VERSION');
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->psVersion = Tools::substr(_PS_VERSION_, 0, 3);
        $this->log = _PS_ROOT_DIR_ . '/retailcrm.log';
        $this->module_key = '149c765c6cddcf35e1f13ea6c71e9fa5';
        
        if ($this->psVersion == '1.6') {
            $this->bootstrap = true;
            $this->use_new_hooks = false;
        }

        if ($this->apiUrl && $this->apiKey) {
            $this->api = new RetailcrmProxy($this->apiUrl, $this->apiKey, $this->log, $this->apiVersion);
            $this->reference = new RetailcrmReferences($this->api);
        }

        parent::__construct();
    }

    public function install()
    {
        return (
            parent::install() &&
            $this->registerHook('newOrder') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('actionCustomerAccountAdd') &&
            $this->registerHook('actionOrderEdited') &&
            ($this->use_new_hooks ? $this->registerHook('actionCustomerAccountUpdate') : true)
        );
    }

    public function uninstall()
    {
        return parent::uninstall() &&
        Configuration::deleteByName('RETAILCRM_ADDRESS') &&
        Configuration::deleteByName('RETAILCRM_API_TOKEN') &&
        Configuration::deleteByName('RETAILCRM_API_STATUS') &&
        Configuration::deleteByName('RETAILCRM_API_DELIVERY') &&
        Configuration::deleteByName('RETAILCRM_LAST_SYNC') &&
        Configuration::deleteByName('RETAILCRM_API_VERSION');
    }

    public function getContent()
    {
        $output = null;
        $address = Configuration::get('RETAILCRM_ADDRESS');
        $token = Configuration::get('RETAILCRM_API_TOKEN');
        $version = Configuration::get('RETAILCRM_API_VERSION');

        if (Tools::isSubmit('submit' . $this->name)) {
            $address = (string)(Tools::getValue('RETAILCRM_ADDRESS'));
            $token = (string)(Tools::getValue('RETAILCRM_API_TOKEN'));
            $version = (string)(Tools::getValue('RETAILCRM_API_VERSION'));
            $delivery = json_encode(Tools::getValue('RETAILCRM_API_DELIVERY'));
            $status = json_encode(Tools::getValue('RETAILCRM_API_STATUS'));
            $payment = json_encode(Tools::getValue('RETAILCRM_API_PAYMENT'));
            $deliveryDefault = json_encode(Tools::getValue('RETAILCRM_API_DELIVERY_DEFAULT'));
            $paymentDefault = json_encode(Tools::getValue('RETAILCRM_API_PAYMENT_DEFAULT'));
            $settings  = array(
                'address' => $address,
                'token' => $token,
                'version' => $version
            );

            $output .= $this->validateForm($settings, $output);
            
            if ($output === '') {
                Configuration::updateValue('RETAILCRM_ADDRESS', $address);
                Configuration::updateValue('RETAILCRM_API_TOKEN', $token);
                Configuration::updateValue('RETAILCRM_API_VERSION', $version);
                Configuration::updateValue('RETAILCRM_API_DELIVERY', $delivery);
                Configuration::updateValue('RETAILCRM_API_STATUS', $status);
                Configuration::updateValue('RETAILCRM_API_PAYMENT', $payment);
                Configuration::updateValue('RETAILCRM_API_DELIVERY_DEFAULT', $deliveryDefault);
                Configuration::updateValue('RETAILCRM_API_PAYMENT_DEFAULT', $paymentDefault);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }

            if ($version == 5 && $this->isRegisteredInHook('actionPaymentCCAdd') == 0) {
                $this->registerHook('actionPaymentCCAdd');
            } elseif ($version == 4 && $this->isRegisteredInHook('actionPaymentCCAdd') == 1) {
                $hook_id = Hook::getIdByName('actionPaymentCCAdd');
                $this->unregisterHook($hook_id);
            }
        }

        if ($address && $token) {
            $this->api = new RetailcrmProxy($address, $token, $this->log, $version);
            $this->reference = new RetailcrmReferences($this->api);
        }
        
        $output .= $this->displayConfirmation(
            $this->l('Timezone settings must be identical to both of your crm and shop') .
            " <a target=\"_blank\" href=\"$address/admin/settings#t-main\">$address/admin/settings#t-main</a>"
        );
        
        $this->display(__FILE__, 'retailcrm.tpl');

        return $output . $this->displayForm();
    }

    public function displayForm()
    {

        $this->displayConfirmation($this->l('Settings updated'));

        $default_lang = $this->default_lang;
        $apiVersions = array(
            array(
                'option_id' => '4',
                'name' => 'v4'
            ),
            array(
                'option_id' => '5',
                'name' => 'v5'
            )
        );
        $fields_form = array();
        /*
         * Network connection form
         */
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Network connection'),
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'name' => 'RETAILCRM_API_VERSION',
                    'label' => $this->l('API version'),
                    'options' => array(
                      'query' => $apiVersions,
                      'id' => 'option_id',
                      'name' => 'name'
                    )
                ),
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


        if ($this->api) {
            /*
             * Delivery
             */
            $fields_form[1]['form'] = array(
                'legend' => array('title' => $this->l('Delivery')),
                'input' => $this->reference->getDeliveryTypes(),
            );

            /*
             * Order status
             */
            $fields_form[2]['form'] = array(
                'legend' => array('title' => $this->l('Order statuses')),
                'input' => $this->reference->getStatuses(),
            );

            /*
             * Payment
             */
            $fields_form[3]['form'] = array(
                'legend' => array('title' => $this->l('Payment types')),
                'input' => $this->reference->getPaymentTypes(),
            );

            /*
             * Default
             */
            $fields_form[4]['form'] = array(
                'legend' => array('title' => $this->l('Default')),
                'input' => $this->reference->getPaymentAndDeliveryForDefault(
                    array($this->l('Delivery method'), $this->l('Payment type'))
                ),
            );
        }

        /*
         * Diplay forms
         */

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => sprintf(
                        "%s&configure=%s&save%s&token=%s",
                        AdminController::$currentIndex,
                        $this->name,
                        $this->name,
                        Tools::getAdminTokenLite('AdminModules')
                    )
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $helper->fields_value['RETAILCRM_ADDRESS'] = Configuration::get('RETAILCRM_ADDRESS');
        $helper->fields_value['RETAILCRM_API_TOKEN'] = Configuration::get('RETAILCRM_API_TOKEN');
        $helper->fields_value['RETAILCRM_API_VERSION'] = Configuration::get('RETAILCRM_API_VERSION');

        $deliverySettings = Configuration::get('RETAILCRM_API_DELIVERY');
        if (isset($deliverySettings) && $deliverySettings != '') {
            $deliveryTypes = json_decode($deliverySettings);
            if ($deliveryTypes) {
                foreach ($deliveryTypes as $idx => $delivery) {
                    $name = 'RETAILCRM_API_DELIVERY[' . $idx . ']';
                    $helper->fields_value[$name] = $delivery;
                }
            }
        }

        $statusSettings = Configuration::get('RETAILCRM_API_STATUS');
        if (isset($statusSettings) && $statusSettings != '') {
            $statusTypes = json_decode($statusSettings);
            if ($statusTypes) {
                foreach ($statusTypes as $idx => $status) {
                    $name = 'RETAILCRM_API_STATUS[' . $idx . ']';
                    $helper->fields_value[$name] = $status;
                }
            }
        }

        $paymentSettings = Configuration::get('RETAILCRM_API_PAYMENT');
        if (isset($paymentSettings) && $paymentSettings != '') {
            $paymentTypes = json_decode($paymentSettings);
            if ($paymentTypes) {
                foreach ($paymentTypes as $idx => $payment) {
                    $name = 'RETAILCRM_API_PAYMENT[' . $idx . ']';
                    $helper->fields_value[$name] = $payment;
                }
            }
        }

        $paymentSettingsDefault = Configuration::get('RETAILCRM_API_PAYMENT_DEFAULT');
        if (isset($paymentSettingsDefault) && $paymentSettingsDefault != '') {
            $paymentTypesDefault = json_decode($paymentSettingsDefault);
            if ($paymentTypesDefault) {
                    $name = 'RETAILCRM_API_PAYMENT_DEFAULT';
                    $helper->fields_value[$name] = $paymentTypesDefault;
            }
        }

        $deliverySettingsDefault = Configuration::get('RETAILCRM_API_DELIVERY_DEFAULT');
        if (isset($deliverySettingsDefault) && $deliverySettingsDefault != '') {
            $deliveryTypesDefault = json_decode($deliverySettingsDefault);
            if ($deliveryTypesDefault) {
                $name = 'RETAILCRM_API_DELIVERY_DEFAULT';
                $helper->fields_value[$name] = $deliveryTypesDefault;
            }
        }

        return $helper->generateForm($fields_form);
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $this->api->customersCreate(
            array(
                'externalId' => $params['newCustomer']->id,
                'firstName' => $params['newCustomer']->firstname,
                'lastName' => $params['newCustomer']->lastname,
                'email' => $params['newCustomer']->email,
                'createdAt' => $params['newCustomer']->date_add
            )
        );
    }

    // this hook added in 1.7
    public function hookActionCustomerAccountUpdate($params)
    {
        $this->api->customersEdit(
            array(
                'externalId' => $params['customer']->id,
                'firstName' => $params['customer']->firstname,
                'lastName' => $params['customer']->lastname,
                'email' => $params['customer']->email,
                'birthday' => $params['customer']->birthday
            )
        );
    }

    public function hookNewOrder($params)
    {
        return $this->hookActionOrderStatusPostUpdate($params);
    }

    public function hookActionPaymentConfirmation($params)
    {
        $this->api->ordersEdit(
            array(
                'externalId' => $params['id_order'],
                'paymentStatus' => 'paid'
            )
        );

        return $this->hookActionOrderStatusPostUpdate($params);
    }

    public function hookActionOrderEdited($params)
    {
        $apiVersion = Configuration::get('RETAILCRM_API_VERSION');
        $order = array(
            'externalId' => $params['order']->id,
            'firstName' => $params['customer']->firstname,
            'lastName' => $params['customer']->lastname,
            'email' => $params['customer']->email,
            'createdAt' => $params['order']->date_add,
            'delivery' => array('cost' => $params['order']->total_shipping)
        );

        if ($apiVersion != 5) {
            $order['discount'] = $params['order']->total_discounts;
        } else {
            $order['discountManualAmount'] = $params['order']->total_discounts;
        }

        $orderdb = new Order($params['order']->id);
        foreach ($orderdb->getProducts() as $item) {
            if (isset($item['product_attribute_id']) && $item['product_attribute_id'] > 0) {
                $productId = $item['product_id'] . '#' . $item['product_attribute_id'];
            } else {
                $productId = $item['product_id'];
            }

            $order['items'][] = array(
                'initialPrice' => $item['unit_price_tax_incl'],
                'quantity' => $item['product_quantity'],
                'offer' => array('externalId' => $productId),
                'productName' => $item['product_name'],
            );
        }

        $order['customer']['externalId'] = $params['order']->id_customer;
        $this->api->ordersEdit($order);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true);
        $payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true);
        $status = json_decode(Configuration::get('RETAILCRM_API_STATUS'), true);
        $apiVersion = Configuration::get('RETAILCRM_API_VERSION');

        if (isset($params['orderStatus'])) {
            $customer = array(
                'externalId' => $params['customer']->id,
                'lastName' => $params['customer']->lastname,
                'firstName' => $params['customer']->firstname,
                'email' => $params['customer']->email,
                'createdAt' => $params['customer']->date_add
            );

            $order = array(
                'externalId' => $params['order']->id,
                'firstName' => $params['customer']->firstname,
                'lastName' => $params['customer']->lastname,
                'email' => $params['customer']->email,
                'createdAt' => $params['order']->date_add,
                'delivery' => array('cost' => $params['order']->total_shipping)
            );

            if ($apiVersion != 5) {
                $order['discount'] = $params['order']->total_discounts;
            } else {
                $order['discountManualAmount'] = $params['order']->total_discounts;
            }

            $cart = new Cart($params['cart']->id);
            $addressCollection = $cart->getAddressCollection();
            $address = array_shift($addressCollection);

            if ($address instanceof Address) {
                $phone = empty($address->phone)
                    ? empty($address->phone_mobile) ? '' : $address->phone_mobile
                    : $address->phone;

                $postcode = $address->postcode;
                $city = $address->city;
                $addres_line = sprintf("%s %s", $address->address1, $address->address2);
            }

            if (!empty($postcode)) {
                $customer['address']['index'] = $postcode;
                $order['delivery']['address']['index'] = $postcode;
            }

            if (!empty($city)) {
                $customer['address']['city'] = $city;
                $order['delivery']['address']['city'] = $city;
            }

            if (!empty($addres_line)) {
                $customer['address']['text'] = $addres_line;
                $order['delivery']['address']['text'] = $addres_line;
            }

            if (!empty($phone)) {
                $customer['phones'][] = array('number' => $phone);
                $order['phone'] = $phone;
            }
            
            foreach ($cart->getProducts() as $item) {
                if (isset($item['id_product_attribute']) && $item['id_product_attribute'] > 0) {
                    $productId = $item['id_product'] . '#' . $item['id_product_attribute'];
                } else {
                    $productId = $item['id_product'];
                }

                if ($item['attributes']) {
                    $arProp = array();
                    $count = 0;
                    $arAttr = explode(",", $item['attributes']);
                    foreach ($arAttr as $valAttr) {
                        $arItem = explode(":", $valAttr);
                        $arProp[$count]['name'] = trim($arItem[0]);
                        $arProp[$count]['value'] = trim($arItem[1]);
                        $count++;
                    }
                }

                $order['items'][] = array(
                    'initialPrice' => !empty($item['rate'])
                        ? $item['price'] + ($item['price'] * $item['rate'] / 100)
                        : $item['price'],
                    'quantity' => $item['quantity'],
                    'offer' => array('externalId' => $productId),
                    'productName' => $item['name'],
                    'properties' => $arProp
                );

                unset($arAttr);
                unset($count);
                unset($arProp);
            }

            $deliveryCode = $params['order']->id_carrier;

            if (array_key_exists($deliveryCode, $delivery) && !empty($delivery[$deliveryCode])) {
                $order['delivery']['code'] = $delivery[$deliveryCode];
            }

            if (Module::getInstanceByName('advancedcheckout') === false) {
                $paymentCode = $params['order']->module;
            } else {
                $paymentCode = $params['order']->payment;
            }

            if ($apiVersion != 5) {
                if (array_key_exists($paymentCode, $payment) && !empty($payment[$paymentCode])) {
                    $order['paymentType'] = $payment[$paymentCode];
                }
            } else {
                $payment = array(
                    'externalId' => $params['order']->id .'#'. $params['order']->reference,
                    'amount' => $params['order']->total_paid,
                    'type' => $payment[$paymentCode] ? $payment[$paymentCode] : ''
                );
            }

            if (isset($payment)) {
                $order['payments'][] = $payment;
            }

            $statusCode = $params['orderStatus']->id;

            if (array_key_exists($statusCode, $status) && !empty($status[$statusCode])) {
                $order['status'] = $status[$statusCode];
            } else {
                $order['status'] = 'new';
            }

            $customerCheck = $this->api->customersGet($customer['externalId']);

            if ($customerCheck === false) {
                $this->api->customersCreate($customer);
            }

            $order['customer']['externalId'] = $customer['externalId'];
            
            $this->api->ordersCreate($order);
        } elseif (isset($params['newOrderStatus'])) {
            $statusCode = $params['newOrderStatus']->id;

            if (array_key_exists($statusCode, $status) && !empty($status[$statusCode])) {
                $orderStatus = $status[$statusCode];
            }

            if (isset($orderStatus)) {
                $this->api->ordersEdit(
                    array(
                        'externalId' => $params['id_order'],
                        'status' => $orderStatus
                    )
                );
            }
        }
    }

    public function hookActionPaymentCCAdd($params)
    {
        $order_id = Order::getOrderByCartId($params['cart']->id);
        $payments = $this->reference->getSystemPaymentModules();
        $paymentCRM = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true);

        foreach ($payments as $valPay) {
            if ($valPay['name'] == $params['paymentCC']->payment_method) {
                $payCode = $valPay['code'];
            }
        }

        if (array_key_exists($payCode, $paymentCRM) && !empty($paymentCRM[$payCode])) {
            $payment = $paymentCRM[$payCode];
        }
        
        $response = $this->api->ordersGet($order_id);

        if ($response !== false) {
            $orderCRM = $response['order'];
            if ($orderCRM && $orderCRM['payments']) {
                foreach ($orderCRM['payments'] as $orderPayment) {
                    if ($orderPayment['type'] == $payment) {
                        $updatePayment = $orderPayment;
                        $updatePayment['amount'] = $params['paymentCC']->amount;
                        $updatePayment['paidAt'] = $params['paymentCC']->date_add;
                        if ($params['paymentCC']->amount == $orderCRM['totalSumm']) {
                            $updatePayment['status'] = 'paid';
                        }
                    }
                }
            }
        }

        if (isset($updatePayment)) {
            $this->api->ordersPaymentEdit($updatePayment);
        } else {
            $this->api->ordersPaymentCreate(
                array(
                    'externalId' => $params['paymentCC']->id,
                    'amount'     => $params['paymentCC']->amount,
                    'paidAt'     => $params['paymentCC']->date_add,
                    'type'       => $payment,
                    'status'     => 'paid',
                    'order'      => array(
                        'externalId' => $order_id,
                    ),
                )
            );
        }
        return true;
    }

    private function validateCrmAddress($address)
    {
        if (preg_match("/https:\/\/(.*).retailcrm.ru/", $address) === 1) {
            return true;
        }

        return false;
    }

    private function validateApiVersion($settings)
    {
        $api = new RetailcrmProxy(
            $settings['address'],
            $settings['token'],
            _PS_ROOT_DIR_ . '/retailcrm.log',
            $settings['version']
        );
        $response = $api->deliveryTypesList();

        if ($response !== false) {
            return true;
        }

        return false;
    }

    private function validateForm($settings, $output)
    {
        if (!$this->validateCrmAddress($settings['address']) || !Validate::isGenericName($settings['address'])) {
            $output .= $this->displayError($this->l('Invalid or empty crm address'));
        } elseif (!$settings['token'] || $settings['token'] == '') {
            $output .= $this->displayError($this->l('Invalid or empty crm api token'));
        } elseif (!$this->validateApiVersion($settings)) {
            $output .= $this->displayError($this->l('The selected version of the API is unavailable'));
        }

        return $output;
    }
}
