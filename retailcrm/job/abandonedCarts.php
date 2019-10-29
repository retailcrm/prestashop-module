<?php
/**
 * @author Retail Driver LCC
 * @copyright RetailCRM
 * @license GPL
 * @version 2.2.9
 * @link https://retailcrm.ru
 *
 */
$_SERVER['HTTPS'] = 1;

require_once(dirname(__FILE__) . '/../../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../../init.php');
require_once(dirname(__FILE__) . '/../bootstrap.php');

if (empty(Configuration::get('RETAILCRM_API_SYNCHRONIZE_CARTS'))) {
    return;
}

$apiUrl = Configuration::get('RETAILCRM_ADDRESS');
$apiKey = Configuration::get('RETAILCRM_API_TOKEN');
$apiVersion = Configuration::get('RETAILCRM_API_VERSION');
$api = null;

if (!empty($apiUrl) && !empty($apiKey)) {
    $api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log', $apiVersion);
} else {
    error_log('abandonedCarts: set api key & url first', 3, _PS_ROOT_DIR_ . '/retailcrm.log');
    return;
}

$time = Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_DELAY');

if (is_numeric($time) && $time > 0) {
    $time = intval($time);
} else {
    $time = 0;
}

$now = new DateTime();
$sql = 'SELECT c.id_cart, c.date_upd 
                FROM '._DB_PREFIX_.'cart AS c
                WHERE id_customer != 0 
                  AND TIME_TO_SEC(TIMEDIFF(\''.pSQL($now->format('Y-m-d H:i:s')).'\', date_upd)) >= '.$time.'
                  AND c.id_cart NOT IN(SELECT id_cart from '._DB_PREFIX_.'orders);';
$rows = Db::getInstance()->executeS($sql);
$status = Configuration::get('RETAILCRM_API_SYNCHRONIZED_CART_STATUS');
$paymentTypes = array_keys(json_decode(Configuration::get('RETAILCRM_API_PAYMENT'), true));

if (empty($rows)
    || empty($status)
    || !$api
    || (count($paymentTypes) < 1)
) {
    return;
}

foreach ($rows as $cartId) {
    $cart = new Cart($cartId['id_cart']);
    $cartExternalId = RetailCRM::getCartOrderExternalId($cart);

    $response = $api->ordersGet($cartExternalId);

    if ($response === false) {
        $api->customersCreate(RetailCRM::buildCrmCustomer(new Customer($cart->id_customer)));
        $order = RetailCRM::buildCrmOrderFromCart($cart, $cartExternalId, $paymentTypes[0], $status);

        if (empty($order)) {
            continue;
        }

        if ($api->ordersCreate($order) !== false) {
            $cart->date_upd = date('Y-m-d H:i:s');
            $cart->save();
        }

        continue;
    }

    if (isset($response['order']) && !empty($response['order'])) {
        $order = RetailCRM::buildCrmOrderFromCart($cart, $response['order']['externalId'], $paymentTypes[0], $status);

        if (empty($order)) {
            continue;
        }

        if ($api->ordersEdit($order) !== false) {
            $cart->date_upd = date('Y-m-d H:i:s');
            $cart->save();
        }
    }
}
