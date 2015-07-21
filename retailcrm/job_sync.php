<?php
include(dirname(__FILE__). '/../../config/config.inc.php');
include(dirname(__FILE__). '/../../init.php');
include(dirname(__FILE__). '/retailcrm.php');

header("Content-type: text/html");

$export = new RetailCRM();
echo $export->orderHistory();
