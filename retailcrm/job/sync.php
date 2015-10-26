<?php
include(dirname(__FILE__). '/../../../config/config.inc.php');
include(dirname(__FILE__). '/../../../init.php');
include(dirname(__FILE__). '/../lib/vendor/Retailcrm.php');

if (file_exists(dirname(__FILE__) . '/../lib/custom/References.php')) {
    require(dirname(__FILE__) . '/../lib/custom/References.php');
} else {
    require(dirname(__FILE__) . '/../lib/classes/References.php');
}

$apiUrl = Configuration::get('RETAILCRM_ADDRESS');
$apiKey = Configuration::get('RETAILCRM_API_TOKEN');

if (!empty($apiUrl) && !empty($apiKey)) {
    $api = new ApiClient($apiUrl, $apiKey);
} else {
    error_log('orderHistory: set api key & url first', 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
    exit();
}

$lastSync = Configuration::get('RETAILCRM_LAST_SYNC');

$startFrom = ($lastSync === false)
    ? date('Y-m-d H:i:s', strtotime('-1 days', strtotime(date('Y-m-d H:i:s'))))
    : $lastSync
;

$history = array();

/*
 * retrive orders from crm since last update
 */
try {
    $history = $api->ordersHistory(new DateTime($startFrom));
}
catch (CurlException $e) {
    error_log('orderHistory: connection error', 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
}
catch (InvalidJsonException $e) {
    error_log('orderHistory: ' . $e->getMessage(), 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
}

/*
 * store recieved data into shop database
*/
if (!empty($history->orders)) {

    /*
     * Customer object. Will be used for further updates.
    */
    $this->customer = new Customer();

    $statuses = array_flip((array)json_decode(Configuration::get('RETAILCRM_API_STATUS')));
    $deliveries = array_flip((array)json_decode(Configuration::get('RETAILCRM_API_DELIVERY')));
    $payments = array_flip((array)json_decode(Configuration::get('RETAILCRM_API_PAYMENT')));

    foreach ($history->orders as $order) {
        if (!array_key_exists('externalId', $order)) {
            /*
             * create customer if not exist
             */
            $this->customer->getByEmail($order['customer']['email']);

            if (!array_key_exists('externalId', $order['customer'])) {
                if (Validate::isEmail($order['customer']['email'])) {

                    if (!$this->customer->id)
                    {
                        $this->customer->firstname = $order['customer']['firstName'];
                        $this->customer->lastname = $order['customer']['lastName'];
                        $this->customer->email = $order['customer']['email'];
                        $this->customer->passwd = substr(str_shuffle(strtolower(sha1(rand() . time()))),0, 5);

                        if($this->customer->add()) {

                            /*
                             * create customer address for delivery data
                             */

                            $this->customer->getByEmail($order['customer']['email']);
                            $this->customer_id = $this->customer->id;

                            $address = new Address();
                            $address->id_customer = $this->customer->id;
                            $address->id_country = $this->default_country;
                            $address->lastname = $this->customer->lastname;
                            $address->firstname = $this->customer->firstname;
                            $address->alias = 'default';
                            $address->postcode = $order['deliveryAddress']['index'];
                            $address->city = $order['deliveryAddress']['city'];
                            $address->address1 = $order['deliveryAddress']['text'];
                            $address->phone = $order['phone'];
                            $address->phone_mobile = $order['phone'];

                            $address->add();

                            /*
                             * store address record id for handle order data
                            */
                            $addr = $this->customer->getAddresses($this->default_lang);
                            $this->address_id = $addr[0]['id_address'];
                        }
                    } else {
                        $addresses =  $this->customer->getAddresses($this->default_lang);
                        $this->address_id = $addresses[0]['id_address'];
                        $this->customer_id = $this->customer->id;
                    }

                    /*
                     * collect customer ids for single fix request
                     */
                    array_push(
                    $this->customerFix,
                    array(
                    'id' => $order['customer']['id'],
                    'externalId' => $this->customer_id
                    )
                    );
                }
            } else {
                $addresses =  $this->customer->getAddresses($this->default_lang);
                $this->address_id = $addresses[0]['id_address'];
                $this->customer_id = $order['customer']['externalId'];
            }

            $delivery = $order['deliveryType'];
            $payment = $order['paymentType'];
            $state = $order['status'];

            $cart = new Cart();
            $cart->id_currency = $this->default_currency;
            $cart->id_lang = $this->default_lang;
            $cart->id_customer = $this->customer_id;
            $cart->id_address_delivery = (int) $this->address_id;
            $cart->id_address_invoice = (int) $this->address_id;
            $cart->id_carrier = (int) $deliveries[$delivery];

            $cart->add();

            $products = array();
            if(!empty($order['items'])) {
                foreach ($order['items'] as $item) {
                    $product = array();
                    $product['id_product'] = (int) $item['offer']['externalId'];
                    $product['quantity'] = $item['quantity'];
                    $product['id_address_delivery'] = (int) $this->address_id;
                    $products[] = $product;
                }
            }

            $cart->setWsCartRows($products);
            $cart->update();

            /*
             * Create order
            */

            $newOrder = new Order();
            $newOrder->id_address_delivery = (int) $this->address_id;
            $newOrder->id_address_invoice = (int) $this->address_id;
            $newOrder->id_cart = (int) $cart->id;
            $newOrder->id_currency = $this->default_currency;
            $newOrder->id_lang = $this->default_lang;
            $newOrder->id_customer = (int) $this->customer_id;
            $newOrder->id_carrier = (int) $deliveries[$delivery];
            $newOrder->payment =  $payments[$payment];
            $newOrder->module = (Module::getInstanceByName('advancedcheckout') === false)
            ? $payments[$payment]
            : 'advancedcheckout'
                ;
                $newOrder->total_paid = $order['summ'] + $order['deliveryCost'];
                $newOrder->total_paid_tax_incl = $order['summ'] + $order['deliveryCost'];
                $newOrder->total_paid_tax_excl = $order['summ'] + $order['deliveryCost'];
                $newOrder->total_paid_real = $order['summ'] + $order['deliveryCost'];
                $newOrder->total_products = $order['summ'];
                $newOrder->total_products_wt = $order['summ'];
                $newOrder->total_shipping = $order['deliveryCost'];
                $newOrder->total_shipping_tax_incl = $order['deliveryCost'];
                $newOrder->total_shipping_tax_excl = $order['deliveryCost'];
                $newOrder->conversion_rate = 1.000000;
                $newOrder->current_state = (int) $statuses[$state];
                $newOrder->delivery_date = $order['deliveryDate'];
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
                array_push($this->orderFix, array('id' => $order['id'], 'externalId' => $newOrder->id));

                /*
                 * Create order details
                */
                $product_list = array();
                foreach ($order['items'] as $item) {
                    $product = new Product((int) $item['offer']['externalId'], false, $this->default_lang);
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

                try {
                    $this->api->customersFixExternalIds($this->customerFix);
                    $this->api->ordesrFixExternalIds($this->orderFix);
                }
                catch (CurlException $e) {
                    error_log('fixExternalId: connection error', 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
                    continue;
                }
                catch (InvalidJsonException $e) {
                    error_log('fixExternalId: ' . $e->getMessage(), 3, _PS_ROOT_DIR_ . "log/retailcrm.log");
                    continue;
                }

        } else {
            if (!in_array($order['id'], $toUpdate))
            {
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
                                        WHERE `id_order` = '.(int) $order['externalId']);
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
                                        WHERE `id_order` = '.(int) $order['externalId']);
                            Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'order_carrier`
                                        SET `id_carrier` = \''.$deliveries[$dtype].'\'
                                        WHERE `id_order` = \''.$orderToUpdate->id.'\'');
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
                                        WHERE `id_order` = '.(int) $order['externalId']);
                            Db::getInstance()->execute('
                                        UPDATE `'._DB_PREFIX_.'order_payment`
                                        SET `payment_method` = \''.$payments[$ptype].'\'
                                        WHERE `order_reference` = \''.$orderToUpdate->reference.'\'');

                        }
                    }

                    /*
                     * check items
                     */

                    /*
                     * Clean deleted
                     */
                    foreach ($order['items'] as $key => $item) {
                        if (isset($item['deleted']) && $item['deleted'] == true) {
                            Db::getInstance()->execute('
                                        DELETE FROM `'._DB_PREFIX_.'order_detail`
                                        WHERE `id_order` = '. $orderToUpdate->id .'
                                        AND `product_id` = '.$item['id']);

                            unset($order['items'][$key]);
                        }
                    }

                    /*
                     * check quantity
                     */

                    foreach ($orderToUpdate->getProductsDetail() as $orderItem) {
                        foreach ($order['items'] as $key => $item) {
                            if ($item['offer']['externalId'] == $orderItem['product_id']) {
                                if (isset($item['quantity']) && $item['quantity'] != $orderItem['product_quantity']) {
                                    Db::getInstance()->execute('
                                                UPDATE `'._DB_PREFIX_.'order_detail`
                                                SET `product_quantity` = '.$item['quantity'].',
                                                `product_quantity_in_stock` = '.$item['quantity'].'
                                                WHERE `id_order_detail` = '.$orderItem['id_order_detail']);
                                }

                                unset($order['items'][$key]);
                            }
                        }
                    }

                    /*
                     * check new items
                     */
                    if (!empty($order['items'])) {
                        foreach ($order['items'] as $key => $newItem) {
                            $product = new Product((int) $newItem['offer']['externalId'], false, $this->default_lang);
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
                                        WHERE `id_order` = '.(int) $order['externalId']);
                    }
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