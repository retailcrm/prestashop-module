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
$instance = new Order();
$records = $instance->getOrdersWithInformations(2);

$delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'));
$payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'));
$status = json_decode(Configuration::get('RETAILCRM_API_STATUS'));

foreach ($records as $record) {

    $object = new Order($record['id_order']);

    if (Module::getInstanceByName('advancedcheckout') === false) {
        $paymentType = $record['module'];
    } else {
        $paymentType = $record['payment'];
    }

    $cart = new Cart($object->getCartIdStatic($record['id_order']));
    $addressCollection = $cart->getAddressCollection();
    $address = array_shift($addressCollection);

    $order = array(
        'externalId' => $record['id_order'],
        'createdAt' => $record['date_add'],
        'status' => $record['current_state'] == 0 ? 'new' : $status->$record['current_state'],
        'firstName' => $record['firstname'],
        'lastName' => $record['lastname'],
        'email' => $record['email'],
        'phone' => $address->phone,
        'delivery' => array(
            'code' => $delivery->$record['id_carrier'],
            'cost' => $record['total_shipping_tax_incl'],
            'address' => array(
                'index' => $address->postcode,
                'city' => $address->city,
                'street' => sprintf("%s %s", $address->address1, $address->address2)
            )
        ),
        'paymentType' => $payment->$paymentType
    );

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

        $customer = new Customer($record['id_customer']);
        $customerCRM = array(
            'externalId' => $customer->id,
            'firstName' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,
            'phones' => array(array('number' => $address->phone)),
            'createdAt' => $customer->date_add,
            'address' => array(
                'index' => $address->postcode,
                'city' => $address->city,
                'street' => sprintf("%s %s", $address->address1, $address->address2)
            )
        );

        $customers[$customer->id] = $customerCRM;
    }

    $orders[] = $order;
}

var_dump(count($customers));
var_dump(count($orders));
