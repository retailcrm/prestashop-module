<?php

require(dirname(__FILE__) . '/../../../config/config.inc.php');
require(dirname(__FILE__) . '/../../../init.php');
require(dirname(__FILE__) . '/../bootstrap.php');

$apiUrl = Configuration::get('RETAILCRM_ADDRESS');
$apiKey = Configuration::get('RETAILCRM_API_TOKEN');

if (!empty($apiUrl) && !empty($apiKey)) {
    $api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log');
} else {
    error_log('orderHistory: set api key & url first', 3, _PS_ROOT_DIR_ . '/retailcrm.log');
    exit();
}

$orders = array();
$customers = array();

$customerInstance = new Customer();
$orderInstance = new Order();

$customerRecords = $customerInstance->getCustomers();
$orderRecords = $orderInstance->getOrdersWithInformations();

$delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true);
$payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true);
$status = json_decode(Configuration::get('RETAILCRM_API_STATUS'), true);

foreach ($customerRecords as $record) {
    $customers[$record['id_customer']] = array(
        'externalId' => $record['id_customer'],
        'firstName' => $record['firstname'],
        'lastname' => $record['lastname'],
        'email' => $record['email']
    );
}

unset($customerRecords);

foreach ($orderRecords as $record) {

    $object = new Order($record['id_order']);

    if (Module::getInstanceByName('advancedcheckout') === false) {
        $paymentType = $record['module'];
    } else {
        $paymentType = $record['payment'];
    }

    if ($record['current_state'] == 0) {
        $order_status = 'completed';
    } else {
        $order_status = array_key_exists($record['current_state'], $status)
            ? $status[$record['current_state']]
            : 'completed'
        ;
    }

    $cart = new Cart($object->getCartIdStatic($record['id_order']));
    $addressCollection = $cart->getAddressCollection();
    $address = array_shift($addressCollection);

    if ($address instanceof Address) {
        $phone = is_null($address->phone)
            ? is_null($address->phone_mobile) ? '' : $address->phone_mobile
            : $address->phone
        ;

        $postcode = $address->postcode;
        $city = $address->city;
        $addres_line = sprintf("%s %s", $address->address1, $address->address2);
    }

    $order = array(
        'externalId' => $record['id_order'],
        'createdAt' => $record['date_add'],
        'status' => $order_status,
        'firstName' => $record['firstname'],
        'lastName' => $record['lastname'],
        'email' => $record['email'],
    );

    if (isset($postcode)) {
        $order['delivery']['address']['postcode'] = $postcode;
    }

    if (isset($city)) {
        $order['delivery']['address']['city'] = $city;
    }

    if (isset($addres_line)) {
        $order['delivery']['address']['text'] = $addres_line;
    }

    if ($phone) {
        $order['phone'] = $phone;
    }

    if (array_key_exists($paymentType, $payment)) {
        $order['paymentType'] = $payment[$paymentType];
    }

    if (array_key_exists($record['id_carrier'], $delivery)) {
        $order['delivery']['code'] = $delivery[$record['id_carrier']];
    }

    if (isset($record['total_shipping_tax_incl']) && (int) $record['total_shipping_tax_incl'] > 0) {
        $order['delivery']['cost'] = round($record['total_shipping_tax_incl'], 2);
    }

    $products = $object->getProducts();

    foreach($products as $product) {
        $item = array(
            'productId' => $product['product_id'],
            'productName' => $product['product_name'],
            'quantity' => $product['product_quantity'],
            'initialPrice' => round($product['product_price'], 2),
            'purchasePrice' => round($product['purchase_supplier_price'], 2)
        );

        $order['items'][] = $item;
    }

    if ($record['id_customer']) {
        $order['customer']['externalId'] = $record['id_customer'];
    }

    $orders[$record['id_order']] = $order;
}

unset($orderRecords);

$customers = array_chunk($customers, 50);

foreach ($customers as $chunk) {
    $api->customersUpload($chunk);
    time_nanosleep(0, 200000000);
}

$orders = array_chunk($orders, 50);

foreach ($orders as $chunk) {
    $api->ordersUpload($chunk);
    time_nanosleep(0, 200000000);
}
