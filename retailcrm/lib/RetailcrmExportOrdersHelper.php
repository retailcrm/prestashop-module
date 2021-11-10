<?php

/**
 * MIT License
 *
 * Copyright (c) 2021 DIGITAL RETAIL TECHNOLOGIES SL
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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmExportOrdersHelper
{
    const ROWS_PER_PAGE = 10;

    /**
     * @param int $id_order
     * @param int|null $id_order_crm
     * @param array|null $errors
     */
    public static function updateExportState($id_order, $id_order_crm = null, array $errors = null)
    {
        if (null === $id_order) {
            return false;
        }

        if (null === $id_order_crm) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'retailcrm_exported_orders` 
                WHERE `id_order` = \'' . pSQL($id_order) . '\';';

            $orderInfo = Db::getInstance()->executeS($sql);
            if (count($orderInfo) > 0 && isset($orderInfo[0]['id_order_crm'])) {
                $id_order_crm = $orderInfo[0]['id_order_crm'];
            }
        }

        if (null !== $errors && count($errors) > 0) {
            $errors = json_encode($errors);
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'retailcrm_exported_orders` 
                (`id_order`, `id_order_crm`, `errors`, `last_uploaded`)
                VALUES(
                    ' . ($id_order ? '\'' . pSQL($id_order) . '\'' : 'NULL') . ', 
            ' . ($id_order_crm ? '\'' . pSQL($id_order_crm) . '\'' : 'NULL') . ', 
            ' . ($errors ? '\'' . pSQL($errors) . '\'' : 'NULL') . ', 
            \'' . pSQL(date('Y-m-d H:i:s')) .
            '\')
            ON DUPLICATE KEY UPDATE 
            `id_order` = ' . ($id_order ? '\'' . pSQL($id_order) . '\'' : 'NULL') . ',
            `id_order_crm` = ' . ($id_order_crm ? '\'' . pSQL($id_order_crm) . '\'' : 'NULL') . ',
            `errors` = ' . ($errors ? '\'' . pSQL($errors) . '\'' : 'NULL') . ',
            `last_uploaded` = \'' . pSQL(date('Y-m-d H:i:s')) . '\';';

        return Db::getInstance()->execute($sql);
    }

    public static function getOrders($ordersIds = [], $withErrors = null, $page = 1)
    {
        if ($page < 0) {
            return [];
        }

        $sqlOrdersInfo = 'SELECT * FROM `' . _DB_PREFIX_ . 'retailcrm_exported_orders` WHERE 1';
        $sqlPagination = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'retailcrm_exported_orders` WHERE 1';

        if (count($ordersIds) > 0) {
            $sqlOrdersInfo .= ' AND (`id_order` IN ( ' . pSQL(implode(', ', $ordersIds)) . ')
                    OR `id_order_crm` IN ( ' . pSQL(implode(', ', $ordersIds)) . ')
                )';
            $sqlPagination .= ' AND (`id_order` IN ( ' . pSQL(implode(', ', $ordersIds)) . ')
                    OR `id_order_crm` IN ( ' . pSQL(implode(', ', $ordersIds)) . ')
                )';
        }

        if ($withErrors !== null) {
            $sqlOrdersInfo .= ' AND errors IS ' . ($withErrors ? 'NOT' : '') . ' NULL';
            $sqlPagination .= ' AND errors IS ' . ($withErrors ? 'NOT' : '') . ' NULL';
        }

        $totalCount = Db::getInstance()->getValue($sqlPagination);

        $pagination = [
            'totalCount' => $totalCount,
            'currentPage' => $page,
            'totalPageCount' => ceil($totalCount / self::ROWS_PER_PAGE)
        ];

        if ($page > $pagination['totalPageCount']) {
            $orderInfo = [];
        } else {
            $sqlOrdersInfo .= ' LIMIT ' . self::ROWS_PER_PAGE * ($page - 1) . ', ' . self::ROWS_PER_PAGE . ';';
            $orderInfo = Db::getInstance()->executeS($sqlOrdersInfo);
        }

        return [
            'orders' => $orderInfo,
            'pagination' => $pagination
        ];
    }
}
