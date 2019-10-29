<?php
/**
 * @author Retail Driver LCC
 * @copyright RetailCRM
 * @license GPL
 * @version 2.2.9
 * @link https://retailcrm.ru
 */
require_once(dirname(__FILE__) . '/../../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../../init.php');
require_once(dirname(__FILE__) . '/../bootstrap.php');

JobManager::startJobs(
    array(
        'abandonedCarts' => null
    ),
    true
);
