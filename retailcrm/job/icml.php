<?php
include(dirname(__FILE__) . '/../../../config/config.inc.php');
include(dirname(__FILE__) . '/../../../init.php');

require(dirname(__FILE__) . '/../lib/vendor/Icml.php');

if (file_exists(dirname(__FILE__) . '/../lib/custom/Catalog.php')) {
    require(dirname(__FILE__) . '/../lib/custom/Catalog.php');
} else {
    require(dirname(__FILE__) . '/../lib/classes/Catalog.php');
}

$job = new Catalog();
$data = $job->getData();

$icml = new Icml(
    Configuration::get('PS_SHOP_NAME'),
    _PS_ROOT_DIR_ . '/retailcrm.xml'
);

$icml->generate($data[0], $data[1]);
