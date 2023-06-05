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

class DataRetailcrmReferences
{
    public static function getCredentials()
    {
        return [
            'siteAccess' => 'access_selective',
            'sitesAvailable' => ['prestashop'],
            'credentials' => ['/api/reference/sites', '/api/reference/sites/{code}/edit'],
        ];
    }

    public static function getApiPaymentTypes()
    {
        return [
            'paymentTypes' => [
                [
                    'name' => 'payment1',
                    'code' => 'payment1',
                    'active' => true,
                    'sites' => ['prestashop'],
                ],
                [
                    'name' => 'payment2',
                    'code' => 'payment2',
                    'active' => true,
                    'sites' => ['woocommerce'],
                ],
                [
                    'name' => 'payment3',
                    'code' => 'payment3',
                    'active' => true,
                    'sites' => [],
                ],
            ],
        ];
    }

    public static function getApiDeliveryTypes()
    {
        return [
            'deliveryTypes' => [
                [
                    'name' => 'delivery1',
                    'code' => 'delivery1',
                    'active' => true,
                    'sites' => ['prestashop'],
                ],
                [
                    'name' => 'delivery2',
                    'code' => 'delivery2',
                    'active' => true,
                    'sites' => ['woocommerce'],
                ],
                [
                    'name' => 'delivery3',
                    'code' => 'delivery3',
                    'active' => true,
                    'sites' => [],
                ],
            ],
        ];
    }
}
