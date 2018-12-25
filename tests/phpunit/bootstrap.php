<?php

if (\file_exists(__DIR__ . '/../../../PrestaShop/tests-legacy')) {
    require_once __DIR__ . '/../../../PrestaShop/tests-legacy/bootstrap.php';
} else {
    require_once __DIR__ . '/../../../PrestaShop/tests/bootstrap.php';
}

require_once dirname(__DIR__) . '../../../PrestaShop/config/config.inc.php';
require_once dirname(__DIR__) . '../../../PrestaShop/config/defines_uri.inc.php';
require_once dirname(__DIR__) . '../../retailcrm/bootstrap.php';
require_once __DIR__ . '/../../retailcrm/retailcrm.php';
require_once __DIR__ . '/../helpers/RetailcrmTestCase.php';
require_once __DIR__ . '/../helpers/RetailcrmTestHelper.php';
require_once dirname(__DIR__) . '../../../PrestaShop/init.php';

$module = new RetailCRM();
$module->install();
