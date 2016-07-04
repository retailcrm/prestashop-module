<?php
$_SERVER['HTTPS'] = 1;

$shortopts = 'o:';
$options = getopt($shortopts);

if (!isset($options['o'])) {
    echo ('Parameter -o is missing');
    exit();
}

require(dirname(__FILE__) . '/../../../config/config.inc.php');
require(dirname(__FILE__) . '/../../../init.php');
require(dirname(__FILE__) . '/../bootstrap.php');

$apiUrl = Configuration::get('RETAILCRM_ADDRESS');
$apiKey = Configuration::get('RETAILCRM_API_TOKEN');

if (!empty($apiUrl) && !empty($apiKey)) {
    $api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log');
} else {
    echo('Set api key & url first');
    exit();
}

$delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true);
$payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true);
$status = json_decode(Configuration::get('RETAILCRM_API_STATUS'), true);

$orderInstance = new Order($options['o']);

$order = array(
    'externalId' => $orderInstance->id,
    'createdAt' => $orderInstance->date_add,
);

/**
 * Add order customer info
 *
 */

if (!empty($orderInstance->id_customer)) {
    $orderCustomer = new Customer($orderInstance->id_customer);
    $customer = array(
        'externalId' => $orderCustomer->id,
        'firstName' => $orderCustomer->firstname,
        'lastname' => $orderCustomer->lastname,
        'email' => $orderCustomer->email,
        'createdAt' => $orderCustomer->date_add
    );

    $response = $api->customersEdit($customer);

    if ($response) {
        $order['customer']['externalId'] = $orderCustomer->id;
        $order['firstName'] = $orderCustomer->firstname;
        $order['lastName'] = $orderCustomer->lastname;
        $order['email'] = $orderCustomer->email;
    } else {
        exit();
    }
}


/**
 *  Add order status
 *
 */

if ($orderInstance->current_state == 0) {
    $order['status'] = 'completed';
} else {
    $order['status'] = array_key_exists($orderInstance->current_state, $status)
        ? $status[$orderInstance->current_state]
        : 'completed'
    ;
}

/**
 * Add order address data
 *
 */

$cart = new Cart($orderInstance->getCartIdStatic($orderInstance->id));
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

if (!empty($postcode)) {
    $order['delivery']['address']['index'] = $postcode;
}

if (!empty($city)) {
    $order['delivery']['address']['city'] = $city;
}

if (!empty($addres_line)) {
    $order['delivery']['address']['text'] = $addres_line;
}

if (!empty($phone)) {
    $order['phone'] = $phone;
}

/**
 * Add payment & shippment data
 */

if (Module::getInstanceByName('advancedcheckout') === false) {
    $paymentType = $orderInstance->module;
} else {
    $paymentType = $orderInstance->payment;
}

if (array_key_exists($paymentType, $payment) && !empty($payment[$paymentType])) {
    $order['paymentType'] = $payment[$paymentType];
}

if (array_key_exists($orderInstance->id_carrier, $delivery) && !empty($delivery[$orderInstance->id_carrier])) {
    $order['delivery']['code'] = $delivery[$orderInstance->id_carrier];
}

if (isset($orderInstance->total_shipping_tax_incl) && (int) $orderInstance->total_shipping_tax_incl > 0) {
    $order['delivery']['cost'] = round($orderInstance->total_shipping_tax_incl, 2);
}

/**
 * Add products
 *
 */

$products = $orderInstance->getProducts();

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

var_dump($order); die();

$api->ordersEdit($order);
