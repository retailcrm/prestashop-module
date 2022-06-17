<?php
/**
 * MIT License
 *
 * Copyright (c) 2021 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
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

require_once dirname(__FILE__) . '/bootstrap.php';

class RetailCRM extends Module
{
    const API_URL = 'RETAILCRM_ADDRESS';
    const API_KEY = 'RETAILCRM_API_TOKEN';
    const DELIVERY = 'RETAILCRM_API_DELIVERY';
    const STATUS = 'RETAILCRM_API_STATUS';
    const OUT_OF_STOCK_STATUS = 'RETAILCRM_API_OUT_OF_STOCK_STATUS';
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
    const ENABLE_ORDER_NUMBER_SENDING = 'RETAILCRM_ENABLE_ORDER_NUMBER_SENDING';
    const ENABLE_ORDER_NUMBER_RECEIVING = 'RETAILCRM_ENABLE_ORDER_NUMBER_RECEIVING';
    const ENABLE_DEBUG_MODE = 'RETAILCRM_ENABLE_DEBUG_MODE';

    const CONSULTANT_SCRIPT = 'RETAILCRM_CONSULTANT_SCRIPT';
    const CONSULTANT_RCCT = 'RETAILCRM_CONSULTANT_RCCT';
    const ENABLE_WEB_JOBS = 'RETAILCRM_ENABLE_WEB_JOBS';

    const REQUIRED_CRM_SITE_ACCESS = 'access_selective';
    const REQUIRED_CRM_SITE_COUNT = 1;
    const REQUIRED_CRM_SCOPES = [
        'order_read',
        'order_write',
        'customer_read',
        'customer_write',
        'store_read',
        'store_write',
        'reference_read',
        'reference_write',
        'analytics_write',
        'telephony_read',
        'telephony_write',
        'delivery_read',
        'delivery_write',
        'user_read',
        'user_write',
        'segment_read',
        'custom_fields_write',
        'custom_fields_read',
        'task_read',
        'task_write',
        'integration_read',
        'integration_write',
        'cost_read',
        'cost_write',
        'payments_read',
        'payments_write',
        'file_read',
        'file_write',
        'loyalty_read',
        'loyalty_write',
        'verification_write',
        'verification_read',
    ];

    // todo dynamically define controller classes
    const ADMIN_CONTROLLERS
        = [
            RetailcrmSettingsLinkController::class,
            RetailcrmSettingsController::class,
            RetailcrmReferencesController::class,
            RetailcrmCatalogController::class,
            RetailcrmJobsController::class,
            RetailcrmLogsController::class,
            RetailcrmOrdersController::class,
            RetailcrmExportController::class,
        ];

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
        $this->version = '3.4.3';
        $this->author = 'DIGITAL RETAIL TECHNOLOGIES SL';
        $this->displayName = $this->l('Simla.com');
        $this->description = $this->l('Integration module for Simla.com');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        $this->apiUrl = Configuration::get(static::API_URL);
        $this->apiKey = Configuration::get(static::API_KEY);
        $this->ps_versions_compliancy = ['min' => '1.6.1.0', 'max' => _PS_VERSION_];
        $this->psVersion = Tools::substr(_PS_VERSION_, 0, 3);
        $this->log = RetailcrmLogger::getLogFile();
        $this->module_key = 'dff3095326546f5fe8995d9e86288491';
        $this->assetsBase =
            Tools::getShopDomainSsl(true, true) .
            __PS_BASE_URI__ .
            'modules/' .
            $this->name .
            '/views';

        if ('1.6' == $this->psVersion) {
            $this->bootstrap = true;
            $this->use_new_hooks = false;
        }

        if ($this->apiUrl && $this->apiKey) {
            $this->api = new RetailcrmProxy($this->apiUrl, $this->apiKey);
            $this->reference = new RetailcrmReferences($this->api);
        }

        parent::__construct();
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return
            parent::install()
            && $this->registerHook('newOrder')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionPaymentConfirmation')
            && $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('actionOrderEdited')
            && $this->registerHook('actionCarrierUpdate')
            && $this->registerHook('header')
            && ($this->use_new_hooks ? $this->registerHook('actionCustomerAccountUpdate') : true)
            && ($this->use_new_hooks ? $this->registerHook('actionValidateCustomerAddressForm') : true)
            && $this->installDB()
            && $this->installTab()
            ;
    }

    /**
     * Installs the tab for the admin controller
     *
     * @return bool
     */
    public function installTab()
    {
        /** @var RetailcrmAdminAbstractController $controller */
        foreach (self::ADMIN_CONTROLLERS as $controller) {
            $tab = new Tab();
            $tab->id = $controller::getId();
            $tab->id_parent = $controller::getParentId();
            $tab->class_name = $controller::getClassName();
            $tab->name = $controller::getName();
            $tab->icon = $controller::getIcon();
            $tab->position = $controller::getPosition();
            $tab->active = 1;
            $tab->module = $this->name;

            if (!$tab->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstallTab()
    {
        /** @var RetailcrmAdminAbstractController $controller */
        foreach (self::ADMIN_CONTROLLERS as $controller) {
            $tabId = $controller::getId();
            if (!$tabId) {
                continue;
            }

            $tab = new Tab($tabId);

            if (!$tab->delete()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function uninstallOldTabs()
    {
        $moduleTabs = Tab::getCollectionFromModule($this->name);

        /** @var Tab $tab */
        foreach ($moduleTabs as $tab) {
            $tabClassName = $tab->class_name . 'Controller';

            if (!in_array($tabClassName, self::ADMIN_CONTROLLERS)) {
                try {
                    $tab->delete();
                } catch (PrestaShopException $e) {
                    RetailcrmLogger::writeCaller(
                        __METHOD__,
                        sprintf('Error while deleting old tabs: %s', $e->getMessage())
                    );
                    RetailcrmLogger::writeDebug(__METHOD__, $e->getTraceAsString());

                    return false;
                }
            }
        }

        return true;
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
            $api = new RetailcrmProxy($apiUrl, $apiKey);

            $clientId = Configuration::get(static::CLIENT_ID);
            $this->integrationModule($api, $clientId, false);
        }

        return parent::uninstall()
            && Configuration::deleteByName(static::API_URL) // todo delete with SettingsItems class
            && Configuration::deleteByName(static::API_KEY)
            && Configuration::deleteByName(static::DELIVERY)
            && Configuration::deleteByName(static::STATUS)
            && Configuration::deleteByName(static::OUT_OF_STOCK_STATUS)
            && Configuration::deleteByName(static::PAYMENT)
            && Configuration::deleteByName(static::DELIVERY_DEFAULT)
            && Configuration::deleteByName(static::PAYMENT_DEFAULT)
            && Configuration::deleteByName(static::STATUS_EXPORT)
            && Configuration::deleteByName(static::CLIENT_ID)
            && Configuration::deleteByName(static::COLLECTOR_ACTIVE)
            && Configuration::deleteByName(static::COLLECTOR_KEY)
            && Configuration::deleteByName(static::SYNC_CARTS_ACTIVE)
            && Configuration::deleteByName(static::SYNC_CARTS_STATUS)
            && Configuration::deleteByName(static::SYNC_CARTS_DELAY)
            && Configuration::deleteByName(static::UPLOAD_ORDERS)
            && Configuration::deleteByName(static::MODULE_LIST_CACHE_CHECKSUM)
            && Configuration::deleteByName(static::ENABLE_CORPORATE_CLIENTS)
            && Configuration::deleteByName(static::ENABLE_HISTORY_UPLOADS)
            && Configuration::deleteByName(static::ENABLE_BALANCES_RECEIVING)
            && Configuration::deleteByName(static::ENABLE_ORDER_NUMBER_SENDING)
            && Configuration::deleteByName(static::ENABLE_ORDER_NUMBER_RECEIVING)
            && Configuration::deleteByName(static::ENABLE_DEBUG_MODE)
            && Configuration::deleteByName(static::ENABLE_WEB_JOBS)
            && Configuration::deleteByName('RETAILCRM_LAST_SYNC')
            && Configuration::deleteByName('RETAILCRM_LAST_ORDERS_SYNC')
            && Configuration::deleteByName('RETAILCRM_LAST_CUSTOMERS_SYNC')
            && Configuration::deleteByName(RetailcrmJobManager::LAST_RUN_NAME)
            && Configuration::deleteByName(RetailcrmJobManager::LAST_RUN_DETAIL_NAME)
            && Configuration::deleteByName(RetailcrmCatalogHelper::ICML_INFO_NAME)
            && Configuration::deleteByName(RetailcrmJobManager::IN_PROGRESS_NAME)
            && Configuration::deleteByName(RetailcrmJobManager::CURRENT_TASK)
            && Configuration::deleteByName(RetailcrmCli::CURRENT_TASK_CLI)
            && $this->uninstallDB()
            && $this->uninstallTab()
            ;
    }

    public function enable($force_all = false)
    {
        return parent::enable($force_all)
            && $this->installTab()
            ;
    }

    public function disable($force_all = false)
    {
        return parent::disable($force_all)
            && $this->uninstallTab()
            ;
    }

    public function installDB()
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'retailcrm_abandonedcarts` (
                    `id_cart` INT UNSIGNED UNIQUE NOT NULL,
                    `last_uploaded` DATETIME,
                    FOREIGN KEY (id_cart) REFERENCES ' . _DB_PREFIX_ . 'cart (id_cart)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) DEFAULT CHARSET=utf8;
                CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'retailcrm_exported_orders` (
                    `id_order` INT UNSIGNED UNIQUE NULL,
                    `id_order_crm` INT UNSIGNED UNIQUE NULL,
                    `errors` TEXT NULL,
                    `last_uploaded` DATETIME,
                    FOREIGN KEY (id_order) REFERENCES ' . _DB_PREFIX_ . 'orders (id_order)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) DEFAULT CHARSET=utf8;'
        );
    }

    public function uninstallDB()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'retailcrm_abandonedcarts`;
            DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'retailcrm_exported_orders`;'
        );
    }

    /**
     * Remove files that was deleted\moved\renamed in a newer version and currently are outdated
     *
     * @param array $files File paths relative to the `modules/` directory
     *
     * @return bool
     */
    public function removeOldFiles($files)
    {
        foreach ($files as $file) {
            try {
                if (0 !== strpos($file, 'retailcrm/')) {
                    continue;
                }

                $relativePath = str_replace('retailcrm/', '', $file);
                $fullPath = sprintf(
                    '%s/%s',
                    __DIR__,
                    $relativePath
                );

                if (!file_exists($fullPath)) {
                    continue;
                }

                RetailcrmLogger::writeCaller(
                    __METHOD__,
                    sprintf('Remove `%s`', $file)
                );

                unlink($fullPath); // todo maybe check and remove empty directories
            } catch (Exception $e) {
                RetailcrmLogger::writeCaller(
                    __METHOD__,
                    sprintf('Error removing `%s`: %s', $file, $e->getMessage())
                );
            } catch (Error $e) {
                RetailcrmLogger::writeCaller(
                    __METHOD__,
                    sprintf('Error removing `%s`: %s', $file, $e->getMessage())
                );
            }
        }

        return true;
    }

    public function getContent()
    {
        $address = Configuration::get(static::API_URL);
        $token = Configuration::get(static::API_KEY);
        if ($address && $token) {
            $this->api = new RetailcrmProxy($address, $token);
        }

        $this->reference = new RetailcrmReferences($this->api);

        $templateFactory = new RetailcrmTemplateFactory($this->context->smarty, $this->assetsBase);

        return $templateFactory
            ->createTemplate($this)
            ->setContext($this->context)
            ->render(__FILE__)
        ;
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
            $address = [];

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
                        ->build()
                    ;
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
        $customerAddress = ['customer' => $customer, 'cart' => $params['cart']];

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
        $deliveryDefault = Configuration::get(static::DELIVERY_DEFAULT);

        if ($oldCarrierId == $deliveryDefault) {
            Configuration::updateValue(static::DELIVERY_DEFAULT, $newCarrier->id);
        }

        if (is_array($delivery) && array_key_exists($oldCarrierId, $delivery)) {
            $delivery[$newCarrier->id] = $delivery[$oldCarrierId];
            unset($delivery[$oldCarrierId]);
            Configuration::updateValue(static::DELIVERY, json_encode($delivery));
        }
    }

    public function hookActionOrderEdited($params)
    {
        // todo refactor it to call hookActionOrderStatusPostUpdate
        if (!$this->api) {
            return false;
        }

        try {
            RetailcrmExport::$api = $this->api;

            return RetailcrmExport::exportOrder($params['order']->id);
        } catch (Exception $e) {
            RetailcrmLogger::writeCaller(__METHOD__, $e->getMessage());
            RetailcrmLogger::writeNoCaller($e->getTraceAsString());
        } catch (Error $e) {
            RetailcrmLogger::writeCaller(__METHOD__, $e->getMessage());
            RetailcrmLogger::writeNoCaller($e->getTraceAsString());
        }

        return false;
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        if (!$this->api) {
            return false;
        }

        $status = json_decode(Configuration::get(static::STATUS), true);

        if (isset($params['orderStatus'])) {
            try {
                RetailcrmExport::$api = $this->api;

                return RetailcrmExport::exportOrder($params['order']->id);
            } catch (Exception $e) {
                RetailcrmLogger::writeCaller(__METHOD__, $e->getMessage());
                RetailcrmLogger::writeNoCaller($e->getTraceAsString());
            } catch (Error $e) {
                RetailcrmLogger::writeCaller(__METHOD__, $e->getMessage());
                RetailcrmLogger::writeNoCaller($e->getTraceAsString());
            }

            return false;
        } elseif (isset($params['newOrderStatus'])) {
            $order = [
                'externalId' => $params['id_order'],
            ];

            $statusCode = $params['newOrderStatus']->id;

            if (array_key_exists($statusCode, $status) && !empty($status[$statusCode])) {
                $order['status'] = $status[$statusCode];
            }

            $order = RetailcrmTools::filter('RetailcrmFilterOrderStatusUpdate', $order, $params);

            if (isset($order['externalId']) && 1 < count($order)) {
                $this->api->ordersEdit($order);

                return true;
            }
        }

        return false;
    }

    public function hookActionPaymentCCAdd($params)
    {
        // todo add checks that module configured correctly

        $payments = array_filter(json_decode(Configuration::get(static::PAYMENT), true));
        $paymentType = false;
        $externalId = false;

        foreach ($this->reference->getSystemPaymentModules() as $paymentCMS) {
            if ($paymentCMS['name'] === $params['paymentCC']->payment_method
                && array_key_exists($paymentCMS['code'], $payments)
                && !empty($payments[$paymentCMS['code']])
            ) {
                $paymentType = $payments[$paymentCMS['code']];
                break;
            }
        }

        if (!$paymentType || empty($params['cart']) || empty((int) $params['cart']->id)) {
            return false;
        }

        $response = $this->api->ordersGet(RetailcrmTools::getCartOrderExternalId($params['cart']));

        if (false !== $response && isset($response['order'])) {
            $externalId = RetailcrmTools::getCartOrderExternalId($params['cart']);
        } else {
            if (version_compare(_PS_VERSION_, '1.7.1.0', '>=')) {
                $id_order = (int) Order::getIdByCartId((int) $params['cart']->id);
            } else {
                $id_order = (int) Order::getOrderByCartId((int) $params['cart']->id);
            }

            if (0 < $id_order) {
                // do not update payment if the order in Cart and OrderPayment aren't the same
                if ($params['paymentCC']->order_reference) {
                    $order = Order::getByReference($params['paymentCC']->order_reference)->getFirst();
                    if (!$order || $order->id !== $id_order) {
                        return false;
                    }
                }

                $response = $this->api->ordersGet($id_order);
                if (false !== $response && isset($response['order'])) {
                    $externalId = $id_order;
                }
            }
        }

        if (false === $externalId) {
            return false;
        }

        $status = (0 < round($params['paymentCC']->amount, 2) ? 'paid' : null);
        $orderCRM = $response['order'];

        if ($orderCRM && $orderCRM['payments']) {
            foreach ($orderCRM['payments'] as $orderPayment) {
                if ($orderPayment['type'] === $paymentType) {
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
            $createPayment = [
                'externalId' => $params['paymentCC']->id,
                'amount' => $params['paymentCC']->amount,
                'paidAt' => $params['paymentCC']->date_add,
                'type' => $paymentType,
                'status' => $status,
                'order' => [
                    'externalId' => $externalId,
                ],
            ];

            $this->api->ordersPaymentCreate($createPayment);
        }

        return true;
    }

    /**
     * Activate/deactivate module in marketplace retailCRM
     *
     * @param \RetailcrmProxy $apiClient
     * @param string $clientId
     * @param bool $active
     *
     * @return bool
     */
    private function integrationModule($apiClient, $clientId, $active = true)
    {
        $scheme = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $logo = 'https://s3.eu-central-1.amazonaws.com/retailcrm-billing/images/5b845ce986911-prestashop2.svg';
        $integrationCode = 'prestashop';
        $name = 'PrestaShop';
        $accountUrl = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $configuration = [
            'clientId' => $clientId,
            'code' => $integrationCode . '-' . $clientId,
            'integrationCode' => $integrationCode,
            'active' => $active,
            'name' => $name,
            'logo' => $logo,
            'accountUrl' => $accountUrl,
        ];
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
     * Loads data from modules list cache
     *
     * @return array|mixed
     */
    private static function requireModulesCache()
    {
        if (file_exists(static::getModulesCache())) {
            return require_once static::getModulesCache();
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

        if (false !== realpath($cacheDir) && is_dir($cacheDir)) {
            return $cacheDir . '/retailcrm_modules_cache.php';
        }

        return _PS_ROOT_DIR_ . '/retailcrm_modules_cache.php';
    }

    /**
     * Returns modules list, caches result. Recreates cache when needed.
     * Activity indicator in cache will be rewrited by current state.
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getCachedCmsModulesList()
    {
        $storedHash = (string) Configuration::get(static::MODULE_LIST_CACHE_CHECKSUM);
        $calculatedHash = md5(implode('#', Module::getModulesDirOnDisk(true)));

        if ($storedHash != $calculatedHash) {
            $serializedModules = [];
            static::$moduleListCache = Module::getModulesOnDisk(true);

            foreach (static::$moduleListCache as $module) {
                $serializedModules[] = json_encode($module);
            }

            Configuration::updateValue(static::MODULE_LIST_CACHE_CHECKSUM, $calculatedHash);
            static::writeModulesCache($serializedModules);

            return static::$moduleListCache;
        }

        try {
            if (is_array(static::$moduleListCache)) {
                return static::$moduleListCache;
            }

            $modulesList = static::requireModulesCache();

            if (false === $modulesList) {
                Configuration::updateValue(static::MODULE_LIST_CACHE_CHECKSUM, 'not exist');

                return static::getCachedCmsModulesList();
            }

            static::$moduleListCache = [];

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
        } catch (Error $exception) {
            RetailcrmLogger::writeCaller(__METHOD__, $exception->getMessage());
            RetailcrmLogger::writeNoCaller($exception->getTraceAsString());
        }

        Configuration::updateValue(static::MODULE_LIST_CACHE_CHECKSUM, 'exception');

        return static::getCachedCmsModulesList();
    }

    /**
     * Writes module list to cache file.
     *
     * @param $data
     */
    private static function writeModulesCache($data)
    {
        $file = fopen(static::getModulesCache(), 'w+');

        if (false !== $file) {
            fwrite($file, '<?php' . PHP_EOL);
            fwrite($file, '// Autogenerated module list cache for retailCRM' . PHP_EOL);
            fwrite($file, '// Delete this file if you cannot see some payment types in module' . PHP_EOL);
            fwrite($file, 'return ' . var_export($data, true) . ';' . PHP_EOL);
            fflush($file);
            fclose($file);
        }
    }
}
