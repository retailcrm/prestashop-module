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
     * @var int
     */
    static $ordersOffset;

    /**
     * @var int
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
    public static function getOrdersCount($skipUploaded = false)
    {
        $sql = 'SELECT count(o.id_order)
            FROM `' . _DB_PREFIX_ . 'orders` o' . ($skipUploaded ? '
            LEFT JOIN `' . _DB_PREFIX_ . 'retailcrm_exported_orders` reo ON o.`id_order` = reo.`id_order`
            ' : '') . '
            WHERE 1
            ' . Shop::addSqlRestriction(false, 'o') . ($skipUploaded ? '
            AND (reo.`last_uploaded` IS NULL OR reo.`errors` IS NOT NULL)
            ' : '');

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get orders ids from the database
     *
     * @param int $start Sets the LIMIT start parameter for sql query
     * @param int|null $count Sets the count of orders to get from database
     *
     * @return Generator
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getOrdersIds($start = 0, $count = null, $skipUploaded = false)
    {
        if (null === $count) {
            $to = static::getOrdersCount($skipUploaded);
            $count = $to - $start;
        } else {
            $to = $start + $count;
        }

        if (0 < $count) {
            $predefinedSql = 'SELECT o.`id_order`
                FROM `' . _DB_PREFIX_ . 'orders` o' . ($skipUploaded ? '
                LEFT JOIN `' . _DB_PREFIX_ . 'retailcrm_exported_orders` reo ON o.`id_order` = reo.`id_order`
                ' : '') . '
                WHERE 1
                ' . Shop::addSqlRestriction(false, 'o') . ($skipUploaded ? '
                AND (reo.`last_uploaded` IS NULL OR reo.`errors` IS NOT NULL)
                ' : '') . '
                ORDER BY o.`id_order` ASC';

            while ($start < $to) {
                $offset = ($start + static::$ordersOffset > $to) ? $to - $start : static::$ordersOffset;
                if (0 >= $offset) {
                    break;
                }

                $sql = $predefinedSql . '
                    LIMIT ' . (int) $start . ', ' . (int) $offset;

                $orders = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

                if (empty($orders)) {
                    break;
                }

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
    public static function exportOrders($from = 0, $count = null, $skipUploaded = false)
    {
        if (!static::validateState()) {
            return;
        }

        $orders = [];
        $orderRecords = static::getOrdersIds($from, $count, $skipUploaded);
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
                // TODO
                // Caused crash before because of empty RetailcrmOrderBuilder::cmsCustomer.
                // Current version *shouldn't* do this, but I suggest more tests for guest customers.
                $orderBuilder->setCmsCustomer(null);
            }

            try {
                $orders[] = $orderBuilder->buildOrderWithPreparedCustomer();
            } catch (Exception $exception) {
                self::handleError($record['id_order'], $exception);
            } catch (Error $exception) {
                self::handleError($record['id_order'], $exception);
            }

            time_nanosleep(0, 250000000);

            if (50 == count($orders)) {
                static::$api->ordersUpload($orders);
                $orders = [];
            }
        }

        if (count($orders)) {
            static::$api->ordersUpload($orders);
        }
    }

    /**
     * Get total count of customers for context shop
     *
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

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
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
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getCustomersIds($start = 0, $count = null, $withOrders = true, $returnAddressId = true)
    {
        if (null === $count) {
            $to = static::getCustomersCount($withOrders);
            $count = $to - $start;
        } else {
            $to = $start + $count;
        }

        if (0 < $count) {
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
                if (0 >= $offset) {
                    break;
                }

                $sql = $predefinedSql . '
                    LIMIT ' . (int) $start . ', ' . (int) $offset;

                $customers = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

                if (empty($customers)) {
                    break;
                }

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

        $customers = [];
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
                        ->getDataArray()
                    ;
                } else {
                    $address = [];
                }

                try {
                    $customers[] = RetailcrmOrderBuilder::buildCrmCustomer($cmsCustomer, $address);
                } catch (Exception $exception) {
                    self::handleError($customerId, $exception);
                } catch (Error $exception) {
                    self::handleError($customerId, $exception);
                }

                if (50 == count($customers)) {
                    static::$api->customersUpload($customers);
                    $customers = [];

                    time_nanosleep(0, 250000000);
                }
            }
        }

        if (count($customers)) {
            static::$api->customersUpload($customers);
        }
    }

    /**
     * @param int $id
     *
     * @return bool
     *
     * @throws RetailcrmNotFoundException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     */
    public static function exportOrder($id)
    {
        if (!static::$api) {
            return false;
        }

        $object = new Order($id);

        if (!Validate::isLoadedObject($object)) {
            throw new RetailcrmNotFoundException('Order not found');
        }

        $customer = new Customer($object->id_customer);
        $apiResponse = static::$api->ordersGet($object->id);
        $existingOrder = [];

        if ($apiResponse->isSuccessful() && $apiResponse->offsetExists('order')) {
            $existingOrder = $apiResponse['order'];
        }

        $orderBuilder = new RetailcrmOrderBuilder();
        $crmOrder = $orderBuilder
            ->defaultLangFromConfiguration()
            ->setApi(static::$api)
            ->setCmsOrder($object)
            ->setCmsCustomer($customer)
            ->buildOrderWithPreparedCustomer()
        ;

        if (empty($crmOrder)) {
            return false;
        }

        if (empty($existingOrder)) {
            $response = static::$api->ordersCreate($crmOrder);
        } else {
            $response = static::$api->ordersEdit($crmOrder);

            if (empty($existingOrder['payments']) && !empty($crmOrder['payments'])) {
                $payment = array_merge(reset($crmOrder['payments']), [
                    'order' => ['externalId' => $crmOrder['externalId']],
                ]);
                static::$api->ordersPaymentCreate($payment);
            }
        }

        if (!$response->isSuccessful()) {
            $errorMsg = '';
            if ($response->offsetExists('errorMsg')) {
                $errorMsg = $response['errorMsg'] . ': ';
            }
            if ($response->offsetExists('errors')) {
                $errorMsg .= implode('; ', $response['errors']);
            }

            throw new Exception($errorMsg);
        }

        return $response->isSuccessful();
    }

    /**
     * @param $orderIds
     *
     * @return array
     *
     * @throws Exception
     */
    public static function uploadOrders($orderIds)
    {
        if (!static::$api || !(static::$api instanceof RetailcrmProxy)) {
            throw new Exception('Set API key and API URL first');
        }

        $isSuccessful = true;
        $skippedOrders = [];
        $uploadedOrders = [];
        $errors = [];

        foreach ($orderIds as $orderId) {
            $id_order = (int) $orderId;
            $response = false;

            try {
                $response = self::exportOrder($id_order);

                if ($response) {
                    $uploadedOrders[] = $id_order;
                }
            } catch (RetailcrmNotFoundException $e) {
                $skippedOrders[] = $id_order;
            } catch (Exception $e) {
                $errors[$id_order][] = $e->getMessage();
            }

            $isSuccessful = $isSuccessful ? $response : false;
            time_nanosleep(0, 50000000);
        }

        return [
            'success' => $isSuccessful,
            'uploadedOrders' => $uploadedOrders,
            'skippedOrders' => $skippedOrders,
            'errors' => $errors,
        ];
    }

    /**
     * Returns false if inner state is not correct
     *
     * @return bool
     */
    private static function validateState()
    {
        if (!static::$api
            || !static::$ordersOffset
            || !static::$customersOffset
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param int $step
     * @param string $entity
     *
     * @return bool
     *
     * @throws Exception
     */
    public static function export($step, $entity = 'order')
    {
        --$step;
        if (0 > $step) {
            throw new Exception('Invalid request data');
        }

        if ('order' === $entity) {
            $stepSize = RetailcrmExport::RETAILCRM_EXPORT_ORDERS_STEP_SIZE_WEB;

            RetailcrmExport::$ordersOffset = $stepSize;
            RetailcrmExport::exportOrders($step * $stepSize, $stepSize, true);
        // todo maybe save current step to database
        } elseif ('customer' === $entity) {
            $stepSize = RetailcrmExport::RETAILCRM_EXPORT_CUSTOMERS_STEP_SIZE_WEB;

            RetailcrmExport::$customersOffset = $stepSize;
            RetailcrmExport::exportCustomers($step * $stepSize, $stepSize);
            // todo maybe save current step to database
        }
    }

    private static function handleError($entityId, $exception)
    {
        RetailcrmLogger::writeException('export', $exception, sprintf(
            'Error while building %s: %s', $entityId, $exception->getMessage()
        ), true);
        RetailcrmLogger::output($exception->getMessage());
    }
}
