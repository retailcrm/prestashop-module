<?php

require_once __DIR__.'/../vendor/autoload.php';

define('_PS_VERSION_', AppKernel::VERSION);

require_once _PS_CONFIG_DIR_.'alias.php';
require_once _PS_CLASS_DIR_.'PrestaShopAutoload.php';
spl_autoload_register([PrestaShopAutoload::getInstance(), 'load']);
