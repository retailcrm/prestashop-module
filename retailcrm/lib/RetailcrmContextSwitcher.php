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
class RetailcrmContextSwitcher
{
    /**
     * @var int
     */
    private static $contextShopType;
    /**
     * @var Shop
     */
    private static $contextShop;

    /**
     * Runs given callback function in all context shops
     *
     * @param callable $callback
     * @param array $arguments Arguments that will be passed to callback function
     *
     * @return array
     */
    public static function runInContext($callback, $arguments = [])
    {
        $result = [];
        self::storeContext();

        foreach (self::getShops() as $shop) {
            self::setShopContext(intval($shop['id_shop']));
            $result[intval($shop['id_shop'])] = call_user_func_array($callback, $arguments);
        }

        self::restoreContext();

        return $result;
    }

    private static function storeContext()
    {
        static::$contextShopType = Shop::getContext();
        static::$contextShop = Context::getContext()->shop;
    }

    private static function restoreContext()
    {
        switch (static::$contextShopType) {
            case Shop::CONTEXT_GROUP:
                $contextShopId = static::$contextShop->id_shop_group;
                break;
            case Shop::CONTEXT_SHOP:
                $contextShopId = static::$contextShop->id;
                break;
            default:
                $contextShopId = null;
                break;
        }

        try {
            Shop::setContext(static::$contextShopType, $contextShopId);
        } catch (PrestaShopException $e) {
            RetailcrmLogger::writeCaller(__METHOD__, 'Unable to set shop context');
        }
        Context::getContext()->shop = static::$contextShop;
    }

    /**
     * Change shop in the context
     *
     * @param $id_shop
     */
    public static function setShopContext($id_shop)
    {
        try {
            Shop::setContext(Shop::CONTEXT_SHOP, $id_shop);
        } catch (PrestaShopException $e) {
            RetailcrmLogger::writeCaller(__METHOD__, 'Unable to set shop context');
        }
        Context::getContext()->shop = new Shop($id_shop);
    }

    /**
     * @return array
     */
    public static function getShops()
    {
        $idShop = Shop::getContextShopID();

        if (Shop::isFeatureActive() && null === $idShop) {
            return Shop::getShops(true, Shop::getContextShopGroupID(true));
        } else {
            return [Shop::getShop($idShop)];
        }
    }
}
