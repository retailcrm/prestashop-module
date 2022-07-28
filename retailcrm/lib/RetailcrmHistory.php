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
    private static $customerFix = [];

    private static function init()
    {
        self::$receiveOrderNumber = (bool) Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING);
        self::$sendOrderNumber = (bool) Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_SENDING);
        self::$cartStatus = (string) Configuration::get(RetailCRM::SYNC_CARTS_STATUS);
        self::$statuses = array_flip(array_filter(json_decode(Configuration::get(RetailCRM::STATUS), true)));
        self::$deliveries = array_flip(array_filter(json_decode(Configuration::get(RetailCRM::DELIVERY), true)));
        self::$payments = array_flip(array_filter(json_decode(Configuration::get(RetailCRM::PAYMENT), true)));
        self::$deliveryDefault = Configuration::get(RetailCRM::DELIVERY_DEFAULT);
        self::$paymentDefault = Configuration::get(RetailCRM::PAYMENT_DEFAULT);
    }

    /**
     * Get customers history
     *
     * @return bool|string
     */
    public static function customersHistory()
    {
        $lastSync = Configuration::get('RETAILCRM_LAST_CUSTOMERS_SYNC');

        $filter = false === $lastSync
            ? ['startDate' => date('Y-m-d H:i:s', strtotime('-1 days', strtotime(date('Y-m-d H:i:s'))))]
            : ['sinceId' => $lastSync];

        $request = new RetailcrmApiSinceIdRequest();
        $history = $request
            ->setApi(self::$api)
            ->setMethod('customersHistory')
            ->setParams([$filter])
            ->setDataKey('history')
            ->setPageLimit(50)
            ->execute()
            ->getData()
        ;

        $historyChanges = [];
        if (0 < count($history)) {
            $historyChanges = static::filterHistory($history, 'customer');
            $end = end($history);
            Configuration::updateValue('RETAILCRM_LAST_CUSTOMERS_SYNC', $end['id']);
        }

        if (count($historyChanges)) {
            $customersHistory = RetailcrmHistoryHelper::assemblyCustomer($historyChanges);
            RetailcrmLogger::writeDebugArray(__METHOD__, ['Assembled history:', $customersHistory]);

            self::$customerFix = [];

            foreach ($customersHistory as $customerHistory) {
                $customerHistory = RetailcrmTools::filter(
                    'RetailcrmFilterCustomersHistory',
                    $customerHistory
                );

                if (isset($customerHistory['deleted']) && $customerHistory['deleted']) {
                    continue;
                }

                if (isset($customerHistory['externalId'])) {
                    self::updateCustomerInPrestashop($customerHistory['externalId']);
                } else {
                    self::createCustomerInPrestashop($customerHistory);
                }
            }

            if (!empty(self::$customerFix)) {
                self::$api->customersFixExternalIds(self::$customerFix);
            }

            return true;
        } else {
            return 'Nothing to sync';
        }
    }

    private static function updateCustomerInPrestashop($externalId)
    {
        $customerBuilder     = new RetailcrmCustomerBuilder();
        $crmCustomerResponse = self::$api->customersGet($externalId);

        if (null === $crmCustomerResponse
            || !$crmCustomerResponse->isSuccessful()
            || !$crmCustomerResponse->offsetExists('customer')
        ) {
            return;
        }

        $customerData = RetailcrmTools::filter(
            'RetailcrmFilterCustomersHistoryUpdate',
            $crmCustomerResponse['customer']
        );

        $foundCustomer   = new Customer($externalId);
        $customerAddress = new Address(RetailcrmTools::searchIndividualAddress($foundCustomer));
        $addressBuilder  = new RetailcrmCustomerAddressBuilder();

        $addressBuilder
            ->setCustomerAddress($customerAddress);

        $customerBuilder
            ->setCustomer($foundCustomer)
            ->setAddressBuilder($addressBuilder)
            ->setDataCrm($customerData)
            ->build();

        $customer = $customerBuilder->getData()->getCustomer();
        $address  = $customerBuilder->getData()->getCustomerAddress();

        if (false === self::loadInPrestashop($customer, 'update')) {
            return;
        }

        if (!empty($address)) {
            RetailcrmTools::assignAddressIdsByFields($customer, $address);

            self::loadInPrestashop($address, 'update');
        }
    }

    private static function createCustomerInPrestashop($customerHistory)
    {
        $customerBuilder = new RetailcrmCustomerBuilder();

        $customerBuilder
            ->setDataCrm($customerHistory)
            ->build();

        $customer = $customerBuilder->getData()->getCustomer();

        if (false === self::loadInPrestashop($customer, 'save')) {
            return;
        }

        self::$customerFix[] = [
            'id'         => $customerHistory['id'],
            'externalId' => $customer->id,
        ];

        $customer->update();

        if (isset($customerHistory['address'])) {
            $address = $customerBuilder->getData()->getCustomerAddress();

            $address->id_customer = $customer->id;

            self::loadInPrestashop($address, 'save');
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

        $filter = false === $lastSync
            ? ['startDate' => date('Y-m-d H:i:s', strtotime('-1 days', strtotime(date('Y-m-d H:i:s'))))]
            : ['sinceId' => $lastSync];

        $request = new RetailcrmApiSinceIdRequest();
        $history = $request
            ->setApi(self::$api)
            ->setMethod('ordersHistory')
            ->setParams([$filter])
            ->setDataKey('history')
            ->setPageLimit(50)
            ->execute()
            ->getData()
        ;

        $historyChanges = [];
        if (0 < count($history)) {
            $historyChanges = static::filterHistory($history, 'order');
            $end = end($history);
            Configuration::updateValue('RETAILCRM_LAST_ORDERS_SYNC', $end['id']);
        }

        if (count($historyChanges)) {
            $orders = RetailcrmHistoryHelper::assemblyOrder($historyChanges);
            RetailcrmLogger::writeDebugArray(__METHOD__, ['Assembled history:', $orders]);

            self::syncOrders($orders);

            return true;
        } else {
            return 'Nothing to sync';
        }
    }

    private static function syncOrders(array $orders)
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

            $newOrder = null;

            try {
                if (!$orderExists) {
                    $newOrder = self::createOrderInPrestashop($orderHistory);
                } else {
                    $newOrder = self::updateOrderInPrestashop($orderHistory);
                }
            } catch (Exception $e) {
                self::handleError($orderHistory, $e);
            } catch (Error $e) {
                self::handleError($orderHistory, $e);
            }

            if (null !== $newOrder && null !== $newOrder->id) {
                RetailcrmExportOrdersHelper::updateExportState($newOrder->id, $orderHistory['id']);

                // collect orders id and reference if option sendOrderNumber enabled
                if (self::$sendOrderNumber) {
                    self::$updateOrderIds[] = [
                        'externalId' => $newOrder->id,
                        'number'     => $newOrder->reference,
                    ];
                }
            }
        }

        if (0 < count(self::$orderFix)) {
            self::$api->ordersFixExternalIds(self::$orderFix);
        }

        if (0 < count(self::$newItemsIdsByOrderId)) {
            foreach (self::$newItemsIdsByOrderId as $newOrderId => $newItemsIds) {
                static::updateOrderItems($newOrderId, $newItemsIds);
            }
        }

        if (0 < count(self::$updateOrderIds)) {
            foreach (self::$updateOrderIds as $updateOrderData) {
                self::$api->ordersEdit($updateOrderData);
            }
        }
    }

    private static function createOrderInPrestashop($orderHistory)
    {
        $crmOrder = self::getOrderFromCrm($orderHistory['id'], 'id');
        $crmOrder = RetailcrmTools::filter('RetailcrmFilterOrdersHistoryCreate', $crmOrder);

        $orderStatus = self::getInternalOrderStatus($crmOrder['status']);

        if (self::$cartStatus && (string) $orderStatus === (string) self::$cartStatus) {
            return;
        }

        $paymentTypeCRM = self::getPaymentTypeFromCRM($crmOrder);
        $paymentId = self::getModulePaymentId($paymentTypeCRM);
        $paymentType = self::getModulePaymentType($paymentId);

        $customerId = self::getCustomerId($crmOrder);
        $customerBuilder = self::getCustomerBuilderById($crmOrder, $customerId);
        $customer = $customerBuilder->getData()->getCustomer();
        $addressInvoice = $customerBuilder->getData()->getCustomerAddress();

        if (empty($customer->id) && !empty($customer->email)) {
            $customer->id = self::getCustomerIdByEmail($customer->email);
        }
        self::loadInPrestashop($customer, 'save');

        self::updateAddressInvoice($addressInvoice, $customer, $crmOrder);

        $addressDelivery = self::createAddress($crmOrder, $customer);
        $deliveryType = self::getDeliveryType($crmOrder);

        $cart = self::createCart($customer, $addressDelivery, $addressInvoice, $deliveryType);

        $products = [];
        if (!empty($crmOrder['items'])) {
            $products = self::createProducts($crmOrder['items'], $addressDelivery);
        }

        $cart = self::addProductsToCart($cart, $products);

        $prestashopOrder = self::createOrder(
            $cart,
            $customer,
            $crmOrder,
            $deliveryType,
            $paymentId,
            $paymentType,
            $addressDelivery,
            $addressInvoice,
            $orderStatus
        );

        if (!isset($prestashopOrder->id) || !$prestashopOrder->id) {
            RetailcrmLogger::writeDebug(__METHOD__, 'Order not created');

            return;
        }

        self::createPayments($crmOrder, $prestashopOrder);

        self::saveCarrier($prestashopOrder->id, $deliveryType, $crmOrder['delivery']['cost']);

        $quantities = self::createOrderDetails($crmOrder, $prestashopOrder);

        self::setOutOfStockStatusInPrestashop($crmOrder, $prestashopOrder, $quantities);

        self::setOutOfStockStatusInCrm($crmOrder, $prestashopOrder, $quantities);

        // collect order ids for single fix request
        self::$orderFix[] = ['id' => $crmOrder['id'], 'externalId' => $prestashopOrder->id];

        return $prestashopOrder;
    }

    private static function updateOrderInPrestashop($crmOrder)
    {
        $prestashopOrder = new Order((int) $crmOrder['externalId']);
        if (!Validate::isLoadedObject($prestashopOrder)) {
            return;
        }

        $crmOrder = RetailcrmTools::filter('RetailcrmFilterOrdersHistoryUpdate', $crmOrder, [
            'orderToUpdate' => $prestashopOrder,
        ]);

        self::handleCustomerDataChange($prestashopOrder, $crmOrder);

        self::checkDeliveryChanges($crmOrder, $prestashopOrder);

        self::checkDeliveryTypeAndCost($crmOrder, $prestashopOrder);

        self::checkPaymentType($crmOrder, $prestashopOrder);

        self::changeOrderTotals($crmOrder, $prestashopOrder);

        $crmOrder = self::cleanDeletedItems($crmOrder, $prestashopOrder);

        $quantities = self::createOrderDetails($crmOrder, $prestashopOrder);

        self::setOutOfStockStatusInPrestashop($crmOrder, $prestashopOrder, $quantities);

        self::setOutOfStockStatusInCrm($crmOrder, $prestashopOrder, $quantities);

        self::switchPrestashopOrderStatusByCrmStatus($crmOrder, $prestashopOrder, $quantities);

        // update order number in PS if receiveOrderNumber option (CRM->PS) enabled
        if (isset($crmOrder['number']) && self::$receiveOrderNumber) {
            $prestashopOrder->reference = $crmOrder['number'];
            $prestashopOrder->update();
        }

        return $prestashopOrder;
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

        if (null !== $crmOrderResponse
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
    private static function getOutOfStockStatus($crmOrder, $cmsOrder)
    {
        $outOfStockType = self::getPrestashopOutOfStockStatusFromModuleConfig($crmOrder);

        if ($outOfStockType) {
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
            AND id_order_detail = ' . pSQL((int) $id_order_detail));
    }

    private static function getNewOrderDetailId()
    {
        return Db::getInstance()->getRow('
            SELECT MAX(id_order_detail) FROM  ' . _DB_PREFIX_ . 'order_detail');
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

            if ('api' == $entry['source']
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
        if (null === Context::getContext()->currency) {
            Context::getContext()->currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        }

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

    private static function createOrder($cart, $customer, $order, $deliveryType, $paymentId, $paymentType, $addressDelivery, $addressInvoice, $orderStatus)
    {
        $default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $newOrder = new Order();
        $newOrder->id_shop = Context::getContext()->shop->id;
        $newOrder->id_shop_group = (int) Context::getContext()->shop->id_shop_group;
        $newOrder->id_address_delivery = isset($addressDelivery->id) ? (int) $addressDelivery->id : 0;
        $newOrder->id_address_invoice = isset($addressInvoice->id) ? (int) $addressInvoice->id : 0;
        $newOrder->id_cart = (int) $cart->id;
        $newOrder->id_currency = $default_currency;
        $newOrder->id_lang = self::$default_lang;
        $newOrder->id_customer = (int) $customer->id;
        $orderNumber = self::$receiveOrderNumber ? $order['number'] : $newOrder->generateReference();
        $newOrder->reference = $orderNumber;
        $newOrder->id_carrier = (int) $deliveryType;
        $newOrder->module = $paymentId;
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
            ));
            $newOrder->add(false, false);

            $newOrderHistoryRecord = new OrderHistory(null, static::$default_lang, Context::getContext()->shop->id);

            $newOrderHistoryRecord->id_order = $newOrder->id;
            $newOrderHistoryRecord->id_order_state = $newOrder->current_state;
            $newOrderHistoryRecord->id_employee = static::getFirstEmployeeId();
            $newOrderHistoryRecord->date_add = date('Y-m-d H:i:s');
            $newOrderHistoryRecord->date_upd = $newOrderHistoryRecord->date_add;

            self::loadInPrestashop($newOrderHistoryRecord, 'save');
        } catch (\Exception $e) {
            RetailcrmLogger::writeCaller(
                __METHOD__,
                sprintf('Error adding order id=%d: %s', $order['id'], $e->getMessage())
            );

            RetailcrmLogger::writeNoCaller($e->getTraceAsString());
        }

        return $newOrder;
    }

    private static function createOrderDetails($crmOrder, $prestashopOrder)
    {
        $newItemsIds = [];
        $quantities = [];

        if (empty($crmOrder['items'])) {
            RetailcrmLogger::writeDebug(__METHOD__, 'Empty order items');

            return $quantities;
        }

        foreach ($crmOrder['items'] as $item) {
            if (!isset($item['offer']['externalId'])) {
                continue;
            }
            $externalId = $item['offer']['externalId'];

            $product = new Product((int) $externalId, false, self::$default_lang);
            $product_id = $externalId;

            if (RetailcrmOrderBuilder::isGiftItem($item)) {
                continue;
            }

            $product_attribute_id = 0;
            if (false !== strpos($externalId, '#')) {
                $externalIds = explode('#', $externalId);
                $product_id = $externalIds[0];
                $product_attribute_id = $externalIds[1];
            }

            $orderDetail = self::createOrderDetail($item, $product, $product_attribute_id, $prestashopOrder);
            $availableInStockOld = StockAvailable::getQuantityAvailableByProduct($product_id);

            $quantities[$product_id]['old'] = $availableInStockOld;

            if (isset($item['initialPrice'])) {
                $deltaQuantity = -1 * $orderDetail->product_quantity;
            } else {
                $deltaQuantity = -1 * ($item['quantity'] - $orderDetail->product_quantity);
            }

            StockAvailable::updateQuantity(
                $product_id,
                $product_attribute_id,
                $deltaQuantity,
                Context::getContext()->shop->id
            );

            $availableInStockNew = StockAvailable::getQuantityAvailableByProduct($product_id);
            $quantities[$product_id]['new'] = $availableInStockNew;

            if (!isset($item['initialPrice'])) {
                $orderDetail->product_quantity = $orderDetail->product_quantity - $deltaQuantity;
                $orderDetail->total_price_tax_incl = $orderDetail->product_price * $orderDetail->product_quantity;
            }

            if (self::loadInPrestashop($orderDetail, 'save')) {
                $newItemsIds[Db::getInstance()->Insert_ID()] = $item['id'];
            }
        }

        // update order items ids in crm
        self::$newItemsIdsByOrderId[$prestashopOrder->id] = $newItemsIds;

        return $quantities;
    }

    private static function switchPrestashopOrderStatusByCrmStatus($crmOrder, $prestashopOrder, $quantities)
    {
        if (!isset($crmOrder['status'])) {
            return;
        }
        $orderStatus = $crmOrder['status'];

        $outOfStockItems = self::getOutOfStockItems($quantities);

        if (0 < count($outOfStockItems)) {
            $orderStatus = self::getOutOfStockStatus($crmOrder, $prestashopOrder);
        }

        $orderStatusChanged = !empty(self::$statuses[$orderStatus]) && self::$statuses[$orderStatus] != $prestashopOrder->current_state;

        if ($orderStatusChanged) {
            self::createOrderHistory($prestashopOrder, $orderStatus);
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
            self::loadInPrestashop($address, 'save');
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
                    if (1 > $orderToUpdate->id_address_delivery || version_compare(_PS_VERSION_, '1.7.7', '<')) {
                        self::loadInPrestashop($address, 'save');
                        $orderToUpdate->id_address_delivery = $address->id;
                        self::loadInPrestashop($orderToUpdate, 'update');
                    } else {
                        $address->id = $orderToUpdate->id_address_delivery;
                        self::loadInPrestashop($address, 'update');
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

        if (RetailcrmHistoryHelper::isAddressLineChanged($orderAddressCrm)
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

            if ((
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
        if (!isset($order['items']) && !isset($order['delivery']['cost'])) {
            return;
        }

        $infoOrder = self::getOrderFromCrm($order['externalId']);
        $orderToUpdate = self::changeTotals($infoOrder, $orderToUpdate);

        self::loadInPrestashop($orderToUpdate, 'update');
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

            $orderPayment = new OrderPayment();

            $paymentType = self::getPaymentType($payment);

            if ($paymentType) {
                $orderToUpdate->payment = $paymentType;
                $orderPayment->payment_method = $paymentType;
            }

            $orderPayment->order_reference = $orderToUpdate->reference;
            $orderPayment->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
            $orderPayment->amount = isset($payment['amount']) ? $payment['amount'] : $orderToUpdate->total_paid;
            $orderPayment->date_add = isset($payment['paidAt']) ? $payment['paidAt'] : date('Y-m-d H:i:s');

            RetailcrmLogger::writeDebug(
                __METHOD__,
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

        self::loadInPrestashop($cart, 'save');

        return $cart;
    }

    private static function createPayments($order, $newOrder)
    {
        if (empty($order['payments'])) {
            return;
        }

        foreach ($order['payments'] as $payment) {
            if (isset($payment['externalId']) || !isset($payment['status']) || 'paid' !== $payment['status']) {
                continue;
            }

            $paymentTypeCRM = self::getPaymentTypeFromCRM($order);
            $paymentId = self::getModulePaymentId($paymentTypeCRM);
            $paymentType = self::getModulePaymentType($paymentId);

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
        if (null === $addressInvoice) {
            RetailcrmLogger::writeDebug(__METHOD__, 'Address invoice is null');

            return;
        }
        if (RetailcrmTools::validateEntity($addressInvoice)) {
            $addressInvoice->id_customer = $customer->id;
            RetailcrmTools::assignAddressIdsByFields($customer, $addressInvoice);

            if (empty($addressInvoice->id)) {
                self::loadInPrestashop($addressInvoice, 'save');

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

    private static function cleanDeletedItems($crmOrder, $orderToUpdate)
    {
        if (!isset($crmOrder['items']) || !is_array($crmOrder['items'])) {
            return $crmOrder;
        }

        foreach ($crmOrder['items'] as $item) {
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
        }

        return $crmOrder;
    }

    private static function checkItemsQuantityAndDiscount($crmOrder, $prestashopOrder)
    {
        $itemQuantities = [];
        foreach ($prestashopOrder->getProductsDetail() as $orderItem) {
            foreach ($crmOrder['items'] as $crmItem) {
                if (RetailcrmOrderBuilder::isGiftItem($crmItem)) {
                    continue;
                }

                $parsedExtId = static::parseItemExternalId($crmItem);
                $product_id = $parsedExtId['product_id'];
                $product_attribute_id = $parsedExtId['product_attribute_id'];
                $isExistingItem = !isset($crmItem['create']);

                if (!$isExistingItem || $product_id != $orderItem['product_id'] || $product_attribute_id != $orderItem['product_attribute_id']) {
                    continue;
                }

                $orderDetailId = !empty($parsedExtId['id_order_detail'])
                    ? $parsedExtId['id_order_detail'] : $orderItem['id_order_detail'];
                $orderDetail = new OrderDetail($orderDetailId);

                // price
                if (isset($crmItem['initialPrice'])) {
                    $productPrice = round($crmItem['initialPrice'], 2);
                    $orderDetail->unit_price_tax_incl = $productPrice;
                }

                // quantity
                if (isset($crmItem['quantity']) && $crmItem['quantity'] != $orderItem['product_quantity']) {
                    $deltaQuantity = $orderDetail->product_quantity - $crmItem['quantity'];
                    $orderDetail->product_quantity = $crmItem['quantity'];
                    $orderDetail->product_quantity_in_stock = $crmItem['quantity'];

                    $itemQuantities[$product_id] = StockAvailable::getQuantityAvailableByProduct($product_id);

                    StockAvailable::updateQuantity(
                        $product_id,
                        $product_attribute_id,
                        $deltaQuantity,
                        Context::getContext()->shop->id
                    );
                }

                $orderDetail->id_warehouse = !empty($prestashopOrder->id_warehouse)
                    ? $prestashopOrder->id_warehouse : 0;

                self::loadInPrestashop($orderDetail, 'update');
            }
        }

        return $itemQuantities;
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
            return self::$payments[$paymentTypeCRM];
        }

        return self::$paymentDefault;
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
        $deliveryType = self::$deliveryDefault;
        $delivery = isset($order['delivery']['code']) ? $order['delivery']['code'] : false;
        if ($delivery && isset(self::$deliveries[$delivery]) && '' != self::$deliveries[$delivery]) {
            $deliveryType = self::$deliveries[$delivery];
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

    private static function setOutOfStockStatusInPrestashop($crmOrder, $prestashopOrder, $quantities)
    {
        if (!isset($crmOrder['items'])) {
            return false;
        }

        $outOfStockItems = self::getOutOfStockItems($quantities);
        $newStatus = self::getOutOfStockStatus($crmOrder, $prestashopOrder);

        if (0 < count($outOfStockItems) && $newStatus) {
            self::createOrderHistory($prestashopOrder, $newStatus);
            $prestashopOrder->current_state = self::$statuses[$newStatus];
            self::loadInPrestashop($prestashopOrder, 'save');

            return true;
        }

        return false;
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

    private static function getCustomerBuilderById($order, $customerId)
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

    private static function setOutOfStockStatusInCrm($crmOrder, $prestashopOrder, $quantities = null)
    {
        if (!isset($crmOrder['items']) || !is_array($crmOrder['items'])) {
            return false;
        }

        if (null === $quantities) {
            $quantities = self::checkItemsQuantityAndDiscount($crmOrder, $prestashopOrder);
        }

        $outOfStockItems = self::getOutOfStockItems($quantities);

        if (0 < count($outOfStockItems)) {
            $crmOrder['status'] = self::getOutOfStockStatus($crmOrder, $prestashopOrder);

            self::$api->ordersEdit($crmOrder, 'id');

            return true;
        }

        return false;
    }

    private static function getPrestashopOutOfStockStatusFromModuleConfig(array $crmOrder)
    {
        $statusArray = json_decode(
            Configuration::get(RetailCRM::OUT_OF_STOCK_STATUS),
            true
        );

        if (!empty($crmOrder['fullPaidAt'])) {
            return $statusArray['out_of_stock_paid'];
        } else {
            return $statusArray['out_of_stock_not_paid'];
        }
    }

    private static function createOrderHistory($prestashopOrder, $orderStatus)
    {
        $orderHistory = new OrderHistory();
        $orderHistory->id_employee = 0;
        $orderHistory->id_order = $prestashopOrder->id;
        $orderHistory->id_order_state = self::$statuses[$orderStatus];
        $orderHistory->date_add = date('Y-m-d H:i:s');

        self::loadInPrestashop($orderHistory, 'save');
        RetailcrmLogger::writeDebug(
            __METHOD__,
            sprintf(
                '<Order ID: %d> %s::%s',
                $prestashopOrder->id,
                get_class($orderHistory),
                'changeIdOrderState'
            )
        );

        $orderHistory->changeIdOrderState(self::$statuses[$orderStatus], $prestashopOrder->id, true);
    }

    private static function getOutOfStockItems($quantities)
    {
        return array_filter($quantities, function ($value) {
            if (0 > $value['new'] || $value['new'] == $value['old']) {
                return true;
            }

            return false;
        });
    }

    private static function createOrderDetail($item, $product, $product_attribute_id, $prestashopOrder)
    {
        $product_id = $item['offer']['externalId'];

        if (0 != $product_attribute_id) {
            $productName = htmlspecialchars(
                strip_tags(Product::getProductName($product_id, $product_attribute_id))
            );
        } else {
            $productName = htmlspecialchars(strip_tags($product->name));
        }

        if (isset($item['initialPrice'])) {
            $productPrice = round($item['initialPrice'], 2);
            $orderDetail = new OrderDetail();
            $orderDetail->product_quantity = (int) $item['quantity'];
            $orderDetail->product_quantity_in_stock = (int) $item['quantity'];
        } else {
            $parsedExtId = static::parseItemExternalId($item);
            $orderDetail = new OrderDetail($parsedExtId['id_order_detail']);
            $productPrice = $orderDetail->product_price;
        }

        static::setOrderDetailProductName($orderDetail, $productName);

        $orderDetail->id_order = $prestashopOrder->id;
        $orderDetail->id_order_invoice = $prestashopOrder->invoice_number;
        $orderDetail->id_shop = Context::getContext()->shop->id;

        $orderDetail->product_id = (int) $product_id;
        $orderDetail->product_attribute_id = (int) $product_attribute_id;
        $orderDetail->product_reference = implode('', ['\'', $product->reference, '\'']);

        $orderDetail->product_price = $productPrice;
        $orderDetail->original_product_price = $productPrice;

        $orderDetail->total_price_tax_incl = $productPrice * $orderDetail->product_quantity;
        $orderDetail->unit_price_tax_incl = $productPrice;

        $orderDetail->id_warehouse = !empty($prestashopOrder->id_warehouse) ? $prestashopOrder->id_warehouse : 0;

        return $orderDetail;
    }

    private static function handleError($order, $e)
    {
        RetailcrmLogger::writeCaller(
            __METHOD__,
            sprintf(
                'Error %s order id=%d: %s',
                (isset($order['externalId']) ? 'updating' : 'creating'),
                $order['id'],
                $e->getMessage()
            )
        );

        RetailcrmLogger::writeNoCaller($e->getTraceAsString());

        RetailcrmExportOrdersHelper::updateExportState(
            isset($order['externalId']) ? $order['externalId'] : null,
            $order['id'],
            [$e->getMessage()]
        );
    }
}
