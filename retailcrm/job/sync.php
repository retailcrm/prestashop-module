<?php

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

if ($history->isSuccess() && count($history->orders) > 0) {

    $statuses = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_STATUS'), true)));
    $deliveries = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true)));
    $payments = array_flip(array_filter(json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true)));

    foreach ($history->orders as $order) {

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
                        $customer->getByEmail($order['customer']['email']);
                        $customer_id = $customer->id;

                        $address = new Address();
                        $address->id_customer = $customer->id;
                        $address->id_country = $default_country;
                        $address->lastname = $customer->lastname;
                        $address->firstname = $customer->firstname;
                        $address->alias = 'default';
                        $address->postcode = $customer['address']['index'];
                        $address->city = $customer['address']['city'];
                        $address->address1 = $customer['address']['text'];
                        $address->phone = $customer['phones'][0]['number'];

                        $address->add();
                        $addr = $customer->getAddresses($default_lang);
                        $address_id = $addr[0]['id_address'];
                    }
                } else {
                    $addresses =  $customer->getAddresses($default_lang);
                    $address_id = $addresses[0]['id_address'];
                    $customer_id = $customer->id;
                }

                array_push(
                    $customerFix,
                    array(
                        'id' => $order['customer']['id'],
                        'externalId' => $customer_id
                    )
                );
            } else {
                $addresses =  $customer->getAddresses($default_lang);
                $address_id = $addresses[0]['id_address'];
                $customer_id = $order['customer']['externalId'];
            }

            $delivery = $order['delivery']['code'];
            $payment = $order['paymentType'];
            $state = $order['status'];

            $cart = new Cart();
            $cart->id_currency = $default_currency;
            $cart->id_lang = $default_lang;
            $cart->id_customer = $customer_id;
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
            $newOrder->id_address_delivery = (int) $address_id;
            $newOrder->id_address_invoice = (int) $address_id;
            $newOrder->id_cart = (int) $cart->id;
            $newOrder->id_currency = $default_currency;
            $newOrder->id_lang = $default_lang;
            $newOrder->id_customer = (int) $customer_id;
            $newOrder->id_carrier = (int) $deliveries[$delivery];
            $newOrder->payment =  $payments[$payment];
            $newOrder->module = (Module::getInstanceByName('advancedcheckout') === false)
                ? $payments[$payment]
                : 'advancedcheckout'
                ;
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
            $newOrder->current_state = (int) $statuses[$state];
            $newOrder->delivery_date = $order['delivery']['date'];
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
                    .(int) $newOrder->id.',
                        0,
                        '. $this->context->shop->id.',
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

            $this->api->customersFixExternalIds($customerFix);
            $this->api->ordersFixExternalIds($orderFix);
        } else {
            /*
             * take last order update only
             */
            if ($order['paymentType'] != null && $order['deliveryType'] != null && $order['status'] != null) {
                $orderToUpdate = new Order((int) $order['externalId']);

                /*
                 * check status
                */
                $stype = $order['status'];
                if ($statuses[$stype] != null) {
                    if ($statuses[$stype] != $orderToUpdate->current_state) {
                        Db::getInstance()->execute('
                            UPDATE `'._DB_PREFIX_.'orders`
                            SET `current_state` = \''.$statuses[$stype].'\'
                            WHERE `id_order` = '.(int) $order['externalId']
                        );
                    }
                }

                /*
                 * check delivery type
                 */
                $dtype = $order['deliveryType'];
                if ($deliveries[$dtype] != null) {
                    if ($deliveries[$dtype] != $orderToUpdate->id_carrier) {
                        Db::getInstance()->execute('
                            UPDATE `'._DB_PREFIX_.'orders`
                            SET `id_carrier` = \''.$deliveries[$dtype].'\'
                            WHERE `id_order` = '.(int) $order['externalId']
                        );
                        Db::getInstance()->execute('
                            UPDATE `'._DB_PREFIX_.'order_carrier`
                            SET `id_carrier` = \''.$deliveries[$dtype].'\'
                            WHERE `id_order` = \''.$orderToUpdate->id.'\''
                        );
                    }
                }

                /*
                 * check payment type
                 */
                $ptype = $order['paymentType'];
                if ($payments[$ptype] != null) {
                    if ($payments[$ptype] != $orderToUpdate->payment) {
                        Db::getInstance()->execute('
                            UPDATE `'._DB_PREFIX_.'orders`
                            SET `payment` = \''.$payments[$ptype].'\'
                            WHERE `id_order` = '.(int) $order['externalId']
                        );
                        Db::getInstance()->execute('
                            UPDATE `'._DB_PREFIX_.'order_payment`
                            SET `payment_method` = \''.$payments[$ptype].'\'
                            WHERE `order_reference` = \''.$orderToUpdate->reference.'\''
                        );

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
                            '. $this->context->shop->id.',
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
                    unset($order['items'][$key]);
                }

                /*
                 * Fix prices & discounts
                 * Discounts only for whole order
                 */
                $orderDiscout = null;
                $orderTotal = $order['summ'];

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

                if ($totalDiscount != $orderToUpdate->total_discounts || $orderTotal != $orderToUpdate->total_paid) {
                    Db::getInstance()->execute('
                        UPDATE `'._DB_PREFIX_.'orders`
                        SET `total_discounts` = '.$totalDiscount.',
                        `total_discounts_tax_incl` = '.$totalDiscount.',
                        `total_discounts_tax_excl` = '.$totalDiscount.',
                        `total_paid` = '.$orderTotal.',
                        `total_paid_tax_incl` = '.$orderTotal.',
                        `total_paid_tax_excl` = '.$orderTotal.'
                        WHERE `id_order` = '.(int) $order['externalId']
                    );
                }
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
