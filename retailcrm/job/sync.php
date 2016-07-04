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
$address_id = 0;

$history = $api->ordersHistory(new DateTime($startFrom));

if ($history->isSuccessful() && count($history->orders) > 0) {

    $statuses = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_STATUS'), true)));
    $deliveries = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true)));
    $payments = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true)));

    foreach ($history->orders as $order) {
        if (isset($order['deleted']) && $order['deleted'] == true) continue;

        if (!array_key_exists('externalId', $order)) {
            $customer = new Customer();
            $customer->getByEmail($order['customer']['email']);

            if (
                !array_key_exists('externalId', $order['customer']) &&
                Validate::isEmail($order['customer']['email'])
            ) {
                if (!$customer->id)
                {
                    $customer->firstname = $order['customer']['firstName'];
                    $customer->lastname = $order['customer']['lastName'];
                    $customer->email = $order['customer']['email'];
                    $customer->passwd = substr(str_shuffle(strtolower(sha1(rand() . time()))),0, 5);

                    if($customer->add()) {
                        $address = new Address();
                        $address->id_customer = $customer->id;
                        $address->id_country = $default_country;
                        $address->lastname = $customer->lastname;
                        $address->firstname = $customer->firstname;
                        $address->alias = 'default';
                        $address->postcode = $order['address']['index'];
                        $address->city = $order['address']['city'];
                        $address->address1 = $order['address']['text'];
                        $address->phone = $order['phone'];
                        $address->add();
                    }
                }

                array_push(
                    $customerFix,
                    array(
                        'id' => $order['customer']['id'],
                        'externalId' => $customer->id
                    )
                );
            }

            $delivery = $order['delivery']['code'];

            if (array_key_exists($delivery, $deliveries) && $deliveries[$delivery] != '') {
                $deliveryType = $deliveries[$delivery];
            }

            $payment = $order['paymentType'];

            if (array_key_exists($payment, $payments) && $payments[$payment] != '') {
                $paymentType = $payments[$payment];
            }

            $state = $order['status'];

            if (array_key_exists($state, $statuses) && $statuses[$state] != '') {
                $orderStatus = $statuses[$state];
            }

            $cart = new Cart();
            $cart->id_currency = $default_currency;
            $cart->id_lang = $default_lang;
            $cart->id_customer = $customer->id;
            $cart->id_address_delivery = (int) $address_id;
            $cart->id_address_invoice = (int) $address_id;
            $cart->id_carrier = (int) $deliveries[$delivery];

            $cart->add();

            $products = array();

            if(!empty($order['items'])) {
                foreach ($order['items'] as $item) {
                    $product = array();
                    $product['id_product'] = (int) $item['offer']['externalId'];
                    $product['quantity'] = $item['quantity'];
                    $product['id_address_delivery'] = (int) $address_id;
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
            $newOrder->id_shop = Shop::getCurrentShop();
            $newOrder->id_shop_group = (int)$shops[Shop::getCurrentShop()]['id_shop_group'];

            $newOrder->id_address_delivery = (int) $address_id;
            $newOrder->id_address_invoice = (int) $address_id;
            $newOrder->id_cart = (int) $cart->id;
            $newOrder->id_currency = $default_currency;
            $newOrder->id_lang = $default_lang;
            $newOrder->id_customer = (int) $customer->id;
            if (isset($deliveryType)) $newOrder->id_carrier = (int) $deliveryType;
            $newOrder->payment =  $payments[$payment];
            if (isset($paymentType)) {
                $newOrder->module = (Module::getInstanceByName('advancedcheckout') === false)
                    ? $paymentType
                    : 'advancedcheckout'
                ;
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
            $newOrder->valid = 1;
            $newOrder->secure_key = md5(time());

            if (isset($order['discount']))
            {
                $newOrder->total_discounts = $order['discount'];
            }

            $newOrder->add(false, false);

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
                $qty = $item['quantity'];
                $product_list[] = array('product' => $product, 'quantity' => $qty);
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
                        0,
                        '.implode('', array('\'', $product['product']->name, '\'')).',
                        '.(int) $product['quantity'].',
                        '.(int) $product['quantity'].',
                        '.$product['product']->price.',
                        '.implode('', array('\'', $product['product']->reference, '\'')).',
                        '.$product['product']->price.',
                        '.$product['product']->price.',
                        '.$product['product']->price.',
                        '.$product['product']->price.',
                        '.$product['product']->price.'
                    ),';
            }

            Db::getInstance()->execute(rtrim($query, ','));

            $api->customersFixExternalIds($customerFix);
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
                    if ($payments[$ptype] != $orderToUpdate->payment) {
                        Db::getInstance()->execute('
                        UPDATE `' . _DB_PREFIX_ . 'orders`
                        SET `payment` = \'' . $payments[$ptype] . '\'
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
                if (isset($item['deleted']) && $item['deleted'] == true) {
                    Db::getInstance()->execute('
                        DELETE FROM `'._DB_PREFIX_.'order_detail`
                        WHERE `id_order` = '. $orderToUpdate->id .'
                        AND `product_id` = '.$item['id']
                    );

                    unset($order['items'][$key]);
                }
            }

            /*
             * Check items quantity
             */
            foreach ($orderToUpdate->getProductsDetail() as $orderItem) {
                foreach ($order['items'] as $key => $item) {
                    if ($item['offer']['externalId'] == $orderItem['product_id']) {
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
                foreach ($order['items'] as $key => $newItem) {
                    $product = new Product((int) $newItem['offer']['externalId'], false, $default_lang);
                    $qty = $newItem['quantity'];
                    $product_list[] = array('product' =>$product, 'quantity' => $qty);
                }


                $query = 'INSERT `'._DB_PREFIX_.'order_detail`
                    (
                        `id_order`, `id_order_invoice`, `id_shop`, `product_id`, `product_attribute_id`,
                        `product_name`, `product_quantity`, `product_quantity_in_stock`, `product_price`,
                        `product_reference`, `total_price_tax_excl`, `total_price_tax_incl`,
                        `unit_price_tax_excl`, `unit_price_tax_incl`, `original_product_price`
                    )

                    VALUES';

                foreach ($product_list as $product) {
                    $query .= '('
                        .(int) $orderToUpdate->id.',
                        0,
                        '. Context::getContext()->shop->id.',
                        '.(int) $product['product']->id.',
                        0,
                        '.implode('', array('\'', $product['product']->name, '\'')).',
                        '.(int) $product['quantity'].',
                        '.(int) $product['quantity'].',
                        '.$product['product']->price.',
                        '.implode('', array('\'', $product['product']->reference, '\'')).',
                        '.$product['product']->price * $product['quantity'].',
                        '.($product['product']->price + $product['product']->price / 100 * 18) * $product['quantity'].',
                        '.$product['product']->price.',
                        '.($product['product']->price + $product['product']->price / 100 * 18).',
                        '.$product['product']->price.'
                    ),';
                }

                Db::getInstance()->execute(rtrim($query, ','));
                unset($order['items'][$key]);
            }

            /*
             * Fix prices & discounts
             * Discounts only for whole order
             */
            $orderDiscout = null;
            $orderTotalProducts = $order['summ'];

            if (isset($order['discount']) && $order['discount'] > 0) {
                if ($order['discount'] != $orderToUpdate->total_discounts) {
                    $orderDiscout = ($orderDiscout == null) ? $order['discount'] : $order['discount'] + $orderDiscout;
                }
            }

            if (isset($order['discountPercent']) && $order['discountPercent'] > 0) {
                $percent = ($order['summ'] * $order['discountPercent'])/100;
                if ($percent != $orderToUpdate->total_discounts) {
                    $orderDiscout = ($orderDiscout == null) ? $percent : $percent + $orderDiscout;
                }
            }

            $totalDiscount = ($orderDiscout == null) ? $orderToUpdate->total_discounts : $orderDiscout;

            $deliveryCost = $orderToUpdate->total_shipping;
            if(!empty($order['delivery']['cost'])) {
                $deliveryCost = $order['delivery']['cost'];
            }

            $totalPaid = $deliveryCost + $orderTotalProducts;

            if ($totalDiscount != $orderToUpdate->total_discounts ||
                $orderTotalProducts != $orderToUpdate->total_products_wt ||
                $deliveryCost != $orderToUpdate->total_shipping
            ) {
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
