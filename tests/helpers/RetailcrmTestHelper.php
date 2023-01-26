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

class RetailcrmTestHelper
{
    public static function createOrderPayment($order_reference)
    {
        $orderPayment = new OrderPayment();
        $orderPayment->order_reference = $order_reference;
        $orderPayment->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $orderPayment->conversion_rate = 1.000000;
        $orderPayment->amount = 100;
        $orderPayment->payment_method = 'Bank wire';
        $orderPayment->date_add = date('Y-m-d H:i:s');

        $orderPayment->save();

        return $orderPayment;
    }

    public static function deleteOrderPayment($id)
    {
        $orderPayment = new OrderPayment($id);

        return $orderPayment->delete();
    }

    public static function getMaxOrderId()
    {
        return Db::getInstance()->getValue(
            'SELECT MAX(id_order) FROM `' . _DB_PREFIX_ . 'orders`'
        );
    }

    public static function getMaxCustomerId()
    {
        return Db::getInstance()->getValue(
            'SELECT MAX(id_customer) FROM `' . _DB_PREFIX_ . 'customer`'
        );
    }
}
