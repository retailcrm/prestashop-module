<?php

require_once __DIR__.'/../../PrestaShop/config/defines.inc.php';
require_once __DIR__.'/../../PrestaShop/classes/PrestaShopAutoload.php';

require_once __DIR__ . '/../../PrestaShop/config/config.inc.php';
require_once __DIR__ . '/../../PrestaShop/config/defines_uri.inc.php';
require_once __DIR__ . '/../retailcrm/bootstrap.php';
require_once __DIR__ . '/../retailcrm/retailcrm.php';
require_once __DIR__ . '/../../PrestaShop/init.php';

$module = new RetailCRM();
$module->install();