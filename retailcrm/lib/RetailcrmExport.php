<?php

/**
 * MIT License
 *
 * Copyright (c) 2020 DIGITAL RETAIL TECHNOLOGIES SL
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
 * @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmExport
{

    /**
     * Get total count of orders for context shop
     *
     * @return int
     */
    public static function getOrdersCount()
    {
        $sql = 'SELECT count(o.id_order) 
            FROM `' . _DB_PREFIX_ . 'orders` o 
            WHERE 1
            ' . Shop::addSqlRestriction(false, 'o');

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get orders ids from the database
     *
     * @param int $start Sets the LIMIT start parameter for sql query
     * @param int|null $count Sets the count of orders to get from database
     *
     * @return Generator
     * @throws PrestaShopDatabaseException
     */
    public static function getOrdersIds($start = 0, $count = null)
    {
        if (is_null($count)) {
            $count = static::getOrdersCount();
        }

        if ($count > 0) {
            $loadSize = 5000;
            $predefinedSql = 'SELECT o.`id_order`
                FROM `' . _DB_PREFIX_ . 'orders` o 
                WHERE 1
                ' . Shop::addSqlRestriction(false, 'o') . '
                ORDER BY o.`id_order` ASC';

            while ($start <= $count) {
                $offset = ($start + $loadSize > $count) ? $count - $start : $loadSize;

                $sql = $predefinedSql . '
                    LIMIT ' . (int)$start . ', ' . (int)$offset;

                $orders = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

                if (empty($orders))
                    break;

                foreach ($orders as $order) {
                    yield $order;
                }

                $start += $offset;
            }
        }
    }

    /**
     * Get total count of customers without orders for context shop
     *
     * @return int
     */
    public static function getCustomersCount()
    {
        $sql = 'SELECT count(c.id_customer) 
            FROM `' . _DB_PREFIX_ . 'customer` c
            WHERE 1
            ' . Shop::addSqlRestriction(false, 'c') . '
            AND c.id_customer not in (
                select o.id_customer 
                from `' . _DB_PREFIX_ . 'orders` o 
                WHERE 1
                ' . Shop::addSqlRestriction(false, 'o') . '
                group by o.id_customer
            )';

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get customers ids from database
     *
     * @param int $start Sets the LIMIT start parameter for sql query
     * @param null $count Sets the count of customers to get from database
     * @param bool $withAddressId If set to <b>true</b>, then also return address id in <i>`id_address`</i>
     *
     * @return Generator
     * @throws PrestaShopDatabaseException
     */
    public static function getCustomersIds($start = 0, $count = null, $withAddressId = true)
    {
        if (is_null($count)) {
            $count = static::getCustomersCount();
        }

        if ($count > 0) {
            $loadSize = 500;
            $predefinedSql = 'SELECT c.`id_customer`
                ' . ($withAddressId ? ', a.`id_address`' : '') . '
                FROM `' . _DB_PREFIX_ . 'customer` c
                ' . ($withAddressId ? '
                LEFT JOIN
                (
                    SELECT
                        ad.`id_customer`,
                        ad.`id_address`
                    FROM
                        `' . _DB_PREFIX_ . 'address` ad
                    INNER JOIN
                    (
                        SELECT
                            `id_customer`,
                            MAX(`date_add`) AS `date_add`
                        FROM
                            `' . _DB_PREFIX_ . 'address`
                        GROUP BY
                            id_customer
                    ) ad2
                    ON
                        ad2.`id_customer` = ad.`id_customer` AND ad2.`date_add` = ad.`date_add`
                    ORDER BY
                        ad.`id_customer` ASC
                ) a
                ON
                    a.`id_customer` = c.`id_customer`
                ' : '') . '
                WHERE 1
                ' . Shop::addSqlRestriction(false, 'c') . '
                AND c.`id_customer` not in (
                    select o.`id_customer` 
                    from `' . _DB_PREFIX_ . 'orders` o 
                    WHERE 1
                    ' . Shop::addSqlRestriction(false, 'o') . '
                    group by o.`id_customer`
                )
                ORDER BY c.`id_customer` ASC';


            while ($start <= $count) {
                $offset = ($start + $loadSize > $count) ? $count - $start : $loadSize;

                $sql = $predefinedSql . '
                    LIMIT ' . (int)$start . ', ' . (int)$offset;

                $customers = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

                if (empty($customers))
                    break;

                foreach ($customers as $customer) {
                    yield $customer;
                }

                $start += $offset;
            }
        }
    }

}
