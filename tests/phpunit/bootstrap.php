<?php

require_once dirname(__DIR__) . '../../../PrestaShop/tests/bootstrap.php';
require_once dirname(__DIR__) . '../../../PrestaShop/config/config.inc.php';
require_once dirname(__DIR__) . '../../../PrestaShop/config/defines_uri.inc.php';
require_once dirname(__DIR__) . '../../retailcrm/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/RetailcrmTestCase.php';
require_once dirname(__DIR__) . '/helpers/RetailcrmTestHelper.php';

$module = new RetailCRM();
$module->install();
