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
    const RETAILCRM_EXPORT_ORDERS_STEP_SIZE_CLI = 5000;
    const RETAILCRM_EXPORT_CUSTOMERS_STEP_SIZE_CLI = 5000;
    const RETAILCRM_EXPORT_ORDERS_STEP_SIZE_WEB = 50;
    const RETAILCRM_EXPORT_CUSTOMERS_STEP_SIZE_WEB = 300;

    /**
     * @var \RetailcrmProxy|\RetailcrmApiClientV5
     */
    static $api;

    /**
     * @var integer
     */
    static $ordersOffset;

    /**
     * @var integer
     */
    static $customersOffset;

    /**
     * Initialize inner state
     */
    public static function init()
    {
        static::$api = null;
        static::$ordersOffset = self::RETAILCRM_EXPORT_ORDERS_STEP_SIZE_CLI;
        static::$customersOffset = self::RETAILCRM_EXPORT_CUSTOMERS_STEP_SIZE_CLI;
    }

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
            $to = static::getOrdersCount();
            $count = $to - $start;
        } else {
            $to = $start + $count;
        }

        if ($count > 0) {
            $predefinedSql = 'SELECT o.`id_order`
                FROM `' . _DB_PREFIX_ . 'orders` o 
                WHERE 1
                ' . Shop::addSqlRestriction(false, 'o') . '
                ORDER BY o.`id_order` ASC';

            while ($start < $to) {
                $offset = ($start + static::$ordersOffset > $to) ? $to - $start : static::$ordersOffset;
                if ($offset <= 0)
                    break;

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
     * @param int $from
     * @param int|null $count
     */
    public static function exportOrders($from = 0, $count = null)
    {
        if (!static::validateState()) {
            return;
        }

        $orders = array();
        $orderRecords = static::getOrdersIds($from, $count);
        $orderBuilder = new RetailcrmOrderBuilder();
        $orderBuilder->defaultLangFromConfiguration()->setApi(static::$api);

        foreach ($orderRecords as $record) {
            $orderBuilder->reset();

            $order = new Order($record['id_order']);

            $orderCart = new Cart($order->id_cart);
            $orderCustomer = new Customer($order->id_customer);

            $orderBuilder->setCmsOrder($order);

            if (!empty($orderCart->id)) {
                $orderBuilder->setCmsCart($orderCart);
            } else {
                $orderBuilder->setCmsCart(null);
            }

            if (!empty($orderCustomer->id)) {
                $orderBuilder->setCmsCustomer($orderCustomer);
            } else {
                //TODO
                // Caused crash before because of empty RetailcrmOrderBuilder::cmsCustomer.
                // Current version *shouldn't* do this, but I suggest more tests for guest customers.
                $orderBuilder->setCmsCustomer(null);
            }

            try {
                $orders[] = $orderBuilder->buildOrderWithPreparedCustomer();
            } catch (\InvalidArgumentException $exception) {
                RetailcrmLogger::writeCaller('export', sprintf('Error while building %s: %s', $record['id_order'], $exception->getMessage()));
                RetailcrmLogger::writeNoCaller($exception->getTraceAsString());
                RetailcrmLogger::output($exception->getMessage());
            }

            time_nanosleep(0, 250000000);

            if (count($orders) == 50) {
                static::$api->ordersUpload($orders);
                $orders = array();
            }
        }

        if (count($orders)) {
            static::$api->ordersUpload($orders);
        }
    }

    /**
     * Get total count of customers for context shop
     * @param bool $withOrders If set to <b>true</b>, then return total count of customers.
     *                         If set to <b>false</b>, then return count of customers without orders
     *
     * @return int
     */
    public static function getCustomersCount($withOrders = true)
    {
        $sql = 'SELECT count(c.id_customer) 
            FROM `' . _DB_PREFIX_ . 'customer` c
            WHERE 1
            ' . Shop::addSqlRestriction(false, 'c');

        if (!$withOrders) {
            $sql .= '
            AND c.id_customer not in (
                select o.id_customer 
                from `' . _DB_PREFIX_ . 'orders` o 
                WHERE 1
                ' . Shop::addSqlRestriction(false, 'o') . '
                group by o.id_customer
            )';
        }

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get customers ids from database
     *
     * @param int $start Sets the LIMIT start parameter for sql query
     * @param null $count Sets the count of customers to get from database
     * @param bool $withOrders If set to <b>true</b>, then return all customers ids.
     *                         If set to <b>false</b>, then return ids only of customers without orders
     * @param bool $returnAddressId If set to <b>true</b>, then also return address id in <i>`id_address`</i>
     *
     * @return Generator
     * @throws PrestaShopDatabaseException
     */
    public static function getCustomersIds($start = 0, $count = null, $withOrders = true, $returnAddressId = true)
    {
        if (is_null($count)) {
            $to = static::getCustomersCount($withOrders);
            $count = $to - $start;
        } else {
            $to = $start + $count;
        }

        if ($count > 0) {
            $predefinedSql = 'SELECT c.`id_customer`
                ' . ($returnAddressId ? ', a.`id_address`' : '') . '
                FROM `' . _DB_PREFIX_ . 'customer` c
                ' . ($returnAddressId ? '
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
                ' . Shop::addSqlRestriction(false, 'c') .
                ($withOrders ? '' : '
                AND c.`id_customer` not in (
                    select o.`id_customer` 
                    from `' . _DB_PREFIX_ . 'orders` o 
                    WHERE 1
                    ' . Shop::addSqlRestriction(false, 'o') . '
                    group by o.`id_customer`
                )') . '
                ORDER BY c.`id_customer` ASC';


            while ($start < $to) {
                $offset = ($start + static::$customersOffset > $to) ? $to - $start : static::$customersOffset;
                if ($offset <= 0)
                    break;

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

    /**
     * @param int $from
     * @param int|null $count
     */
    public static function exportCustomers($from = 0, $count = null)
    {
        if (!static::validateState()) {
            return;
        }

        $customers = array();
        $customersRecords = RetailcrmExport::getCustomersIds($from, $count, false);

        foreach ($customersRecords as $record) {
            $customerId = $record['id_customer'];
            $addressId = $record['id_address'];

            $cmsCustomer = new Customer($customerId);

            if (Validate::isLoadedObject($cmsCustomer)) {
                if ($addressId) {
                    $cmsAddress = new Address($addressId);

                    $addressBuilder = new RetailcrmAddressBuilder();
                    $address = $addressBuilder
                        ->setAddress($cmsAddress)
                        ->build()
                        ->getDataArray();
                } else {
                    $address = array();
                }

                try {
                    $customers[] = RetailcrmOrderBuilder::buildCrmCustomer($cmsCustomer, $address);
                } catch (\Exception $exception) {
                    RetailcrmLogger::writeCaller('export', sprintf('Error while building %s: %s', $customerId, $exception->getMessage()));
                    RetailcrmLogger::writeNoCaller($exception->getTraceAsString());
                    RetailcrmLogger::output($exception->getMessage());
                }

                if (count($customers) == 50) {
                    static::$api->customersUpload($customers);
                    $customers = array();

                    time_nanosleep(0, 250000000);
                }
            }
        }

        if (count($customers)) {
            static::$api->customersUpload($customers);
        }
    }

    /**
     * @param int   $id
     * @param false $receiveOrderNumber
     * @return bool
     * @throws PrestaShopObjectNotFoundExceptionCore
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function exportOrder($id, $receiveOrderNumber = false)
    {
        if (!static::$api) {
            return false;
        }

        $object = new Order($id);
        $customer = new Customer($object->id_customer);
        $apiResponse = static::$api->ordersGet($object->id);
        $existingOrder = [];

        if ($apiResponse->isSuccessful() && $apiResponse->offsetExists('order')) {
            $existingOrder = $apiResponse['order'];
        }

        if (!Validate::isLoadedObject($object)) {
            throw new PrestaShopObjectNotFoundExceptionCore('Order not found');
        }

        $orderBuilder = new RetailcrmOrderBuilder();
        $crmOrder = $orderBuilder
            ->defaultLangFromConfiguration()
            ->setApi(static::$api)
            ->setCmsOrder($object)
            ->setCmsCustomer($customer)
            ->buildOrderWithPreparedCustomer();

        if (empty($crmOrder)) {
            return false;
        }

        if (empty($existingOrder)) {
            $response = static::$api->ordersCreate($crmOrder);

            if ($receiveOrderNumber && $response instanceof RetailcrmApiResponse && $response->isSuccessful()) {
                $crmOrder = $response->order;
                $object->reference = $crmOrder['number'];
                $object->update();
            }
        } else {
            $response = static::$api->ordersEdit($crmOrder);

            if (empty($existingOrder['payments']) && !empty($crmOrder['payments'])) {
                $payment = array_merge(reset($crmOrder['payments']), array(
                    'order' => array('externalId' => $crmOrder['externalId'])
                ));
                static::$api->ordersPaymentCreate($payment);
            }
        }

        return $response->isSuccessful();
    }

    /**
     * Returns false if inner state is not correct
     *
     * @return bool
     */
    private static function validateState()
    {
        if (!static::$api ||
            !static::$ordersOffset ||
            !static::$customersOffset
        ) {
            return false;
        }

        return true;
    }
}
