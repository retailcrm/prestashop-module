<?php
/**
 * MIT License
 *
 * Copyright (c) 2019 DIGITAL RETAIL TECHNOLOGIES SL
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2007-2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to version 3.0.0
 *
 * @param \RetailCRM $module
 *
 * @return bool
 */
function upgrade_module_3_0_0($module)
{
    $result = true;

    $apiVersion = 'RETAILCRM_API_VERSION';
    $lastRun = 'RETAILCRM_LAST_RUN';
    $syncCarts = 'RETAILCRM_API_SYNCHRONIZED_CART_DELAY';

    // Suppress warning. DB creation below shouldn't be changed in next versions.
    if ('retailcrm' != $module->name) {
        return false;
    }

    // API v4 is deprecated, so API version flag is removed for now.
    if (Configuration::hasKey($apiVersion)) {
        $result = Configuration::deleteByName($apiVersion);
    }

    // Fixes consequences of old fixed bug in JobManager
    if (Configuration::hasKey($lastRun)) {
        $result = $result && Configuration::deleteByName($lastRun);
    }

    // Immediate cart synchronization is not safe anymore (causes data inconsistency)
    if (Configuration::hasKey($syncCarts) && Configuration::get($syncCarts) == "0") {
        $result = $result && Configuration::set($syncCarts, "900");
    }

    return $result && Db::getInstance()->execute(
        'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'retailcrm_abandonedcarts` (
                    `id_cart` INT UNSIGNED UNIQUE NOT NULL,
                    `last_uploaded` DATETIME,
                    FOREIGN KEY (id_cart) REFERENCES '._DB_PREFIX_.'cart (id_cart)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) DEFAULT CHARSET=utf8;'
    );
}
