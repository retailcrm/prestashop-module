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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmHistory
{
    /** @var \RetailcrmApiClientV5 */
    public static $api;
    public static $default_lang;

    private static $receiveOrderNumber;
    private static $sendOrderNumber;
    private static $statuses;
    private static $cartStatus;
    private static $deliveries;
    private static $payments;
    private static $deliveryDefault;
    private static $paymentDefault;
    private static $newItemsIdsByOrderId = [];
    private static $updateOrderIds = [];
    private static $orderFix = [];

    private static function init()
    {
        self::$receiveOrderNumber = (bool) (Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING));
        self::$sendOrderNumber = (bool) (Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_SENDING));
        self::$cartStatus = (string) (Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_STATUS'));
        self::$statuses = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_STATUS'), true)));
        self::$deliveries = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true)));
        self::$payments = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true)));
        self::$deliveryDefault = json_decode(Configuration::get('RETAILCRM_API_DELIVERY_DEFAULT'), true);
        self::$paymentDefault = json_decode(Configuration::get('RETAILCRM_API_PAYMENT_DEFAULT'), true);
    }

    /**
     * Get customers history
     *
     * @return mixed
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function customersHistory()
    {
        $lastSync = Configuration::get('RETAILCRM_LAST_CUSTOMERS_SYNC');

        $customerFix = [];

        $filter = false === $lastSync
            ? ['startDate' => date('Y-m-d H:i:s', strtotime('-1 days', strtotime(date('Y-m-d H:i:s'))))]
            : ['sinceId' => $lastSync];

        $request = new RetailcrmApiPaginatedRequest();
        $historyChanges = [];
        $history = $request
            ->setApi(self::$api)
            ->setMethod('customersHistory')
            ->setParams([$filter, '{{page}}'])
            ->setDataKey('history')
            ->setLimit(100)
            ->setPageLimit(50)
            ->execute()
            ->getData()
        ;

        if (0 < count($history)) {
            $historyChanges = static::filterHistory($history, 'customer');
            $end = end($history);
            Configuration::updateValue('RETAILCRM_LAST_CUSTOMERS_SYNC', $end['id']);
        }

        if (count($historyChanges)) {
            $customersHistory = RetailcrmHistoryHelper::assemblyCustomer($historyChanges);
            RetailcrmLogger::writeDebugArray(__METHOD__, ['Assembled history:', $customersHistory]);

            foreach ($customersHistory as $customerHistory) {
                $customerHistory = RetailcrmTools::filter(
                    'RetailcrmFilterCustomersHistory',
                    $customerHistory
                );

                if (isset($customerHistory['deleted']) && $customerHistory['deleted']) {
                    continue;
                }

                $customerBuilder = new RetailcrmCustomerBuilder();

                if (isset($customerHistory['externalId'])) {
                    $crmCustomerResponse = self::$api->customersGet($customerHistory['externalId']);

                    if (
                        null === $crmCustomerResponse
                        || !$crmCustomerResponse->isSuccessful()
                        || !$crmCustomerResponse->offsetExists('customer')
                    ) {
                        continue;
                    }

                    $customerData = RetailcrmTools::filter(
                        'RetailcrmFilterCustomersHistoryUpdate',
                        $crmCustomerResponse['customer']
                    );

                    $foundCustomer = new Customer($customerHistory['externalId']);
                    $customerAddress = new Address(RetailcrmTools::searchIndividualAddress($foundCustomer));
                    $addressBuilder = new RetailcrmCustomerAddressBuilder();

                    $addressBuilder
                        ->setCustomerAddress($customerAddress)
                    ;

                    $customerBuilder
                        ->setCustomer($foundCustomer)
                        ->setAddressBuilder($addressBuilder)
                        ->setDataCrm($customerData)
                        ->build()
                    ;

                    $customer = $customerBuilder->getData()->getCustomer();
                    $address = $customerBuilder->getData()->getCustomerAddress();

                    if (false === self::loadInPrestashop($customer, 'update')) {
                        continue;
                    }

                    if (!empty($address)) {
                        RetailcrmTools::assignAddressIdsByFields($customer, $address);

                        self::loadInPrestashop($address, 'update');
                    }
                } else {
                    $customerBuilder
                        ->setDataCrm($customerHistory)
                        ->build()
                    ;

                    $customer = $customerBuilder->getData()->getCustomer();

                    if (false === self::loadInPrestashop($customer, 'add')) {
                        continue;
                    }

                    $customerFix[] = [
                        'id' => $customerHistory['id'],
                        'externalId' => $customer->id,
                    ];

                    $customer->update();

                    if (isset($customerHistory['address'])) {
                        $address = $customerBuilder->getData()->getCustomerAddress();

                        $address->id_customer = $customer->id;

                        self::loadInPrestashop($address, 'add');
                    }
                }
            }

            if (!empty($customerFix)) {
                self::$api->customersFixExternalIds($customerFix);
            }

            return true;
        } else {
            return 'Nothing to sync';
        }
    }

    /**
     * Get orders history
     *
     * @return mixed
     *
     * @throws \PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    public static function ordersHistory()
    {
        $lastSync = Configuration::get('RETAILCRM_LAST_ORDERS_SYNC');
        $lastDate = Configuration::get('RETAILCRM_LAST_SYNC');

        if (false === $lastSync && false === $lastDate) {
            $filter = [
                'startDate' => date(
                    'Y-m-d H:i:s',
                    strtotime('-1 days', strtotime(date('Y-m-d H:i:s')))
                ),
            ];
        } elseif (false === $lastSync && false !== $lastDate) {
            $filter = ['startDate' => $lastDate];
        } elseif (false !== $lastSync) {
            $filter = ['sinceId' => $lastSync];
        } else {
            $filter = [];
        }

        $historyChanges = [];

        $request = new RetailcrmApiPaginatedRequest();
        $history = $request
            ->setApi(self::$api)
            ->setMethod('ordersHistory')
            ->setParams([$filter, '{{page}}'])
            ->setDataKey('history')
            ->setLimit(100)
            ->setPageLimit(50)
            ->execute()
            ->getData()
        ;

        if (0 < count($history)) {
            $historyChanges = static::filterHistory($history, 'order');
            $end = end($history);
            Configuration::updateValue('RETAILCRM_LAST_ORDERS_SYNC', $end['id']);
        }

        if (count($historyChanges)) {
            $orders = RetailcrmHistoryHelper::assemblyOrder($historyChanges);
            RetailcrmLogger::writeDebugArray(__METHOD__, ['Assembled history:', $orders]);

            self::updateOrders($orders);

            return true;
        } else {
            return 'Nothing to sync';
        }
    }

    private static function updateOrders(array $orders)
    {
        self::init();

        foreach ($orders as $orderHistory) {
            $orderHistory = RetailcrmTools::filter('RetailcrmFilterOrdersHistory', $orderHistory);
            $orderDeleted = isset($orderHistory['deleted']) && true == $orderHistory['deleted'];
            $orderExists = isset($orderHistory['externalId']);

            if ($orderDeleted) {
                continue;
            }

            if ($orderExists && (false !== stripos($orderHistory['externalId'], 'pscart_'))) {
                continue;
            }

            if (!$orderExists) {
                $newOrder = self::createNewOrder($orderHistory);
            } else {
                $orderToUpdate = new Order((int) $orderHistory['externalId']);
                $newOrder = self::updateOrder($orderHistory, $orderToUpdate);
            }
        }

        if (0 < count(self::$orderFix)) {
            self::$api->ordersFixExternalIds(self::$orderFix);
        }

        // collect orders id and reference if option sendOrderNumber enabled
        if (self::$sendOrderNumber) {
            self::$updateOrderIds[] = [
                'externalId' => $newOrder->id,
                'number' => $newOrder->reference,
            ];
        }

        if (0 < count(self::$updateOrderIds)) {
            foreach (self::$updateOrderIds as $upOrder) {
                self::$api->ordersEdit($upOrder);
            }
        }
    }

    private static function createNewOrder($orderHistory)
    {
        $order = self::getOrderFromCrm($orderHistory['id'], 'id');
        $order = RetailcrmTools::filter('RetailcrmFilterOrdersHistoryCreate', $order);

        $orderStatus = self::getInternalOrderStatus($order['status']);

        if (self::$cartStatus && (string) $orderStatus === (string) self::$cartStatus) {
            return;
        }

        $paymentTypeCRM = self::getPaymentTypeFromCRM($order);
        if ($paymentTypeCRM) {
            $paymentType = self::getModulePaymentId($paymentTypeCRM);
            if (!$paymentType) {
                RetailcrmLogger::writeDebug(__METHOD__, "Payment type $paymentType undefined");

                return;
            }
        }

        $customerId = self::getCustomerId($order);
        $customerBuilder = self::getCustomerById($order, $customerId);
        $customer = $customerBuilder->getData()->getCustomer();
        $addressInvoice = $customerBuilder->getData()->getCustomerAddress();

        if (empty($customer->id) && !empty($customer->email)) {
            $customer->id = self::getCustomerIdByEmail($customer->email);
        }
        self::loadInPrestashop($customer, 'save');

        if (null !== $addressInvoice) {
            self::updateAddressInvoice($addressInvoice, $customer, $order);
        }

        $addressDelivery = self::createAddress($order, $customer);

        $deliveryType = self::getDeliveryType($order);

        $cart = self::createCart($customer, $addressDelivery, $addressInvoice, $deliveryType);

        $products = [];
        if (!empty($order['items'])) {
            $products = self::createProducts($order['items'], $addressDelivery);
        }

        $cart = self::addProductsToCart($cart, $products);

        $newOrder = self::createOrder(
            $cart,
            $customer,
            $order,
            $deliveryType,
            $paymentType,
            $addressDelivery,
            $addressInvoice,
            $orderStatus,
            $paymentTypeCRM
        );

        self::createPayments($order, $newOrder);

        self::saveCarrier($newOrder->id, $deliveryType, $order['delivery']['cost']);

        if (!empty($order['items'])) {
            self::createOrderDetails($order, $newOrder);
        }

        // collect order ids for single fix request
        self::$orderFix[] = ['id' => $order['id'], 'externalId' => $newOrder->id];

        return $newOrder;
    }

    private static function updateOrder($order, $orderToUpdate)
    {
        if (!Validate::isLoadedObject($orderToUpdate)) {
            return;
        }

        $order = RetailcrmTools::filter('RetailcrmFilterOrdersHistoryUpdate', $order, [
            'orderToUpdate' => $orderToUpdate,
        ]
        );

        self::handleCustomerDataChange($orderToUpdate, $order);

        self::checkDeliveryChanges($order, $orderToUpdate);

        self::checkDeliveryTypeAndCost($order, $orderToUpdate);

        self::checkPaymentType($order, $orderToUpdate);

        self::changeOrderTotals($order, $orderToUpdate);

        self::checkOrderStatus($order, $orderToUpdate);

        // update order number in PS if receiveOrderNumber option (CRM->PS) enabled

        if (isset($order['number']) && self::$receiveOrderNumber) {
            $orderToUpdate->reference = $order['number'];
            $orderToUpdate->update();
        }

        return $orderToUpdate;
    }

    /**
     * Updates sinceId for orders or customers to the latest value
     *
     * @param string $entity Can be either 'orders' or 'customers'
     *
     * @return bool
     */
    public static function updateSinceId($entity)
    {
        if ('orders' === $entity) {
            $key = 'RETAILCRM_LAST_ORDERS_SYNC';
            $method = 'ordersHistory';
        } elseif ('customers' === $entity) {
            $key = 'RETAILCRM_LAST_CUSTOMERS_SYNC';
            $method = 'customersHistory';
        } else {
            return false;
        }

        $currentSinceID = Configuration::get($key);
        RetailcrmLogger::writeDebug(__METHOD__, "Current $entity sinceId: $currentSinceID");

        $historyResponse = call_user_func_array(
            [self::$api, $method],
            [
                ['sinceId' => $currentSinceID],
                null,
                20,
            ]
        );

                        $corporateCustomerBuilder
                            ->setCustomer(new Customer($customerId))
                            ->setDataCrm($dataOrder)
                            ->extractCompanyDataFromOrder($order)
                            ->build();

                        $customer = $corporateCustomerBuilder->getData()->getCustomer();
                        $addressInvoice = $corporateCustomerBuilder->getData()->getCustomerAddress();
                    } else {
                        $customerBuilder = new RetailcrmCustomerBuilder();

                        if ($customerId) {
                            $customerBuilder->setCustomer(new Customer($customerId));
                        }

                        $customerBuilder
                            ->setDataCrm($order['customer'])
                            ->build();

                        $customer = $customerBuilder->getData()->getCustomer();
                        $addressInvoice = $customerBuilder->getData()->getCustomerAddress();
                    }

                    if (empty($customer->id) && !empty($customer->email)) {
                        $customer->id = self::getCustomerIdByEmail($customer->email);
                    }

                    if (self::loadInCMS($customer, 'save') === false) {
                        continue;
                    }
                    if ($addressInvoice !== null) {
                        if (RetailcrmTools::validateEntity($addressInvoice)) {
                            $addressInvoice->id_customer = $customer->id;
                            RetailcrmTools::assignAddressIdsByFields($customer, $addressInvoice);

                            if (empty($addressInvoice->id)) {
                                self::loadInCMS($addressInvoice, 'add');

                                if (!empty($order['company']) && RetailcrmTools::isCorporateEnabled()) {
                                    self::$api->customersCorporateAddressesEdit(
                                        $order['customer']['id'],
                                        $order['company']['address']['id'],
                                        array_merge(
                                            $order['company']['address'],
                                            ['externalId' => $addressInvoice->id]
                                        ),
                                        'id',
                                        'id'
                                    );

                                    time_nanosleep(0, 20000000);
                                }
                            } else {
                                self::loadInCMS($addressInvoice, 'save');
                            }
                        }
                    }

        if ($historyResponse instanceof RetailcrmApiResponse && $historyResponse->offsetExists('pagination')) {
            $lastPage = $historyResponse['pagination']['totalPageCount'];
            if (1 < $lastPage) {
                $historyResponse = call_user_func_array(
                    [self::$api, $method],
                    [
                        ['sinceId' => $currentSinceID],
                        $lastPage,
                        20,
                    ]
                );
            }

            if ($historyResponse instanceof RetailcrmApiResponse
                && $historyResponse->offsetExists('history')
                && !empty($historyResponse['history'])
            ) {
                $history = $historyResponse['history'];
                $lastSinceId = end($history)['id'];

                if ($currentSinceID !== (string) $lastSinceId) {
                    RetailcrmLogger::writeDebug(__METHOD__, "Updating to: $lastSinceId");
                    Configuration::updateValue($key, $lastSinceId);
                }
            }
        }

        return true;
    }

    /**
     * Returns retailCRM order by id or by externalId.
     * It returns only order data, not ApiResponse or something.
     *
     * @param string $id Order identifier
     * @param string $by Search field (default: 'externalId')
     *
     * @return array
     */
    protected static function getOrderFromCrm($id, $by = 'externalId')
    {
        $crmOrderResponse = self::$api->ordersGet($id, $by);

        if (
            null !== $crmOrderResponse
            && $crmOrderResponse->isSuccessful()
            && $crmOrderResponse->offsetExists('order')
        ) {
            return (array) $crmOrderResponse['order'];
        }

        return [];
    }

    /**
     * Sets all needed data for customer switch to switcher state
     *
     * @param array $crmCustomer
     * @param \RetailcrmCustomerSwitcherState $data
     * @param bool $isContact
     */
    protected static function prepareChangeToIndividual($crmCustomer, $data, $isContact = false)
    {
        RetailcrmLogger::writeDebugArray(
            __METHOD__,
            [
                'Using this individual person data in order to set it into order,',
                $data->getOrder()->id,
                ': ',
                $crmCustomer,
            ]
        );

        if ($isContact) {
            $data->setNewContact($crmCustomer);
        } else {
            $data->setNewCustomer($crmCustomer);
        }
    }

    /**
     * Sets order status to 'outOfStock' and returns CRM status or false if status will not change
     *
     * @param array $crmOrder
     * @param \Order $cmsOrder
     *
     * @return string|false
     */
    private static function setOutOfStockStatus($crmOrder, $cmsOrder)
    {
        $statusArray = json_decode(
            Configuration::get(RetailCRM::OUT_OF_STOCK_STATUS),
            true
        );

        if (isset($crmOrder['fullPaidAt']) && !empty($crmOrder['fullPaidAt'])) {
            $outOfStockType = $statusArray['out_of_stock_paid'];

            if ('' == $outOfStockType) {
                return false;
            }
        } else {
            $outOfStockType = $statusArray['out_of_stock_not_paid'];

            if ('' == $outOfStockType) {
                return false;
            }
        }

        if (self::$statuses[$outOfStockType] != $cmsOrder->current_state) {
            $orderHistory = new OrderHistory();
            $orderHistory->id_order = $cmsOrder->id;
            $orderHistory->id_order_state = self::$statuses[$outOfStockType];
            $orderHistory->date_add = date('Y-m-d H:i:s');

            self::loadInPrestashop($orderHistory, 'save');

            RetailcrmLogger::writeDebug(
                __METHOD__,
                sprintf(
                    '<Order ID: %d> %s::%s',
                    $cmsOrder->id,
                    get_class($orderHistory),
                    'changeIdOrderState'
                )
            );

            $orderHistory->changeIdOrderState(
                (int) self::$statuses[$outOfStockType],
                $cmsOrder->id,
                true
            );

            return $outOfStockType;
        }

        return false;
    }

    /**
     * Handle customer data change (from individual to corporate, company change, etc.)
     *
     * @param \Order $order
     * @param array $historyOrder
     *
     * @return bool true if customer change happened; false otherwise
     */
    private static function handleCustomerDataChange($order, $historyOrder)
    {
        $handled = false;
        $crmOrder = [];
        $newCustomerId = null;
        $switcher = new RetailcrmCustomerSwitcher();
        $data = new RetailcrmCustomerSwitcherState();
        $data->setOrder($order);

        RetailcrmLogger::writeDebug(
            __METHOD__,
            $historyOrder
        );

        if (isset($historyOrder['customer'])) {
            $crmOrder = self::getOrderFromCrm($historyOrder['id'], 'id');

            if (empty($crmOrder)) {
                RetailcrmLogger::writeCaller(__METHOD__, sprintf(
                    'Cannot get order data from retailCRM. Skipping customer change. History data: %s',
                    print_r($historyOrder, true)
                ));

                return false;
            }

            $newCustomerId = $historyOrder['customer']['id'];
            $isChangedToRegular = RetailcrmTools::isCustomerChangedToRegular($historyOrder);
            $isChangedToCorporate = RetailcrmTools::isCustomerChangedToLegal($historyOrder);

            if (!$isChangedToRegular && !$isChangedToCorporate) {
                $isChangedToCorporate = RetailcrmTools::isCrmOrderCorporate($crmOrder);
                $isChangedToRegular = !$isChangedToCorporate;
            }

            if ($isChangedToRegular) {
                self::prepareChangeToIndividual(
                    RetailcrmTools::arrayValue($crmOrder, 'customer', []),
                    $data
                );
            }
        }

        if (isset($historyOrder['contact'])) {
            $newCustomerId = $historyOrder['contact']['id'];

            if (empty($crmOrder)) {
                $crmOrder = self::getOrderFromCrm($historyOrder['id'], 'id');
            }

            if (empty($crmOrder)) {
                RetailcrmLogger::writeCaller(__METHOD__, sprintf(
                    'Cannot get order data from retailCRM. Skipping customer change. History data: %s',
                    print_r($historyOrder, true)
                ));

                return false;
            }

            if (RetailcrmTools::isCrmOrderCorporate($crmOrder)) {
                self::prepareChangeToIndividual(
                    RetailcrmTools::arrayValue($crmOrder, 'contact', []),
                    $data,
                    true
                );

                $data->setNewCustomer([]);
            }
        }

        if (isset($historyOrder['company'])) {
            if (!empty($crmOrder['company'])) {
                $data->setNewCompany($crmOrder['company']);
            } else {
                $data->setNewCompany($historyOrder['company']);
            }
        }

        if ($data->feasible()) {
            if (!empty($crmOrder) && !empty($crmOrder['delivery']) && !empty($crmOrder['delivery']['address'])) {
                $data->setCrmOrderShippingAddress($crmOrder['delivery']['address']);
            }

            try {
                $result = $switcher
                    ->setData($data)
                    ->build()
                    ->getResult()
                ;
                $result->save();
                $handled = true;
            } catch (\Exception $exception) {
                $errorMessage = sprintf(
                    'Error switching order externalId=%s to customer id=%s (new company: id=%s %s). Reason: %s',
                    $historyOrder['externalId'],
                    $newCustomerId,
                    isset($historyOrder['company']) ? $historyOrder['company']['id'] : '',
                    isset($historyOrder['company']) ? $historyOrder['company']['name'] : '',
                    $exception->getMessage()
                );
                RetailcrmLogger::writeCaller(__METHOD__, $errorMessage);
                RetailcrmLogger::writeDebug(__METHOD__, sprintf(
                    '%s%s%s',
                    $errorMessage,
                    PHP_EOL,
                    $exception->getTraceAsString()
                ));
                $handled = false;
            }
        }

        return $handled;
    }

    /**
     * Delete order product from order by product
     *
     * @param $order_id
     * @param $product_id
     * @param $product_attribute_id
     * @param $id_order_detail
     *
     * @return void
     */
    private static function deleteOrderDetailByProduct($order_id, $product_id, $product_attribute_id, $id_order_detail)
    {
        Db::getInstance()->execute('
            DELETE FROM ' . _DB_PREFIX_ . 'order_detail
            WHERE id_order = ' . pSQL((int) $order_id) . '
            AND product_id = ' . pSQL((int) $product_id) . '
            AND product_attribute_id = ' . pSQL((int) $product_attribute_id) . '
            AND id_order_detail = ' . pSQL((int) $id_order_detail)
        );
    }

    private static function getNewOrderDetailId()
    {
        return Db::getInstance()->getRow('
            SELECT MAX(id_order_detail) FROM  ' . _DB_PREFIX_ . 'order_detail'
        );
    }

    /**
     * load and catch exception
     *
     * @param \ObjectModel|\ObjectModelCore $object
     * @param string $action
     *
     * @return bool
     */
    private static function loadInPrestashop($object, $action)
    {
        try {
            $prefix = $object->id;
            if (empty($object->id)) {
                if (property_exists(get_class($object), 'id_customer')) {
                    $prefix = sprintf('Customer ID: %d', $object->id_customer);
                }

                if (property_exists(get_class($object), 'id_order')) {
                    $prefix = sprintf('Order ID: %d', $object->id_order);
                }
            }

            RetailcrmLogger::writeDebug(
                __METHOD__,
                sprintf(
                    '<%s> %s::%s',
                    $prefix,
                    get_class($object),
                    $action
                )
            );
            $object->$action();
        } catch (PrestaShopException $e) {
            RetailcrmLogger::writeCaller(
                'loadInCMS',
                sprintf(
                    ' > %s %s',
                    (string) $action,
                    $e->getMessage()
                )
            );
            RetailcrmLogger::writeNoCaller($e->getTraceAsString());

            return false;
        }

        return true;
    }

    /**
     * Filters out history by these terms:
     *  - Changes from current API key will be added only if CMS changes are more actual than history.
     *  - All other changes will be merged as usual.
     * It fixes these problems:
     *  - Changes from current API key are merged when it's not needed.
     *  - Changes from CRM can overwrite more actual changes from CMS due to ignoring current API key changes.
     *
     * @param array $historyEntries Raw history from CRM
     * @param string $recordType Entity field name, e.g. `customer` or `order`.
     *
     * @return array
     */
    private static function filterHistory($historyEntries, $recordType)
    {
        $history = [];
        $organizedHistory = [];
        $notOurChanges = [];

        foreach ($historyEntries as $entry) {
            if (!isset($entry[$recordType]['externalId'])) {
                if ('api' == $entry['source']
                    && isset($change['apiKey']['current'])
                    && true == $entry['apiKey']['current']
                    && 'externalId' != $entry['field']
                ) {
                    continue;
                } else {
                    $history[] = $entry;
                }

                continue;
            }

            $externalId = $entry[$recordType]['externalId'];
            $field = $entry['field'];

            if (!isset($organizedHistory[$externalId])) {
                $organizedHistory[$externalId] = [];
            }

            if (!isset($notOurChanges[$externalId])) {
                $notOurChanges[$externalId] = [];
            }

            if (
                'api' == $entry['source']
                && isset($entry['apiKey']['current'])
                && true == $entry['apiKey']['current']
            ) {
                if (isset($notOurChanges[$externalId][$field]) || 'externalId' == $field || 'status' == $field) {
                    $organizedHistory[$externalId][] = $entry;
                }
            } else {
                $organizedHistory[$externalId][] = $entry;
                $notOurChanges[$externalId][$field] = true;
            }
        }

        unset($notOurChanges);

        foreach ($organizedHistory as $historyChunk) {
            $history = array_merge($history, $historyChunk);
        }

        return $history;
    }

    /**
     * Returns customer ID by email if such customer was found in the DB.
     *
     * @param string $customerEmail
     *
     * @return int
     */
    private static function getCustomerIdByEmail($customerEmail)
    {
        if (!empty($customerEmail)) {
            $item = Customer::getCustomersByEmail($customerEmail);

            if (is_array($item) && 0 < count($item)) {
                $item = reset($item);

                return (int) $item['id_customer'];
            }
        }

        return 0;
    }

    /**
     * Returns array with product id and product attribute id.
     * Returns 0 as attribute id if attribute is not present.
     * Returns array(0, 0) in case of failure.
     *
     * @param $item
     *
     * @return array
     */
    private static function parseItemExternalId($item)
    {
        if (isset($item['externalIds'])) {
            foreach ($item['externalIds'] as $externalId) {
                if ('prestashop' == $externalId['code']) {
                    return static::parseItemExternalIdString($externalId['value']);
                }
            }
        } else {
            return static::parseItemExternalIdString($item['offer']['externalId']);
        }

        return static::parseItemExternalIdString('0#0_0');
    }

    /**
     * Parse item externalId
     *
     * @param $externalIdString
     *
     * @return array
     */
    private static function parseItemExternalIdString($externalIdString)
    {
        $parsed = explode('_', $externalIdString);
        $data = [
            'product_id' => 0,
            'product_attribute_id' => 0,
            'id_order_detail' => 0,
        ];

        if (0 < count($parsed)) {
            $productIdParsed = explode('#', $parsed[0]);

            if (2 == count($productIdParsed)) {
                $data['product_id'] = $productIdParsed[0];
                $data['product_attribute_id'] = $productIdParsed[1];
            } elseif (1 == count($productIdParsed)) {
                $data['product_id'] = $parsed[0];
            }

            if (2 == count($parsed)) {
                $data['id_order_detail'] = $parsed[1];
            }
        }

        return $data;
    }

    /**
     * Returns the oldest active employee id. For order history record.
     *
     * @return false|string|null
     */
    private static function getFirstEmployeeId()
    {
        return Db::getInstance()->getValue('
            SELECT `id_employee`
            FROM `' . _DB_PREFIX_ . 'employee`
            WHERE `active` = 1
            ORDER BY `id_employee` ASC
		');
    }

    /**
     * Removes quotes on string start and end
     *
     * @param $str
     *
     * @return false|string
     */
    private static function removeEdgeQuotes($str)
    {
        if (2 <= strlen($str)) {
            $newStr = $str;

            if ('\'' == $newStr[0] && '\'' == $newStr[strlen($newStr) - 1]) {
                $newStr = substr($newStr, 1, strlen($newStr) - 2);
            }

            return $newStr;
        }

        return $str;
    }

    /**
     * Sets product_name in OrderDetail through validation
     *
     * @param OrderDetail|\OrderDetailCore $object
     * @param string $name
     *
     * @throws \PrestaShopException
     */
    private static function setOrderDetailProductName(&$object, $name)
    {
        $object->product_name = static::removeEdgeQuotes($name);

        if (true !== $object->validateField('product_name', $object->product_name)) {
            $object->product_name = implode('', ['\'', $name, '\'']);
        }
    }

    private static function updateOrderItems($orderId, $newItemsIds)
    {
        $upOrderItems = [
            'externalId' => $orderId,
        ];

        $prestashopOrder = new Order($orderId);
        foreach ($prestashopOrder->getProducts() as $item) {
            if (isset($item['product_attribute_id']) && 0 < $item['product_attribute_id']) {
                $productId = $item['product_id'] . '#' . $item['product_attribute_id'];
            } else {
                $productId = $item['product_id'];
            }

            $crmItem = [
                'externalIds' => [
                    [
                        'code' => 'prestashop',
                        'value' => $productId . '_' . $item['id_order_detail'],
                    ],
                ],
            ];

            if (isset($newItemsIds[$item['id_order_detail']])) {
                $crmItem['id'] = $newItemsIds[$item['id_order_detail']];
            }

            $upOrderItems['items'][] = $crmItem;
        }

        unset($prestashopOrder);
        if (isset($upOrderItems['items'])) {
            self::$api->ordersEdit($upOrderItems);
        }
    }

    private static function createProducts($crmOrderItems, $addressDelivery)
    {
        $products = [];
        foreach ($crmOrderItems as $item) {
            if (RetailcrmOrderBuilder::isGiftItem($item)) {
                continue;
            }

            if (isset($item['offer']['externalId'])) {
                $productId = explode('#', $item['offer']['externalId']);
                $product = [];
                $product['id_product'] = (int) $productId[0];
                $product['id_product_attribute'] = !empty($productId[1]) ? $productId[1] : 0;
                $product['quantity'] = $item['quantity'];
                $product['id_address_delivery'] = isset($addressDelivery->id) ? (int) $addressDelivery->id : 0;
                $products[] = $product;
            }
        }

        return $products;
    }

    private static function createOrder($cart, $customer, $order, $deliveryType, $paymentType, $addressDelivery, $addressInvoice, $orderStatus, $paymentTypeCRM)
    {
        $default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $newOrder = new Order();
        $newOrder->id_shop = Context::getContext()->shop->id;
        $newOrder->id_shop_group = (int) (Context::getContext()->shop->id_shop_group);
        $newOrder->id_address_delivery = isset($addressDelivery->id) ? (int) $addressDelivery->id : 0;
        $newOrder->id_address_invoice = isset($addressInvoice->id) ? (int) $addressInvoice->id : 0;
        $newOrder->id_cart = (int) $cart->id;
        $newOrder->id_currency = $default_currency;
        $newOrder->id_lang = self::$default_lang;
        $newOrder->id_customer = (int) $customer->id;
        $orderNumber = self::$receiveOrderNumber ? $order['number'] : $newOrder->generateReference();
        $newOrder->reference = $orderNumber;
        $newOrder->id_carrier = (int) $deliveryType;

        if (isset(self::$payments[$paymentTypeCRM])) {
            $newOrder->module = self::$payments[$paymentTypeCRM];
        }

        $newOrder->payment = $paymentType;

        // totals
        $totalPaid = $order['totalSumm'];
        $orderTotalProducts = array_reduce($order['items'], function ($sum, $it) {
            $sum += $it['initialPrice'] * $it['quantity'];

            return $sum;
        });
        $deliveryCost = $order['delivery']['cost'];
        $totalDiscount = round($deliveryCost + $orderTotalProducts - $totalPaid, 2);

        $newOrder->total_discounts = $totalDiscount;
        $newOrder->total_discounts_tax_incl = $totalDiscount;
        $newOrder->total_discounts_tax_excl = $totalDiscount;
        $newOrder->total_paid = $totalPaid;
        $newOrder->total_paid_tax_incl = $totalPaid;
        $newOrder->total_paid_tax_excl = $totalPaid;
        $newOrder->total_paid_real = $totalPaid;
        $newOrder->total_products = (int) $orderTotalProducts;
        $newOrder->total_products_wt = (int) $orderTotalProducts;
        $newOrder->total_shipping = $deliveryCost;
        $newOrder->total_shipping_tax_incl = $deliveryCost;
        $newOrder->total_shipping_tax_excl = $deliveryCost;
        $newOrder->conversion_rate = 1.000000;
        $newOrder->current_state = (int) $orderStatus;

        if (!empty($order['delivery']['date'])) {
            $newOrder->delivery_date = $order['delivery']['date'];
        }

        $newOrder->date_add = $order['createdAt'];
        $newOrder->date_upd = $order['createdAt'];
        $newOrder->invoice_date = $order['createdAt'];
        $newOrder->valid = 1;
        $newOrder->secure_key = md5(time());

        try {
            RetailcrmLogger::writeDebug(__METHOD__, sprintf(
                    '<Customer ID: %d> %s::%s',
                    $newOrder->id_customer,
                    get_class($newOrder),
                    'add'
                )
            );
            $newOrder->add(false, false);

            $newOrderHistoryRecord = new OrderHistory(null, static::$default_lang, Context::getContext()->shop->id);

            $newOrderHistoryRecord->id_order = $newOrder->id;
            $newOrderHistoryRecord->id_order_state = $newOrder->current_state;
            $newOrderHistoryRecord->id_employee = static::getFirstEmployeeId();
            $newOrderHistoryRecord->date_add = date('Y-m-d H:i:s');
            $newOrderHistoryRecord->date_upd = $newOrderHistoryRecord->date_add;

            self::loadInPrestashop($newOrderHistoryRecord, 'add');
        } catch (\Exception $e) {
            RetailcrmLogger::writeCaller(
                __METHOD__,
                sprintf('Error adding order id=%d: %s', $order['id'], $e->getMessage())
            );

            RetailcrmLogger::writeNoCaller($e->getTraceAsString());
        }

        return $newOrder;
    }

    private static function createOrderDetails($order, $newOrder)
    {
        $newItemsIds = [];
        foreach ($order['items'] as $item) {
            if (!isset($item['offer']['externalId'])) {
                continue;
            }
            $product = new Product((int) $item['offer']['externalId'], false, self::$default_lang);
            $product_id = $item['offer']['externalId'];
            $product_attribute_id = 0;

            if (RetailcrmOrderBuilder::isGiftItem($item)) {
                continue;
            }

            if (false !== strpos($item['offer']['externalId'], '#')) {
                $externalIds = explode('#', $item['offer']['externalId']);
                $product_id = $externalIds[0];
                $product_attribute_id = $externalIds[1];
            }

            if (0 != $product_attribute_id) {
                $productName = htmlspecialchars(
                    strip_tags(Product::getProductName($product_id, $product_attribute_id))
                );
            } else {
                $productName = htmlspecialchars(strip_tags($product->name));
            }

            $productPrice = round($item['initialPrice'], 2);

            $orderDetail = new OrderDetail();
            static::setOrderDetailProductName($orderDetail, $productName);

            $orderDetail->id_order = $newOrder->id;
            $orderDetail->id_order_invoice = $newOrder->invoice_number;
            $orderDetail->id_shop = Context::getContext()->shop->id;

            $orderDetail->product_id = (int) $product_id;
            $orderDetail->product_attribute_id = (int) $product_attribute_id;
            $orderDetail->product_reference = implode('', ['\'', $product->reference, '\'']);

            $orderDetail->product_price = $productPrice;
            $orderDetail->original_product_price = $productPrice;
            $orderDetail->product_quantity = (int) $item['quantity'];
            $orderDetail->product_quantity_in_stock = (int) $item['quantity'];

            $orderDetail->total_price_tax_incl = $productPrice * $orderDetail->product_quantity;
            $orderDetail->unit_price_tax_incl = $productPrice;

            $orderDetail->id_warehouse = !empty($newOrder->id_warehouse) ? $newOrder->id_warehouse : 0;

            if (!$product->checkQty($orderDetail->product_quantity)) {
                self::setOutOfStockStatus($order, $newOrder);
            }

            StockAvailable::updateQuantity(
                $product_id,
                $product_attribute_id,
                -1 * $orderDetail->product_quantity,
                Context::getContext()->shop->id
            );

            if (self::loadInPrestashop($orderDetail, 'save')) {
                $newItemsIds[Db::getInstance()->Insert_ID()] = $item['id'];
            }

            unset($orderDetail);
        }

        // update order items ids in crm
        self::$newItemsIdsByOrderId[$newOrder->id] = $newItemsIds;

        // update order items ids in crm
        if (!empty(self::$newItemsIdsByOrderId)) {
            foreach (self::$newItemsIdsByOrderId as $newOrderId => $newItemsIds) {
                static::updateOrderItems($newOrderId, $newItemsIds);
            }
        }
    }

    private static function checkOrderStatus($order, $orderToUpdate)
    {
        $updateOrderStatuses = self::getOrderStatuses($order, $orderToUpdate);

        $orderStatus = $order['status'];
        $orderStatusChanged = isset(self::$statuses[$orderStatus]) && !empty(self::$statuses[$orderStatus]) && (self::$statuses[$orderStatus] != $orderToUpdate->current_state);

        if (!empty($orderStatus) && !isset($updateOrderStatuses[$orderToUpdate->id]) && $orderStatusChanged) {
            $orderHistory = new OrderHistory();
            $orderHistory->id_employee = 0;
            $orderHistory->id_order = $orderToUpdate->id;
            $orderHistory->id_order_state = self::$statuses[$orderStatus];
            $orderHistory->date_add = date('Y-m-d H:i:s');

            self::loadInPrestashop($orderHistory, 'save');
            RetailcrmLogger::writeDebug(
                __METHOD__,
                sprintf(
                    '<Order ID: %d> %s::%s',
                    $orderToUpdate->id,
                    get_class($orderHistory),
                    'changeIdOrderState'
                )
            );

            $orderHistory->changeIdOrderState(self::$statuses[$orderStatus], $orderToUpdate->id, true);
        }
    }

    private static function getPaymentsCms(RetailcrmReferences $references)
    {
        $paymentsCMS = [];
        foreach ($references->getSystemPaymentModules() as $paymentCMS) {
            $paymentsCMS[$paymentCMS['code']] = $paymentCMS['name'];
        }

        return $paymentsCMS;
    }

    private static function createAddress($order, $customer)
    {
        $addressBuilder = new RetailcrmCustomerAddressBuilder();
        $address = $addressBuilder
            ->setIdCustomer($customer->id)
            ->setDataCrm(isset($order['delivery']['address']) ? $order['delivery']['address'] : [])
            ->setFirstName(isset($order['firstName']) ? $order['firstName'] : null)
            ->setLastName(isset($order['lastName']) ? $order['lastName'] : null)
            ->setPhone(isset($order['phone']) ? $order['phone'] : null)
            ->build()
            ->getData()
        ;

        if (RetailcrmTools::validateEntity($address)) {
            RetailcrmTools::assignAddressIdsByFields($customer, $address);
            self::loadInPrestashop($address, 'add');
        }

        return $address;
    }

    private static function checkDeliveryChanges($order, $orderToUpdate)
    {
        if (isset($order['delivery']['address'])
            || isset($order['firstName'])
            || isset($order['lastName'])
            || isset($order['phone'])
        ) {
            $address = self::createOrderAddress($order, $orderToUpdate);

            if (RetailcrmTools::validateEntity($address, $orderToUpdate)) {
                $address->id = null;
                RetailcrmTools::assignAddressIdsByFields(new Customer($orderToUpdate->id_customer), $address);

                if (null === $address->id) {
                    if (1 > $orderToUpdate->id_address_delivery) {
                        self::loadInPrestashop($address, 'add');
                        $orderToUpdate->id_address_delivery = $address->id;
                        self::loadInPrestashop($order, 'update');
                    } else {
                        if (version_compare(_PS_VERSION_, '1.7.7', '<')) {
                            self::loadInPrestashop($address, 'add');

                            $orderToUpdate->id_address_delivery = $address->id;
                            self::loadInPrestashop($orderToUpdate, 'update');
                        } else {
                            $address->id = $orderToUpdate->id_address_delivery;
                            self::loadInPrestashop($address, 'update');
                        }
                    }
                } elseif ($address->id !== $orderToUpdate->id_address_delivery) {
                    RetailcrmLogger::writeDebug(__METHOD__, sprintf(
                        'Binding to existing address [%d]',
                        $address->id
                    ));

                    $orderToUpdate->id_address_delivery = $address->id;
                    self::loadInPrestashop($orderToUpdate, 'update');
                }
            }
        }
    }

    private static function createOrderAddress($order, $orderToUpdate)
    {
        $addressBuilder = new RetailcrmCustomerAddressBuilder();

        $orderAddress = new Address($orderToUpdate->id_address_delivery);
        $orderFirstName = $orderAddress->firstname;
        $orderLastName = $orderAddress->lastname;
        $orderAddressCrm = isset($order['delivery']['address']) ? $order['delivery']['address'] : [];
        $orderPhone = $orderAddress->phone;

        if (
            RetailcrmHistoryHelper::isAddressLineChanged($orderAddressCrm)
            || !Validate::isLoadedObject($orderAddress)
        ) {
            $infoOrder = self::getOrderFromCrm($order['externalId']);
            if (isset($infoOrder['delivery']['address'])) {
                // array_replace used to save changes, made by custom filters
                $orderAddressCrm = array_replace(
                    $infoOrder['delivery']['address'],
                    $orderAddressCrm
                );
            }

            if (isset($infoOrder['firstName'])) {
                $orderFirstName = $infoOrder['firstName'];
            }
            if (isset($infoOrder['lastName'])) {
                $orderLastName = $infoOrder['lastName'];
            }
            if (isset($infoOrder['phone'])) {
                $orderPhone = $infoOrder['phone'];
            }
        }

        // may override actual order data, but used to save changes, made by custom filters
        if (isset($order['firstName'])) {
            $orderFirstName = $order['firstName'];
        }
        if (isset($order['lastName'])) {
            $orderLastName = $order['lastName'];
        }
        if (isset($order['phone'])) {
            $orderPhone = $order['phone'];
        }

        return $addressBuilder
            ->setCustomerAddress($orderAddress)
            ->setIdCustomer($orderToUpdate->id_customer)
            ->setDataCrm($orderAddressCrm)
            ->setFirstName($orderFirstName)
            ->setLastName($orderLastName)
            ->setPhone($orderPhone)
            ->setAlias($orderAddress->alias)
            ->build()
            ->getData()
        ;
    }

    private static function checkDeliveryTypeAndCost($order, $orderToUpdate)
    {
        if (!empty($order['delivery']['code']) || !empty($order['delivery']['cost'])) {
            $orderDeliveryCode = !empty($order['delivery']['code']) ? $order['delivery']['code'] : null;
            $orderDeliveryCost = !empty($order['delivery']['cost']) ? $order['delivery']['cost'] : null;

            if (
                (
                    null !== $orderDeliveryCode
                    && isset(self::$deliveries[$orderDeliveryCode])
                    && null !== self::$deliveries[$orderDeliveryCode]
                    && self::$deliveries[$orderDeliveryCode] !== $orderToUpdate->id_carrier
                )
                || null !== $orderDeliveryCost
            ) {
                $orderCarrier = self::getOrderCarrier($orderToUpdate);

                if (null != $orderDeliveryCode) {
                    $orderCarrier->id_carrier = self::$deliveries[$orderDeliveryCode];
                }

                if (null != $orderDeliveryCost) {
                    $orderCarrier->shipping_cost_tax_incl = $orderDeliveryCost;
                    $orderCarrier->shipping_cost_tax_excl = $orderDeliveryCost;
                }

                $orderCarrier->id_order = $orderToUpdate->id;

                self::loadInPrestashop($orderCarrier, 'update');
            }
        }
    }

    private static function changeOrderTotals($order, $orderToUpdate)
    {
        if (isset($order['items']) || isset($order['delivery']['cost'])) {
            // get full order
            if (empty($infoOrder)) {
                $infoOrder = self::getOrderFromCrm($order['externalId']);
            }

            if (isset($order['items']) && is_array($order['items'])) {
                $order = self::cleanDeletedItems($order, $orderToUpdate);

                self::checkItemsQuantityAndDiscount($order, $orderToUpdate);

                self::checkNewItems($order, $orderToUpdate);
            }

            $orderToUpdate = self::changeTotals($infoOrder, $orderToUpdate);

            self::loadInPrestashop($orderToUpdate, 'update');
        }
    }

    private static function checkPaymentType($order, $orderToUpdate)
    {
        if (empty($order['payments'])) {
            return;
        }
        foreach ($order['payments'] as $payment) {
            if (isset($payment['externalId'])) {
                continue;
            }
            if (!isset($payment['status'])) {
                continue;
            }
            if ('paid' !== $payment['status']) {
                continue;
            }

            $paymentType = self::getPaymentType($payment);

            $orderToUpdate->payment = $paymentType;
            $orderPayment = new OrderPayment();
            $orderPayment->payment_method = $paymentType;
            $orderPayment->order_reference = $orderToUpdate->reference;

            if (isset($payment['amount'])) {
                $orderPayment->amount = $payment['amount'];
            } else {
                $orderPayment->amount = $orderToUpdate->total_paid;
            }

            $orderPayment->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
            $orderPayment->date_add = isset($payment['paidAt']) ? $payment['paidAt'] : date('Y-m-d H:i:s');

            RetailcrmLogger::writeDebug(__METHOD__,
                sprintf(
                    '<Order Reference: %s> %s::%s',
                    $orderToUpdate->reference,
                    get_class($orderPayment),
                    'save'
                )
            );
            $orderPayment->save();
        }
    }

    private static function getPaymentType($payment)
    {
        $paymentTypeCRM = null;
        if (isset($payment['type'])) {
            $paymentTypeCRM = $payment['type'];
        }

        $paymentId = null;

        if ($paymentTypeCRM && isset(self::$payments[$paymentTypeCRM])) {
            $paymentId = self::$payments[$paymentTypeCRM];
        } elseif (self::$paymentDefault) {
            $paymentId = self::$paymentDefault;
        }

        return $paymentId;
    }

    private static function saveCarrier($orderId, $deliveryType, $cost)
    {
        // delivery save
        $carrier = new OrderCarrier();
        $carrier->id_order = $orderId;
        $carrier->id_carrier = $deliveryType;
        $carrier->shipping_cost_tax_excl = $cost;
        $carrier->shipping_cost_tax_incl = $cost;

        RetailcrmLogger::writeDebug(
            __METHOD__,
            sprintf(
                '<Order ID: %d> %s::%s',
                $carrier->id_order,
                get_class($carrier),
                'add'
            )
        );

        $carrier->add(true, false);
    }

    private static function createCart($customer, $addressDelivery, $addressInvoice, $deliveryType)
    {
        $cart = new Cart();
        $cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_lang = self::$default_lang;
        $cart->id_shop = Context::getContext()->shop->id;
        $cart->id_shop_group = Context::getContext()->shop->id_shop_group;
        $cart->id_customer = $customer->id;
        $cart->id_address_delivery = isset($addressDelivery->id) ? (int) $addressDelivery->id : 0;
        $cart->id_address_invoice = isset($addressInvoice->id) ? (int) $addressInvoice->id : 0;
        $cart->id_carrier = (int) $deliveryType;

        self::loadInPrestashop($cart, 'add');

        return $cart;
    }

    private static function createPayments($order, $newOrder)
    {
        if (empty($order['payments'])) {
            return;
        }

        $references = new RetailcrmReferences(self::$api);
        $paymentsCMS = self::getPaymentsCms($references);

        foreach ($order['payments'] as $payment) {
            if (isset($payment['externalId']) || !isset($payment['status']) || 'paid' !== $payment['status']) {
                continue;
            }

            $paymentTypeCRM = isset($payment['type']) ? $payment['type'] : null;
            $paymentType = null;
            $paymentId = null;

            if ($paymentTypeCRM) {
                if (!isset(self::$payments[$paymentTypeCRM]) || empty(self::$payments[$paymentTypeCRM])) {
                    continue;
                }

                $paymentId = self::$payments[$paymentTypeCRM];
            } elseif (self::$paymentDefault) {
                $paymentId = self::$paymentDefault;
            } else {
                continue;
            }

            $paymentType = isset($paymentsCMS[$paymentId]) ? $paymentsCMS[$paymentId] : $paymentId;

            $orderPayment = new OrderPayment();
            $orderPayment->payment_method = $paymentType;
            $orderPayment->order_reference = $newOrder->reference;
            $orderPayment->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
            $orderPayment->amount = $payment['amount'];
            $orderPayment->date_add = $payment['paidAt'];

            RetailcrmLogger::writeDebug(
                __METHOD__,
                sprintf(
                    '<Order Reference: %s> %s::%s',
                    $newOrder->reference,
                    get_class($orderPayment),
                    'save'
                )
            );

            try {
                $orderPayment->save();
            } catch (PrestaShopException $exception) {
                RetailcrmLogger::writeDebug(__METHOD__, $exception->getMessage());
            }
        }
    }

    private static function updateAddressInvoice($addressInvoice, $customer, $order)
    {
        if (RetailcrmTools::validateEntity($addressInvoice)) {
            $addressInvoice->id_customer = $customer->id;
            RetailcrmTools::assignAddressIdsByFields($customer, $addressInvoice);

            if (empty($addressInvoice->id)) {
                self::loadInPrestashop($addressInvoice, 'add');

                if (!empty($order['company']) && RetailcrmTools::isCorporateEnabled()) {
                    self::$api->customersCorporateAddressesEdit(
                        $order['customer']['id'],
                        $order['company']['address']['id'],
                        array_merge(
                            $order['company']['address'],
                            ['externalId' => $addressInvoice->id]
                        ),
                        'id',
                        'id'
                    );

                    time_nanosleep(0, 20000000);
                }
            } else {
                self::loadInPrestashop($addressInvoice, 'save');
            }
        }
    }

    private static function createAddressInvoiceForCustomer($customerBuilder, $customerId)
    {
        if ($customerId) {
            $customerBuilder->setCustomer(new Customer($customerId));
        }

        return $customerBuilder;
    }

    private static function cleanDeletedItems($order, $orderToUpdate)
    {
        foreach ($order['items'] as $key => $item) {
            if (!isset($item['delete']) || true != $item['delete']) {
                continue;
            }
            if (RetailcrmOrderBuilder::isGiftItem($item)) {
                $orderToUpdate->gift = false;
            }

            $parsedExtId = static::parseItemExternalId($item);
            $product_id = $parsedExtId['product_id'];
            $product_attribute_id = $parsedExtId['product_attribute_id'];
            $id_order_detail = !empty($parsedExtId['id_order_detail'])
                ? $parsedExtId['id_order_detail'] : 0;

            if (isset($item['quantity'])) {
                StockAvailable::updateQuantity(
                    $product_id,
                    $product_attribute_id,
                    $item['quantity'],
                    Context::getContext()->shop->id
                );
            }

            self::deleteOrderDetailByProduct(
                $orderToUpdate->id,
                $product_id,
                $product_attribute_id,
                $id_order_detail
            );
            unset($order['items'][$key]);
        }

        return $order;
    }

    private static function checkItemsQuantityAndDiscount($order, $orderToUpdate)
    {
        foreach ($orderToUpdate->getProductsDetail() as $orderItem) {
            foreach ($order['items'] as $key => $item) {
                if (RetailcrmOrderBuilder::isGiftItem($item)) {
                    continue;
                }

                $parsedExtId = static::parseItemExternalId($item);
                $product_id = $parsedExtId['product_id'];
                $product_attribute_id = $parsedExtId['product_attribute_id'];
                $isExistingItem = !isset($item['create']);

                if (!$isExistingItem || $product_id != $orderItem['product_id'] || $product_attribute_id != $orderItem['product_attribute_id']) {
                    continue;
                }

                $orderDetailId = !empty($parsedExtId['id_order_detail'])
                    ? $parsedExtId['id_order_detail'] : $orderItem['id_order_detail'];
                $orderDetail = new OrderDetail($orderDetailId);

                // price
                if (isset($item['initialPrice'])) {
                    $productPrice = round($item['initialPrice'], 2);
                    $orderDetail->unit_price_tax_incl = $productPrice;
                }

                // quantity
                if (isset($item['quantity']) && $item['quantity'] != $orderItem['product_quantity']) {
                    $deltaQuantity = $orderDetail->product_quantity - $item['quantity'];
                    $orderDetail->product_quantity = $item['quantity'];
                    $orderDetail->product_quantity_in_stock = $item['quantity'];

                    StockAvailable::updateQuantity(
                        $product_id,
                        $product_attribute_id,
                        $deltaQuantity,
                        Context::getContext()->shop->id
                    );
                }

                $orderDetail->id_warehouse = !empty($orderToUpdate->id_warehouse)
                    ? $orderToUpdate->id_warehouse : 0;

                self::loadInPrestashop($orderDetail, 'update');
                unset($order['items'][$key]);
            }
        }
    }

    private static function checkNewItems($order, $orderToUpdate)
    {
        $isNewItemsExist = false;
        $newItemsIds = [];
        foreach ($order['items'] as $key => $newItem) {
            if (RetailcrmOrderBuilder::isGiftItem($newItem)) {
                continue;
            }

            $isNewItem = isset($newItem['create']) ? $newItem['create'] : false;
            if (!$isNewItem) {
                continue;
            }

            $parsedExtId = static::parseItemExternalId($newItem);
            $product_id = $parsedExtId['product_id'];
            $product_attribute_id = $parsedExtId['product_attribute_id'];

            $product = new Product((int) $product_id, false, self::$default_lang);

            if (0 != $product_attribute_id) {
                $productName = htmlspecialchars(
                    strip_tags(Product::getProductName($product_id, $product_attribute_id))
                );
            } else {
                $productName = htmlspecialchars(strip_tags($product->name));
            }

            $productPrice = $newItem['initialPrice'];

            $orderDetail = new OrderDetail(
                !empty($parsedExtId['id_order_detail']) ? $parsedExtId['id_order_detail'] : null
            );

            static::setOrderDetailProductName($orderDetail, $productName);

            $orderDetail->id_order = $orderToUpdate->id;
            $orderDetail->id_order_invoice = $orderToUpdate->invoice_number;
            $orderDetail->id_shop = Context::getContext()->shop->id;

            $orderDetail->product_id = (int) $product_id;
            $orderDetail->product_attribute_id = (int) $product_attribute_id;
            $orderDetail->product_reference = implode('', ['\'', $product->reference, '\'']);

            $orderDetail->product_price = $productPrice;
            $orderDetail->original_product_price = $productPrice;
            $orderDetail->product_quantity = (int) $newItem['quantity'];
            $orderDetail->product_quantity_in_stock = (int) $newItem['quantity'];

            $orderDetail->total_price_tax_incl = $productPrice * $orderDetail->product_quantity;
            $orderDetail->unit_price_tax_incl = $productPrice;

            $orderDetail->id_warehouse = !empty($orderToUpdate->id_warehouse)
                ? $orderToUpdate->id_warehouse : 0;
            $orderDetail->id_order_detail = !empty($parsedExtId['id_order_detail'])
                ? $parsedExtId['id_order_detail'] : null;

            if (!$product->checkQty($orderDetail->product_quantity)) {
                if (empty($infoOrder)) {
                    $infoOrder = self::getOrderFromCrm($order['externalId']);
                }
                $newStatus = self::setOutOfStockStatus(
                    $infoOrder,
                    $orderToUpdate
                );

                if ($newStatus) {
                    $updateOrderStatuses[$orderToUpdate->id] = $orderToUpdate->id;
                    $orderToUpdate->current_state = self::$statuses[$newStatus];
                }
            }

            StockAvailable::updateQuantity(
                $product_id,
                $product_attribute_id,
                -1 * $orderDetail->product_quantity,
                Context::getContext()->shop->id
            );

            if (self::loadInPrestashop($orderDetail, 'save')) {
                $newItemsIds[Db::getInstance()->Insert_ID()] = $newItem['id'];
            }

            unset($orderDetail);
            unset($order['items'][$key]);
            $isNewItemsExist = true;
        }

        // update order items ids in crm
        if ($isNewItemsExist) {
            self::$newItemsIdsByOrderId[$orderToUpdate->id] = $newItemsIds;
        }
    }

    private static function getInternalOrderStatus($state)
    {
        $status = null;
        if (isset(self::$statuses[$state]) && '' != self::$statuses[$state]) {
            $status = self::$statuses[$state];
        }

        return $status;
    }

    private static function getPaymentTypeFromCRM($order)
    {
        $paymentType = null;

        if (isset($order['payments'])) {
            if (1 === count($order['payments'])) {
                $paymentFromCRM = end($order['payments']);
                $paymentType = $paymentFromCRM['type'];
            } elseif (1 < count($order['payments'])) {
                foreach ($order['payments'] as $paymentFromCRM) {
                    if (isset($paymentFromCRM['status']) && 'paid' !== $paymentFromCRM['status']) {
                        $paymentType = $paymentFromCRM['type'];
                        break;
                    }
                }
            }
        }

        return $paymentType;
    }

    private static function getModulePaymentId($paymentTypeCRM)
    {
        if (isset(self::$payments[$paymentTypeCRM]) && !empty(self::$payments[$paymentTypeCRM])) {
            $paymentId = self::$payments[$paymentTypeCRM];

            return self::getModulePaymentType($paymentId);
        }

        return false;
    }

    private static function getModulePaymentType($paymentId)
    {
        $references = new RetailcrmReferences(self::$api);
        $paymentsCMS = self::getPaymentsCms($references);

        if ($paymentId && isset($paymentsCMS[$paymentId])) {
            return $paymentsCMS[$paymentId];
        }

        return $paymentId;
    }

    private static function getDeliveryType($order)
    {
        $deliveryType = null;
        $delivery = isset($order['delivery']['code']) ? $order['delivery']['code'] : false;
        if ($delivery && isset(self::$deliveries[$delivery]) && '' != self::$deliveries[$delivery]) {
            $deliveryType = self::$deliveries[$delivery];
        }

        if (!isset($deliveryType) || !$deliveryType) {
            if (self::$deliveryDefault) {
                $deliveryType = self::$deliveryDefault;
            }
        }

        return $deliveryType;
    }

    private static function changeTotals(array $infoOrder, $orderToUpdate)
    {
        // totals
        $totalPaid = $infoOrder['totalSumm'];
        $orderTotalProducts = array_reduce($infoOrder['items'], function ($sum, $it) {
            $sum += $it['initialPrice'] * $it['quantity'];

            return $sum;
        });
        $deliveryCost = $infoOrder['delivery']['cost'];
        $totalDiscount = round($deliveryCost + $orderTotalProducts - $totalPaid, 2);

        // delete all cart discount
        $orderCartRules = $orderToUpdate->getCartRules();
        foreach ($orderCartRules as $valCartRules) {
            $order_cart_rule = new OrderCartRule($valCartRules['id_order_cart_rule']);
            $order_cart_rule->delete();
        }

        $orderToUpdate->total_discounts = $totalDiscount;
        $orderToUpdate->total_discounts_tax_incl = $totalDiscount;
        $orderToUpdate->total_discounts_tax_excl = $totalDiscount;
        $orderToUpdate->total_shipping = $deliveryCost;
        $orderToUpdate->total_shipping_tax_incl = $deliveryCost;
        $orderToUpdate->total_shipping_tax_excl = $deliveryCost;
        $orderToUpdate->total_paid = $totalPaid;
        $orderToUpdate->total_paid_tax_incl = $totalPaid;
        $orderToUpdate->total_paid_tax_excl = $totalPaid;
        $orderToUpdate->total_products_wt = $orderTotalProducts;

        return $orderToUpdate;
    }

    private static function getOrderStatuses($order, $orderToUpdate)
    {
        $updateOrderStatuses = [];

        if (!isset($order['items'])) {
            return $updateOrderStatuses;
        }

        foreach ($order['items'] as $item) {
            if (RetailcrmOrderBuilder::isGiftItem($item)) {
                continue;
            }

            $parsedExtId = static::parseItemExternalId($item);
            $product_id = $parsedExtId['product_id'];
            $product = new Product((int) $product_id, false, self::$default_lang);

            foreach ($orderToUpdate->getProductsDetail() as $orderItem) {
                $orderDetailId = !empty($parsedExtId['id_order_detail'])
                    ? $parsedExtId['id_order_detail'] : $orderItem['id_order_detail'];
                $orderDetail = new OrderDetail($orderDetailId);

                $deltaQuantity = $orderDetail->product_quantity - $item['quantity'];

                if (0 <= $deltaQuantity || $product->checkQty(-1 * $deltaQuantity)) {
                    continue;
                }

                $infoOrder = self::getOrderFromCrm($order['externalId']);

                $newStatus = self::setOutOfStockStatus(
                    $infoOrder,
                    $orderToUpdate
                );

                if ($newStatus) {
                    $updateOrderStatuses[$orderToUpdate->id] = $orderToUpdate->id;
                    $orderToUpdate->current_state = self::$statuses[$newStatus];
                }
            }
        }

        return $updateOrderStatuses;
    }

    private static function getCustomerId($order)
    {
        $customerId = null;
        $existingCorporateContact = isset($order['contact']['externalId']);
        $existingCustomer = isset($order['customer']['externalId']);

        $isCorporateCustomer =
            'customer_corporate' === $order['customer']['type']
            && RetailcrmTools::isCorporateEnabled()
            && !empty($order['contact'])
            && $existingCorporateContact;

        if ($isCorporateCustomer) {
            if ($existingCorporateContact) {
                $customerId = Customer::customerIdExistsStatic($order['contact']['externalId']);
            }

            if (empty($customerId) && !empty($order['contact']['email'])) {
                $customer = Customer::getCustomersByEmail($order['contact']['email']);
                $customer = is_array($customer) ? reset($customer) : [];

                if (isset($customer['id_customer'])) {
                    $customerId = $customer['id_customer'];
                }
            }
        } elseif ($existingCustomer) {
            $customerId = Customer::customerIdExistsStatic($order['customer']['externalId']);
        }

        return $customerId;
    }

    private static function getCustomerById($order, $customerId)
    {
        // address invoice
        if (!empty($order['company']) && RetailcrmTools::isCorporateEnabled()) {
            $dataOrder = array_merge(
                $order['contact'],
                ['address' => $order['company']['address']]
            );

            $customerBuilder = new RetailcrmCorporateCustomerBuilder();
            $customerBuilder
                ->setCustomer(new Customer($customerId))
                ->setDataCrm($dataOrder)
                ->extractCompanyDataFromOrder($order)
                ->build()
            ;
        } else {
            $customerBuilder = new RetailcrmCustomerBuilder();
            $customerBuilder
                ->setDataCrm($order['customer'])
                ->build()
            ;

            $customerBuilder = self::createAddressInvoiceForCustomer($customerBuilder, $customerId);
        }

        return $customerBuilder;
    }

    private static function getOrderCarrier($orderToUpdate)
    {
        if (property_exists($orderToUpdate, 'id_order_carrier')) {
            $idOrderCarrier = $orderToUpdate->id_order_carrier;
        } elseif (method_exists($orderToUpdate, 'getIdOrderCarrier')) {
            $idOrderCarrier = $orderToUpdate->getIdOrderCarrier();
        } else {
            $idOrderCarrier = null;
        }

        return new OrderCarrier($idOrderCarrier);
    }

    private static function addProductsToCart(Cart $cart, array $products)
    {
        if (0 < count($products)) {
            $cart->setWsCartRows($products);
            self::loadInPrestashop($cart, 'update');
        }

        return $cart;
    }
}
