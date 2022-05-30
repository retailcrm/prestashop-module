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

class RetailcrmOrderBuilderTest extends RetailcrmTestCase
{
    /**
     * @var RetailcrmProxy
     */
    private $apiMock;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getApiMock(
            [
                'method',
                'credentials',
                'customersCorporateAddresses',
                'customersCorporateAddressesEdit',
                'customersCorporateAddressesCreate',
            ]
        );
    }

    public function testInitialPriceZero()
    {
        $item = $this->getDataItemInitialPriceZero();
        $resultItem = RetailcrmTools::clearArray($item);

        $this->assertTrue(isset($resultItem['initialPrice']));
        $this->assertEquals(0, $resultItem['initialPrice']);
    }

    /**
     * @dataProvider buildCrmCustomerAddresses
     */
    public function testBuildCrmCustomer($address, $isCorp)
    {
        $customer = new Customer(1);
        $crmCustomer = RetailcrmOrderBuilder::buildCrmCustomer($customer, $address);

        $this->assertEquals($isCorp, $crmCustomer['isContact']);
        $this->assertNotEmpty($crmCustomer['email']);
    }

    /**
     * @dataProvider appendAdditionalAddressToCorporateAddresses
     */
    public function testAppendAdditionalAddressToCorporate($address, $method)
    {
        $this->apiClientMock->expects($this->any())
            ->method('credentials')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'success' => true,
                        ]
                    )
                )
            )
        ;
        $this->apiClientMock->expects($this->once())
            ->method('customersCorporateAddresses')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'pagination' => [
                                'totalPageCount' => 1,
                                'currentPage' => 1,
                            ],
                            'addresses' => $this->getCorpAddresses(),
                        ]
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->once())
            ->method($method)
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'pagination' => [
                                'totalPageCount' => 1,
                                'currentPage' => 1,
                            ],
                            'addresses' => $this->getCorpAddresses(),
                        ]
                    )
                )
            )
        ;

        $orderBuilder = new RetailcrmOrderBuilder();
        $orderBuilder->setApi($this->apiMock);

        $class = new ReflectionClass($orderBuilder);

        $property = $class->getProperty('invoiceAddress');
        $property->setAccessible(true);
        $property->setValue($orderBuilder, $this->convertAddress($address));

        $method = $class->getMethod('appendAdditionalAddressToCorporate');
        $method->setAccessible(true);
        $method->invoke($orderBuilder, 0);
    }

    public function testBuildCrmOrder()
    {
        $order = new Order(1);
        $order->reference = 'test_n';
        $order->current_state = 0;
        Configuration::updateValue(RetailCRM::DELIVERY, '{"1":"test_delivery"}');
        Configuration::updateValue(RetailCRM::STATUS, '{"1":"test_status"}');
        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_SENDING, false);
        $crmOrder = RetailcrmOrderBuilder::buildCrmOrder($order);

        $this->assertArrayNotHasKey('number', $crmOrder);

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_SENDING, true);
        $crmOrder = RetailcrmOrderBuilder::buildCrmOrder($order);

        $this->assertEquals($order->reference, $crmOrder['number']);
    }

    private function convertAddress($crmAddress)
    {
        $address = new Address();
        $address->address1 = $crmAddress['text'];
        $address->alias = $crmAddress['name'];
        $address->city = $crmAddress['city'];
        $address->id_country = Country::getByIso($crmAddress['countryIso']);
        $address->country = Country::getNameById((int) Configuration::get('PS_LANG_DEFAULT'), $address->id_country);
        $address->postcode = $crmAddress['index'];
        $address->company = $crmAddress['name'];
        $address->id = isset($crmAddress['externalId']) ? $crmAddress['externalId'] : null;

        return $address;
    }

    public function appendAdditionalAddressToCorporateAddresses()
    {
        return [
            [
                $this->getCorpAddresses()[0],
                'customersCorporateAddressesEdit',
            ],
            [
                $this->getCorpAddresses()[1],
                'customersCorporateAddressesEdit',
            ],
            [
                [
                    'id' => '3',
                    'externalId' => '30',
                    'index' => '423120',
                    'city' => 'Kazan',
                    'countryIso' => 'RU',
                    'text' => 'ul. Fuchika, d. 129',
                    'notes' => 'Building under the big tree',
                    'region' => 'Republic of Tatarstan',
                    'company' => 'MyCompany',
                    'name' => 'Home',
                ],
                'customersCorporateAddressesCreate',
            ],
        ];
    }

    /**
     * @return array
     */
    private function getDataItemInitialPriceZero()
    {
        return [
            'id' => 160,
            'initialPrice' => 0,
            'createdAt' => '2018-01-01 00:00:00',
            'quantity' => 1,
            'status' => 'new',
            'offer' => [
                'id' => 1,
                'externalId' => 1,
                'xmlId' => '1',
                'name' => 'Test name',
                'vatRate' => 'none',
            ],
            'properties' => [],
            'purchasePrice' => 50,
        ];
    }

    public function buildCrmCustomerAddresses()
    {
        return [
            [
                'Address' => [
                    'company' => 'RetailCRM',
                ],
                'Is corporate address?' => true,
            ],
            [
                'Address' => [],
                'Is corporate address?' => false,
            ],
        ];
    }

    /**
     * @return array
     */
    private function getDataOrder()
    {
        $order = [
            'slug' => 1,
            'id' => 1,
            'number' => '1C',
            'orderType' => 'eshop-individual',
            'orderMethod' => 'phone',
            'countryIso' => 'RU',
            'createdAt' => '2018-01-01 00:00:00',
            'statusUpdatedAt' => '2018-01-01 00:00:00',
            'summ' => 100,
            'totalSumm' => 100,
            'prepaySum' => 0,
            'purchaseSumm' => 50,
            'markDatetime' => '2018-01-01 00:00:00',
            'firstName' => 'Test',
            'lastName' => 'Test',
            'phone' => '80000000000',
            'call' => false,
            'expired' => false,
            'customer' => [
                'segments' => [],
                'id' => 1,
                'externalId' => '777',
                'type' => 'customer',
                'firstName' => 'Test',
                'lastName' => 'Test',
                'email' => 'email@test.ru',
                'phones' => [
                    [
                        'number' => '111111111111111',
                    ],
                    [
                        'number' => '+7111111111',
                    ],
                ],
                'address' => [
                    'id_customer' => 2222,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Test region',
                    'city' => 'Test',
                    'text' => 'Test text address',
                ],
                'createdAt' => '2018-01-01 00:00:00',
                'managerId' => 1,
                'vip' => false,
                'bad' => false,
                'site' => 'test-com',
                'contragent' => [
                    'contragentType' => 'individual',
                ],
                'personalDiscount' => 0,
                'cumulativeDiscount' => 0,
                'marginSumm' => 58654,
                'totalSumm' => 61549,
                'averageSumm' => 15387.25,
                'ordersCount' => 4,
                'costSumm' => 101,
                'customFields' => [
                    'custom' => 'test',
                ],
            ],
            'contragent' => [],
            'delivery' => [
                'code' => 'delivery',
                'cost' => 100,
                'netCost' => 0,
                'address' => [
                    'id_customer' => 2222,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Test region',
                    'city' => 'Test',
                    'text' => 'Test text address',
                ],
            ],
            'site' => 'test-com',
            'status' => 'new',
            'items' => [
                [
                    'id' => 160,
                    'initialPrice' => 100,
                    'createdAt' => '2018-01-01 00:00:00',
                    'quantity' => 1,
                    'status' => 'new',
                    'offer' => [
                        'id' => 1,
                        'externalId' => 1,
                        'xmlId' => '1',
                        'name' => 'Test name',
                        'vatRate' => 'none',
                    ],
                    'properties' => [],
                    'purchasePrice' => 50,
                ],
            ],
            'fromApi' => false,
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'shipmentStore' => 'main',
            'shipped' => false,
            'customFields' => [],
            'uploadedToExternalStoreSystem' => false,
        ];

        $order['payments'][] = [
            'id' => 97,
            'type' => 'cheque',
            'amount' => 210,
        ];

        return $order;
    }

    private function getCorpAddresses()
    {
        return [
            [
                'id' => '1',
                'externalId' => '10',
                'index' => '452320',
                'city' => 'Dyurtyuli',
                'countryIso' => 'RU',
                'text' => 'ul. Matrosova, d. 8',
                'notes' => 'from 12:00 to 15:00',
                'region' => 'Republic of Bashkortostan',
                'company' => 'MyCompany',
                'name' => 'Office',
            ],
            [
                'id' => '2',
                'externalId' => '90',
                'index' => '760021',
                'city' => 'Cali',
                'countryIso' => 'CO',
                'text' => 'Av 6 A NORTE No. 28 N-10, C.P 76001',
                'notes' => 'Red door next to the road',
                'region' => 'Cali',
                'company' => 'MyCompany',
                'name' => 'Warehouse',
            ],
        ];
    }
}
