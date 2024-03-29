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
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
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
    public static function updateExportState($id_order = null, $id_order_crm = null, array $errors = null)
    {
        if (null === $id_order && null === $id_order_crm) {
            return false;
        }

        if (null === $id_order) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'retailcrm_exported_orders`
                WHERE `id_order_crm` = \'' . pSQL($id_order_crm) . '\';';

            $orderInfo = Db::getInstance()->executeS($sql);
            if (0 < count($orderInfo) && isset($orderInfo[0]['id_order'])) {
                $id_order = $orderInfo[0]['id_order'];
            }
        }

        if (null === $id_order_crm) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'retailcrm_exported_orders`
                WHERE `id_order` = \'' . pSQL($id_order) . '\';';

            $orderInfo = Db::getInstance()->executeS($sql);
            if (0 < count($orderInfo) && isset($orderInfo[0]['id_order_crm'])) {
                $id_order_crm = $orderInfo[0]['id_order_crm'];
            }
        }

        if (null !== $errors && 0 < count($errors)) {
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
        if (0 > $page) {
            return [];
        }

        $sqlOrdersInfo = 'FROM `' . _DB_PREFIX_ . 'retailcrm_exported_orders` eo
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o on o.`id_order` = eo.`id_order`
            WHERE ( eo.`id_order` IS NULL OR ( 1 ' . Shop::addSqlRestriction(false, 'o') . ' ) )';

        if (0 < count($ordersIds)) {
            $sqlOrdersInfo .= ' AND (eo.`id_order` IN ( ' . pSQL(implode(', ', $ordersIds)) . ')
                    OR eo.`id_order_crm` IN ( ' . pSQL(implode(', ', $ordersIds)) . ')
                )';
        }

        if (null !== $withErrors) {
            $sqlOrdersInfo .= ' AND eo.`errors` IS ' . ($withErrors ? 'NOT' : '') . ' NULL';
        }

        $sqlPagination = 'SELECT COUNT(*) ' . $sqlOrdersInfo;
        $totalCount = Db::getInstance()->getValue($sqlPagination);

        $pagination = [
            'totalCount' => (int) $totalCount,
            'currentPage' => $page,
            'totalPageCount' => ceil($totalCount / self::ROWS_PER_PAGE),
        ];

        if ($page > $pagination['totalPageCount']) {
            $orderInfo = [];
        } else {
            $sqlOrdersInfo .= ' ORDER BY eo.`last_uploaded` DESC'; // todo order by function $orderBy argument
            $sqlOrdersInfo .= ' LIMIT ' . self::ROWS_PER_PAGE * ($page - 1) . ', ' . self::ROWS_PER_PAGE . ';';
            $sqlOrdersInfo = 'SELECT eo.* ' . $sqlOrdersInfo;
            $orderInfo = Db::getInstance()->executeS($sqlOrdersInfo);
        }

        return [
            'orders' => $orderInfo,
            'pagination' => $pagination,
        ];
    }

    public static function removeOrders()
    {
        $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'retailcrm_exported_orders` WHERE 1';

        return Db::getInstance()->execute($sql);
    }
}
