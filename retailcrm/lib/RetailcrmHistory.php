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
class RetailcrmHistory
{
    /** @var \RetailcrmApiClientV5 */
    public static $api;
    public static $default_lang;

    /**
     * Get customers history
     *
     * @return mixed
     */
    public static function customersHistory()
    {
        $lastSync = Configuration::get('RETAILCRM_LAST_CUSTOMERS_SYNC');

        $customerFix = array();

        $filter = $lastSync === false
            ? array('startDate' => date('Y-m-d H:i:s', strtotime('-1 days', strtotime(date('Y-m-d H:i:s')))))
            : array('sinceId' => $lastSync);

        $request = new RetailcrmApiPaginatedRequest();
        $historyChanges = array();
        $history = $request
            ->setApi(self::$api)
            ->setMethod('customersHistory')
            ->setParams(array($filter, '{{page}}'))
            ->setDataKey('history')
            ->setLimit(100)
            ->execute()
            ->getData();

        if (count($history) > 0) {
            $historyChanges = static::filterHistory($history, 'customer');
        }

        if (count($historyChanges)) {
            $end = end($historyChanges);
            Configuration::updateValue('RETAILCRM_LAST_CUSTOMERS_SYNC', $end['id']);

            $customersHistory = RetailcrmHistoryHelper::assemblyCustomer($historyChanges);
            RetailcrmLogger::writeDebugArray(__METHOD__, array('Assembled history:', $customersHistory));

            foreach ($customersHistory as $customerHistory) {
                if (isset($customerHistory['deleted']) && $customerHistory['deleted']) {
                    continue;
                }

                $customerBuilder = new RetailcrmCustomerBuilder();
                $customerBuilder->setDataCrm($customerHistory);

                if (isset($customerHistory['externalId'])) {

                    $customerData = self::$api->customersGet($customerHistory['externalId'], 'externalId');
                    if (isset($customerData['customer']) && $customerData['customer']) {

                        $foundCustomer = new Customer($customerHistory['externalId']);
                        $customerAddress = new Address(RetailcrmTools::searchIndividualAddress($foundCustomer));
                        $addressBuilder = new RetailcrmCustomerAddressBuilder();

                        $addressBuilder
                            ->setCustomerAddress($customerAddress);

                        $customerBuilder
                            ->setCustomer($foundCustomer)
                            ->setAddressBuilder($addressBuilder)
                            ->setDataCrm($customerData['customer'])
                            ->build();

                        $customer = $customerBuilder->getData()->getCustomer();
                        $address = $customerBuilder->getData()->getCustomerAddress();

                        if (self::loadInCMS($customer, 'update') === false) {
                            continue;
                        }

                        if (!empty($address)) {
                            RetailcrmTools::assignAddressIdsByFields($customer, $address);

                            if (self::loadInCMS($address, 'update') === false) {
                                continue;
                            }
                        }
                    }

                } else {
                    $customerBuilder->build();

                    $customer = $customerBuilder->getData()->getCustomer();

                    if (self::loadInCMS($customer, 'add') === false) {
                        continue;
                    }

                    $customerFix[] = array(
                        'id' => $customerHistory['id'],
                        'externalId' => $customer->id
                    );

                    $customer->update();

                    if (isset($customerHistory['address'])) {
                        $address = $customerBuilder->getData()->getCustomerAddress();

                        $address->id_customer = $customer->id;

                        if (self::loadInCMS($address, 'add') === false) {
                            continue;
                        }
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
     * @throws \PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    public static function ordersHistory()
    {
        $default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');

        $lastSync = Configuration::get('RETAILCRM_LAST_ORDERS_SYNC');
        $lastDate = Configuration::get('RETAILCRM_LAST_SYNC');
        $references = new RetailcrmReferences(self::$api);

        if ($lastSync === false && $lastDate === false) {
            $filter = array(
                'startDate' => date(
                    'Y-m-d H:i:s',
                    strtotime('-1 days', strtotime(date('Y-m-d H:i:s')))
                )
            );
        } elseif ($lastSync === false && $lastDate !== false) {
            $filter = array('startDate' => $lastDate);
        } elseif ($lastSync !== false) {
            $filter = array('sinceId' => $lastSync);
        } else {
            $filter = array();
        }

        $customerFix = array();
        $orderFix = array();
        $historyChanges = array();
        $request = new RetailcrmApiPaginatedRequest();
        $history = $request
            ->setApi(self::$api)
            ->setMethod('ordersHistory')
            ->setParams(array($filter, '{{page}}'))
            ->setDataKey('history')
            ->setLimit(100)
            ->execute()
            ->getData();

        if (count($history) > 0) {
            $historyChanges = static::filterHistory($history, 'order');
        }

        if (count($historyChanges)) {
            $end = end($historyChanges);
            Configuration::updateValue('RETAILCRM_LAST_ORDERS_SYNC', $end['id']);

            $statuses = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_STATUS'), true)));
            $cartStatus = (string)(Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_STATUS'));
            $deliveries = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true)));
            $payments = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true)));
            $deliveryDefault = json_decode(Configuration::get('RETAILCRM_API_DELIVERY_DEFAULT'), true);
            $paymentDefault = json_decode(Configuration::get('RETAILCRM_API_PAYMENT_DEFAULT'), true);
            $orders = RetailcrmHistoryHelper::assemblyOrder($historyChanges);
            RetailcrmLogger::writeDebugArray(__METHOD__, array('Assembled history:', $orders));

            foreach ($orders as $order_history) {
                if (isset($order_history['deleted']) && $order_history['deleted'] == true) {
                    continue;
                }

                if (!array_key_exists('externalId', $order_history)) {
                    $response = self::$api->ordersGet($order_history['id'], 'id');

                    if ($response) {
                        $order = $response['order'];

                        if ($order['status'] == $cartStatus) {
                            continue;
                        }
                    } else {
                        continue;
                    }

                    $delivery = isset($order['delivery']['code']) ? $order['delivery']['code'] : false;

                    if ($delivery && array_key_exists($delivery, $deliveries) && $deliveries[$delivery] != '') {
                        $deliveryType = $deliveries[$delivery];
                    }

                    if (isset($order['payments']) && count($order['payments']) == 1) {
                        $paymentCRM = end($order['payments']);
                        $payment = $paymentCRM['type'];
                    } elseif (isset($order['payments']) && count($order['payments']) > 1) {
                        foreach ($order['payments'] as $paymentCRM) {
                            if (isset($paymentCRM['status']) && $paymentCRM['status'] != 'paid') {
                                $payment = $paymentCRM['type'];
                            }
                        }
                    }

                    $crmPaymentType = isset($payment)
                        ? ((is_array($payment) && isset($payment['type'])) ? $payment['type'] : $payment)
                        : null;

                    if (!is_null($crmPaymentType) && array_key_exists($crmPaymentType, $payments) && !empty($payments[$crmPaymentType])) {
                        if (Module::getInstanceByName($payments[$crmPaymentType])) {
                            $paymentType = Module::getModuleName($payments[$crmPaymentType]);
                        } else {
                            $paymentType = $payments[$crmPaymentType];
                        }

                        $paymentId = $payments[$crmPaymentType];
                    }

                    $state = $order['status'];

                    if (array_key_exists($state, $statuses) && $statuses[$state] != '') {
                        $orderStatus = $statuses[$state];
                    }
                    if (!isset($paymentId) || !$paymentId) {
                        $paymentId = $paymentDefault;
                    }

                    if (!isset($paymentType) || !$paymentType) {
                        if ($paymentDefault) {
                            if (Module::getInstanceByName($paymentDefault)) {
                                $paymentType = Module::getModuleName($paymentDefault);
                            } else {
                                $paymentType = $paymentDefault;
                            }
                        } else {
                            RetailcrmLogger::writeCaller(
                                'orderHistory',
                                sprintf(
                                    'set default payment(error in order where id = %d)',
                                    $order['id']
                                )
                            );

                            continue;
                        }
                    }

                    if (!isset($deliveryType) || !$deliveryType) {
                        if ($deliveryDefault) {
                            $deliveryType = $deliveryDefault;
                        } else {
                            RetailcrmLogger::writeCaller(
                                'orderHistory',
                                sprintf(
                                    'set default delivery(error in order where id = %d)',
                                    $order['id']
                                )
                            );

                            continue;
                        }
                    }

                    $address = new Address();
                    $customer = null;
                    $customerId = null;

                    if ($order['customer']['type'] == 'customer_corporate'
                        && RetailcrmTools::isCorporateEnabled()
                        && !empty($order['contact'])
                        && array_key_exists('externalId', $order['contact'])
                    ) {
                        if (isset($order['contact']['externalId'])) {
                            $customerId = Customer::customerIdExistsStatic($order['contact']['externalId']);
                        }

                        if (empty($customerId) && !empty($order['contact']['email'])) {
                            $customer = Customer::getCustomersByEmail($order['contact']['email']);
                            $customer = is_array($customer) ? reset($customer) : array();

                            if (array_key_exists('id_customer', $customer)) {
                                $customerId = $customer['id_customer'];
                            }
                        }
                    } elseif (array_key_exists('externalId', $order['customer'])) {
                        $customerId = Customer::customerIdExistsStatic($order['customer']['externalId']);
                        $customerBuilder = new RetailcrmCustomerBuilder();

                        $customerBuilder->setCustomer(new Customer($customerId));
                    }

                    if (!empty($order['company']) && RetailcrmTools::isCorporateEnabled()) {
                        $corporateCustomerBuilder = new RetailcrmCorporateCustomerBuilder();
                        $dataOrder = array_merge(
                            $order['contact'],
                            array('address' => $order['company']['address'])
                        );

                        $corporateCustomerBuilder
                            ->setCustomer(new Customer($customerId))
                            ->setDataCrm($dataOrder)
                            ->extractCompanyDataFromOrder($order)
                            ->build();

                        $customer = $corporateCustomerBuilder->getData()->getCustomer();
                        $address = $corporateCustomerBuilder->getData()->getCustomerAddress();
                    } else {
                        $customerBuilder = new RetailcrmCustomerBuilder();

                        $customerBuilder
                            ->setDataCrm($order['customer'])
                            ->build();

                        $customer = $customerBuilder->getData()->getCustomer();

                        if (!empty($address)) {
                            $address = $customerBuilder->getData()->getCustomerAddress();
                        }
                    }

                    if (!empty($customer->email)) {
                        $customer->id = self::getCustomerIdByEmail($customer->email);
                    }

                    if (self::loadInCMS($customer, 'save') === false) {
                        continue;
                    }

                    if (!empty($address)) {
                        $address->id_customer = $customer->id;
                        RetailcrmTools::assignAddressIdsByFields($customer, $address);

                        if (empty($address->id)) {
                            RetailcrmLogger::writeDebug(
                                __METHOD__,
                                sprintf(
                                    '<Customer ID: %d> %s::%s',
                                    $address->id_customer,
                                    get_class($address),
                                    'add'
                                )
                            );

                            $address->add();

                            if (!empty($order['company']) && RetailcrmTools::isCorporateEnabled()) {
                                self::$api->customersCorporateAddressesEdit(
                                    $order['customer']['id'],
                                    $order['company']['address']['id'],
                                    array_merge($order['company']['address'], array('externalId' => $address->id)),
                                    'id',
                                    'id'
                                );

                                time_nanosleep(0, 20000000);
                            }
                        } else {
                            RetailcrmLogger::writeDebug(
                                __METHOD__,
                                sprintf('<%d> %s::%s', $address->id, get_class($address), 'save')
                            );

                            $address->save();
                        }
                    }

                    $cart = new Cart();
                    $cart->id_currency = $default_currency;
                    $cart->id_lang = self::$default_lang;
                    $cart->id_shop = Context::getContext()->shop->id;
                    $cart->id_shop_group = intval(Context::getContext()->shop->id_shop_group);
                    $cart->id_customer = $customer->id;
                    $cart->id_address_delivery = (int) $address->id;
                    $cart->id_address_invoice = (int) $address->id;
                    $cart->id_carrier = (int) $deliveryType;

                    RetailcrmLogger::writeDebug(
                        __METHOD__,
                        sprintf(
                            '<Customer ID: %d> %s::%s',
                            $cart->id_customer,
                            get_class($cart),
                            'add'
                        )
                    );

                    $cart->add();

                    $products = array();

                    if (!empty($order['items'])) {
                        foreach ($order['items'] as $item) {
                            if (RetailcrmOrderBuilder::isGiftItem($item)) {
                                continue;
                            }

                            $productId = explode('#', $item['offer']['externalId']);
                            $product = array();
                            $product['id_product'] = (int) $productId[0];
                            $product['id_product_attribute'] = !empty($productId[1]) ? $productId[1] : 0;
                            $product['quantity'] = $item['quantity'];
                            $product['id_address_delivery'] = (int) $address->id;
                            $products[] = $product;
                        }
                    }

                    $cart->setWsCartRows($products);

                    RetailcrmLogger::writeDebug(
                        __METHOD__,
                        sprintf(
                            '<%d> %s::%s',
                            $cart->id,
                            get_class($cart),
                            'update'
                        )
                    );

                    $cart->update();

                    /*
                     * Create order
                    */
                    $newOrder = new Order();
                    $newOrder->id_shop = Context::getContext()->shop->id;
                    $newOrder->id_shop_group = intval(Context::getContext()->shop->id_shop_group);
                    $newOrder->reference = $newOrder->generateReference();
                    $newOrder->id_address_delivery = (int) $address->id;
                    $newOrder->id_address_invoice = (int) $address->id;
                    $newOrder->id_cart = (int) $cart->id;
                    $newOrder->id_currency = $default_currency;
                    $newOrder->id_lang = self::$default_lang;
                    $newOrder->id_customer = (int) $customer->id;

                    if (isset($deliveryType)) {
                        $newOrder->id_carrier = (int) $deliveryType;
                    }

                    if (isset($paymentType)) {
                        $newOrder->payment = $paymentType;
                        $newOrder->module = $paymentId;
                    }

                    $newOrder->total_paid = $order['summ'] + $order['delivery']['cost'];
                    $newOrder->total_paid_tax_incl = $order['summ'] + $order['delivery']['cost'];
                    $newOrder->total_paid_tax_excl = $order['summ'] + $order['delivery']['cost'];
                    $newOrder->total_paid_real = $order['summ'] + $order['delivery']['cost'];
                    $newOrder->total_products = $order['summ'];
                    $newOrder->total_products_wt = $order['summ'];
                    $newOrder->total_shipping = $order['delivery']['cost'];
                    $newOrder->total_shipping_tax_incl = $order['delivery']['cost'];
                    $newOrder->total_shipping_tax_excl = $order['delivery']['cost'];
                    $newOrder->conversion_rate = 1.000000;

                    if (isset($orderStatus)) {
                        $newOrder->current_state = (int) $orderStatus;
                        $newOrderHistoryRecord = new OrderHistory(
                            null,
                            static::$default_lang,
                            Context::getContext()->shop->id
                        );
                    }

                    if (!empty($order['delivery']['date'])) {
                        $newOrder->delivery_date = $order['delivery']['date'];
                    }

                    $newOrder->date_add = $order['createdAt'];
                    $newOrder->date_upd = $order['createdAt'];
                    $newOrder->invoice_date = $order['createdAt'];
                    $newOrder->valid = 1;
                    $newOrder->secure_key = md5(time());

                    if (isset($order['discount'])) {
                        $newOrder->total_discounts = $order['discount'];
                    }

                    $product_list = array();

                    foreach ($order['items'] as $item) {
                        $product = new Product((int) $item['offer']['externalId'], false, self::$default_lang);
                        $product_id = $item['offer']['externalId'];
                        $product_attribute_id = 0;

                        if (RetailcrmReferences::GIFT_WRAPPING_ITEM_EXTERNAL_ID == $product_id) {
                            continue;
                        }

                        if (strpos($item['offer']['externalId'], '#') !== false) {
                            $product_id = explode('#', $item['offer']['externalId']);
                            $product_attribute_id = $product_id[1];
                            $product_id = $product_id[0];
                        }

                        if ($product_attribute_id != 0) {
                            $productName = htmlspecialchars(
                                strip_tags(Product::getProductName($product_id, $product_attribute_id))
                            );

                            $combinationPrice = Combination::getPrice($product_attribute_id);
                            $productPrice = $combinationPrice > 0 ? $product->getPrice() + $combinationPrice : $product->getPrice();
                        } else {
                            $productName = htmlspecialchars(strip_tags($product->name));
                            $productPrice = $product->getPrice();
                        }

                        $product_list[] = array(
                            'id_product' => $product->id,
                            'id_product_attribute' => $product_attribute_id,
                            'price' => $product->price,
                            'price_wt' => $productPrice,
                            'name' => $productName,
                            'cart_quantity' => $item['quantity'],
                            'weight' => 0,
                            'weight_attribute' => 0,
                            'stock_quantity' => $item['quantity'],
                            'ecotax' => 0,
                            'id_shop' => Context::getContext()->shop->id,
                            'additional_shipping_cost' => 0,
                            'total_wt' => $productPrice * $item['quantity'],
                            'total' => $productPrice * $item['quantity'],
                            'wholesale_price' => $product->wholesale_price,
                            'id_supplier' => $product->id_supplier,
                            'id_customization' => 0
                        );

                        if (isset($item['discountTotal'])) {
                            $newOrder->total_discounts += $item['discountTotal'] * $item['quantity'];
                        }
                    }

                    try {
                        RetailcrmLogger::writeDebug(
                            __METHOD__,
                            sprintf(
                                '<Customer ID: %d> %s::%s',
                                $newOrder->id_customer,
                                get_class($newOrder),
                                'add'
                            )
                        );

                        $newOrder->add(false, false);

                        if (isset($newOrderHistoryRecord)) {
                            $newOrderHistoryRecord->id_order = $newOrder->id;
                            $newOrderHistoryRecord->id_order_state = $newOrder->current_state;
                            $newOrderHistoryRecord->id_employee = static::getFirstEmployeeId();
                            $newOrderHistoryRecord->date_add = date('Y-m-d H:i:s');
                            $newOrderHistoryRecord->date_upd = $newOrderHistoryRecord->date_add;

                            RetailcrmLogger::writeDebug(
                                __METHOD__,
                                sprintf(
                                    '<Order ID: %d> %s::%s',
                                    $newOrderHistoryRecord->id_order,
                                    get_class($newOrderHistoryRecord),
                                    'add'
                                )
                            );

                            $newOrderHistoryRecord->add();
                        }
                    } catch (\Exception $e) {
                        RetailcrmLogger::writeCaller(
                            __METHOD__,
                            sprintf('Error adding order id=%d: %s', $order['id'], $e->getMessage())
                        );

                        RetailcrmLogger::writeNoCaller($e->getTraceAsString());
                    }

                    if (isset($order['payments']) && !empty($order['payments'])) {
                        foreach ($order['payments'] as $payment) {
                            if (!isset($payment['externalId'])
                                && isset($payment['status'])
                                && $payment['status'] == 'paid'
                            ) {
                                $ptype = $payment['type'];
                                $ptypes = $references->getSystemPaymentModules();

                                if ($payments[$ptype] != null) {
                                    foreach ($ptypes as $pay) {
                                        if ($pay['code'] == $payments[$ptype]) {
                                            $payType = $pay['name'];
                                        }
                                    }

                                    $orderPayment = new OrderPayment();
                                    $orderPayment->payment_method = $payType;
                                    $orderPayment->order_reference = $newOrder->reference;
                                    $orderPayment->id_currency = $default_currency;
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

                                    $orderPayment->save();
                                }
                            }
                        }
                    }

                    $carrier = new OrderCarrier();
                    $carrier->id_order = $newOrder->id;
                    $carrier->id_carrier = $deliveryType;
                    $carrier->shipping_cost_tax_excl = $order['delivery']['cost'];
                    $carrier->shipping_cost_tax_incl = $order['delivery']['cost'];

                    RetailcrmLogger::writeDebug(
                        __METHOD__,
                        sprintf(
                            '<Order ID: %d> %s::%s',
                            $carrier->id_order,
                            get_class($carrier),
                            'add'
                        )
                    );

                    $carrier->add(false, false);
                    /*
                     * collect order ids for single fix request
                    */
                    array_push($orderFix, array('id' => $order['id'], 'externalId' => $newOrder->id));

                    /*
                     * Create order details
                    */
                    $orderDetail = new OrderDetail();

                    $orderDetail->createList($newOrder, $cart, $newOrder->current_state, $product_list);

                    if (!empty($customerFix)) {
                        self::$api->customersFixExternalIds($customerFix);
                    }

                    if (!empty($orderFix)) {
                        self::$api->ordersFixExternalIds($orderFix);
                    }
                    //TODO
                    // Also update orders numbers after creating them in PrestaShop.
                    // Current logic will result in autogenerated order numbers in retailCRM if
                    // order was placed via retailCRM interface.
                } else {
                    $order = $order_history;

                    if (stripos($order['externalId'], 'pscart_') !== false) {
                        continue;
                    }

                    $orderToUpdate = new Order((int) $order['externalId']);
                    self::handleCustomerDataChange($orderToUpdate, $order);

                    /*
                     * check delivery changes
                     */
                    if (isset($order['status']) && isset($order['delivery'])) {
                        if ($order['status'] == 'new') {
                            $customerData = self::$api->customersGet($orderToUpdate->id_customer, 'externalId');
                            $customerForAddress = $customerData['customer'];

                            $customerBuilder = new RetailcrmCustomerBuilder();

                            if (isset($customerData['customer']['address'])) {
                                $customerForAddress['address'] = array_replace(
                                    $customerData['customer']['address'],
                                    $order['delivery']['address']
                                );
                            } else {
                                $customerForAddress['address'] =  $order['delivery']['address'];
                            }

                            $customerBuilder
                                ->setDataCrm($customerForAddress)
                                ->build();

                            $address = $customerBuilder->getData()->getCustomerAddress();
                            $address->id = $orderToUpdate->id_address_delivery;

                            $address->update();
                            $orderToUpdate->update();
                        }
                    }

                    /*
                     * check delivery type
                     */
                    if (!empty($order['delivery']['code'])) {
                        $dtype = $order['delivery']['code'];
                        $dcost = !empty($order['delivery']['cost']) ? $order['delivery']['cost'] : null;

                        if (isset($deliveries[$dtype]) && $deliveries[$dtype] != null) {
                            if ($deliveries[$dtype] != $orderToUpdate->id_carrier or $dcost != null) {
                                if ($dtype != null) {
                                    if (property_exists($orderToUpdate, 'id_order_carrier')) {
                                        $idOrderCarrier = $orderToUpdate->id_order_carrier;
                                    } elseif (method_exists($orderToUpdate, 'getIdOrderCarrier')) {
                                        $idOrderCarrier = $orderToUpdate->getIdOrderCarrier();
                                    }

                                    $orderCarrier = new OrderCarrier($idOrderCarrier);
                                    $orderCarrier->id_carrier = $deliveries[$dtype];
                                }

                                if ($dtype != null) {
                                    $orderCarrier->id_carrier = $deliveries[$dtype];
                                }

                                if ($dcost != null) {
                                    $orderCarrier->shipping_cost_tax_incl = $dcost;
                                    $orderCarrier->shipping_cost_tax_excl = $dcost;
                                }

                                $orderCarrier->id_order = $orderToUpdate->id;

                                RetailcrmLogger::writeDebug(
                                    __METHOD__,
                                    sprintf(
                                        '<%d> %s::%s',
                                        $orderCarrier->id,
                                        get_class($orderCarrier),
                                        'update'
                                    )
                                );

                                $orderCarrier->update();
                            }
                        }
                    }

                    /**
                     * check payment type
                     */
                    if (!empty($order['payments'])) {
                        foreach ($order['payments'] as $payment) {
                            if (!isset($payment['externalId'])
                                && isset($payment['status'])
                                && $payment['status'] == 'paid'
                            ) {
                                $ptype = $payment['type'];
                                $ptypes = $references->getSystemPaymentModules();

                                if ($payments[$ptype] != null) {
                                    foreach ($ptypes as $pay) {
                                        if ($pay['code'] == $payments[$ptype]) {
                                            $payType = $pay['name'];
                                        }
                                    }

                                    $paymentType = Module::getModuleName($payments[$ptype]);
                                    $orderToUpdate->payment = $paymentType != null ? $paymentType : $payments[$ptype];
                                    $orderPayment = new OrderPayment();
                                    $orderPayment->payment_method = $payType;
                                    $orderPayment->order_reference = $orderToUpdate->reference;

                                    if (isset($payment['amount'])){
                                        $orderPayment->amount = $payment['amount'];
                                    } else {
                                        $orderPayment->amount = $orderToUpdate->total_paid;
                                    }

                                    $orderPayment->id_currency = $default_currency;
                                    $orderPayment->date_add =
                                        isset($payment['paidAt']) ? $payment['paidAt'] : date('Y-m-d H:i:s');

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
                        }
                    }

                    if (isset($order['items'])) {
                        self::updateItems($order, $orderToUpdate);
                    }

                    /**
                     * check status
                     */
                    if (!empty($order['status'])) {
                        $stype = $order['status'];

                        if (isset($statuses[$stype]) && !empty($statuses[$stype])) {
                            if ($statuses[$stype] != $orderToUpdate->current_state) {
                                $orderHistory = new OrderHistory();
                                $orderHistory->id_employee = 0;
                                $orderHistory->id_order = $orderToUpdate->id;
                                $orderHistory->id_order_state = $statuses[$stype];
                                $orderHistory->date_add = date('Y-m-d H:i:s');

                                RetailcrmLogger::writeDebug(
                                    __METHOD__,
                                    sprintf(
                                        '<Order ID: %d> %s::%s',
                                        $orderToUpdate->id,
                                        get_class($orderHistory),
                                        'save'
                                    )
                                );

                                $orderHistory->save();

                                $orderToUpdate->current_state = $statuses[$stype];

                                RetailcrmLogger::writeDebug(
                                    __METHOD__,
                                    sprintf(
                                        '<Order ID: %d> %s::%s',
                                        $orderToUpdate->id,
                                        get_class($orderToUpdate),
                                        'update'
                                    )
                                );

                                $orderToUpdate->update();
                            }
                        }
                    }
                }
            }

            return true;
        } else {
            return 'Nothing to sync';
        }
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
    protected static function getCRMOrder($id, $by = 'externalId')
    {
        $crmOrderResponse = self::$api->ordersGet($id, $by);

        if (!empty($crmOrderResponse)
            && $crmOrderResponse->isSuccessful()
            && $crmOrderResponse->offsetExists('order')
        ) {
            return (array) $crmOrderResponse['order'];
        }

        return array();
    }

    /**
     * Sets all needed data for customer switch to switcher state
     *
     * @param array                           $crmCustomer
     * @param \RetailcrmCustomerSwitcherState $data
     * @param bool                            $isContact
     */
    protected static function prepareChangeToIndividual($crmCustomer, $data, $isContact = false)
    {
        RetailcrmLogger::writeDebugArray(
            __METHOD__,
            array(
                'Using this individual person data in order to set it into order,',
                $data->getOrder()->id,
                ': ',
                $crmCustomer
            )
        );

        if ($isContact) {
            $data->setNewContact($crmCustomer);
        } else {
            $data->setNewCustomer($crmCustomer);
        }
    }

    /**
     * Handle customer data change (from individual to corporate, company change, etc)
     *
     * @param \Order $order
     * @param array  $historyOrder
     *
     * @return bool True if customer change happened; false otherwise.
     */
    private static function handleCustomerDataChange($order, $historyOrder)
    {
        $handled = false;
        $crmOrder = array();
        $newCustomerId = null;
        $switcher = new RetailcrmCustomerSwitcher();
        $data = new RetailcrmCustomerSwitcherState();
        $data->setOrder($order);

        RetailcrmLogger::writeDebug(
            __METHOD__,
            $historyOrder
        );

        if (isset($historyOrder['customer'])) {
            $crmOrder = self::getCRMOrder($historyOrder['id'], 'id');

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
                    RetailcrmTools::arrayValue($crmOrder, 'customer', array()),
                    $data
                );
            }
        }

        if (isset($historyOrder['contact'])) {
            $newCustomerId = $historyOrder['contact']['id'];

            if (empty($crmOrder)) {
                $crmOrder = self::getCRMOrder($historyOrder['id'], 'id');
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
                    RetailcrmTools::arrayValue($crmOrder, 'contact', array()),
                    $data,
                    true
                );

                $data->setNewCustomer(array());
            }
        }

        if (isset($historyOrder['company'])) {
            if (!empty($crmOrder['company'])) {
                $data->setNewCompany($crmOrder['company']);
            } else {
                $data->setNewCompany($historyOrder['company']);
            }
        }

        if (!empty($crmOrder) && !empty($crmOrder['delivery']) && !empty($crmOrder['delivery']['address'])) {
            $data->setCrmOrderShippingAddress($crmOrder['delivery']['address']);
        }

        if ($data->feasible()) {
            try {
                $result = $switcher->setData($data)
                    ->build()
                    ->getResult();
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
     * Updates items in order via history
     *
     * @param array            $order
     * @param Order|\OrderCore $orderToUpdate
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private static function updateItems($order, $orderToUpdate)
    {
        /*
         * Clean deleted items
         */
        $id_order_detail = null;

        foreach ($order['items'] as $key => $item) {
            if (isset($item['delete']) && $item['delete'] == true) {
                if (RetailcrmOrderBuilder::isGiftItem($item)) {
                    $orderToUpdate->gift = false;
                }

                $parsedExtId = static::parseItemExternalId($item);
                $product_id = $parsedExtId['product_id'];
                $product_attribute_id = $parsedExtId['product_attribute_id'];
                $id_order_detail = !empty($parsedExtId['id_order_detail'])
                    ? $parsedExtId['id_order_detail'] : 0;

                self::deleteOrderDetailByProduct($orderToUpdate->id, $product_id, $product_attribute_id, $id_order_detail);

                unset($order['items'][$key]);
                $ItemDiscount = true;
            }
        }

        /*
         * Check items quantity and discount
         */
        foreach ($orderToUpdate->getProductsDetail() as $orderItem) {
            foreach ($order['items'] as $key => $item) {
                if (RetailcrmOrderBuilder::isGiftItem($item)) {
                    continue;
                }

                $parsedExtId = static::parseItemExternalId($item);
                $product_id = $parsedExtId['product_id'];
                $product_attribute_id = $parsedExtId['product_attribute_id'];
                $isExistingItem = isset($item['create']) ? false : true;

                if ($isExistingItem &&
                    $product_id == $orderItem['product_id'] &&
                    $product_attribute_id == $orderItem['product_attribute_id']
                ) {
                    $product = new Product((int) $product_id, false, self::$default_lang);
                    $tax = new TaxCore($product->id_tax_rules_group);

                    if ($product_attribute_id != 0) {
                        $prodPrice = Combination::getPrice($product_attribute_id);
                        $prodPrice = $prodPrice > 0 ? $prodPrice : $product->price;
                    } else {
                        $prodPrice = $product->price;
                    }

                    $prodPrice = $prodPrice + $prodPrice / 100 * $tax->rate;

                    // discount
                    $productPrice = $prodPrice - (isset($item['discountTotal']) ? $item['discountTotal'] : 0);
                    $productPrice = round($productPrice, 2);
                    $orderDetailId = !empty($parsedExtId['id_order_detail'])
                        ? $parsedExtId['id_order_detail'] : $orderItem['id_order_detail'];
                    $orderDetail = new OrderDetail($orderDetailId);
                    $orderDetail->unit_price_tax_incl = $productPrice;
                    $orderDetail->id_warehouse = 0;

                    // quantity
                    if (isset($item['quantity']) && $item['quantity'] != $orderItem['product_quantity']) {
                        $orderDetail->product_quantity = $item['quantity'];
                        $orderDetail->product_quantity_in_stock = $item['quantity'];
                        $orderDetail->id_order_detail = $orderDetailId;
                        $orderDetail->id_warehouse = 0;
                    }

                    if (!isset($orderDetail->id_order) && !isset($orderDetail->id_shop)) {
                        $orderDetail->id_order = $orderToUpdate->id;
                        $orderDetail->id_shop = Context::getContext()->shop->id;
                        $product = new Product((int) $product_id, false, self::$default_lang);

                        $productName = static::removeEdgeQuotes(htmlspecialchars(strip_tags(
                            !empty($item['offer']['displayName'])
                                ? $item['offer']['displayName']
                                : Product::getProductName($product_id, $product_attribute_id)
                        )));

                        static::setOrderDetailProductName($orderDetail, $productName);
                        $orderDetail->product_price = isset($item['initialPrice'])
                            ? $item['initialPrice'] : $product->price;

                        $orderDetail->product_id = (int) $product_id;
                        $orderDetail->product_attribute_id = (int) $product_attribute_id;
                        $orderDetail->product_quantity = (int) $item['quantity'];

                        RetailcrmLogger::writeDebug(
                            __METHOD__,
                            sprintf(
                                '<Order ID: %d> %s::%s',
                                $orderDetail->id_order,
                                get_class($orderDetail),
                                'save'
                            )
                        );

                        if ($orderDetail->save()) {
                            $upOrderItems = array(
                                'externalId' => $orderDetail->id_order,
                            );

                            $orderdb = new Order($orderDetail->id_order);

                            foreach ($orderdb->getProducts() as $item) {
                                if (isset($item['product_attribute_id']) && $item['product_attribute_id'] > 0) {
                                    $productId = $item['product_id'] . '#' . $item['product_attribute_id'];
                                } else {
                                    $productId = $item['product_id'];
                                }

                                $upOrderItems['items'][] = array(
                                    "id" => $key,
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

                            unset($orderdb);
                            self::$api->ordersEdit($upOrderItems);
                        }
                    }

                    RetailcrmLogger::writeDebug(
                        __METHOD__,
                        sprintf(
                            '<%d> %s::%s',
                            $orderDetail->id,
                            get_class($orderDetail),
                            'update'
                        )
                    );

                    $orderDetail->update();
                    $ItemDiscount = true;
                    unset($order['items'][$key]);
                }
            }
        }

        /*
         * Check new items
         */
        if (!empty($order['items'])) {
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
                $tax = new TaxCore($product->id_tax_rules_group);

                if ($product_attribute_id != 0) {
                    $productName = htmlspecialchars(
                        strip_tags(Product::getProductName($product_id, $product_attribute_id))
                    );
                    $productPrice = Combination::getPrice($product_attribute_id);
                    $productPrice = $productPrice > 0 ? $productPrice : $product->price;
                } else {
                    $productName = htmlspecialchars(strip_tags($product->name));
                    $productPrice = $product->price;
                }

                // discount
                if ((isset($newItem['discount']) && $newItem['discount'])
                    || (isset($newItem['discountPercent']) && $newItem['discountPercent'])
                    || (isset($newItem['discountTotal']) && $newItem['discountTotal'])
                ) {
                    $productPrice = $productPrice - $newItem['discount'];
                    $productPrice = $productPrice - $newItem['discountTotal'];
                    $productPrice = $productPrice - ($prodPrice / 100 * $newItem['discountPercent']);
                    $ItemDiscount = true;
                }

                $orderDetail = new OrderDetail(
                    !empty($parsedExtId['id_order_detail']) ? $parsedExtId['id_order_detail'] : null
                );

                static::setOrderDetailProductName($orderDetail, $productName);
                $orderDetail->id_order = $orderToUpdate->id;
                $orderDetail->id_order_invoice = $orderToUpdate->invoice_number;
                $orderDetail->id_shop = Context::getContext()->shop->id;
                $orderDetail->product_id = (int) $product_id;
                $orderDetail->product_attribute_id = (int) $product_attribute_id;
                $orderDetail->product_quantity = (int) $newItem['quantity'];
                $orderDetail->product_quantity_in_stock = (int) $newItem['quantity'];
                $orderDetail->product_price = $productPrice;
                $orderDetail->product_reference = implode('', array('\'', $product->reference, '\''));
                $orderDetail->total_price_tax_excl = $productPrice * $newItem['quantity'];
                $orderDetail->total_price_tax_incl = ($productPrice + $productPrice / 100 * $tax->rate) * $newItem['quantity'];
                $orderDetail->unit_price_tax_excl = $productPrice;
                $orderDetail->unit_price_tax_incl = ($productPrice + $productPrice / 100 * $tax->rate);
                $orderDetail->original_product_price = $productPrice;
                $orderDetail->id_warehouse = !empty($orderToUpdate->id_warehouse) ? $orderToUpdate->id_warehouse : 0;
                $orderDetail->id_order_detail =
                    !empty($parsedExtId['id_order_detail']) ? $parsedExtId['id_order_detail'] : null;

                RetailcrmLogger::writeDebug(
                    __METHOD__,
                    sprintf(
                        '<Order ID: %d> %s::%s',
                        $orderDetail->id_order,
                        get_class($orderDetail),
                        'save'
                    )
                );

                if ($orderDetail->save()) {
                    $upOrderItems = array(
                        'externalId' => $orderDetail->id_order,
                    );

                    $orderdb = new Order($orderDetail->id_order);

                    foreach ($orderdb->getProducts() as $item) {
                        if (isset($item['product_attribute_id']) && $item['product_attribute_id'] > 0) {
                            $productId = $item['product_id'] . '#' . $item['product_attribute_id'];
                        } else {
                            $productId = $item['product_id'];
                        }

                        $upOrderItems['items'][] = array(
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

                    unset($orderdb);
                    self::$api->ordersEdit($upOrderItems);
                }

                unset($orderDetail);
                unset($order['items'][$key]);
            }
        }

        $infoOrd = self::$api->ordersGet($order['externalId']);
        $infoOrder = $infoOrd->order;
        $totalPaid = $infoOrder['totalSumm'];
        $orderToUpdate->total_paid = $totalPaid;

        RetailcrmLogger::writeDebug(
            __METHOD__,
            sprintf(
                '<%d> %s::%s',
                $orderToUpdate->id,
                get_class($orderToUpdate),
                'update'
            )
        );

        $orderToUpdate->update();

        /*
         * Fix prices & discounts
         * Discounts only for whole order
         */
        if (isset($order['discount'])
            || isset($order['discountPercent'])
            || isset($order['delivery']['cost'])
            || isset($order['discountTotal'])
            || isset($ItemDiscount) && $ItemDiscount
        ) {
            $orderTotalProducts = $infoOrder['summ'];
            $deliveryCost = $infoOrder['delivery']['cost'];
            $totalDiscount = round($deliveryCost + $orderTotalProducts - $totalPaid, 2);
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

            RetailcrmLogger::writeDebug(
                __METHOD__,
                sprintf(
                    '<%d> %s::%s',
                    $orderToUpdate->id,
                    get_class($orderToUpdate),
                    'update'
                )
            );

            $orderToUpdate->update();
            unset($ItemDiscount);
        }
    }

    /**
     * Delete order product from order by product
     *
     * @param $order_id
     * @param $product_id
     * @param $product_attribute_id
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
     * @return boolean
     */
    private static function loadInCMS($object, $action)
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
                    (string)$action,
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
     * @param array  $historyEntries Raw history from CRM
     * @param string $recordType     Entity field name, e.g. `customer` or `order`.
     *
     * @return array
     */
    private static function filterHistory($historyEntries, $recordType)
    {
        $history = array();
        $organizedHistory = array();
        $notOurChanges = array();

        foreach ($historyEntries as $entry) {
            if (!isset($entry[$recordType]['externalId'])) {
                if ($entry['source'] == 'api'
                    && isset($change['apiKey']['current'])
                    && $entry['apiKey']['current'] == true
                    && $entry['field'] != 'externalId'
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
                $organizedHistory[$externalId] = array();
            }

            if (!isset($notOurChanges[$externalId])) {
                $notOurChanges[$externalId] = array();
            }

            if ($entry['source'] == 'api'
                && isset($entry['apiKey']['current'])
                && $entry['apiKey']['current'] == true
            ) {
                if (isset($notOurChanges[$externalId][$field]) || $entry['field'] == 'externalId') {
                    $organizedHistory[$externalId][] = $entry;
                } else {
                    continue;
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

            if (is_array($item) && count($item) > 0) {
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
                if ($externalId['code'] == 'prestashop') {
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
        $data = array(
            'product_id' => 0,
            'product_attribute_id' => 0,
            'id_order_detail' => 0
        );

        if (count($parsed) > 0) {
            $productIdParsed = explode('#', $parsed[0]);

            if (count($productIdParsed) == 2) {
                $data['product_id'] = $productIdParsed[0];
                $data['product_attribute_id'] = $productIdParsed[1];
            } elseif (count($productIdParsed) == 1) {
                $data['product_id'] = $parsed[0];
            }

            if (count($parsed) == 2) {
                $data['id_order_detail'] = $parsed[1];
            }
        }

        return $data;
    }

    /**
     * Returns oldest active employee id. For order history record.
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
        if (strlen($str) >= 2) {
            $newStr = $str;

            if ($newStr[0] == '\'' && $newStr[strlen($newStr) - 1] == '\'') {
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
     * @param string                       $name
     *
     * @throws \PrestaShopException
     */
    private static function setOrderDetailProductName(&$object, $name)
    {
        $object->product_name = static::removeEdgeQuotes($name);

        if ($object->validateField('product_name', $object->product_name) !== true) {
            $object->product_name = implode('', array('\'', $name, '\''));
        }
    }
}

