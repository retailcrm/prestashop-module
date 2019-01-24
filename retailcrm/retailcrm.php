<?php
/**
 * @author Retail Driver LCC
 * @copyright RetailCRM
 * @license GPL
 * @version 2.2.10
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
    public $api = false;
    public $default_lang;
    public $default_currency;
    public $default_country;
    public $apiUrl;
    public $apiKey;
    public $apiVersion;
    public $psVersion;
    public $log;
    public $confirmUninstall;
    public $reference;

    private $use_new_hooks = true;

    public function __construct()
    {
        $this->name = 'retailcrm';
        $this->tab = 'export';
        $this->version = '2.2.10';
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
            $this->registerHook('header') &&
            ($this->use_new_hooks ? $this->registerHook('actionCustomerAccountUpdate') : true) &&
            ($this->use_new_hooks ? $this->registerHook('actionValidateCustomerAddressForm') : true)
        );
    }

    public function hookHeader()
    {
        if (Configuration::get('RETAILCRM_DAEMON_COLLECTOR_ACTIVE')
            && Configuration::get('RETAILCRM_DAEMON_COLLECTOR_KEY')
        ) {
            $collector = new RetailcrmDaemonCollector(
                $this->context->customer,
                Configuration::get('RETAILCRM_DAEMON_COLLECTOR_KEY')
            );

            return $collector->buildScript()->getJs();
        }
    }

    public function uninstall()
    {
        $api = new RetailcrmProxy(
            Configuration::get('RETAILCRM_ADDRESS'),
            Configuration::get('RETAILCRM_API_TOKEN'),
            _PS_ROOT_DIR_ . '/retailcrm.log',
            Configuration::get('RETAILCRM_API_VERSION')
        );

        $clientId = Configuration::get('RETAILCRM_CLIENT_ID');
        $this->integrationModule($api, $clientId, Configuration::get('RETAILCRM_API_VERSION'), false);

        return parent::uninstall() &&
        Configuration::deleteByName('RETAILCRM_ADDRESS') &&
        Configuration::deleteByName('RETAILCRM_API_TOKEN') &&
        Configuration::deleteByName('RETAILCRM_API_STATUS') &&
        Configuration::deleteByName('RETAILCRM_API_DELIVERY') &&
        Configuration::deleteByName('RETAILCRM_LAST_SYNC') &&
        Configuration::deleteByName('RETAILCRM_API_VERSION') &&
        Configuration::deleteByName('RETAILCRM_LAST_CUSTOMERS_SYNC') &&
        Configuration::deleteByName('RETAILCRM_LAST_ORDERS_SYNC');
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
            $statusExport = (string)(Tools::getValue('RETAILCRM_STATUS_EXPORT'));
            $collectorActive = (Tools::getValue('RETAILCRM_DAEMON_COLLECTOR_ACTIVE_1'));
            $collectorKey = (string)(Tools::getValue('RETAILCRM_DAEMON_COLLECTOR_KEY'));
            $clientId = Configuration::get('RETAILCRM_CLIENT_ID');

            $settings  = array(
                'address' => $address,
                'token' => $token,
                'version' => $version,
                'clientId' => $clientId
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
                Configuration::updateValue('RETAILCRM_STATUS_EXPORT', $statusExport);
                Configuration::updateValue('RETAILCRM_DAEMON_COLLECTOR_ACTIVE', $collectorActive);
                Configuration::updateValue('RETAILCRM_DAEMON_COLLECTOR_KEY', $collectorKey);

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
            "<a target=\"_blank\" href=\"$address/admin/settings#t-main\">$address/admin/settings#t-main</a>"
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
        $fields_form[]['form'] = array(
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

        /*
         * Daemon Collector
         */
        $fields_form[]['form'] = array(
            'legend' => array('title' => $this->l('Daemon Collector')),
            'input' => array(
                array(
                    'type' => 'checkbox',
                    'label' => $this->l('Activate'),
                    'name' => 'RETAILCRM_DAEMON_COLLECTOR_ACTIVE',
                    'values'  => array(
                        'query' => array(
                              array(
                                'id_option' => 1,
                              )
                          ),
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Site key'),
                    'name' => 'RETAILCRM_DAEMON_COLLECTOR_KEY',
                    'size' => 20,
                    'required' => false
                )
            )
        );

        if ($this->api) {
            /*
             * Delivery
             */
            $fields_form[]['form'] = array(
                'legend' => array('title' => $this->l('Delivery')),
                'input' => $this->reference->getDeliveryTypes(),
            );

            /*
             * Order status
             */
            $fields_form[]['form'] = array(
                'legend' => array('title' => $this->l('Order statuses')),
                'input' => $this->reference->getStatuses(),
            );

            /*
             * Payment
             */
            $fields_form[]['form'] = array(
                'legend' => array('title' => $this->l('Payment types')),
                'input' => $this->reference->getPaymentTypes(),
            );

            /*
             * Default
             */
            $fields_form[]['form'] = array(
                'legend' => array('title' => $this->l('Default')),
                'input' => $this->reference->getPaymentAndDeliveryForDefault(
                    array($this->l('Delivery method'), $this->l('Payment type'))
                ),
            );

            /*
             * Status in export
             */
            $fields_form[]['form'] = array(
                'legend' => array('title' => $this->l('Default status')),
                'input' => array(array(
                    'type' => 'select',
                    'name' => 'RETAILCRM_STATUS_EXPORT',
                    'label' => $this->l('Default status in export'),
                    'options' => array(
                        'query' => $this->reference->getStatuseDefaultExport(),
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                )),
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
        $helper->fields_value['RETAILCRM_STATUS_EXPORT'] = Configuration::get('RETAILCRM_STATUS_EXPORT');
        $helper->fields_value['RETAILCRM_DAEMON_COLLECTOR_ACTIVE_1'] = Configuration::get('RETAILCRM_DAEMON_COLLECTOR_ACTIVE');
        $helper->fields_value['RETAILCRM_DAEMON_COLLECTOR_KEY'] = Configuration::get('RETAILCRM_DAEMON_COLLECTOR_KEY');

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
        $customer = $params['newCustomer'];
        $customerSend = array(
            'externalId' => $customer->id,
            'firstName' => $customer->firstname,
            'lastName' => $customer->lastname,
            'email' => $customer->email,
            'createdAt' => $customer->date_add
        );

        $this->api->customersCreate($customerSend);

        return $customerSend;
    }

    // this hook added in 1.7
    public function hookActionCustomerAccountUpdate($params)
    {
        $customer = $params['customer'];

        $customerSend = array(
            'externalId' => $customer->id,
            'firstName' => $customer->firstname,
            'lastName' => $customer->lastname,
            'email' => $customer->email,
            'birthday' => $customer->birthday
        );

        $addreses = $customer->getAddresses($this->default_lang);
        $address = array_shift($addreses);

        if (!empty($address)){

            if (is_object($address)) {
                $address = $this->addressParse($address);
            } else {
                $address = new Address($address['id_address']);
                $address = $this->addressParse($address);
            }

            $customerSend = array_merge($customerSend, $address['customer']);
        }

        if (isset($params['cart'])){
            $address = $this->addressParse($params['cart']);
            $customerSend = array_merge($customerSend, $address['customer']);
        }

        $customerSend = array_merge($customerSend, $address['customer']);

        $this->api->customersEdit($customerSend);

        return $customerSend;
    }

    // this hook added in 1.7
    public function hookActionValidateCustomerAddressForm($params)
    {
        $customer = new Customer($params['cart']->id_customer);
        $customerAddress = array('customer' => $customer, 'cart' => $params['cart']);

        return $this->hookActionCustomerAccountUpdate($customerAddress);
    }

    public function hookNewOrder($params)
    {
        return $this->hookActionOrderStatusPostUpdate($params);
    }

    public function hookActionPaymentConfirmation($params)
    {
        if ($this->apiVersion == 4) {
            $this->api->ordersEdit(
                array(
                    'externalId' => $params['id_order'],
                    'paymentStatus' => 'paid'
                )
            );
        }

        return $this->hookActionOrderStatusPostUpdate($params);
    }

    public function hookActionOrderEdited($params)
    {
        $order = array(
            'externalId' => $params['order']->id,
            'firstName' => $params['customer']->firstname,
            'lastName' => $params['customer']->lastname,
            'email' => $params['customer']->email,
            'createdAt' => $params['order']->date_add,
            'delivery' => array('cost' => $params['order']->total_shipping)
        );

        if ($this->apiVersion != 5) {
            $order['discount'] = $params['order']->total_discounts;
        } else {
            $order['discountManualAmount'] = $params['order']->total_discounts;
        }

        $orderdb = new Order($params['order']->id);

        $comment = $orderdb->getFirstMessage();
        if ($comment !== false) {
            $order['customerComment'] = $comment;
        }

        unset($comment);

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

        return $order;
    }

    private function addressParse($address)
    {
        if ($address instanceof Address) {
            $postcode = $address->postcode;
            $city = $address->city;
            $addres_line = sprintf("%s %s", $address->address1, $address->address2);
            $countryIso = CountryCore::getIsoById($address->id_country);
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

        if (!empty($countryIso)) {
            $order['countryIso'] = $countryIso;
            $customer['address']['countryIso'] = $countryIso;
        }

        $phones = $this->getPhone($address);
        $order = array_merge($order, $phones['order']);
        $customer = array_merge($customer, $phones['customer']);
        $addressArray = array('order' => $order, 'customer' => $customer);

        return $addressArray;
    }

    private function getPhone($address)
    {
        if (!empty($address->phone_mobile)){
            $order['phone'] = $address->phone_mobile;
            $customer['phones'][] = array('number'=> $address->phone_mobile);
        }

        if (!empty($address->phone)){
            $order['additionalPhone'] = $address->phone;
            $customer['phones'][] = array('number'=> $address->phone);
        }

        if (!isset($order['phone']) && !empty($order['additionalPhone'])){
            $order['phone'] = $order['additionalPhone'];
            unset($order['additionalPhone']);
        }

        $phonesArray = array('customer' => $customer, 'order' => $order);

        return $phonesArray;
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true);
        $payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true);
        $status = json_decode(Configuration::get('RETAILCRM_API_STATUS'), true);

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

            if ($this->apiVersion != 5) {
                $order['discount'] = $params['order']->total_discounts;
            } else {
                $order['discountManualAmount'] = $params['order']->total_discounts;
            }

            $cart = $params['cart'];

            $addressCollection = $cart->getAddressCollection();
            $address = array_shift($addressCollection);
            $address = $this->addressParse($address);

            $customer = array_merge($customer, $address['customer']);
            $order = array_merge($order, $address['order']);
            $comment = $params['order']->getFirstMessage();
            $order['delivery']['cost'] = $params['order']->total_shipping;

            if ($comment !== false) {
                $order['customerComment'] = $comment;
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

                        if ($arItem[0] && $arItem[1]) {
                            $arProp[$count]['name'] = trim($arItem[0]);
                            $arProp[$count]['value'] = trim($arItem[1]);
                        }

                        $count++;
                    }
                }

                $orderItem = array(
                    'initialPrice' => !empty($item['rate'])
                        ? $item['price'] + ($item['price'] * $item['rate'] / 100)
                        : $item['price'],
                    'quantity' => $item['quantity'],
                    'offer' => array('externalId' => $productId),
                    'productName' => $item['name']
                );

                if (isset($arProp)) {
                    $orderItem['properties'] = $arProp;
                }

                $order['items'][] = $orderItem;

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

            if ($this->apiVersion != 5) {
                if (array_key_exists($paymentCode, $payment) && !empty($payment[$paymentCode])) {
                    $order['paymentType'] = $payment[$paymentCode];
                }
            } else {
                $paymentSend = array(
                    'externalId' => $params['order']->id .'#'. $params['order']->reference,
                    'amount' => $params['order']->total_paid,
                    'type' => $payment[$paymentCode] ? $payment[$paymentCode] : ''
                );
            }

            if (isset($paymentSend)) {
                $order['payments'][] = $paymentSend;
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

            return $order;

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

                return $orderStatus;
            }
        }

        return false;
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

            return $updatePayment;
        } else {
            $createPayment = array(
                'externalId' => $params['paymentCC']->id,
                'amount'     => $params['paymentCC']->amount,
                'paidAt'     => $params['paymentCC']->date_add,
                'type'       => $payment,
                'status'     => 'paid',
                'order'      => array(
                    'externalId' => $order_id,
                ),
            );

            $this->api->ordersPaymentCreate($createPayment);

            return $createPayment;
        }

        return false;
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
            if (!$settings['clientId']) {
                $clientId = uniqid();
                $result = $this->integrationModule($api, $clientId, $settings['version']);

                if ($result) {
                    Configuration::updateValue('RETAILCRM_CLIENT_ID', $clientId);
                }
            }

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

    /**
     * Activate/deactivate module in marketplace retailCRM
     *
     * @param \RetailcrmProxy $apiClient
     * @param string $clientId
     * @param string $apiVersion
     * @param boolean $active
     *
     * @return boolean
     */
    private function integrationModule($apiClient, $clientId, $apiVersion, $active = true)
    {
        $scheme = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $logo = 'https://s3.eu-central-1.amazonaws.com/retailcrm-billing/images/5b845ce986911-prestashop2.svg';
        $integrationCode = 'prestashop';
        $name = 'PrestaShop';
        $accountUrl = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        if ($apiVersion == '4') {
            $configuration = array(
                'name' => $name,
                'code' => $integrationCode . '-' . $clientId,
                'logo' => $logo,
                'configurationUrl' => $accountUrl,
                'active' => $active
            );

            $response = $apiClient->marketplaceSettingsEdit($configuration);
        } else {
            $configuration = array(
                'clientId' => $clientId,
                'code' => $integrationCode . '-' . $clientId,
                'integrationCode' => $integrationCode,
                'active' => $active,
                'name' => $name,
                'logo' => $logo,
                'accountUrl' => $accountUrl
            );

            $response = $apiClient->integrationModulesEdit($configuration);
        }

        if (!$response) {
            return false;
        }

        if ($response->isSuccessful()) {
            return true;
        }

        return false;
    }
}
