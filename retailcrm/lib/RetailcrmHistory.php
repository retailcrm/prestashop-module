<?php

class RetailcrmHistory
{
    public static $api;
    public static $default_lang;
    public static $apiVersion;

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

        $history = self::$api->customersHistory($filter);

        if ($history && count($history->history)) {
            $historyChanges = $history->history;
            $end = end($historyChanges);
            $sinceid = $end['id'];

            $customersHistory = RetailcrmHistoryHelper::assemblyCustomer($historyChanges);

            foreach ($customersHistory as $customerHistory) {
                if (isset($customerHistory['externalId'])) {
                    $customer = new Customer($customerHistory['externalId']);

                    if (isset($customerHistory['firstName'])) {
                        $customer->firstname = $customerHistory['firstName'];
                    }

                    if (isset($customerHistory['lastName'])) {
                        $customer->lastname = $customerHistory['lastName'];
                    }

                    if (isset($customerHistory['birthday'])) {
                        $customer->birthday = $customerHistory['birthday'];
                    }

                    if (isset($customerHistory['email']) && Validate::isEmail($customerHistory['email'])) {
                        $customer->email = $customerHistory['email'];
                    }

                    if (self::loadInCMS($customer, 'update') === false) {
                        continue;
                    }

                } else {
                    $customer = new Customer();

                    $customer->firstname = $customerHistory['firstName'];
                    $customer->lastname = isset($customerHistory['lastName']) ? $customerHistory['lastName'] : '--';
                    $customer->id_lang = self::$default_lang;

                    if (isset($customerHistory['birthday'])) {
                        $customer->birthday = $customerHistory['birthday'];
                    }

                    if (isset($customerHistory['sex'])) {
                        $customer->id_gender = $customerHistory['sex'] == 'male' ? 1 : 2;
                    }

                    if (isset($customerHistory['email']) && Validate::isEmail($customerHistory['email'])) {
                        $customer->email = $customerHistory['email'];
                    } else {
                        $customer->email = md5($customerHistory['firstName']) . '@retailcrm.ru';
                    }

                    $customer->passwd = Tools::substr(str_shuffle(Tools::strtolower(sha1(rand() . time()))), 0, 5);

                    if (self::loadInCMS($customer, 'add') === false) {
                        continue;
                    }

                    if (isset($customerHistory['address'])) {
                        $customerAddress = new Address();
                        $customerAddress->id_customer = $customer->id;
                        $customerAddress->alias = 'default';
                        $customerAddress->lastname = $customer->lastname;
                        $customerAddress->firstname = $customer->firstname;

                        if (isset($customerHistory['address']['countryIso'])) {
                            $customerAddress->id_country = Country::getByIso($customerHistory['address']['countryIso']);
                        }

                        if (isset($customerHistory['address']['region'])) {
                            $customerAddress->id_state = State::getIdByName($customerHistory['address']['region']);
                        }

                        $customerAddress->city = isset($customerHistory['address']['city']) ? $customerHistory['address']['city'] : '--';

                        $customerAddress->address1 = isset($customerHistory['address']['text']) ? $customerHistory['address']['text'] : '--';

                        if (isset($customerHistory['phones'])) {
                            $phone = reset($customerHistory['phones']);
                            $customerAddress->phone = $phone['number'];
                        }

                        if (isset($customerHistory['address']['index'])) {
                            $customerAddress->postcode = $customerHistory['address']['index'];
                        }

                        if (self::loadInCMS($customerAddress, 'add') === false) {
                            continue;
                        }
                    }

                    $customerFix[] = array(
                        'id' => $customerHistory['id'],
                        'externalId' => $customer->id
                    );
                }
            }

            if (!empty($customerFix)) {
                self::$api->customersFixExternalIds($customerFix);
            }

            /*
             * Update last sync id
             */
            Configuration::updateValue('RETAILCRM_LAST_CUSTOMERS_SYNC', $sinceid);

            return true;
        } else {
            return 'Nothing to sync';
        }
    }

    /**
     * Get orders history
     *
     * @return mixed
     */
    public static function ordersHistory()
    {
        $default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');

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
        }

        $customerFix = array();
        $orderFix = array();
        $history = self::$api->ordersHistory($filter);

        if ($history && count($history->history) > 0) {
            $historyChanges = $history->history;
            $end = end($historyChanges);
            $sinceId = $end['id'];

            $statuses = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_STATUS'), true)));
            $cartStatus = (string)(Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_STATUS'));
            $deliveries = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true)));
            $payments = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true)));
            $deliveryDefault = json_decode(Configuration::get('RETAILCRM_API_DELIVERY_DEFAULT'), true);
            $paymentDefault = json_decode(Configuration::get('RETAILCRM_API_PAYMENT_DEFAULT'), true);
            $orders = RetailcrmHistoryHelper::assemblyOrder($historyChanges);

            foreach ($orders as $order_history) {
                if (isset($order_history['deleted']) && $order_history['deleted'] == true) {
                    continue;
                }

                if (!array_key_exists('externalId', $order_history)) {
                    $responce = self::$api->ordersGet($order_history['id'], 'id');

                    if ($responce) {
                        $order = $responce['order'];

                        if ($order['status'] == $cartStatus) {
                            continue;
                        }
                    } else {
                        continue;
                    }

                    $delivery = $order['delivery']['code'];

                    if (array_key_exists($delivery, $deliveries) && $deliveries[$delivery] != '') {
                        $deliveryType = $deliveries[$delivery];
                    }

                    if (self::$apiVersion != 5) {
                        $payment = $order['paymentType'];
                    } else {
                        if (isset($order['payments']) && count($order['payments']) == 1) {
                            $paymentCRM = end($order['payments']);
                            $payment = $paymentCRM['type'];
                        } elseif (isset($order['payments']) && count($order['payments']) > 1) {
                            foreach ($order['payments'] as $paymentCRM) {
                                if ($paymentCRM['status'] != 'paid') {
                                    $payment = $paymentCRM['type'];
                                }
                            }
                        }
                    }

                    if (array_key_exists($payment, $payments) && !empty($payments[$payment])) {
                        if (Module::getInstanceByName($payments[$payment])) {
                            $paymentType = Module::getModuleName($payments[$payment]);
                        } else {
                            $paymentType = $payments[$payment];
                        }
                        $paymentId = $payments[$payment];
                    }

                    $state = $order['status'];

                    if (array_key_exists($state, $statuses) && $statuses[$state] != '') {
                        $orderStatus = $statuses[$state];
                    }
                    if (!isset($paymentId) || !$paymentId) {
                        $paymentId = $paymentDefault;
                    }

                    if (!$paymentType) {
                        if ($paymentDefault) {
                            if (Module::getInstanceByName($paymentDefault)) {
                                $paymentType = Module::getModuleName($paymentDefault);
                            } else {
                                $paymentType = $paymentDefault;
                            }
                        } else {
                            error_log(
                                'orderHistory: set default payment(error in order where id = '.$order['id'].')',
                                3,
                                _PS_ROOT_DIR_ . '/retailcrm.log'
                            );

                            continue;
                        }
                    }

                    if (!isset($deliveryType) || !$deliveryType) {
                        if ($deliveryDefault) {
                            $deliveryType = $deliveryDefault;
                        } else {
                            error_log(
                                'orderHistory: set default delivery(error in order where id = '.$order['id'].')',
                                3,
                                _PS_ROOT_DIR_ . '/retailcrm.log'
                            );
                            continue;
                        }
                    }

                    if (array_key_exists('externalId', $order['customer'])) {
                        $customerId = Customer::customerIdExistsStatic($order['customer']['externalId']);
                    }

                    if (!array_key_exists('externalId', $order['customer'])
                        || (isset($customerId) && $customerId == 0)
                    ) {
                        $customer = new Customer();
                        $customer->firstname = $order['customer']['firstName'];
                        $customer->lastname = !empty($order['customer']['lastName']) ? $order['customer']['lastName'] : '--';
                        $customer->email = Validate::isEmail($order['customer']['email']) ?
                            $order['customer']['email'] :
                            md5($order['customer']['firstName']) . '@retailcrm.ru';
                        $customer->passwd = Tools::substr(str_shuffle(Tools::strtolower(sha1(rand() . time()))), 0, 5);

                        if (self::loadInCMS($customer, 'add') === false) {
                            continue;
                        }

                        array_push(
                            $customerFix,
                            array(
                                'id' => $order['customer']['id'],
                                'externalId' => $customer->id
                            )
                        );
                    } else {
                        $customer = new Customer($customerId);
                    }

                    $address = new Address();
                    $address->id_customer = $customer->id;
                    $address->id_country = $default_country;
                    $address->lastname = $customer->lastname;
                    $address->firstname = $customer->firstname;
                    $address->alias = 'default';
                    $address->postcode = isset($order['delivery']['address']['index']) ? $order['delivery']['address']['index'] : '--';
                    $address->city = !empty($order['delivery']['address']['city']) ?
                        $order['delivery']['address']['city'] : '--';
                    $address->address1 = !empty($order['delivery']['address']['text']) ?
                        $order['delivery']['address']['text'] : '--';
                    $address->phone = isset($order['phone']) ? $order['phone'] : '';
                    $address->add();

                    $cart = new Cart();
                    $cart->id_currency = $default_currency;
                    $cart->id_lang = self::$default_lang;
                    $cart->id_customer = $customer->id;
                    $cart->id_address_delivery = (int) $address->id;
                    $cart->id_address_invoice = (int) $address->id;
                    $cart->id_carrier = (int) $deliveryType;

                    $cart->add();

                    $products = array();

                    if (!empty($order['items'])) {
                        foreach ($order['items'] as $item) {
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
                    $cart->update();

                    /*
                     * Create order
                    */
                    $newOrder = new Order();
                    $shops = Shop::getShops();
                    $newOrder->id_shop = Context::getContext()->shop->id;
                    $newOrder->id_shop_group = (int)$shops[Context::getContext()->shop->id]['id_shop_group'];
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

                        if (isset($item['discountTotal']) && self::$apiVersion == 5) {
                            $newOrder->total_discounts += $item['discountTotal'] * $item['quantity'];
                        }
                    }

                    $newOrder->add(false, false);

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
                                    $orderPayment->save();
                                }
                            }
                        }
                    }

                    $carrier = new OrderCarrierCore();
                    $carrier->id_order = $newOrder->id;
                    $carrier->id_carrier = $deliveryType;
                    $carrier->shipping_cost_tax_excl = $order['delivery']['cost'];
                    $carrier->shipping_cost_tax_incl = $order['delivery']['cost'];
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
                } else {
                    $order = $order_history;
                    $orderToUpdate = new Order((int) $order['externalId']);

                    /*
                     * check delivery type
                     */
                    if (!empty($order['delivery']['code'])) {
                        $dtype = $order['delivery']['code'];
                        $dcost = !empty($order['delivery']['cost']) ? $order['delivery']['cost'] : null;

                        if (isset($deliveries[$dtype]) && $deliveries[$dtype] != null) {
                            if ($deliveries[$dtype] != $orderToUpdate->id_carrier or $dcost != null) {
                                if ($dtype != null) {
                                    $orderCarrier = new OrderCarrier($orderToUpdate->id_order_carrier);
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

                                $orderCarrier->update();
                            }
                        }
                    }

                    /**
                     * check payment type
                     */
                    if (!empty($order['paymentType']) && self::$apiVersion != 5) {
                        $ptype = $order['paymentType'];

                        if ($payments[$ptype] != null) {
                            $paymentType = Module::getModuleName($payments[$ptype]);
                            if ($payments[$ptype] != $orderToUpdate->payment) {
                                $orderToUpdate->payment = $paymentType != null ? $paymentType : $payments[$ptype];
                                $orderPayment = OrderPayment::getByOrderId($orderToUpdate->id);
                                $orderPayment->payment_method = $payments[$ptype];
                                $orderPayment->update();
                            }
                        }
                    } elseif (!empty($order['payments']) && self::$apiVersion == 5) {
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
                                    $orderPayment->date_add = $payment['paidAt'];
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

                        if ($statuses[$stype] != null) {
                            if ($statuses[$stype] != $orderToUpdate->current_state) {
                                $orderHistory = new OrderHistory();
                                $orderHistory->id_employee = 0;
                                $orderHistory->id_order = $orderToUpdate->id;
                                $orderHistory->id_order_state = $statuses[$stype];
                                $orderHistory->date_add = date('Y-m-d H:i:s');
                                $orderHistory->save();

                                $orderToUpdate->current_state = $statuses[$stype];
                                $orderToUpdate->update();
                            }
                        }
                    }
                }
            }

            /*
             * Update last sync timestamp
             */
            Configuration::updateValue('RETAILCRM_LAST_ORDERS_SYNC', $sinceId);

            return true;
        } else {
            return 'Nothing to sync';
        }
    }

    private static function updateItems($order, $orderToUpdate)
    {
        /*
         * Clean deleted items
         */
        $id_order_detail = null;
        foreach ($order['items'] as $key => $item) {
            if (isset($item['delete']) && $item['delete'] == true) {
                if (strpos($item['offer']['externalId'], '#') !== false) {
                    $itemId = explode('#', $item['offer']['externalId']);
                    $product_id = $itemId[0];
                    $product_attribute_id = $itemId[1];
                } else {
                    $product_id = $item['offer']['externalId'];
                    $product_attribute_id = 0;
                }

                if (isset($item['externalIds'])) {
                    foreach ($item['externalIds'] as $externalId) {
                        if ($externalId['code'] == 'prestashop') {
                            $id_order_detail = explode('_', $externalId['value']);
                        }
                    }
                }else {
                    $id_order_detail = explode('#', $item['offer']['externalId']);
                }

                if (isset($id_order_detail[1])){
                    $id_order_detail = $id_order_detail[1];
                }

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
                if (strpos($item['offer']['externalId'], '#') !== false) {
                    $itemId = explode('#', $item['offer']['externalId']);
                    $product_id = $itemId[0];
                    $product_attribute_id = $itemId[1];
                } else {
                    $product_id = $item['offer']['externalId'];
                    $product_attribute_id = 0;
                }
                if ($product_id == $orderItem['product_id'] &&
                    $product_attribute_id == $orderItem['product_attribute_id']) {
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
                    if (self::$apiVersion == 5) {
                        $productPrice = $prodPrice - (isset($item['discountTotal']) ? $item['discountTotal'] : 0);
                    } else {
                        $productPrice = $prodPrice - $item['discount'];
                        if ($item['discountPercent'] > 0) {
                            $productPrice = $productPrice - ($prodPrice / 100 * $item['discountPercent']);
                        }
                    }
                    $productPrice = round($productPrice, 2);

                    if (isset($item['externalIds'])) {
                        foreach ($item['externalIds'] as $externalId) {
                            if ($externalId['code'] == 'prestashop') {
                                $id_order_detail = explode('_', $externalId['value']);
                            }
                        }
                    } else {
                        $id_order_detail = explode('#', $item['offer']['externalId']);
                    }

                    $orderDetail = new OrderDetail($id_order_detail[1]);
                    $orderDetail->unit_price_tax_incl = $productPrice;
                    $orderDetail->id_warehouse = 0;

                    // quantity
                    if (isset($item['quantity']) && $item['quantity'] != $orderItem['product_quantity']) {
                        $orderDetail->product_quantity = $item['quantity'];
                        $orderDetail->product_quantity_in_stock = $item['quantity'];
                        $orderDetail->id_order_detail = $id_order_detail[1];
                        $orderDetail->id_warehouse = 0;
                    }

                    if (!isset($orderDetail->id_order) && !isset($orderDetail->id_shop)) {
                        $orderDetail->id_order = $orderToUpdate->id;
                        $orderDetail->id_shop = Context::getContext()->shop->id;
                        $product = new Product((int) $product_id, false, self::$default_lang);

                        $productName = htmlspecialchars(strip_tags($item['offer']['displayName']));

                        $orderDetail->product_name = implode('', array('\'', $productName, '\''));
                        $orderDetail->product_price = $item['initialPrice'] ? $item['initialPrice'] : $product->price;

                        if (strpos($item['offer']['externalId'], '#') !== false) {
                            $product_id = explode('#', $item['offer']['externalId']);
                            $product_attribute_id = $product_id[1];
                            $product_id = $product_id[0];
                        }

                        $orderDetail->product_id = (int) $product_id;
                        $orderDetail->product_attribute_id = (int) $product_attribute_id;
                        $orderDetail->product_quantity = (int) $item['quantity'];

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
                $product_id = $newItem['offer']['externalId'];
                $product_attribute_id = 0;
                if (strpos($product_id, '#') !== false) {
                    $product_id = explode('#', $product_id);
                    $product_attribute_id = $product_id[1];
                    $product_id = $product_id[0];
                }
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

                if(isset($newItem['externalIds'])) {
                    foreach ($newItem['externalIds'] as $externalId) {
                        if ($externalId['code'] == 'prestashop') {
                            $id_order_detail = explode('_', $externalId['value']);
                        }
                    }
                } else {
                    $id_order_detail = explode('#', $item['offer']['externalId']);
                }

                $orderDetail = new OrderDetail($id_order_detail[1]);
                $orderDetail->id_order = $orderToUpdate->id;
                $orderDetail->id_order_invoice = $orderToUpdate->invoice_number;
                $orderDetail->id_shop = Context::getContext()->shop->id;
                $orderDetail->product_id = (int) $product_id;
                $orderDetail->product_attribute_id = (int) $product_attribute_id;
                $orderDetail->product_name = implode('', array('\'', $productName, '\''));
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
                $orderDetail->id_order_detail = $id_order_detail[1];

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
        $orderToUpdate->update();

        /*
         * Fix prices & discounts
         * Discounts only for whole order
         */
        if (isset($order['discount'])
            || isset($order['discountPercent'])
            || isset($order['delivery']['cost'])
            || isset($order['discountTotal'])
            || $ItemDiscount
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
            WHERE id_order = ' . $order_id . '
            AND product_id = ' . $product_id . '
            AND product_attribute_id = ' . $product_attribute_id . '
            AND id_order_detail = ' . $id_order_detail
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
     * @param $object
     * @param $action
     *
     * @return boolean
     */
    private static function loadInCMS($object, $action)
    {
        try {
            $object->$action();
        } catch (PrestaShopException $e) {
            error_log(
                '[' . date('Y-m-d H:i:s') . '] History:loadInCMS ' . $e->getMessage() . "\n",
                3,
                _PS_ROOT_DIR_ . '/retailcrm.log'
            );

            return false;
        }

        return true;
    }
}
