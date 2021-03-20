<?php
/**
 * MIT License
 *
 * Copyright (c) 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
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
    const API_URL = 'RETAILCRM_ADDRESS';
    const API_KEY = 'RETAILCRM_API_TOKEN';
    const DELIVERY = 'RETAILCRM_API_DELIVERY';
    const STATUS = 'RETAILCRM_API_STATUS';
    const PAYMENT = 'RETAILCRM_API_PAYMENT';
    const DELIVERY_DEFAULT = 'RETAILCRM_API_DELIVERY_DEFAULT';
    const PAYMENT_DEFAULT = 'RETAILCRM_API_PAYMENT_DEFAULT';
    const STATUS_EXPORT = 'RETAILCRM_STATUS_EXPORT';
    const CLIENT_ID = 'RETAILCRM_CLIENT_ID';
    const COLLECTOR_ACTIVE = 'RETAILCRM_DAEMON_COLLECTOR_ACTIVE';
    const COLLECTOR_KEY = 'RETAILCRM_DAEMON_COLLECTOR_KEY';
    const SYNC_CARTS_ACTIVE = 'RETAILCRM_API_SYNCHRONIZE_CARTS';
    const SYNC_CARTS_STATUS = 'RETAILCRM_API_SYNCHRONIZED_CART_STATUS';
    const SYNC_CARTS_DELAY = 'RETAILCRM_API_SYNCHRONIZED_CART_DELAY';
    const UPLOAD_ORDERS = 'RETAILCRM_UPLOAD_ORDERS_ID';
    const MODULE_LIST_CACHE_CHECKSUM = 'RETAILCRM_MODULE_LIST_CACHE_CHECKSUM';
    const ENABLE_CORPORATE_CLIENTS = 'RETAILCRM_ENABLE_CORPORATE_CLIENTS';
    const ENABLE_HISTORY_UPLOADS = 'RETAILCRM_ENABLE_HISTORY_UPLOADS';
    const ENABLE_BALANCES_RECEIVING = 'RETAILCRM_ENABLE_BALANCES_RECEIVING';

    const LATEST_API_VERSION = '5';
    const CONSULTANT_SCRIPT = 'RETAILCRM_CONSULTANT_SCRIPT';
    const CONSULTANT_RCCT = 'RETAILCRM_CONSULTANT_RCCT';
    const ENABLE_WEB_JOBS = 'RETAILCRM_ENABLE_WEB_JOBS';

    /**
     * @var array $templateErrors
     */
    private $templateErrors;

    /**
     * @var array $templateWarnings
     */
    private $templateWarnings;

    /**
     * @var array $templateConfirms
     */
    private $templateConfirms;

    /**
     * @var array $templateInfos
     */
    private $templateInfos;

    /** @var bool|\RetailcrmApiClientV5 */
    public $api = false;
    public $default_lang;
    public $default_currency;
    public $default_country;
    public $apiUrl;
    public $apiKey;
    public $psVersion;
    public $log;
    public $confirmUninstall;

    /**
     * @var \RetailcrmReferences
     */
    public $reference;
    public $assetsBase;
    private static $moduleListCache;

    private $use_new_hooks = true;

    public function __construct()
    {
        $this->name = 'retailcrm';
        $this->tab = 'export';
        $this->version = '3.2.4';
        $this->author = 'DIGITAL RETAIL TECHNOLOGIES SL';
        $this->displayName = $this->l('retailCRM');
        $this->description = $this->l('Integration module for retailCRM');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        $this->apiUrl = Configuration::get(static::API_URL);
        $this->apiKey = Configuration::get(static::API_KEY);
        $this->ps_versions_compliancy = array('min' => '1.6.1.0', 'max' => _PS_VERSION_);
        $this->psVersion = Tools::substr(_PS_VERSION_, 0, 3);
        $this->log = RetailcrmLogger::getLogFile();
        $this->module_key = 'dff3095326546f5fe8995d9e86288491';
        $this->assetsBase =
            Tools::getShopDomainSsl(true, true) .
            __PS_BASE_URI__ .
            'modules/' .
            $this->name .
            '/views';

        if ($this->psVersion == '1.6') {
            $this->bootstrap = true;
            $this->use_new_hooks = false;
        }

        if ($this->apiUrl && $this->apiKey) {
            $this->api = new RetailcrmProxy($this->apiUrl, $this->apiKey, $this->log);
            $this->reference = new RetailcrmReferences($this->api);
        }

        parent::__construct();
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return (
            parent::install() &&
            $this->registerHook('newOrder') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('actionCustomerAccountAdd') &&
            $this->registerHook('actionOrderEdited') &&
            $this->registerHook('actionCarrierUpdate') &&
            $this->registerHook('header') &&
            ($this->use_new_hooks ? $this->registerHook('actionCustomerAccountUpdate') : true) &&
            ($this->use_new_hooks ? $this->registerHook('actionValidateCustomerAddressForm') : true) &&
            $this->installDB()
        );
    }

    public function hookHeader()
    {
        if (!empty($this->context) && !empty($this->context->controller)) {
            $this->context->controller->addJS($this->assetsBase . '/js/retailcrm-compat.min.js');
            $this->context->controller->addJS($this->assetsBase . '/js/retailcrm-jobs.min.js');
            $this->context->controller->addJS($this->assetsBase . '/js/retailcrm-collector.min.js');
            $this->context->controller->addJS($this->assetsBase . '/js/retailcrm-consultant.min.js');
        }
    }

    public function uninstall()
    {
        $apiUrl = Configuration::get(static::API_URL);
        $apiKey = Configuration::get(static::API_KEY);

        if (!empty($apiUrl) && !empty($apiKey)) {
            $api = new RetailcrmProxy(
                $apiUrl,
                $apiKey,
                RetailcrmLogger::getLogFile()
            );

            $clientId = Configuration::get(static::CLIENT_ID);
            $this->integrationModule($api, $clientId, false);
        }

        return parent::uninstall() &&
            Configuration::deleteByName(static::API_URL) &&
            Configuration::deleteByName(static::API_KEY) &&
            Configuration::deleteByName(static::DELIVERY) &&
            Configuration::deleteByName(static::STATUS) &&
            Configuration::deleteByName(static::PAYMENT) &&
            Configuration::deleteByName(static::DELIVERY_DEFAULT) &&
            Configuration::deleteByName(static::PAYMENT_DEFAULT) &&
            Configuration::deleteByName(static::STATUS_EXPORT) &&
            Configuration::deleteByName(static::CLIENT_ID) &&
            Configuration::deleteByName(static::COLLECTOR_ACTIVE) &&
            Configuration::deleteByName(static::COLLECTOR_KEY) &&
            Configuration::deleteByName(static::SYNC_CARTS_ACTIVE) &&
            Configuration::deleteByName(static::SYNC_CARTS_STATUS) &&
            Configuration::deleteByName(static::SYNC_CARTS_DELAY) &&
            Configuration::deleteByName(static::UPLOAD_ORDERS) &&
            Configuration::deleteByName(static::MODULE_LIST_CACHE_CHECKSUM) &&
            Configuration::deleteByName(static::ENABLE_CORPORATE_CLIENTS) &&
            Configuration::deleteByName(static::ENABLE_HISTORY_UPLOADS) &&
            Configuration::deleteByName(static::ENABLE_BALANCES_RECEIVING) &&
            Configuration::deleteByName('RETAILCRM_LAST_SYNC') &&
            Configuration::deleteByName('RETAILCRM_LAST_ORDERS_SYNC') &&
            Configuration::deleteByName('RETAILCRM_LAST_CUSTOMERS_SYNC') &&
            $this->uninstallDB();
    }

    public function installDB()
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'retailcrm_abandonedcarts` (
                    `id_cart` INT UNSIGNED UNIQUE NOT NULL,
                    `last_uploaded` DATETIME,
                    FOREIGN KEY (id_cart) REFERENCES '._DB_PREFIX_.'cart (id_cart)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) DEFAULT CHARSET=utf8;'
        );
    }

    public function uninstallDB()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'retailcrm_abandonedcarts`;');
    }

    public function getContent()
    {
        $output = null;
        $address = Configuration::get(static::API_URL);
        $token = Configuration::get(static::API_KEY);

        if (Tools::isSubmit('submit' . $this->name)) {
            $ordersIds = (string)(Tools::getValue(static::UPLOAD_ORDERS));

            if (!empty($ordersIds)) {
                $output .= $this->uploadOrders(RetailcrmTools::partitionId($ordersIds));
            } else {
                $output .= $this->saveSettings();
            }
        }

        if ($address && $token) {
            $this->api = new RetailcrmProxy($address, $token, $this->log);
            $this->reference = new RetailcrmReferences($this->api);
        }

        $templateFactory = new RetailcrmTemplateFactory($this->context->smarty, $this->assetsBase);

        return $templateFactory
            ->createTemplate($this)
            ->setContext($this->context)
            ->setErrors($this->getErrorMessages())
            ->setWarnings($this->getWarningMessage())
            ->setInformations($this->getInformationMessages())
            ->setConfirmations($this->getConfirmationMessages())
            ->render(__FILE__);
    }

    public function uploadOrders($orderIds)
    {
        if (count($orderIds) > 10) {
            return $this->displayConfirmation($this->l("Can't upload more than 10 orders per request"));
        }

        if (count($orderIds) < 1) {
            return $this->displayConfirmation($this->l("At least one order ID should be specified"));
        }

        $apiUrl = Configuration::get(static::API_URL);
        $apiKey = Configuration::get(static::API_KEY);
        $isSuccessful = true;

        if (!empty($apiUrl) && !empty($apiKey)) {
            if (!($this->api instanceof RetailcrmProxy)) {
                $this->api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log');
            }
        } else {
            return $this->displayError($this->l("Can't upload orders - set API key and API URL first!"));
        }

        $result = '';

        foreach ($orderIds as $orderId) {
            $object = new Order($orderId);
            $customer = new Customer($object->id_customer);
            $apiResponse = $this->api->ordersGet($object->id);
            $existingOrder = (!empty($apiResponse) && isset($apiResponse['order'])) ? $apiResponse['order'] : array();

            try {
                $orderBuilder = new RetailcrmOrderBuilder();
                $crmOrder = $orderBuilder
                    ->defaultLangFromConfiguration()
                    ->setApi($this->api)
                    ->setCmsOrder($object)
                    ->setCmsCustomer($customer)
                    ->buildOrderWithPreparedCustomer();
            } catch (\InvalidArgumentException $exception) {
                $result .= $this->displayError($exception->getMessage());
                RetailcrmLogger::writeCaller(__METHOD__, $exception->getTraceAsString());
            }

            if (!empty($crmOrder)) {
                $response = false;

                if (empty($existingOrder)) {
                    $response = $this->api->ordersCreate($crmOrder);
                } else {
                    $response = $this->api->ordersEdit($crmOrder);
                }

                $isSuccessful = $isSuccessful ? (is_bool($response) ? $response : $response->isSuccessful()) : false;

                time_nanosleep(0, 50000000);
            }
        }

        if ($isSuccessful) {
            return $this->displayConfirmation($this->l('All orders were uploaded successfully'));
        } else {
            $result .= $this->displayWarning($this->l('Not all orders were uploaded successfully'));

            foreach (RetailcrmApiErrors::getErrors() as $error) {
                $result .= $this->displayError($error);
            }

            return $result;
        }
    }

    public function hookActionCustomerAccountAdd($params)
    {
        if ($this->api) {
            $customer = $params['newCustomer'];
            $customerSend = RetailcrmOrderBuilder::buildCrmCustomer($customer);

            $this->api->customersCreate($customerSend);

            return true;
        }

        return false;
    }

    // this hook added in 1.7
    public function hookActionCustomerAccountUpdate($params)
    {
        if ($this->api) {
            /** @var Customer|CustomerCore|null $customer */
            $customer = isset($params['customer']) ? $params['customer'] : null;

            if (empty($customer)) {
                return false;
            }

            /** @var Cart|CartCore|null $cart */
            $cart = isset($params['cart']) ? $params['cart'] : null;

            /** @var array $customerSend */
            $customerSend = RetailcrmOrderBuilder::buildCrmCustomer($customer);

            /** @var \RetailcrmAddressBuilder $addressBuilder */
            $addressBuilder = new RetailcrmAddressBuilder();

            /** @var Address|\AddressCore|array $address */
            $address = array();

            if (isset($customerSend['externalId'])) {
                $customerData = $this->api->customersGet($customerSend['externalId']);

                // Necessary part if we don't want to overwrite other phone numbers.
                if ($customerData instanceof RetailcrmApiResponse
                    && $customerData->isSuccessful()
                    && $customerData->offsetExists('customer')
                ) {
                    $customerSend['phones'] = $customerData['customer']['phones'];
                }

                // Workaround: PrestaShop will return OLD address data, before editing.
                // In order to circumvent this we are using post data to fill new address object.
                if (Tools::getIsset('submitAddress')
                    && Tools::getIsset('id_customer')
                    && Tools::getIsset('id_address')
                ) {
                    $address = new Address(Tools::getValue('id_address'));

                    foreach (array_keys(Address::$definition['fields']) as $field) {
                        if (property_exists($address, $field) && Tools::getIsset($field)) {
                            $address->$field = Tools::getValue($field);
                        }
                    }
                } else {
                    $addresses = $customer->getAddresses($this->default_lang);
                    $address = array_shift($addresses);
                }

                if (!empty($address)) {
                    $addressBuilder->setMode(RetailcrmAddressBuilder::MODE_CUSTOMER);

                    if (is_object($address)) {
                        $addressBuilder->setAddress($address);
                    } else {
                        $addressBuilder->setAddressId($address['id_address']);
                    }

                    $addressBuilder->build();
                } elseif (!empty($cart)) {
                    $addressBuilder
                        ->setMode(RetailcrmAddressBuilder::MODE_ORDER_DELIVERY)
                        ->setAddressId($cart->id_address_invoice)
                        ->build();
                }

                $customerSend = RetailcrmTools::mergeCustomerAddress($customerSend, $addressBuilder->getDataArray());

                $this->api->customersEdit($customerSend);

                return true;
            }
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
        return $this->hookActionOrderStatusPostUpdate($params);
    }

    /**
     * This will ensure that our delivery mapping will not lose associations with edited deliveries.
     * PrestaShop doesn't actually edit delivery - it will hide it via `delete` flag in DB and create new one.
     * That's why we need to intercept this here and update delivery ID in mapping if necessary.
     *
     * @param array $params
     */
    public function hookActionCarrierUpdate($params)
    {
        if (!array_key_exists('id_carrier', $params) || !array_key_exists('carrier', $params)) {
            return;
        }

        /** @var Carrier|\CarrierCore $newCarrier */
        $newCarrier = $params['carrier'];
        $oldCarrierId = $params['id_carrier'];

        if (!($newCarrier instanceof Carrier) && !($newCarrier instanceof CarrierCore)) {
            return;
        }

        $delivery = json_decode(Configuration::get(RetailCRM::DELIVERY), true);
        $deliveryDefault = json_decode(Configuration::get(static::DELIVERY_DEFAULT), true);

        if ($oldCarrierId == $deliveryDefault) {
            Configuration::updateValue(static::DELIVERY_DEFAULT, json_encode($newCarrier->id));
        }

        if (is_array($delivery) && array_key_exists($oldCarrierId, $delivery)) {
            $delivery[$newCarrier->id] = $delivery[$oldCarrierId];
            unset($delivery[$oldCarrierId]);
            Configuration::updateValue(static::DELIVERY, json_encode($delivery));
        }
    }

    public function hookActionOrderEdited($params)
    {
        if ($this->api) {
            $order = array(
                'externalId' => $params['order']->id,
                'firstName' => $params['customer']->firstname,
                'lastName' => $params['customer']->lastname,
                'email' => $params['customer']->email,
                'createdAt' => RetailcrmTools::verifyDate($params['order']->date_add, 'Y-m-d H:i:s')
                    ? $params['order']->date_add : date('Y-m-d H:i:s'),
                'delivery' => array('cost' => $params['order']->total_shipping),
                'discountManualAmount' => round($params['order']->total_discounts, 2)
            );

            if (((float) $order['discountManualAmount']) > ((float) $params['order']->total_paid)) {
                $order['discountManualAmount'] = $params['order']->total_paid;
            }

            try {
                $orderdb = new Order($params['order']->id);
            } catch (PrestaShopDatabaseException $exception) {
                RetailcrmLogger::writeCaller('hookActionOrderEdited', $exception->getMessage());
                RetailcrmLogger::writeNoCaller($exception->getTraceAsString());
            } catch (PrestaShopException $exception) {
                RetailcrmLogger::writeCaller('hookActionOrderEdited', $exception->getMessage());
                RetailcrmLogger::writeNoCaller($exception->getTraceAsString());
            }

            $orderCrm = $this->api->ordersGet($order['externalId']);

            if (!($orderCrm instanceof RetailcrmApiResponse) || !$orderCrm->isSuccessful()) {
                /** @var Order|\OrderCore $order */
                $order = $params['order'];

                $this->hookNewOrder(array(
                    'orderStatus' => $order->current_state,
                    'id_order' => (int) $order->id,
                    'order' => $order,
                    'cart' => new Cart($order->id_cart),
                    'customer' => new Customer($order->id_customer)
                ));

                return false;
            }

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

    public function hookActionOrderStatusPostUpdate($params)
    {
        $status = json_decode(Configuration::get(static::STATUS), true);

        if (isset($params['orderStatus'])) {
            $cmsOrder = $params['order'];
            $cart = $params['cart'];
            $customer = $params['customer'];
            $response = $this->api->ordersGet(RetailcrmTools::getCartOrderExternalId($cart));
            $crmOrder = isset($response['order']) ? $response['order'] : array();
            $orderBuilder = new RetailcrmOrderBuilder();

            try {
                $order = $orderBuilder
                    ->defaultLangFromConfiguration()
                    ->setApi($this->api)
                    ->setCmsOrder($cmsOrder)
                    ->setCmsCart($cart)
                    ->setCmsCustomer($customer)
                    ->buildOrderWithPreparedCustomer();
            } catch (\InvalidArgumentException $exception) {
                RetailcrmLogger::writeCaller(
                    'hookActionOrderStatusPostUpdate',
                    $exception->getMessage()
                );
                RetailcrmLogger::writeNoCaller($exception->getTraceAsString());

                return false;
            }

            if (!empty($crmOrder)) {
                $order['id'] = $crmOrder['id'];
                $this->api->ordersEdit($order, 'id');

                if (empty($crmOrder['payments']) && !empty($order['payments'])) {
                    $payment = array_merge(reset($order['payments']), array(
                        'order' => array('externalId' => $order['externalId'])
                    ));
                    $this->api->ordersPaymentCreate($payment);
                }
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
        $payments = $this->reference->getSystemPaymentModules();
        $paymentCRM = json_decode(Configuration::get(static::PAYMENT), true);
        $payment = "";
        $payCode = "";

        foreach ($payments as $valPay) {
            if ($valPay['name'] == $params['paymentCC']->payment_method) {
                $payCode = $valPay['code'];
            }
        }

        if (!empty($payCode) && array_key_exists($payCode, $paymentCRM) && !empty($paymentCRM[$payCode])) {
            $payment = $paymentCRM[$payCode];
        }

        if (empty($payment)) {
            return false;
        }

        $externalId = false;

        if(empty($params['cart']))
            return false;

        $response = $this->api->ordersGet(RetailcrmTools::getCartOrderExternalId($params['cart']));

        if ($response !== false && isset($response['order'])) {
            $externalId = RetailcrmTools::getCartOrderExternalId($params['cart']);
        } else {
            if (version_compare(_PS_VERSION_, '1.7.1.0', '>=')) {
                $id_order = (int)Order::getIdByCartId((int)$params['cart']->id);
            } else {
                $id_order = (int)Order::getOrderByCartId((int)$params['cart']->id);
            }

            if ($id_order > 0) {
                $response = $this->api->ordersGet($id_order);
                if ($response !== false && isset($response['order'])) {
                    $externalId = $id_order;
                }
            }
        }

        $status = (round($params['paymentCC']->amount, 2) > 0 ? 'paid' : null);

        if ($externalId !== false) {
            $orderCRM = $response['order'];

            if ($orderCRM && $orderCRM['payments']) {
                foreach ($orderCRM['payments'] as $orderPayment) {
                    if ($orderPayment['type'] == $payment) {
                        $updatePayment = $orderPayment;
                        $updatePayment['amount'] = $params['paymentCC']->amount;
                        $updatePayment['paidAt'] = $params['paymentCC']->date_add;
                        $updatePayment['status'] = $status;
                    }
                }
            }

            if (isset($updatePayment)) {
                $this->api->ordersPaymentEdit($updatePayment, 'id');
            } else {
                $createPayment = array(
                    'externalId' => $params['paymentCC']->id,
                    'amount'     => $params['paymentCC']->amount,
                    'paidAt'     => $params['paymentCC']->date_add,
                    'type'       => $payment,
                    'status'     => $status,
                    'order'      => array(
                        'externalId' => $externalId,
                    ),
                );

                $this->api->ordersPaymentCreate($createPayment);
            }
        }

        return true;
    }

    /**
     * Save settings handler
     *
     * @return string
     */
    private function saveSettings()
    {
        $output = '';
        $url = (string) Tools::getValue(static::API_URL);
        $apiKey = (string) Tools::getValue(static::API_KEY);
        $consultantCode = (string) Tools::getValue(static::CONSULTANT_SCRIPT);

        if (!empty($url) && !empty($apiKey)) {
            $settings  = array(
                'url' => $url,
                'apiKey' => $apiKey,
                'address' => (string)(Tools::getValue(static::API_URL)),
                'delivery' => json_encode(Tools::getValue(static::DELIVERY)),
                'status' => json_encode(Tools::getValue(static::STATUS)),
                'payment' => json_encode(Tools::getValue(static::PAYMENT)),
                'deliveryDefault' => json_encode(Tools::getValue(static::DELIVERY_DEFAULT)),
                'paymentDefault' => json_encode(Tools::getValue(static::PAYMENT_DEFAULT)),
                'statusExport' => (string)(Tools::getValue(static::STATUS_EXPORT)),
                'enableCorporate' => (Tools::getValue(static::ENABLE_CORPORATE_CLIENTS) !== false),
                'enableHistoryUploads' => (Tools::getValue(static::ENABLE_HISTORY_UPLOADS) !== false),
                'enableBalancesReceiving' => (Tools::getValue(static::ENABLE_BALANCES_RECEIVING) !== false),
                'collectorActive' => (Tools::getValue(static::COLLECTOR_ACTIVE) !== false),
                'collectorKey' => (string)(Tools::getValue(static::COLLECTOR_KEY)),
                'clientId' => Configuration::get(static::CLIENT_ID),
                'synchronizeCartsActive' => (Tools::getValue(static::SYNC_CARTS_ACTIVE) !== false),
                'synchronizedCartStatus' => (string)(Tools::getValue(static::SYNC_CARTS_STATUS)),
                'synchronizedCartDelay' => (string)(Tools::getValue(static::SYNC_CARTS_DELAY))
            );

            $output .= $this->validateForm($settings, $output);

            if ($output === '') {
                Configuration::updateValue(static::API_URL, $settings['url']);
                Configuration::updateValue(static::API_KEY, $settings['apiKey']);
                Configuration::updateValue(static::DELIVERY, $settings['delivery']);
                Configuration::updateValue(static::STATUS, $settings['status']);
                Configuration::updateValue(static::PAYMENT, $settings['payment']);
                Configuration::updateValue(static::DELIVERY_DEFAULT, $settings['deliveryDefault']);
                Configuration::updateValue(static::PAYMENT_DEFAULT, $settings['paymentDefault']);
                Configuration::updateValue(static::STATUS_EXPORT, $settings['statusExport']);
                Configuration::updateValue(static::ENABLE_CORPORATE_CLIENTS, $settings['enableCorporate']);
                Configuration::updateValue(static::ENABLE_HISTORY_UPLOADS, $settings['enableHistoryUploads']);
                Configuration::updateValue(static::ENABLE_BALANCES_RECEIVING, $settings['enableBalancesReceiving']);
                Configuration::updateValue(static::COLLECTOR_ACTIVE, $settings['collectorActive']);
                Configuration::updateValue(static::COLLECTOR_KEY, $settings['collectorKey']);
                Configuration::updateValue(static::SYNC_CARTS_ACTIVE, $settings['synchronizeCartsActive']);
                Configuration::updateValue(static::SYNC_CARTS_STATUS, $settings['synchronizedCartStatus']);
                Configuration::updateValue(static::SYNC_CARTS_DELAY, $settings['synchronizedCartDelay']);

                $this->apiUrl = $settings['url'];
                $this->apiKey = $settings['apiKey'];
                $this->api = new RetailcrmProxy($this->apiUrl, $this->apiKey, $this->log);
                $this->reference = new RetailcrmReferences($this->api);

                if ($this->isRegisteredInHook('actionPaymentCCAdd') == 0) {
                    $this->registerHook('actionPaymentCCAdd');
                }
            }
        }

        if (!empty($consultantCode)) {
            $extractor = new RetailcrmConsultantRcctExtractor();
            $rcct = $extractor->setConsultantScript($consultantCode)->build()->getDataString();

            if (!empty($rcct)) {
                Configuration::updateValue(static::CONSULTANT_SCRIPT, $consultantCode, true);
                Configuration::updateValue(static::CONSULTANT_RCCT, $rcct);
                Cache::getInstance()->set(static::CONSULTANT_RCCT, $rcct);
            } else {
                Configuration::deleteByName(static::CONSULTANT_SCRIPT);
                Configuration::deleteByName(static::CONSULTANT_RCCT);
                Cache::getInstance()->delete(static::CONSULTANT_RCCT);
            }
        }

        return $output;
    }

    /**
     * Activate/deactivate module in marketplace retailCRM
     *
     * @param \RetailcrmProxy $apiClient
     * @param string $clientId
     * @param boolean $active
     *
     * @return boolean
     */
    private function integrationModule($apiClient, $clientId, $active = true)
    {
        $scheme = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $logo = 'https://s3.eu-central-1.amazonaws.com/retailcrm-billing/images/5b845ce986911-prestashop2.svg';
        $integrationCode = 'prestashop';
        $name = 'PrestaShop';
        $accountUrl = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
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

        if (!$response) {
            return false;
        }

        if ($response->isSuccessful()) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if provided connection supports API v5
     *
     * @param $settings
     *
     * @return bool
     */
    private function validateApiVersion($settings)
    {
        /** @var \RetailcrmProxy|\RetailcrmApiClientV5 $api */
        $api = new RetailcrmProxy(
            $settings['url'],
            $settings['apiKey'],
            $this->log
        );

        $response = $api->apiVersions();

        if ($response !== false && isset($response['versions']) && !empty($response['versions'])) {
            foreach ($response['versions'] as $version) {
                if ($version == static::LATEST_API_VERSION
                    || Tools::substr($version, 0, 1) == static::LATEST_API_VERSION
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Workaround to pass translate method into another classes
     *
     * @param $text
     *
     * @return mixed
     */
    public function translate($text)
    {
        return $this->l($text);
    }

    /**
     * Cart status must be present and must be unique to cartsIds only
     *
     * @param string $statuses
     * @param string $statusExport
     * @param string $cartStatus
     *
     * @return bool
     */
    private function validateCartStatus($statuses, $statusExport, $cartStatus)
    {
        if ($cartStatus != '' && ($cartStatus == $statusExport || stripos($statuses, $cartStatus))) {
            return false;
        }

        return true;
    }

    /**
     * Returns false if mapping is not valid in one-to-one relation
     *
     * @param string $statuses
     *
     * @return bool
     */
    private function validateMappingOneToOne($statuses)
    {
        $data = json_decode($statuses, true);

        if (json_last_error() != JSON_ERROR_NONE || !is_array($data)) {
            return true;
        }

        $statusesList = array_filter(array_values($data));

        if (count($statusesList) != count(array_unique($statusesList))) {
            return false;
        }

        return true;
    }

    /**
     * Settings form validator
     *
     * @param $settings
     * @param $output
     *
     * @return string
     */
    private function validateForm($settings, $output)
    {
        if (!RetailcrmTools::validateCrmAddress($settings['url']) || !Validate::isGenericName($settings['url'])) {
            $output .= $this->displayError($this->l('Invalid or empty crm address'));
        } elseif (!$settings['apiKey'] || $settings['apiKey'] == '') {
            $output .= $this->displayError($this->l('Invalid or empty crm api token'));
        } elseif (!$this->validateApiVersion($settings)) {
            $output .= $this->displayError($this->l('The selected version of the API is unavailable'));
        } elseif (!$this->validateCartStatus(
            $settings['status'],
            $settings['statusExport'],
            $settings['synchronizedCartStatus']
        )) {
            $output .= $this->displayError(
                $this->l('Order status for abandoned carts should not be used in other settings')
            );
        } elseif (!$this->validateMappingOneToOne($settings['status'])) {
            $output .= $this->displayError(
                $this->l('Order statuses should not repeat in statuses matrix')
            );
        } elseif (!$this->validateMappingOneToOne($settings['delivery'])) {
            $output .= $this->displayError(
                $this->l('Delivery types should not repeat in delivery matrix')
            );
        } elseif (!$this->validateMappingOneToOne($settings['payment'])) {
            $output .= $this->displayError(
                $this->l('Payment types should not repeat in payment matrix')
            );
        }

        return $output;
    }

    /**
     * Loads data from modules list cache
     *
     * @return array|mixed
     */
    private static function requireModulesCache()
    {
        if (file_exists(static::getModulesCache())) {
            return require_once(static::getModulesCache());
        }

        return false;
    }

    /**
     * Returns path to modules list cache
     *
     * @return string
     */
    private static function getModulesCache()
    {
        if (defined('_PS_CACHE_DIR_')) {
            return _PS_CACHE_DIR_ . '/retailcrm_modules_cache.php';
        }

        if (!defined('_PS_ROOT_DIR_')) {
            return '';
        }

        $cacheDir = _PS_ROOT_DIR_ . '/cache';

        if (realpath($cacheDir) !== false && is_dir($cacheDir)) {
            return $cacheDir . '/retailcrm_modules_cache.php';
        }

        return _PS_ROOT_DIR_ . '/retailcrm_modules_cache.php';
    }

    /**
     * Returns all module settings
     *
     * @return array
     */
    public static function getSettings()
    {
        $syncCartsDelay = (string) (Configuration::get(static::SYNC_CARTS_DELAY));

        // Use 15 minutes as default interval but don't change immediate interval to it if user already made decision
        if (empty($syncCartsDelay) && $syncCartsDelay !== "0") {
            $syncCartsDelay = "900";
        }

        return array(
            'url' => (string)(Configuration::get(static::API_URL)),
            'apiKey' => (string)(Configuration::get(static::API_KEY)),
            'delivery' => json_decode(Configuration::get(static::DELIVERY), true),
            'status' => json_decode(Configuration::get(static::STATUS), true),
            'payment' => json_decode(Configuration::get(static::PAYMENT), true),
            'deliveryDefault' => json_decode(Configuration::get(static::DELIVERY_DEFAULT), true),
            'paymentDefault' => json_decode(Configuration::get(static::PAYMENT_DEFAULT), true),
            'statusExport' => (string)(Configuration::get(static::STATUS_EXPORT)),
            'collectorActive' => (Configuration::get(static::COLLECTOR_ACTIVE)),
            'collectorKey' => (string)(Configuration::get(static::COLLECTOR_KEY)),
            'clientId' => Configuration::get(static::CLIENT_ID),
            'synchronizeCartsActive' => (Configuration::get(static::SYNC_CARTS_ACTIVE)),
            'synchronizedCartStatus' => (string)(Configuration::get(static::SYNC_CARTS_STATUS)),
            'synchronizedCartDelay' => $syncCartsDelay,
            'consultantScript' => (string)(Configuration::get(static::CONSULTANT_SCRIPT)),
            'enableCorporate' => (bool)(Configuration::get(static::ENABLE_CORPORATE_CLIENTS)),
            'enableHistoryUploads' => (bool)(Configuration::get(static::ENABLE_HISTORY_UPLOADS)),
            'enableBalancesReceiving' => (bool)(Configuration::get(static::ENABLE_BALANCES_RECEIVING)),
        );
    }

    /**
     * Returns all settings names in DB
     *
     * @return array
     */
    public static function getSettingsNames()
    {
        return array(
            'urlName' => static::API_URL,
            'apiKeyName' => static::API_KEY,
            'deliveryName' => static::DELIVERY,
            'statusName' => static::STATUS,
            'paymentName' => static::PAYMENT,
            'deliveryDefaultName' => static::DELIVERY_DEFAULT,
            'paymentDefaultName' => static::PAYMENT_DEFAULT,
            'statusExportName' => static::STATUS_EXPORT,
            'collectorActiveName' => static::COLLECTOR_ACTIVE,
            'collectorKeyName' => static::COLLECTOR_KEY,
            'clientIdName' => static::CLIENT_ID,
            'synchronizeCartsActiveName' => static::SYNC_CARTS_ACTIVE,
            'synchronizedCartStatusName' => static::SYNC_CARTS_STATUS,
            'synchronizedCartDelayName' => static::SYNC_CARTS_DELAY,
            'uploadOrders' => static::UPLOAD_ORDERS,
            'consultantScriptName' => static::CONSULTANT_SCRIPT,
            'enableCorporateName' => static::ENABLE_CORPORATE_CLIENTS,
            'enableHistoryUploadsName' => static::ENABLE_HISTORY_UPLOADS,
            'enableBalancesReceivingName' => static::ENABLE_BALANCES_RECEIVING
        );
    }

    /**
     * Returns modules list, caches result. Recreates cache when needed.
     * Activity indicator in cache will be rewrited by current state.
     *
     * @return array
     */
    public static function getCachedCmsModulesList()
    {
        $storedHash = (string) Configuration::get(static::MODULE_LIST_CACHE_CHECKSUM);
        $calculatedHash = md5(implode('#', Module::getModulesDirOnDisk(true)));

        if ($storedHash != $calculatedHash) {
            $serializedModules = array();
            static::$moduleListCache = Module::getModulesOnDisk(true);

            foreach (static::$moduleListCache as $module) {
                $serializedModules[] = json_encode($module);
            }

            Configuration::updateValue(static::MODULE_LIST_CACHE_CHECKSUM, $calculatedHash);
            static::writeModulesCache($serializedModules);

            return static::$moduleListCache;
        } else {
            try {
                if (is_array(static::$moduleListCache)) {
                    return static::$moduleListCache;
                }

                $modulesList = static::requireModulesCache();

                if ($modulesList === false) {
                    Configuration::updateValue(static::MODULE_LIST_CACHE_CHECKSUM, 'not exist');

                    return static::getCachedCmsModulesList();
                }

                static::$moduleListCache = array();

                foreach ($modulesList as $serializedModule) {
                    $deserialized = json_decode($serializedModule);

                    if ($deserialized instanceof stdClass
                        && property_exists($deserialized, 'name')
                        && property_exists($deserialized, 'active')
                    ) {
                        $deserialized->active = Module::isEnabled($deserialized->name);
                        static::$moduleListCache[] = $deserialized;
                    }
                }

                static::$moduleListCache = array_filter(static::$moduleListCache);
                unset($modulesList);

                return static::$moduleListCache;
            } catch (Exception $exception) {
                RetailcrmLogger::writeCaller(__METHOD__, $exception->getMessage());
                RetailcrmLogger::writeNoCaller($exception->getTraceAsString());
                Configuration::updateValue(static::MODULE_LIST_CACHE_CHECKSUM, 'exception');

                return static::getCachedCmsModulesList();
            }
        }
    }

    /**
     * Writes module list to cache file.
     *
     * @param $data
     */
    private static function writeModulesCache($data)
    {
        $file = fopen(static::getModulesCache(), 'w+');

        if ($file !== false) {
            fwrite($file, '<?php' . PHP_EOL);
            fwrite($file, '// Autogenerated module list cache for retailCRM' . PHP_EOL);
            fwrite($file, '// Delete this file if you cannot see some payment types in module' . PHP_EOL);
            fwrite($file, 'return ' . var_export($data, true) . ';' . PHP_EOL);
            fflush($file);
            fclose($file);
        }
    }

    /**
     * Synchronized cartsIds time choice
     *
     * @return array
     */
    public function getSynchronizedCartsTimeSelect()
    {
        return array(
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
        );
    }

    /**
     * Initializes arrays of messages
     */
    private function initializeTemplateMessages()
    {
        if (is_null($this->templateErrors)) {
            $this->templateErrors = array();
        }

        if (is_null($this->templateWarnings)) {
            $this->templateWarnings = array();
        }

        if (is_null($this->templateConfirms)) {
            $this->templateConfirms = array();
        }

        if (is_null($this->templateErrors)) {
            $this->templateInfos = array();
        }
    }

    /**
     * Returns error messages
     *
     * @return array
     */
    protected function getErrorMessages()
    {
        if (empty($this->templateErrors)) {
            return array();
        }

        return $this->templateErrors;
    }

    /**
     * Returns warning messages
     *
     * @return array
     */
    protected function getWarningMessage()
    {
        if (empty($this->templateWarnings)) {
            return array();
        }

        return $this->templateWarnings;
    }

    /**
     * Returns information messages
     *
     * @return array
     */
    protected function getInformationMessages()
    {
        if (empty($this->templateInfos)) {
            return array();
        }

        return $this->templateInfos;
    }

    /**
     * Returns confirmation messages
     *
     * @return array
     */
    protected function getConfirmationMessages()
    {
        if (empty($this->templateConfirms)) {
            return array();
        }

        return $this->templateConfirms;
    }

    /**
     * Replacement for default error message helper
     *
     * @param string|array $message
     *
     * @return string
     */
    public function displayError($message)
    {
        $this->initializeTemplateMessages();
        $this->templateErrors[] = $message;

        return ' ';
    }

    /**
     * Replacement for default warning message helper
     *
     * @param string|array $message
     *
     * @return string
     */
    public function displayWarning($message)
    {
        $this->initializeTemplateMessages();
        $this->templateWarnings[] = $message;

        return ' ';
    }

    /**
     * Replacement for default warning message helper
     *
     * @param string|array $message
     *
     * @return string
     */
    public function displayConfirmation($message)
    {
        $this->initializeTemplateMessages();
        $this->templateConfirms[] = $message;

        return ' ';
    }

    /**
     * Replacement for default warning message helper
     *
     * @param string|array $message
     *
     * @return string
     */
    public function displayInformation($message)
    {
        $this->initializeTemplateMessages();
        $this->templateInfos[] = $message;

        return ' ';
    }
}
