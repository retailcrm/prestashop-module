<?php
$_SERVER['HTTPS'] = 1;

require(dirname(__FILE__) . '/../../../config/config.inc.php');
require(dirname(__FILE__) . '/../../../init.php');
require(dirname(__FILE__) . '/../bootstrap.php');

$job = new RetailcrmCatalog();
$data = $job->getData();

$icml = new RetailcrmIcml(Configuration::get('PS_SHOP_NAME'), _PS_ROOT_DIR_ . '/retailcrm.xml');
$icml->generate($data[0], $data[1]);
