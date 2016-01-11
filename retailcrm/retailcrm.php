<?php

if (
    function_exists('date_default_timezone_set') &&
    function_exists('date_default_timezone_get')
) {
    date_default_timezone_set(@date_default_timezone_get());
}

if (!defined('_PS_VERSION_')) {
    exit;
} else {
    $allowed = array('1.4', '1.5', '1.6');
    $version = substr(_PS_VERSION_, 0, 3);
    if (!in_array($version, $allowed)) {
        exit;
    } else {
        require_once (dirname(__FILE__) . '/bootstrap.php');
        require(dirname(__FILE__) . '/version.' . $version . '.php');
    }
}

