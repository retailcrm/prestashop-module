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

class RetailcrmToolsTest extends RetailcrmTestCase
{
    /**
     * @dataProvider clearAddresses
     */
    public function testClearAddress($address, $result)
    {
        $this->assertEquals($result, RetailcrmTools::clearAddress($address));
    }

    public function clearAddresses()
    {
        return [
            [
                'Calle 66 # 11 -27, Casa || Casa',
                'calle661127casacasa',
            ],
            [
                'Calle 111 # 7c 10 || 202 torre 6',
                'calle1117c10202torre6',
            ],
            [
                'Bogotá, D.C. Calle 4B 20-85 || 312',
                'bogotadccalle4b2085312',
            ],
        ];
    }

    /**
     * @dataProvider equalCustomerAddresses
     */
    public function testIsEqualCustomerAddress($address1, $address2, $result)
    {
        $this->assertEquals($result, RetailcrmTools::isEqualCustomerAddress($address1, $address2));
    }

    public function equalCustomerAddresses()
    {
        return [
            'equal_addresses' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                true,
            ],
            'changed_phone' => [
                [
                    'phones' => [
                        ['number' => '222'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'changed_index' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '222',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'reduced_address' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                    ],
                ],
                false,
            ],
            'expanded_address' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'reduced_phone' => [
                [
                    'phones' => [
                        ['number' => '111'],
                        ['number' => '222'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'expanded_phone' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '222'],
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                false,
            ],
            'replaced_field' => [
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'text' => 'Address line 1 (client Address 2)',
                    ],
                ],
                [
                    'phones' => [
                        ['number' => '111'],
                    ],
                    'address' => [
                        'index' => '398055',
                        'city' => 'Order City here',
                        'region' => 'Region',
                    ],
                ],
                false,
            ],
        ];
    }
}
