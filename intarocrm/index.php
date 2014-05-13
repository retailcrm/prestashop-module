<?php
include(dirname(__FILE__). '/../../config/config.inc.php');
include(dirname(__FILE__). '/../../init.php');
include(dirname(__FILE__). '/intarocrm.php');

header("Content-type: text/xml");

$export = new IntaroCRM();
echo $export->exportCatalog();