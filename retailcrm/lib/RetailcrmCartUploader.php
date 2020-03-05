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
class RetailcrmCartUploader
{
    /**
     * @var \RetailcrmProxy|\RetailcrmApiClientV5
     */
    static $api;

    /**
     * @var array
     */
    static $cartsIds;

    /**
     * @var array
     */
    static $paymentTypes;

    /**
     * @var int
     */
    static $syncDelay;

    /**
     * @var string
     */
    static $syncStatus;

    /**
     * @var \DateTime
     */
    static $now;

    /**
     * Cast provided sync delay to integer
     *
     * @param $time
     */
    public static function setSyncDelay($time)
    {
        if (is_numeric($time) && $time > 0) {
            static::$syncDelay = (int)$time;
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
        static::$cartsIds = array();
        static::$paymentTypes = array();
        static::$syncDelay = 0;
        static::$syncStatus = '';
        static::$now = new \DateTime();
    }

    /**
     * run carts upload
     */
    public static function run()
    {
        if (!static::validateState()) {
            return;
        }

        static::loadAbandonedCartsIds();

        foreach (static::$cartsIds as $cartId) {
            $cart = new Cart($cartId['id_cart']);
            $cartExternalId = RetailcrmTools::getCartOrderExternalId($cart);
            $cartLastUpdateDate = null;

            if (static::isGuestCart($cart) || static::isCartTooOld($cart->date_upd) || static::isCartEmpty($cart)) {
                continue;
            }

            if (!empty($cart->date_upd)) {
                $cartLastUpdateDate = \DateTime::createFromFormat('Y-m-d H:i:s', $cart->date_upd);
            }

            if (!static::isAbandonedCartShouldBeUpdated(
                static::getAbandonedCartLastSync($cart->id),
                $cartLastUpdateDate
            )) {
                continue;
            }

            $response = static::$api->ordersGet($cartExternalId);

            if (!($response instanceof RetailcrmApiResponse)) {
                //TODO
                // Extract address from cart (if exists) and append to customer?
                // Or maybe this customer will not order anything, so we don't need it's address...
                static::$api->customersCreate(RetailcrmOrderBuilder::buildCrmCustomer(new Customer($cart->id_customer)));

                $order = static::buildCartOrder($cart, $cartExternalId);

                if (empty($order)) {
                    continue;
                }

                if (static::$api->ordersCreate($order) !== false) {
                    $cart->date_upd = date('Y-m-d H:i:s');
                    $cart->save();
                }

                continue;
            }

            if (isset($response['order']) && !empty($response['order'])) {
                $order = static::buildCartOrder($cart, $response['order']['externalId']);

                if (empty($order)) {
                    continue;
                }

                if (static::$api->ordersEdit($order) !== false) {
                    static::registerAbandonedCartSync($cart->id);
                }
            }
        }
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
     * Returns true if cart is too old to update.
     *
     * @param string $cartDateUpd It's $cart->date_upd
     *
     * @return bool
     */
    private static function isCartTooOld($cartDateUpd)
    {
        try {
            $allowedUpdateInterval = new \DateInterval('P1D');
            $cartLastUpdate = \DateTime::createFromFormat('Y-m-d H:i:s', $cartDateUpd);

            if ($cartLastUpdate instanceof \DateTime) {
                $cartLastUpdateDiff = $cartLastUpdate->diff(new \DateTime('now'));

                // Workaround for PHP bug: https://bugs.php.net/bug.php?id=49914
                ob_start();
                var_dump($allowedUpdateInterval);
                var_dump($cartLastUpdateDiff);
                ob_clean();
                ob_end_flush();

                if ($cartLastUpdateDiff > $allowedUpdateInterval) {
                    return true;
                }
            }
        } catch (\Exception $exception) {
            RetailcrmLogger::writeCaller(__CLASS__.'::'.__METHOD__, $exception->getMessage());
        }

        return false;
    }

    /**
     * Returns true if cart is empty
     *
     * @param Cart|CartCore $cart
     *
     * @return bool
     */
    private static function isCartEmpty($cart)
    {
        $shouldBeUploaded = true;

        try {
            $currentCartTotal = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);

            if ($currentCartTotal == 0) {
                $shouldBeUploaded = false;
            }
        } catch (\Exception $exception) {
            RetailcrmLogger::writeCaller(__CLASS__.'::'.__METHOD__, $exception->getMessage());
        }

        // Don't upload empty cartsIds.
        if (count($cart->getProducts()) == 0 || !$shouldBeUploaded) {
            return true;
        }

        return false;
    }

    /**
     * Build order for abandoned cart
     *
     * @param Cart|\CartCore $cart
     * @param string $cartExternalId
     *
     * @return array
     */
    private static function buildCartOrder($cart, $cartExternalId)
    {
        $order = array();

        try {
            $order = RetailcrmOrderBuilder::buildCrmOrderFromCart(
                static::$api,
                $cart,
                $cartExternalId,
                static::$paymentTypes[0],
                static::$syncStatus
            );
        } catch (\Exception $exception) {
            RetailcrmLogger::writeCaller(
                'abandonedCarts',
                $exception->getMessage() . PHP_EOL . $exception->getTraceAsString()
            );
        }

        return $order;
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
     * @return \DateTime|false
     */
    private static function getAbandonedCartLastSync($cartId)
    {
        $sql = 'SELECT `last_uploaded` FROM `' . _DB_PREFIX_ . 'retailcrm_abandonedcarts`
                WHERE `id_cart` = \'' . pSQL((int) $cartId) . '\'';
        $when = Db::getInstance()->getValue($sql);

        if (empty($when)) {
            return null;
        }

        return \DateTime::createFromFormat('Y-m-d H:i:s', $when);
    }

    /**
     * Loads abandoned carts ID's from DB.
     */
    private static function loadAbandonedCartsIds()
    {
        $sql = 'SELECT c.id_cart, c.date_upd 
                FROM ' . _DB_PREFIX_ . 'cart AS c
                WHERE id_customer != 0 
                  AND TIME_TO_SEC(TIMEDIFF(\'' . pSQL(static::$now->format('Y-m-d H:i:s'))
            . '\', date_upd)) >= ' . pSQL(static::$syncDelay) . '
                  AND c.id_cart NOT IN(SELECT id_cart from ' . _DB_PREFIX_ . 'orders);';
        static::$cartsIds = Db::getInstance()->executeS($sql);
    }

    /**
     * Returns true if abandoned cart should be uploaded
     *
     * @param \DateTime|null $lastUploadDate
     * @param \DateTime|null $lastUpdatedDate
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

        if (is_null($lastUploadDate) || is_null($lastUpdatedDate)) {
            return true;
        }

        return $lastUploadDate < $lastUpdatedDate;
    }

    /**
     * Returns false if inner state is not correct
     *
     * @return bool
     */
    private static function validateState()
    {
        if (empty(static::$syncStatus)
            || (count(static::$paymentTypes) < 1)
            || is_null(static::$now)
            || !static::$api
        ) {
            return false;
        }

        return true;
    }
}
