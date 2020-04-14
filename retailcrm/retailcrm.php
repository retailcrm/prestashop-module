<?php
/**
 * @author Retail Driver LCC
 * @copyright RetailCRM
 * @license GPL
 * @version 2.2.11
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
    public $assetsBase;

    private $use_new_hooks = true;

    public function __construct()
    {
        $this->name = 'retailcrm';
        $this->tab = 'export';
        $this->version = '2.5.1';
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
        $this->assetsBase =
            Tools::getShopDomainSsl(true, true) .
            __PS_BASE_URI__ .
            'modules/' .
            $this->name .
            '/public';

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
        $this->context->controller->addJS($this->assetsBase . '/js/exec-jobs.js');

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
            Configuration::deleteByName('RETAILCRM_API_SYNCHRONIZE_CARTS') &&
            Configuration::deleteByName('RETAILCRM_API_SYNCHRONIZED_CART_STATUS') &&
            Configuration::deleteByName('RETAILCRM_API_SYNCHRONIZED_CART_DELAY') &&
            Configuration::deleteByName('RETAILCRM_LAST_ORDERS_SYNC');
    }

    public function getContent()
    {
        $output = null;
        $address = Configuration::get('RETAILCRM_ADDRESS');
        $token = Configuration::get('RETAILCRM_API_TOKEN');
        $version = Configuration::get('RETAILCRM_API_VERSION');

        if (Tools::isSubmit('submit' . $this->name)) {
            $ordersIds = (string)(Tools::getValue('RETAILCRM_UPLOAD_ORDERS_ID'));

            if (!empty($ordersIds)) {
                $output .= $this->uploadOrders(static::partitionId($ordersIds));
            } else {
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
                $synchronizeCartsActive = (Tools::getValue('RETAILCRM_API_SYNCHRONIZE_CARTS_1'));
                $synchronizedCartStatus = (string)(Tools::getValue('RETAILCRM_API_SYNCHRONIZED_CART_STATUS'));
                $synchronizedCartDelay = (string)(Tools::getValue('RETAILCRM_API_SYNCHRONIZED_CART_DELAY'));

                $settings  = array(
                    'address' => $address,
                    'token' => $token,
                    'version' => $version,
                    'clientId' => $clientId,
                    'status' => $status,
                    'statusExport' => $statusExport,
                    'synchronizeCartStatus' => $synchronizedCartStatus
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
                    Configuration::updateValue('RETAILCRM_API_SYNCHRONIZE_CARTS', $synchronizeCartsActive);
                    Configuration::updateValue('RETAILCRM_API_SYNCHRONIZED_CART_STATUS', $synchronizedCartStatus);
                    Configuration::updateValue('RETAILCRM_API_SYNCHRONIZED_CART_DELAY', $synchronizedCartDelay);

                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }

                if ($version == 5 && $this->isRegisteredInHook('actionPaymentCCAdd') == 0) {
                    $this->registerHook('actionPaymentCCAdd');
                } elseif ($version == 4 && $this->isRegisteredInHook('actionPaymentCCAdd') == 1) {
                    $hook_id = Hook::getIdByName('actionPaymentCCAdd');
                    $this->unregisterHook($hook_id);
                }
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

        $this->context->controller->addCSS($this->assetsBase . '/css/retailcrm-upload.css');
        $this->context->controller->addJS($this->assetsBase . '/js/retailcrm-upload.js');
        $this->context->controller->addJS($this->assetsBase . '/js/exec-jobs.js');
        $this->display(__FILE__, 'retailcrm.tpl');

        return $output . $this->displaySettingsForm() . $this->displayUploadOrdersForm();
    }

    public function uploadOrders($orderIds)
    {
        if (count($orderIds) > 10) {
            return $this->displayConfirmation($this->l("Can't upload more than 10 orders per request"));
        }

        if (count($orderIds) < 1) {
            return $this->displayConfirmation($this->l("At least one order ID should be specified"));
        }

        $apiUrl = Configuration::get('RETAILCRM_ADDRESS');
        $apiKey = Configuration::get('RETAILCRM_API_TOKEN');
        $apiVersion = Configuration::get('RETAILCRM_API_VERSION');

        if (!empty($apiUrl) && !empty($apiKey)) {
            if (!($this->api instanceof RetailcrmProxy)) {
                $this->api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log', $apiVersion);
            }
        } else {
            return $this->displayError($this->l("Can't upload orders - set API key and API URL first!"));
        }

        $orders = array();
        $customers = array();
        $isSuccessful = true;

        foreach ($orderIds as $orderId) {
            $object = new Order($orderId);
            $customer = new Customer($object->id_customer);

            array_push($customers, static::buildCrmCustomer($customer));
            array_push($orders, static::buildCrmOrder($object, $customer, null, true));
        }

        foreach ($customers as $item) {
            if ($this->api->customersGet($item['externalId']) === false) {
                $this->api->customersCreate($item);
                time_nanosleep(0, 50000000);
            }
        }

        foreach ($orders as $item) {
            if ($this->api->ordersGet($item['externalId']) === false) {
                $response = $this->api->ordersCreate($item);
            } else {
                $response = $this->api->ordersEdit($item);
            }

            $isSuccessful = is_bool($response) ? $response : $response->isSuccessful();

            time_nanosleep(0, 50000000);
        }

        if ($isSuccessful) {
            return $this->displayConfirmation($this->l('All orders were uploaded successfully'));
        } else {
            $result = $this->displayWarning($this->l('Not all orders were uploaded successfully'));

            foreach (RetailcrmApiErrors::getErrors() as $error) {
                $result .= $this->displayError($error);
            }

            return $result;
        }
    }

    /**
     * Returns 'true' if provided date string is valid
     *
     * @param $date
     * @param string $format
     *
     * @return bool
     */
    public static function verifyDate($date, $format = "Y-m-d")
    {
        return $date !== "0000-00-00" && (bool)date_create_from_format($format, $date);
    }

    /**
     * Build array with order data for retailCRM from PrestaShop cart data
     *
     * @param Cart   $cart        Cart with data
     * @param string $externalId  External ID for order
     * @param string $paymentType Payment type (buildCrmOrder requires it)
     * @param string $status      Status for order
     *
     * @return array
     */
    public static function buildCrmOrderFromCart(Cart $cart = null, $externalId = '', $paymentType = '', $status = '')
    {
        if (empty($cart) || empty($paymentType) || empty($status)) {
            return array();
        }

        $order = new Order();
        $order->id_cart = $cart->id;
        $order->id_customer = $cart->id_customer;
        $order->total_discounts = 0;
        $order->module = $paymentType;
        $order->payment = $paymentType;
        $orderData = static::buildCrmOrder(
            $order,
            new Customer($cart->id_customer),
            $cart,
            false,
            true,
            true
        );
        $orderData['externalId'] = $externalId;
        $orderData['status'] = $status;

        unset($orderData['payments']);

        return $orderData;
    }

    /**
     * Build array with order data for retailCRM from PrestaShop order data
     *
     * @param Order    $order                 PrestaShop Order
     * @param Customer $customer              PrestaShop Customer
     * @param Cart     $orderCart             Cart for provided order. Optional
     * @param bool     $isStatusExport        Use status for export
     * @param bool     $preferCustomerAddress Use customer address even if delivery address is provided
     * @param bool     $dataFromCart          Prefer data from cart
     *
     * @return array   retailCRM order data
     */
    public static function buildCrmOrder(
        Order $order,
        Customer $customer = null,
        Cart $orderCart = null,
        $isStatusExport = false,
        $preferCustomerAddress = false,
        $dataFromCart = false
    ) {
        $apiVersion = Configuration::get('RETAILCRM_API_VERSION');
        $statusExport = Configuration::get('RETAILCRM_STATUS_EXPORT');
        $delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true);
        $payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true);
        $status = json_decode(Configuration::get('RETAILCRM_API_STATUS'), true);

        if (Module::getInstanceByName('advancedcheckout') === false) {
            $paymentType = $order->module;
        } else {
            $paymentType = $order->payment;
        }

        if ($order->current_state == 0) {
            $order_status = $statusExport;

            if (!$isStatusExport) {
                $order_status =
                    array_key_exists($order->current_state, $status)
                        ? $status[$order->current_state] : 'new';
            }
        } else {
            $order_status = array_key_exists($order->current_state, $status)
                ? $status[$order->current_state]
                : $statusExport
            ;
        }

        $phone = '';
        $cart = $orderCart;

        if (is_null($cart)) {
            $cart = new Cart($order->getCartIdStatic($order->id));
        }

        if (is_null($customer)) {
            $customer = new Customer($order->id_customer);
        }

        $crmOrder = array_filter(array(
            'externalId' => $order->id,
            'createdAt' => static::verifyDate($order->date_add, 'Y-m-d H:i:s')
                ? $order->date_add : date('Y-m-d H:i:s'),
            'status' => $order_status,
            'firstName' => $customer->firstname,
            'lastName' => $customer->lastname,
            'email' => $customer->email,
        ));

        $addressCollection = $cart->getAddressCollection();
        $address = new Address($order->id_address_delivery);

        if (is_null($address->id) || $preferCustomerAddress === true) {
            $address = array_filter(
                $addressCollection,
                function ($v) use ($customer) {
                    return $v->id_customer == $customer->id;
                }
            );

            if (is_array($address) && count($address) == 1) {
                $address = reset($address);
            }
        }

        $address = static::addressParse($address);
        $crmOrder = array_merge($crmOrder, $address['order']);

        if ($phone) {
            $crmOrder['phone'] = $phone;
        }

        if ($apiVersion != 5) {
            if (array_key_exists($paymentType, $payment) && !empty($payment[$paymentType])) {
                $crmOrder['paymentType'] = $payment[$paymentType];
            }

            $crmOrder['discount'] = round($order->total_discounts, 2);
        } else {
            $order_payment = array(
                'externalId' => $order->id .'#'. $order->reference,
                'amount' => round($order->total_paid, 2),
                'type' => $payment[$paymentType] ? $payment[$paymentType] : ''
            );

            $crmOrder['discountManualAmount'] = round($order->total_discounts, 2);
        }

        if (isset($order_payment)) {
            $crmOrder['payments'][] = $order_payment;
        } else {
            $crmOrder['payments'] = array();
        }

        $idCarrier = $dataFromCart ? $cart->id_carrier : $order->id_carrier;

        if (empty($idCarrier)) {
            $idCarrier = $order->id_carrier;
            $totalShipping = $order->total_shipping;
            $totalShippingWithoutTax = $order->total_shipping_tax_excl;
        } else {
            $totalShipping = $dataFromCart ? $cart->getCarrierCost($idCarrier) : $order->total_shipping;

            if (!empty($totalShipping) && $totalShipping != 0) {
                $totalShippingWithoutTax = $dataFromCart
                    ? $totalShipping - $cart->getCarrierCost($idCarrier, false)
                    : $order->total_shipping_tax_excl;
            } else {
                $totalShippingWithoutTax = $order->total_shipping_tax_excl;
            }
        }

        if (array_key_exists($idCarrier, $delivery) && !empty($delivery[$idCarrier])) {
            $crmOrder['delivery']['code'] = $delivery[$idCarrier];
        }

        if (isset($totalShipping) && ((int) $totalShipping) > 0) {
            $crmOrder['delivery']['cost'] = round($totalShipping, 2);
        }

        if (isset($totalShippingWithoutTax) && $totalShippingWithoutTax > 0) {
            $crmOrder['delivery']['netCost'] = round($totalShippingWithoutTax, 2);
        }

        $comment = $order->getFirstMessage();

        if ($comment !== false) {
            $crmOrder['customerComment'] = $comment;
        }

        if ($dataFromCart) {
            $productStore = $cart;
            $converter = function ($product) {
                $product['product_attribute_id'] = $product['id_product_attribute'];
                $product['product_quantity'] = $product['cart_quantity'];
                $product['product_id'] = $product['id_product'];
                $product['id_order_detail'] = $product['id_product'];
                $product['product_name'] = $product['name'];
                $product['product_price'] = $product['price'];
                $product['purchase_supplier_price'] = $product['price'];
                $product['product_price_wt'] = $product['price_wt'];

                return $product;
            };
        } else {
            $productStore = $order;
            $converter = function ($product) {
                return $product;
            };
        }

        foreach ($productStore->getProducts() as $productData) {
            $product = $converter($productData);

            if (isset($product['product_attribute_id']) && $product['product_attribute_id'] > 0) {
                $productId = $product['product_id'] . '#' . $product['product_attribute_id'];
            } else {
                $productId = $product['product_id'];
            }

            if (isset($product['attributes']) && $product['attributes']) {
                $arProp = array();
                $count = 0;
                $arAttr = explode(",", $product['attributes']);

                foreach ($arAttr as $valAttr) {
                    $arItem = explode(":", $valAttr);

                    if ($arItem[0] && $arItem[1]) {
                        $arProp[$count]['name'] = trim($arItem[0]);
                        $arProp[$count]['value'] = trim($arItem[1]);
                    }

                    $count++;
                }
            }

            $item = array(
                "externalIds" => array(
                    array(
                        'code' =>'prestashop',
                        'value' => $productId."_".$product['id_order_detail'],
                    ),
                ),
                'offer' => array('externalId' => $productId),
                'productName' => $product['product_name'],
                'quantity' => $product['product_quantity'],
                'initialPrice' => round($product['product_price'], 2),
                /*'initialPrice' => !empty($item['rate'])
                    ? $item['price'] + ($item['price'] * $item['rate'] / 100)
                    : $item['price'],*/
                'purchasePrice' => round($product['purchase_supplier_price'], 2)
            );

            if (true == Configuration::get('PS_TAX')) {
                $item['initialPrice'] = round($product['product_price_wt'], 2);
            }

            if (isset($arProp)) {
                $item['properties'] = $arProp;
            }

            $crmOrder['items'][] = $item;
        }

        if ($order->id_customer) {
            $crmOrder['customer']['externalId'] = $order->id_customer;
        }

        return $crmOrder;
    }

    /**
     * Builds retailCRM customer data from PrestaShop customer data
     *
     * @param Customer $object
     * @param array $address
     *
     * @return array
     */
    public static function buildCrmCustomer(Customer $object, $address = array())
    {
        return array_merge(
            array(
                'externalId' => $object->id,
                'firstName' => $object->firstname,
                'lastName' => $object->lastname,
                'email' => $object->email,
                'createdAt' => self::verifyDate($object->date_add, 'Y-m-d H:i:s')
                    ? $object->date_add : date('Y-m-d H:i:s'),
                'birthday' => self::verifyDate($object->birthday, 'Y-m-d')
                    ? $object->birthday : date('Y-m-d', 0)
            ),
            $address
        );
    }

    /**
     * Split a string to id
     *
     * @param string $ids string with id
     *
     * @return array|string
     */
    public static function partitionId($ids)
    {
        $ids = explode(',', $ids);

        $ranges = [];

        foreach ($ids as $idx => $uid) {
            if (strpos($uid, '-')) {
                $range = explode('-', $uid);
                $ranges = array_merge($ranges, range($range[0], $range[1]));
                unset($ids[$idx]);
            }
        }

        $ids = implode(',', array_merge($ids, $ranges));
        $ids = explode(',', $ids);

        return $ids;
    }

    public function displaySettingsForm()
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
             * Synchronize carts form
             */
            $fields_form[]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Synchronization of buyer carts'),
                ),
                'input' => array(
                    array(
                        'type' => 'checkbox',
                        'label' => $this->l('Create orders for abandoned carts of buyers'),
                        'name' => 'RETAILCRM_API_SYNCHRONIZE_CARTS',
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
                        'type' => 'select',
                        'name' => 'RETAILCRM_API_SYNCHRONIZED_CART_STATUS',
                        'label' => $this->l('Order status for abandoned carts of buyers'),
                        'options' => array(
                            'query' => $this->reference->getStatuseDefaultExport(),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'RETAILCRM_API_SYNCHRONIZED_CART_DELAY',
                        'label' => $this->l('Upload abandoned carts'),
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => '0',
                                    'name' => $this->l('Immediately')
                                ),
                                array(
                                    'id_option' => '60',
                                    'name' => $this->l('After 1 minute')
                                ),
                                array(
                                    'id_option' => '300',
                                    'name' => $this->l('After 5 minutes')
                                ),
                                array(
                                    'id_option' => '600',
                                    'name' => $this->l('After 10 minutes')
                                ),
                                array(
                                    'id_option' => '900',
                                    'name' => $this->l('After 15 minutes')
                                ),
                                array(
                                    'id_option' => '1800',
                                    'name' => $this->l('After 30 minutes')
                                ),
                                array(
                                    'id_option' => '2700',
                                    'name' => $this->l('After 45 minute')
                                ),
                                array(
                                    'id_option' => '3600',
                                    'name' => $this->l('After 1 hour')
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    )
                )
            );

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
         * Display forms
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
        $helper->fields_value['RETAILCRM_API_SYNCHRONIZE_CARTS_1'] = Configuration::get('RETAILCRM_API_SYNCHRONIZE_CARTS');
        $helper->fields_value['RETAILCRM_API_SYNCHRONIZED_CART_STATUS'] = Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_STATUS');
        $helper->fields_value['RETAILCRM_API_SYNCHRONIZED_CART_DELAY'] = Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_DELAY');
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

        $synchronizedCartsStatusDefault = Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_STATUS');
        if (isset($synchronizedCartsStatusDefault) && $synchronizedCartsStatusDefault != '') {
            $synchronizedCartsStatus = json_decode($synchronizedCartsStatusDefault);
            if ($synchronizedCartsStatus) {
                $name = 'RETAILCRM_API_SYNCHRONIZED_CART_STATUS';
                $helper->fields_value[$name] = $synchronizedCartsStatus;
            }
        }

        $synchronizedCartsDelayDefault = Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_DELAY');
        if (isset($synchronizedCartsDelayDefault) && $synchronizedCartsDelayDefault != '') {
            $synchronizedCartsDelay = json_decode($synchronizedCartsDelayDefault);
            if ($synchronizedCartsDelay) {
                $name = 'RETAILCRM_API_SYNCHRONIZED_CART_DELAY';
                $helper->fields_value[$name] = $synchronizedCartsDelay;
            }
        }

        return $helper->generateForm($fields_form);
    }

    public function displayUploadOrdersForm()
    {
        $default_lang = $this->default_lang;
        $fields_form = array();

        if ($this->api) {
            $fields_form[]['form'] = array(
                'legend' => array('title' => $this->l('Manual Order Upload')),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Orders IDs'),
                        'name' => 'RETAILCRM_UPLOAD_ORDERS_ID',
                        'required' => false
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Upload'),
                    'class' => 'button'
                )
            );
        }

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->id = "retailcrm_upload_form";

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

        $helper->fields_value['RETAILCRM_UPLOAD_ORDERS_ID'] = '';

        return $helper->generateForm($fields_form);
    }

    public function hookActionCustomerAccountAdd($params)
    {
        if ($this->api) {
            $customer = $params['newCustomer'];
            $customerSend = static::buildCrmCustomer($customer);

            $this->api->customersCreate($customerSend);

            return true;
        }

        return false;
    }

    // this hook added in 1.7
    public function hookActionCustomerAccountUpdate($params)
    {
        if ($this->api) {
            $customer = $params['customer'];

            $customerSend = static::buildCrmCustomer($customer);

            $addreses = $customer->getAddresses($this->default_lang);
            $address = array_shift($addreses);

            if (!empty($address)){

                if (is_object($address)) {
                    $address = static::addressParse($address);
                } else {
                    $address = new Address($address['id_address']);
                    $address = static::addressParse($address);
                }

                $customerSend = array_merge($customerSend, $address['customer']);
            }

            if (isset($params['cart'])){
                $address = static::addressParse($params['cart']);
                $customerSend = array_merge($customerSend, $address['customer']);
            }

            $customerSend = array_merge($customerSend, isset($address['customer']) ? $address['customer'] : []);

            $this->api->customersEdit($customerSend);

            return true;
        }

        return false;
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
        if ($this->api) {
            $order = array(
                'externalId' => $params['order']->id,
                'firstName' => $params['customer']->firstname,
                'lastName' => $params['customer']->lastname,
                'email' => $params['customer']->email,
                'createdAt' => self::verifyDate($params['order']->date_add, 'Y-m-d H:i:s')
                    ? $params['order']->date_add : date('Y-m-d H:i:s'),
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
                    "externalIds" => array(
                        array(
                            'code' =>'prestashop',
                            'value' => $productId."_".$item['id_order_detail'],
                        )
                    ),
                    'initialPrice' => $item['unit_price_tax_incl'],
                    'quantity' => $item['product_quantity'],
                    'offer' => array('externalId' => $productId),
                    'productName' => $item['product_name'],
                );
            }

            $order['customer']['externalId'] = $params['order']->id_customer;
            $this->api->ordersEdit($order);

            return true;
        }

        return false;
    }

    private static function addressParse($address)
    {
        if (!isset($customer)) {
            $customer = [];
        }

        if (!isset($order)) {
            $order = [];
        }

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

        $phones = static::getPhone($address);
        $order = array_merge($order, $phones['order']);
        $customer = array_merge($customer, $phones['customer']);
        $addressArray = array('order' => $order, 'customer' => $customer);

        return $addressArray;
    }

    private static function getPhone($address)
    {
        if (!isset($customer)) {
            $customer = [];
        }

        if (!isset($order)) {
            $order = [];
        }

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
            $cart = $params['cart'];
            $response = $this->api->ordersGet(self::getCartOrderExternalId($cart));
            $order = static::buildCrmOrder($params['order'], $params['customer'], $cart, false);

            if (!empty($response) && isset($response['order'])) {
                $order['id'] = $response['order']['id'];
                $this->api->ordersEdit($order, 'id');
            } else {
                $this->api->ordersCreate($order);
            }

            return true;

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

                return true;
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

            return true;
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

            return true;
        }

        return false;
    }

    private function validateCrmAddress($address)
    {
        if (preg_match("/https:\/\/(.*).retailcrm.(pro|ru|es)/", $address) === 1) {
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

    private function validateStatuses($statuses, $statusExport, $cartStatus)
    {
        if ($cartStatus != '' && ($cartStatus == $statusExport || (stripos($statuses, $cartStatus) !== false))) {
            return false;
        }

        return true;
    }

    private function validateForm($settings, $output)
    {
        if (!$this->validateCrmAddress($settings['address']) || !Validate::isGenericName($settings['address'])) {
            $output .= $this->displayError($this->l('Invalid or empty crm address'));
        } elseif (!$settings['token'] || $settings['token'] == '') {
            $output .= $this->displayError($this->l('Invalid or empty crm api token'));
        } elseif (!$this->validateApiVersion($settings)) {
            $output .= $this->displayError($this->l('The selected version of the API is unavailable'));
        } elseif (!$this->validateStatuses(
            $settings['status'],
            $settings['statusExport'],
            $settings['synchronizeCartStatus'])
        ) {
            $output .= $this->displayError($this->l('Order status for abandoned carts should not be used in other settings'));
        }

        return $output;
    }

    /**
     * Returns externalId for order
     *
     * @param Cart $cart
     *
     * @return string
     */
    public static function getCartOrderExternalId(Cart $cart)
    {
        return sprintf('pscart_%d', $cart->id);
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
