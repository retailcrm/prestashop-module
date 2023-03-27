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

class RetailcrmCartUploader
{
    /**
     * @var \RetailcrmProxy|\RetailcrmApiClientV5
     */
    static $api;

    /**
     * @var string
     */
    static $site;

    /** @var \Context|\ContextCore */
    static $context;

    /** @var Cart|\CartCore */
    static $origCart;

    /** @var \Employee|\EmployeeCore */
    static $origEmployee;

    /**
     * @var array
     */
    static $cartsIds;
    /**
     * @var int
     */
    static $syncDelay;

    /**
     * @var int
     */
    static $allowedUpdateInterval;

    /**
     * @var \DateTime
     */
    static $now;

    /**
     * @var string
     */
    static $crmCartDateFormat;

    /**
     * Cast provided sync delay to integer
     *
     * @param $time
     */
    public static function setSyncDelay($time)
    {
        if (is_numeric($time) && 0 < $time) {
            static::$syncDelay = (int) $time;
        } else {
            static::$syncDelay = 0;
        }
    }

    /**
     * Initialize inner state
     */
    public static function init()
    {
        static::$api = null;
        static::$cartsIds = [];
        static::$syncDelay = 0;
        static::$allowedUpdateInterval = 86400;
        static::$now = new DateTimeImmutable();
        static::$context = Context::getContext();
        static::$crmCartDateFormat = 'Y-m-d H:i:sP';
    }

    /**
     * run carts upload
     */
    public static function run()
    {
        if (null === static::$now || null === static::$api) {
            return;
        }

        static::backupCurrentCart();
        static::backupCurrentEmployee();
        static::setAnyEmployeeToContext();
        static::loadAbandonedCartsIds();

        foreach (static::$cartsIds as $cartId) {
            $cart = new Cart($cartId['id_cart']);
            $cartExternalId = RetailcrmTools::getCartOrderExternalId($cart);
            $cartLastUpdateDate = null;

            if (static::isGuestCart($cart)) {
                continue;
            }

            if (!empty($cart->date_upd)) {
                $cartLastUpdateDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cart->date_upd);
            }

            if (!static::isAbandonedCartShouldBeUpdated(
                static::getAbandonedCartLastSync($cart->id),
                $cartLastUpdateDate
            )) {
                continue;
            }

            static::populateContextWithCart($cart);

            $response = static::$api->cartGet($cart->id_customer, static::$site);

            if ($response instanceof RetailcrmApiResponse) {
                $isExistExternalId = !empty($response['cart']['externalId']);

                if (static::isCartEmpty($cart)) {
                    if ($isExistExternalId) {
                        static::$api->cartClear(
                            [
                                'clearedAt' => date(static::$crmCartDateFormat),
                                'customer' => ['externalId' => $cart->id_customer],
                            ],
                            static::$site
                        );
                    }

                    continue;
                }

                $crmCart = static::buildCrmCart($cart, $cartExternalId, $isExistExternalId);

                if (empty($crmCart)) {
                    continue;
                }

                $response = static::$api->cartSet($crmCart, static::$site);

                if ($response instanceof RetailcrmApiResponse && $response->isSuccessful()) {
                    if ($isExistExternalId) {
                        static::registerAbandonedCartSync($cart->id);
                    } else {
                        $cart->date_upd = date('Y-m-d H:i:s');
                        $cart->save();
                    }
                }
            }
        }

        static::restoreCurrentCart();
        static::restoreCurrentEmployee();
    }

    /**
     * Returns true if cart belongs to guest.
     *
     * @param $cart
     *
     * @return bool
     */
    private static function isGuestCart($cart)
    {
        /** @var Customer|\CustomerCore $guestCustomer */
        $guestCustomer = new Customer($cart->id_guest);

        if (!empty($cart->id_guest) && $cart->id_customer == $cart->id_guest && $guestCustomer->is_guest) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if cart is empty or if cart emptiness cannot be checked because something gone wrong.
     * Errors with checking cart emptiness will be correctly written to log.
     *
     * @param Cart|CartCore $cart
     *
     * @return bool
     */
    private static function isCartEmpty($cart)
    {
        $shouldBeUploaded = true;

        $messageTotal = sprintf('Failure while trying to get cart total (cart id=%d)', $cart->id);
        $messageProducts = sprintf('Failure while trying to get cart products (cart id=%d)', $cart->id);

        try {
            $currentCartTotal = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);

            if (0 == $currentCartTotal) {
                $shouldBeUploaded = false;
            }
        } catch (Exception $exception) {
            return RetailcrmLogger::writeException(__METHOD__, $exception, $messageTotal, true);
        } catch (Error $exception) {
            return RetailcrmLogger::writeException(__METHOD__, $exception, $messageTotal, true);
        }

        try {
            // Don't upload empty cartsIds.
            if (0 == count($cart->getProducts(true)) || !$shouldBeUploaded) {
                return true;
            }
        } catch (Exception $exception) {
            return RetailcrmLogger::writeException(__METHOD__, $exception, $messageProducts, true);
        } catch (Error $exception) {
            return RetailcrmLogger::writeException(__METHOD__, $exception, $messageProducts, true);
        }

        return false;
    }

    /**
     * Build order for abandoned cart
     *
     * @param Cart|CartCore $cart
     * @param string $cartExternalId
     * @param bool $isExistExternalId
     *
     * @return array
     */
    private static function buildCrmCart($cart, string $cartExternalId, bool $isExistExternalId)
    {
        try {
            $crmCart = [
                'customer' => ['externalId' => $cart->id_customer],
                'clearAt' => null,
                'createdAt' => DateTime::createFromFormat('Y-m-d H:i:s', $cart->date_add)->format(static::$crmCartDateFormat),
            ];

            if (!$isExistExternalId) {
                $crmCart['externalId'] = $cartExternalId . uniqid('_', true);
            }

            if (!empty($cart->date_upd)) {
                $crmCart['updatedAt'] = DateTime::createFromFormat('Y-m-d H:i:s', $cart->date_upd)->format(static::$crmCartDateFormat);
            }

            $products = $cart->getProducts();

            foreach ($products as $product) {
                // Check variable products
                $offers = Product::getProductAttributesIds($product['id_product']);

                $crmCart['items'][] = [
                    'offer' => [
                        'externalId' => !empty($offers)
                            ? $product['id_product'] . '#' . $product['id_product_attribute']
                            : $product['id_product'],
                    ],
                    'quantity' => $product['cart_quantity'],
                    'createdAt' => DateTime::createFromFormat('Y-m-d H:i:s', $product['date_add'])->format(static::$crmCartDateFormat),
                    'price' => !empty($product['rate'])
                        ? round($product['price'], 2) + (round($product['price'], 2) * $product['rate'] / 100)
                        : round($product['price'], 2),
                ];
            }
        } catch (Exception $exception) {
            RetailcrmLogger::writeException(__METHOD__, $exception, null, true);
        } catch (Error $exception) {
            RetailcrmLogger::writeException(__METHOD__, $exception, null, true);
        }

        return $crmCart;
    }

    /**
     * Register abandoned cart sync event
     *
     * @param int $cartId
     *
     * @return bool
     */
    private static function registerAbandonedCartSync($cartId)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'retailcrm_abandonedcarts` (`id_cart`, `last_uploaded`)
                VALUES (\'' . pSQL($cartId) . '\', \'' . pSQL(date('Y-m-d H:i:s')) . '\')
                ON DUPLICATE KEY UPDATE `last_uploaded` = \'' . pSQL(date('Y-m-d H:i:s')) . '\';';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Get abandoned cart last sync time
     *
     * @param int $cartId
     *
     * @return DateTimeImmutable|false|null
     */
    private static function getAbandonedCartLastSync($cartId)
    {
        $sql = 'SELECT `last_uploaded` FROM `' . _DB_PREFIX_ . 'retailcrm_abandonedcarts`
                WHERE `id_cart` = \'' . pSQL((int) $cartId) . '\'';
        $when = Db::getInstance()->getValue($sql);

        if (empty($when)) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $when);
    }

    /**
     * Loads abandoned carts ID's from DB.
     */
    private static function loadAbandonedCartsIds()
    {
        $sql = 'SELECT c.id_cart, c.date_upd
                FROM ' . _DB_PREFIX_ . 'cart AS c
                LEFT JOIN ' . _DB_PREFIX_ . 'customer cus
                  ON
                    c.id_customer = cus.id_customer
                WHERE c.id_customer != 0
                  AND cus.is_guest = 0
                ' . Shop::addSqlRestriction(false, 'c') . '
                  AND TIME_TO_SEC(TIMEDIFF(\'' . pSQL(static::$now->format('Y-m-d H:i:s'))
            . '\', c.date_upd)) >= ' . pSQL(static::$syncDelay) . '
                  AND TIME_TO_SEC(TIMEDIFF(\'' . pSQL(static::$now->format('Y-m-d H:i:s'))
            . '\', c.date_upd)) <= ' . pSQL(static::$allowedUpdateInterval) . '
                  AND c.id_cart NOT IN(SELECT id_cart from ' . _DB_PREFIX_ . 'orders);';
        static::$cartsIds = Db::getInstance()->executeS($sql);
    }

    /**
     * Returns true if abandoned cart should be uploaded
     *
     * @param DateTimeImmutable|null $lastUploadDate
     * @param DateTimeImmutable|null $lastUpdatedDate
     *
     * @return bool
     */
    private static function isAbandonedCartShouldBeUpdated($lastUploadDate, $lastUpdatedDate)
    {
        // Workaround for PHP bug: https://bugs.php.net/bug.php?id=49914
        ob_start();
        var_dump($lastUploadDate);
        var_dump($lastUpdatedDate);
        ob_clean();
        ob_end_flush();

        if (null === $lastUploadDate || null === $lastUpdatedDate) {
            return true;
        }

        return $lastUploadDate < $lastUpdatedDate;
    }

    /**
     * Backups current cart in context
     */
    private static function backupCurrentCart()
    {
        self::$origCart = self::$context->cart;
    }

    /**
     * Restores current cart in context
     */
    private static function restoreCurrentCart()
    {
        self::populateContextWithCart(self::$origCart);
    }

    /**
     * Populates current context with provided cart.
     *
     * @param Cart|\CartCore $cart
     */
    private static function populateContextWithCart($cart)
    {
        self::$context->cart = $cart;
    }

    /**
     * Backups current employee in context
     */
    private static function backupCurrentEmployee()
    {
        self::$origEmployee = self::$context->employee;
    }

    /**
     * Restores current employee in context
     */
    private static function restoreCurrentEmployee()
    {
        self::populateContextWithEmployee(self::$origEmployee);
    }

    /**
     * Sets any employee to context
     */
    private static function setAnyEmployeeToContext()
    {
        $employees = EmployeeCore::getEmployees();
        $employee = reset($employees);

        if (isset($employee['id_employee'])) {
            self::$context->employee = new Employee($employee['id_employee']);
        }
    }

    /**
     * Populates current context with provided cart.
     *
     * @param \Employee|\EmployeeCore $employee
     */
    private static function populateContextWithEmployee($employee)
    {
        self::$context->employee = $employee;
    }
}
