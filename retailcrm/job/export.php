<?php
/**
 * @author Retail Driver LCC
 * @copyright RetailCRM
 * @license GPL
 * @version 2.2.0
 * @link https://retailcrm.ru
 *
 */

$_SERVER['HTTPS'] = 1;

require(dirname(__FILE__) . '/../../../config/config.inc.php');
require(dirname(__FILE__) . '/../../../init.php');
require(dirname(__FILE__) . '/../bootstrap.php');

$apiUrl = Configuration::get('RETAILCRM_ADDRESS');
$apiKey = Configuration::get('RETAILCRM_API_TOKEN');
$apiVersion = Configuration::get('RETAILCRM_API_VERSION');
$statusExport = Configuration::get('RETAILCRM_STATUS_EXPORT');

if (!empty($apiUrl) && !empty($apiKey)) {
    $api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log', $apiVersion);
} else {
    error_log('orderHistory: set api key & url first', 3, _PS_ROOT_DIR_ . '/retailcrm.log');
    exit();
}

$orders = array();
$customers = array();

$customerRecords = Customer::getCustomers();
$orderRecords = Order::getOrdersWithInformations();

$delivery = json_decode(Configuration::get('RETAILCRM_API_DELIVERY'), true);
$payment = json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true);
$status = json_decode(Configuration::get('RETAILCRM_API_STATUS'), true);

foreach ($customerRecords as $record) {
    $customers[$record['id_customer']] = RetailCRM::buildCrmCustomer(new Customer($record['id_customer']));
}

unset($customerRecords);

foreach ($orderRecords as $record) {
    $order = new Order();

    foreach ($record as $property => $value) {
        $order->$property = $value;
    }

    $order->id = $record['id_order'];

    $orders[$record['id_order']] = RetailCRM::buildCrmOrder(
        $order,
        null,
        null,
        true
    );
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
