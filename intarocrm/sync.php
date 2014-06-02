<?php
include(dirname(__FILE__). '/../../config/config.inc.php');
include(dirname(__FILE__). '/../../init.php');
include(dirname(__FILE__). '/intarocrm.php');

header("Content-type: text/html");

$export = new IntaroCRM();
echo $export->orderHistory();
