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
RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
RetailcrmHistory::$apiVersion = $apiVersion;

if (!empty($apiUrl) && !empty($apiKey)) {
    RetailcrmHistory::$api = new RetailcrmProxy($apiUrl, $apiKey, _PS_ROOT_DIR_ . '/retailcrm.log', $apiVersion);
} else {
    error_log('orderHistory: set api key & url first', 3, _PS_ROOT_DIR_ . '/retailcrm.log');
    exit();
}

RetailcrmHistory::customersHistory();
RetailcrmHistory::ordersHistory();
