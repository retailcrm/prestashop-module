<?php
$_SERVER['HTTPS'] = 1;

require(dirname(__FILE__) . '/../../../config/config.inc.php');
require(dirname(__FILE__) . '/../../../init.php');
require(dirname(__FILE__) . '/../bootstrap.php');

$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
$default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
$default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');

$apiUrl = Configuration::get('RETAILCRM_ADDRESS');
$apiKey = Configuration::get('RETAILCRM_API_TOKEN');

if (!empty($apiUrl) && !empty($apiKey)) {
    $api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log');
} else {
    error_log('orderHistory: set api key & url first', 3, _PS_ROOT_DIR_ . '/retailcrm.log');
    exit();
}

$lastSync = Configuration::get('RETAILCRM_LAST_SYNC');

$startFrom = ($lastSync === false)
    ? date('Y-m-d H:i:s', strtotime('-1 days', strtotime(date('Y-m-d H:i:s'))))
    : $lastSync
;

$customerFix = array();
$orderFix = array();

$startDate = new DateTime($startFrom);
$history = $api->ordersHistory(array(
    'startDate' => $startDate->format('Y-m-d H:i:s')
));

if ($history->isSuccessful() && count($history->history) > 0) {

    $statuses = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_STATUS'), true)));
    $deliveries = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true)));
    $payments = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true)));
    $deliveryDefault = json_decode(Configuration::get('RETAILCRM_API_DELIVERY_DEFAULT'), true);
    $paymentDefault = json_decode(Configuration::get('RETAILCRM_API_PAYMENT_DEFAULT'), true);

    $orders = RetailcrmHistoryHelper::assemblyOrder($history->history);

    foreach ($orders as $order) {
        if (isset($order['deleted']) && $order['deleted'] == true) continue;

        if (!array_key_exists('externalId', $order)) {

            $delivery = $order['delivery']['code'];

            if (array_key_exists($delivery, $deliveries) && $deliveries[$delivery] != '') {
                $deliveryType = $deliveries[$delivery];
            }

            $payment = $order['paymentType'];

            if (array_key_exists($payment, $payments) && $payments[$payment] != '') {
                if(Module::getInstanceByName($payments[$payment])) {
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

            if (!$paymentType){
                if ($paymentDefault) {

                    if(Module::getInstanceByName($paymentDefault)) {
                        $paymentType = Module::getModuleName($paymentDefault);
                    } else {
                        $paymentType = $paymentDefault;
                    }

                $paymentId = $paymentDefault;

                } else{
                    error_log('orderHistory: set default payment(error in order where id = '.$order['id'].')', 3, _PS_ROOT_DIR_ . '/retailcrm.log');
                    continue;
                }
            }

            if (!$deliveryType){
                if ($deliveryDefault) {
                    $deliveryType = $deliveryDefault;
                } else{
                    error_log('orderHistory: set default delivery(error in order where id = '.$order['id'].')', 3, _PS_ROOT_DIR_ . '/retailcrm.log');
                    continue;
                }
            }

            $customer = new Customer();
            if(!empty($order['customer']['email']))
                $customer->getByEmail($order['customer']['email']);

            if (!array_key_exists('externalId', $order['customer'])) {
                if (!$customer->id)
                {
                    $customer->firstname = $order['customer']['firstName'];
                    $customer->lastname = !empty($order['customer']['lastName']) ? $order['customer']['lastName'] : '-';
                    $customer->email = Validate::isEmail($order['customer']['email']) ? $order['customer']['email'] : md5($order['customer']['firstName']) . '@retailcrm.ru';
                    $customer->passwd = substr(str_shuffle(strtolower(sha1(rand() . time()))),0, 5);

                    $customer->add();
                }

                array_push(
                    $customerFix,
                    array(
                        'id' => $order['customer']['id'],
                        'externalId' => $customer->id
                    )
                );
            }

            $address = new Address();
            $address->id_customer = $customer->id;
            $address->id_country = $default_country;
            $address->lastname = $customer->lastname;
            $address->firstname = $customer->firstname;
            $address->alias = 'default';
            $address->postcode = $order['delivery']['address']['index'];
            $address->city = !empty($order['delivery']['address']['city']) ? $order['delivery']['address']['city'] : '-';
            $address->address1 = !empty($order['delivery']['address']['text']) ? $order['delivery']['address']['text'] : '-';
            $address->phone = $order['phone'];
            $address->add();

            $cart = new Cart();
            $cart->id_currency = $default_currency;
            $cart->id_lang = $default_lang;
            $cart->id_customer = $customer->id;
            $cart->id_address_delivery = (int) $address->id;
            $cart->id_address_invoice = (int) $address->id;
            $cart->id_carrier = (int) $deliveryType;

            $cart->add();

            $products = array();

            if(!empty($order['items'])) {
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

            $newOrder->id_address_delivery = (int) $address->id;
            $newOrder->id_address_invoice = (int) $address->id;
            $newOrder->id_cart = (int) $cart->id;
            $newOrder->id_currency = $default_currency;
            $newOrder->id_lang = $default_lang;
            $newOrder->id_customer = (int) $customer->id;
            if (isset($deliveryType)) $newOrder->id_carrier = (int) $deliveryType;
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
            if (isset($orderStatus)) $newOrder->current_state = (int) $orderStatus;
            if (!empty($order['delivery']['date'])) $newOrder->delivery_date = $order['delivery']['date'];
            $newOrder->date_add = $order['createdAt'];
            $newOrder->date_upd = $order['createdAt'];
            $newOrder->invoice_date = $order['createdAt'];
            $newOrder->valid = 1;
            $newOrder->secure_key = md5(time());

            if (isset($order['discount']))
            {
                $newOrder->total_discounts = $order['discount'];
            }

            $newOrder->add(false, false);

            $carrier = new OrderCarrierCore();
            $carrier->id_order = $newOrder->id;
            $carrier->id_carrier = $deliveryType;
            $carrier->shipping_cost_tax_excl = $order['delivery']['cost'];
            $carrier->shipping_cost_tax_incl = $order['delivery']['cost'];
            $carrier->date_add = $order['delivery']['date'];
            $carrier->add(false, false);

            /*
             * collect order ids for single fix request
            */
            array_push($orderFix, array('id' => $order['id'], 'externalId' => $newOrder->id));

            /*
             * Create order details
            */
            $product_list = array();

            foreach ($order['items'] as $item) {
                $product = new Product((int) $item['offer']['externalId'], false, $default_lang);
                $product_id = $item['offer']['externalId'];
                $product_attribute_id = 0;
                if(strpos($item['offer']['externalId'], '#') !== false) {
                    $product_id = explode('#', $item['offer']['externalId']);
                    $product_attribute_id = $product_id[1];
                    $product_id = $product_id[0];
                }

                if($product_attribute_id != 0) {
                    $productName = htmlspecialchars(strip_tags(Product::getProductName($product_id, $product_attribute_id)));

                    $productPrice = Combination::getPrice($product_attribute_id);
                    $productPrice = $productPrice > 0 ? $productPrice : $product->price;
                } else {
                    $productName = htmlspecialchars(strip_tags($product->name));
                    $productPrice = $product->price;
                }

                $product_list[] = array(
                    'product' => $product,
                    'product_attribute_id' => $product_attribute_id,
                    'product_price' => $productPrice,
                    'product_name' => $productName,
                    'quantity' => $item['quantity']
                );
            }

            $query = 'INSERT `'._DB_PREFIX_.'order_detail`
                (
                    `id_order`, `id_order_invoice`, `id_shop`, `product_id`, `product_attribute_id`,
                    `product_name`, `product_quantity`, `product_quantity_in_stock`, `product_price`,
                    `product_reference`, `total_price_tax_excl`, `total_price_tax_incl`,
                    `unit_price_tax_excl`, `unit_price_tax_incl`, `original_product_price`
                )

                VALUES';

            $context = new Context();
            foreach ($product_list as $product) {
                $query .= '('
                    .(int) $newOrder->id.',
                        0,
                        '. Context::getContext()->shop->id.',
                        '.(int) $product['product']->id.',
                        '.$product['product_attribute_id'].',
                        '.implode('', array('\'', $product['product_name'], '\'')).',
                        '.(int) $product['quantity'].',
                        '.(int) $product['quantity'].',
                        '.$product['product_price'].',
                        '.implode('', array('\'', $product['product']->reference, '\'')).',
                        '.$product['product_price'].',
                        '.$product['product_price'].',
                        '.$product['product_price'].',
                        '.$product['product_price'].',
                        '.$product['product_price'].'
                    ),';
            }

            Db::getInstance()->execute(rtrim($query, ','));

            if(!empty($customerFix))
                $api->customersFixExternalIds($customerFix);
            if(!empty($orderFix))
                $api->ordersFixExternalIds($orderFix);
        } else {
            $orderToUpdate = new Order((int) $order['externalId']);

            /*
             * check status
            */
            if(!empty($order['status'])) {
                $stype = $order['status'];

                if ($statuses[$stype] != null) {
                    if ($statuses[$stype] != $orderToUpdate->current_state) {
                        Db::getInstance()->execute('
                        INSERT INTO `' . _DB_PREFIX_ . 'order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`)
                        VALUES (
                            0,
                            ' . $orderToUpdate->id . ',
                            ' . $statuses[$stype] . ',
                            "' . date('Y-m-d H:i:s') . '"
                        )
                        ');

                        Db::getInstance()->execute('
                        UPDATE `' . _DB_PREFIX_ . 'orders`
                        SET `current_state` = \'' . $statuses[$stype] . '\'
                        WHERE `id_order` = ' . (int)$order['externalId']
                        );
                    }
                }
            }

            /*
             * check delivery type
             */
            if(!empty($order['delivery']['code'])) {
                $dtype = $order['delivery']['code'];
                $dcost = !empty($order['delivery']['cost']) ? $order['delivery']['cost'] : null;

                if ($deliveries[$dtype] != null) {
                    if ($deliveries[$dtype] != $orderToUpdate->id_carrier OR $dcost != null) {

                        if($dtype != null) {
                            Db::getInstance()->execute('
                            UPDATE `' . _DB_PREFIX_ . 'orders`
                            SET `id_carrier` = \'' . $deliveries[$dtype] . '\'
                            WHERE `id_order` = ' . (int)$order['externalId']
                            );
                        }

                        $updateCarrierFields = array();
                        if($dtype != null) {
                            $updateCarrierFields[] = '`id_carrier` = \'' . $deliveries[$dtype] . '\' ';
                        }
                        if($dcost != null) {
                            $updateCarrierFields[] = '`shipping_cost_tax_incl` = \'' . $dcost . '\' ';
                            $updateCarrierFields[] = '`shipping_cost_tax_excl` = \'' . $dcost . '\' ';
                        }
                        $updateCarrierFields = implode(', ', $updateCarrierFields);

                        Db::getInstance()->execute('
                        UPDATE `' . _DB_PREFIX_ . 'order_carrier` SET
                        '.$updateCarrierFields.'
                        WHERE `id_order` = \'' . $orderToUpdate->id . '\''
                        );
                    }
                }
            }

            /*
             * check payment type
             */
            if(!empty($order['paymentType'])) {
                $ptype = $order['paymentType'];

                if ($payments[$ptype] != null) {
                    $paymentType = Module::getModuleName($payments[$ptype]);
                    if ($payments[$ptype] != $orderToUpdate->payment) {
                        Db::getInstance()->execute('
                        UPDATE `' . _DB_PREFIX_ . 'orders`
                        SET `payment` = \'' . ($paymentType != null ? $paymentType : $payments[$ptype]). '\'
                        WHERE `id_order` = ' . (int)$order['externalId']
                        );
                        Db::getInstance()->execute('
                        UPDATE `' . _DB_PREFIX_ . 'order_payment`
                        SET `payment_method` = \'' . $payments[$ptype] . '\'
                        WHERE `order_reference` = \'' . $orderToUpdate->reference . '\''
                        );

                    }
                }
            }

            /*
             * Clean deleted items
             */
            foreach ($order['items'] as $key => $item) {
                if (isset($item['delete']) && $item['delete'] == true) {
                    if(strpos($item['offer']['externalId'], '#') !== false) {
                        $itemId = explode('#', $item['offer']['externalId']);
                        $product_id = $itemId[0];
                        $product_attribute_id = $itemId[1];
                    } else {
                        $product_id = $item['offer']['externalId'];
                        $product_attribute_id = 0;
                    }

                    Db::getInstance()->execute('
                        DELETE FROM `'._DB_PREFIX_.'order_detail`
                        WHERE `id_order` = '. $orderToUpdate->id .'
                        AND `product_id` = '.$product_id. '
                        AND `product_attribute_id` = '.$product_attribute_id
                    );

                    unset($order['items'][$key]);
                    $ItemDiscount = true;
                }
            }

            /*
             * Check items quantity and discount
             */
            foreach ($orderToUpdate->getProductsDetail() as $orderItem) {
                foreach ($order['items'] as $key => $item) {
                    if(strpos($item['offer']['externalId'], '#') !== false) {
                        $itemId = explode('#', $item['offer']['externalId']);
                        $product_id = $itemId[0];
                        $product_attribute_id = $itemId[1];
                    } else {
                        $product_id = $item['offer']['externalId'];
                        $product_attribute_id = 0;
                    }

                    if ($product_id == $orderItem['product_id'] && $product_attribute_id == $orderItem['product_attribute_id']) {

                        // discount
                        if (isset($item['discount']) || isset($item['discountPercent'])) {
                            $product = new Product((int) $product_id, false, $default_lang);
                            $tax = new TaxCore($product->id_tax_rules_group);

                            if($product_attribute_id != 0) {
                                $prodPrice = Combination::getPrice($product_attribute_id);
                                $prodPrice = $prodPrice > 0 ? $prodPrice : $product->price;
                            } else {
                                $prodPrice = $product->price;
                            }

                            $prodPrice = $prodPrice + $prodPrice / 100 * $tax->rate;

                            $productPrice = $prodPrice - $item['discount'];
                            $productPrice = $productPrice - ($prodPrice / 100 * $item['discountPercent']);
                            $ItemDiscount = true;

                            $productPrice = round($productPrice , 2);

                            Db::getInstance()->execute('
                                UPDATE `'._DB_PREFIX_.'order_detail`
                                SET `unit_price_tax_incl` = '.$productPrice.'
                                WHERE `id_order_detail` = '.$orderItem['id_order_detail']
                            );
                        }

                        // quantity
                        if (isset($item['quantity']) && $item['quantity'] != $orderItem['product_quantity']) {
                            Db::getInstance()->execute('
                                UPDATE `'._DB_PREFIX_.'order_detail`
                                SET `product_quantity` = '.$item['quantity'].',
                                `product_quantity_in_stock` = '.$item['quantity'].'
                                WHERE `id_order_detail` = '.$orderItem['id_order_detail']
                            );
                        }

                        unset($order['items'][$key]);
                    }
                }
            }

            /*
             * Check new items
             */
            if (!empty($order['items'])) {
                $query = 'INSERT `'._DB_PREFIX_.'order_detail`
                    (
                        `id_order`, `id_order_invoice`, `id_shop`, `product_id`, `product_attribute_id`,
                        `product_name`, `product_quantity`, `product_quantity_in_stock`, `product_price`,
                        `product_reference`, `total_price_tax_excl`, `total_price_tax_incl`,
                        `unit_price_tax_excl`, `unit_price_tax_incl`, `original_product_price`
                    )

                    VALUES';

                foreach ($order['items'] as $key => $newItem) {
                    $product_id = $newItem['offer']['externalId'];
                    $product_attribute_id = 0;
                    if(strpos($product_id, '#') !== false) {
                        $product_id = explode('#', $product_id);

                        $product_attribute_id = $product_id[1];
                        $product_id = $product_id[0];
                    }

                    $product = new Product((int) $product_id, false, $default_lang);
                    $tax = new TaxCore($product->id_tax_rules_group);

                    if($product_attribute_id != 0) {
                        $productName = htmlspecialchars(strip_tags(Product::getProductName($product_id, $product_attribute_id)));

                        $productPrice = Combination::getPrice($product_attribute_id);
                        $productPrice = $productPrice > 0 ? $productPrice : $product->price;
                    } else {
                        $productName = htmlspecialchars(strip_tags($product->name));
                        $productPrice = $product->price;
                    }

                    // discount
                    if ($newItem['discount'] || $newItem['discountPercent']) {
                        $productPrice = $productPrice - $newItem['discount'];
                        $productPrice = $productPrice - ($prodPrice / 100 * $newItem['discountPercent']);
                        $ItemDiscount = true;
                    }

                    $query .= '('
                        .(int) $orderToUpdate->id.',
                        0,
                        '. Context::getContext()->shop->id.',
                        '.(int) $product_id.',
                        '.(int) $product_attribute_id.',
                        '.implode('', array('\'', $productName, '\'')).',
                        '.(int) $newItem['quantity'].',
                        '.(int) $newItem['quantity'].',
                        '.$productPrice.',
                        '.implode('', array('\'', $product->reference, '\'')).',
                        '.$productPrice * $newItem['quantity'].',
                        '.($productPrice + $productPrice / 100 * $tax->rate) * $newItem['quantity'].',
                        '.$productPrice.',
                        '.($productPrice + $productPrice / 100 * $tax->rate).',
                        '.$productPrice.'
                    ),';

                    unset($order['items'][$key]);
                }

                Db::getInstance()->execute(rtrim($query, ','));
            }

            /*
             * Fix prices & discounts
             * Discounts only for whole order
             */
            if (isset($order['discount']) ||
                isset($order['discountPercent']) ||
                isset($order['delivery']['cost']) ||
                $ItemDiscount) {

                $infoOrd = $api->ordersGet($order['externalId']);
                $infoOrder = $infoOrd->order;

                $orderTotalProducts = $infoOrder['summ'];
                $totalPaid = $infoOrder['totalSumm'];
                $deliveryCost = $infoOrder['delivery']['cost'];
                $totalDiscount = $deliveryCost + $orderTotalProducts - $totalPaid;

                $orderCartRules = $orderToUpdate->getCartRules();
                foreach ($orderCartRules as $valCartRules) {
                    $order_cart_rule = new OrderCartRule($valCartRules['id_order_cart_rule']);
                    $order_cart_rule->delete();
                }
                $orderToUpdate->update();

                Db::getInstance()->execute('
                    UPDATE `'._DB_PREFIX_.'orders`
                    SET `total_discounts` = '.$totalDiscount.',
                    `total_discounts_tax_incl` = '.$totalDiscount.',
                    `total_discounts_tax_excl` = '.$totalDiscount.',
                    `total_shipping` = '.$deliveryCost.',
                    `total_shipping_tax_incl` = '.$deliveryCost.',
                    `total_shipping_tax_excl` = '.$deliveryCost.',
                    `total_paid` = '.$totalPaid.',
                    `total_paid_tax_incl` = '.$totalPaid.',
                    `total_paid_tax_excl` = '.$totalPaid.',
                    `total_products_wt` = '.$orderTotalProducts.'
                    WHERE `id_order` = '.(int) $order['externalId']
                );

                unset($ItemDiscount);
            }
        }
    }
    /*
     * Update last sync timestamp
     */
    Configuration::updateValue('RETAILCRM_LAST_SYNC', $history->generatedAt);
} else {
    return 'Nothing to sync';
}
