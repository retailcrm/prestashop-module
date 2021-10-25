<?php

if (file_exists(__DIR__ . '/../../PrestaShop/tests-legacy')) {
    require_once __DIR__ . '/../../PrestaShop/tests-legacy/bootstrap.php';
} else {
    require_once __DIR__ . '/../../PrestaShop/tests/bootstrap.php';
}

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers/RetailcrmTestCase.php';
require_once __DIR__ . '/helpers/RetailcrmTestHelper.php';

$module = new RetailCRM();
$module->install();
