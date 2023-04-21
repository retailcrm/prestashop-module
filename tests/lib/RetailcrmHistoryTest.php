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

class RetailcrmHistoryTest extends RetailcrmTestCase
{
    private $apiMock;
    private $product;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getApiMock(
            [
                'customersHistory',
                'ordersHistory',
                'ordersGet',
                'ordersEdit',
                'customersGet',
                'customersFixExternalIds',
                'ordersFixExternalIds',
                'customersCorporateAddressesEdit',
            ]
        );

        $catalog = new RetailcrmCatalog();
        $data = $catalog->getData();
        $this->product = $data[1]->current();

        Configuration::updateValue(RetailCRM::DELIVERY_DEFAULT, 2);
        Configuration::updateValue(RetailCRM::PAYMENT_DEFAULT, 'bankwire');

        RetailcrmExportOrdersHelper::removeOrders();

        $this->setConfig();
    }

    public function testCustomersHistory()
    {
        $this->apiClientMock->expects($this->any())
            ->method('customersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryDataNewCustomer()
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('customersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'customer' => $this->getApiCustomer(),
                        ]
                    )
                )
            )
        ;

        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $externalId = isset($this->getApiCustomer()['externalId']) ? $this->getApiCustomer()['externalId'] : null;

        if (!empty($externalId)) {
            $oldCustomer = new Customer($externalId);
            RetailcrmHistory::customersHistory();
            $newCustomer = new Customer($externalId);

            $this->assertNotEquals($oldCustomer, $newCustomer);
        } else {
            $oldLastId = RetailcrmTestHelper::getMaxCustomerId();
            RetailcrmHistory::customersHistory();
            $newLastId = RetailcrmTestHelper::getMaxCustomerId();

            $this->assertTrue($newLastId > $oldLastId);
        }

        $this->assertTrue(RetailcrmHistory::customersHistory());
    }

    public function testOrdersHistory()
    {
        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $order = new Order(1);
        $reference = $order->reference;
        $updReference = 'test';
        $crmOrder = $this->getApiOrder();
        $crmOrder['number'] = $updReference;
        $checkArgs = [
            'externalId' => 1,
            'number' => $reference,
        ];

        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryExistOrder($crmOrder)
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'order' => $crmOrder,
                        ]
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->once())
            ->method('ordersEdit')
            ->with($checkArgs)
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'success' => true,
                            'id' => $crmOrder['id'],
                            'order' => [
                                'externalId' => $order->id,
                                'number' => $updReference,
                            ],
                        ]
                    )
                )
            )
        ;

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, false);
        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_SENDING, false);
        RetailcrmHistory::ordersHistory();
        $firstUpdOrder = new Order(1);

        $this->assertEquals($reference, $firstUpdOrder->reference);

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, true);
        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_SENDING, true);
        RetailcrmHistory::ordersHistory();
        $secondUpdOrder = new Order(1);

        $this->assertEquals($updReference, $secondUpdOrder->reference);
    }

    public function tetsLastSinceId()
    {
        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(new RetailcrmApiResponse('200', json_encode($this->getLastSinceId())))
        ;

        $lastSinceId = 0;
        Configuration::updateValue('RETAILCRM_LAST_ORDERS_SYNC', $lastSinceId);

        $isUpdate = RetailcrmHistory::updateSinceId('orders');

        $this->assertTrue($isUpdate);
        $this->assertNotEquals($lastSinceId, Configuration::get('RETAILCRM_LAST_ORDERS_SYNC'));
    }

    public function orderCreateDataProvider()
    {
        return [
            [
                'orderData' => $this->getApiOrder(11), ],
            [
                'orderData' => $this->getApiOrderWitchCorporateCustomer(12),
            ],
        ];
    }

    /**
     * @dataProvider orderCreateDataProvider
     */
    public function testOrderCreate($orderData)
    {
        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryDataNewOrder($orderData)
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'order' => $orderData,
                        ]
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersEdit')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getEditedOrder($orderData)
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersFixExternalIds')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode([
                        'success' => true,
                    ]
                    )
                )
            )
        ;

        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $oldLastId = RetailcrmTestHelper::getMaxOrderId();
        RetailcrmHistory::ordersHistory();
        $newLastId = RetailcrmTestHelper::getMaxOrderId();

        $this->assertTrue($newLastId > $oldLastId);

        $order = new Order($newLastId);

        $this->assertInstanceOf('Order', $order);

        // delivery address
        $address = new Address($order->id_address_delivery);
        $this->assertEquals($orderData['firstName'], $address->firstname);
        $this->assertEquals($orderData['lastName'], $address->lastname);

        $builder = new RetailcrmAddressBuilder();
        $addressDelivery = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_ORDER_DELIVERY)
            ->setAddress($address)
            ->build()
            ->getDataArray()
        ;

        $this->assertEquals($orderData['delivery']['address']['countryIso'], $addressDelivery['countryIso']);
        unset($orderData['delivery']['address']['countryIso']);

        $this->assertEquals($orderData['delivery']['address'], $addressDelivery['delivery']['address']);
        $this->assertEquals($orderData['phone'], $addressDelivery['phone']);

        // customer address
        $address = new Address($order->id_address_invoice);
        $this->assertEquals($orderData['customer']['firstName'], $address->firstname);
        $this->assertEquals($orderData['customer']['lastName'], $address->lastname);

        $addressInvoice = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_CUSTOMER)
            ->setAddress($address)
            ->build()
            ->getDataArray()
        ;

        if (isset($orderData['customer']['address']['id'])) {
            unset($orderData['customer']['address']['id']);
        }
        $this->assertEquals($orderData['customer']['address'], $addressInvoice['address']);
        $this->assertEquals($orderData['customer']['phones'][0]['number'], $addressInvoice['phones'][0]['number']);

        // types and totals
        $this->assertEquals($orderData['totalSumm'], $order->total_paid);
        $this->assertEquals(10, $order->current_state);
        $this->assertEquals(1, $order->id_carrier);
        $this->assertEquals($orderData['payments'][0]['type'], $order->module);

        // orders table
        $orders = RetailcrmExportOrdersHelper::getOrders([$orderData['id']]);
        $this->assertArrayHasKey('orders', $orders);
        $this->assertArrayHasKey('pagination', $orders);
        $this->assertCount(1, $orders['orders']);

        $exportResult = $orders['orders'][0];

        if (version_compare(_PS_VERSION_, '1.7.4.0', '<')) { // workaround – on 1.7.4.0 id_order always 1
            $this->assertEquals($exportResult['id_order'], $newLastId);
        }

        $this->assertEquals($exportResult['id_order_crm'], $orderData['id']);
        $this->assertNull($exportResult['errors']);
    }

    private function switchCustomer()
    {
        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $history = $this->getHistoryExistOrder();

        $newId = $history['history'][0]['newValue']['externalId'];

        RetailcrmHistory::ordersHistory();

        $order = new Order((int) $history['history'][0]['order']['externalId']);

        $this->assertTrue($newId == $order->id_customer);
    }

    public function testOrderSwitchCustomer()
    {
        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryExistOrder()
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'order' => $this->getApiOrder(),
                        ]
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersEdit')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getEditedOrder($this->getApiOrder())
                    )
                )
            )
        ;

        $this->switchCustomer();
    }

    public function testOrderSwitchCorporateCustomer()
    {
        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryExistOrder()
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'order' => $this->getApiOrderWitchCorporateCustomer(),
                        ]
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersEdit')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getEditedOrder($this->getApiOrderWitchCorporateCustomer())
                    )
                )
            )
        ;

        $this->switchCustomer();
    }

    public function testPaymentStatusUpdate()
    {
        $lastId = RetailcrmTestHelper::getMaxOrderId();

        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getUpdatePaymentStatus($lastId)
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersEdit')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getEditedOrder($this->getApiOrder())
                    )
                )
            )
        ;

        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        RetailcrmHistory::ordersHistory();
    }

    public function testOrderAddressUpdate()
    {
        $orderId = RetailcrmTestHelper::getMaxOrderId();
        $crmOrder = $this->getApiOrderAddressUpdate($orderId);

        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryAddressUpdated($orderId)
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'order' => $crmOrder,
                        ]
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersEdit')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getEditedOrder($this->getApiOrder())
                    )
                )
            )
        ;

        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $order = new Order($orderId);
        $idAddress = $order->id_address_delivery;

        RetailcrmHistory::ordersHistory();

        $orderAfter = new Order($orderId);
        $idAddressAfter = $orderAfter->id_address_delivery;

        if (version_compare(_PS_VERSION_, '1.7.7', '<')) {
            $this->assertNotEquals($idAddress, $idAddressAfter);
        }

        $builder = new RetailcrmAddressBuilder();
        $result = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_ORDER_DELIVERY)
            ->setAddressId($idAddressAfter)
            ->build()
            ->getDataArray()
        ;

        $this->assertEquals($crmOrder['delivery']['address']['countryIso'], $result['countryIso']);
        unset($crmOrder['delivery']['address']['countryIso']);

        $this->assertEquals($crmOrder['delivery']['address'], $result['delivery']['address']);
    }

    public function testOrderNameUpdate()
    {
        $orderId = RetailcrmTestHelper::getMaxOrderId();
        $crmOrder = $this->getApiOrderNameAndPhoneUpdate($orderId);

        $this->apiClientMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryNameAndPhoneUpdated($orderId)
                    )
                )
            )
        ;

        $this->apiClientMock->expects($this->any())
            ->method('ordersEdit')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getEditedOrder($crmOrder)
                    )
                )
            )
        ;

        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $order = new Order($orderId);
        $idAddress = $order->id_address_delivery;

        RetailcrmHistory::ordersHistory();

        $orderAfter = new Order($orderId);
        $idAddressAfter = $orderAfter->id_address_delivery;
        $addressAfter = new Address($idAddressAfter);

        if (version_compare(_PS_VERSION_, '1.7.7', '<')) {
            $this->assertNotEquals($idAddress, $idAddressAfter);
        }

        $this->assertEquals($crmOrder['firstName'], $addressAfter->firstname);
        $this->assertEquals($crmOrder['lastName'], $addressAfter->lastname);
        $this->assertEquals($crmOrder['phone'], $addressAfter->phone);
    }

    private function getHistoryExistOrder()
    {
        return [
            'success' => true,
            'history' => [
                [
                    'id' => 19752,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'api',
                    'field' => 'customer',
                    'apiKey' => ['current' => false],
                    'oldValue' => [
                        'id' => 7778,
                        'externalId' => '1',
                        'site' => '127.0.0.1:8000',
                    ],
                    'newValue' => [
                        'id' => 7777,
                        'externalId' => '777',
                        'site' => '127.0.0.1:8000',
                    ],
                    'order' => [
                        'id' => 6025,
                        'externalId' => '1',
                        'site' => '127.0.0.1:8000',
                        'status' => 'new',
                    ],
                ],
            ],
            'pagination' => [
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
        ];
    }

    private function getHistoryDataNewOrder($orderData)
    {
        return [
            'success' => true,
            'history' => [
                [
                    'id' => 1,
                    'createdAt' => '2018-01-01 00:00:00',
                    'created' => true,
                    'source' => 'user',
                    'user' => [
                        'id' => 1,
                    ],
                    'field' => 'status',
                    'oldValue' => null,
                    'newValue' => [
                        'code' => 'new',
                    ],
                    'order' => $orderData,
                ],
            ],
            'pagination' => [
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
        ];
    }

    private function getEditedOrder($orderData)
    {
        return [
            'success' => true,
            'id' => $orderData['id'],
            'order' => $orderData,
        ];
    }

    private function getApiOrder($id = 1)
    {
        $order = [
            'slug' => $id,
            'id' => $id,
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
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Buenos Aires',
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
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Buenos Aires',
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
                        'externalId' => $this->product['id'],
                        'xmlId' => '1',
                        'name' => 'Test name',
                        'vatRate' => 'none',
                    ],
                    'properties' => [],
                    'purchasePrice' => 50,
                ],
                array_merge(RetailcrmOrderBuilder::getGiftItem(10), ['id' => 25919]),
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

    private function getApiOrderWitchCorporateCustomer($id = 2)
    {
        $orderWithCorporateCustomer = [
            'slug' => $id,
            'id' => $id,
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
                'type' => 'customer_corporate',
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
                    'id' => 2345,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Buenos Aires',
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
            'contact' => [
                'id' => 1,
                'externalId' => '7777',
                'type' => 'customer_corporate',
                'managerId' => 23,
                'isContact' => true,
                'vip' => false,
                'bad' => false,
            ],
            'contragent' => [],
            'delivery' => [
                'code' => 'delivery',
                'cost' => 100,
                'netCost' => 0,
                'address' => [
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Buenos Aires',
                    'city' => 'Test',
                    'text' => 'Test text address',
                ],
            ],
            'company' => [
                'id' => 7777,
                'contragent' => [
                    'legalName' => 'test',
                    'INN' => '255222',
                ],
                'address' => [
                    'id' => 1,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Buenos Aires',
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
                        'externalId' => $this->product['id'],
                        'xmlId' => '1',
                        'name' => 'Test name',
                        'vatRate' => 'none',
                    ],
                    'properties' => [],
                    'purchasePrice' => 50,
                ],
                array_merge(RetailcrmOrderBuilder::getGiftItem(10), ['id' => 25919]),
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

        $orderWithCorporateCustomer['payments'][] = [
            'id' => 97,
            'type' => 'cheque',
            'amount' => 210,
        ];

        return $orderWithCorporateCustomer;
    }

    private function getUpdatePaymentStatus($orderId)
    {
        return [
            'success' => true,
            'pagination' => [
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
            'history' => [
                [
                    'id' => 654,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'user',
                    'user' => [
                        'id' => 1,
                    ],
                    'field' => 'full_paid_at',
                    'oldValue' => null,
                    'newValue' => '2018-01-01 00:00:00',
                    'order' => [
                        'id' => 1,
                        'externalId' => $orderId,
                        'site' => 'test-com',
                        'status' => 'new',
                    ],
                ],
                [
                    'id' => 655,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'user',
                    'user' => [
                        'id' => 1,
                    ],
                    'field' => 'payments.paid_at',
                    'oldValue' => null,
                    'newValue' => '2018-01-01 00:00:00',
                    'order' => [
                        'id' => 1,
                        'externalId' => $orderId,
                        'site' => 'test-com',
                        'status' => 'new',
                    ],
                    'payment' => [
                        'id' => 102,
                        'type' => 'cheque',
                        'externalId' => 1,
                    ],
                ],
                [
                    'id' => 656,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'user',
                    'user' => [
                        'id' => 1,
                    ],
                    'field' => 'payments.status',
                    'oldValue' => [
                        'code' => 'not-paid',
                    ],
                    'newValue' => [
                        'code' => 'paid',
                    ],
                    'order' => [
                        'id' => 1,
                        'externalId' => $orderId,
                        'site' => 'test-com',
                        'status' => 'new',
                    ],
                    'payment' => [
                        'id' => 102,
                        'type' => 'cheque',
                        'externalId' => 1,
                    ],
                ],
            ],
        ];
    }

    private function getHistoryDataNewCustomer()
    {
        return [
            'success' => true,
            'history' => [
                [
                    'id' => 1,
                    'createdAt' => '2018-01-01 00:00:00',
                    'created' => true,
                    'source' => 'api',
                    'field' => 'id',
                    'oldValue' => null,
                    'newValue' => 4949,
                    'customer' => $this->getApiCustomer(),
                ],
            ],
            'pagination' => [
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
        ];
    }

    private function getApiCustomer()
    {
        return [
            'type' => 'customer',
            'id' => 1,
            'externalId' => '1',
            'isContact' => false,
            'createdAt' => '2020-05-08 03:00:38',
            'vip' => false,
            'bad' => false,
            'site' => 'example.com',
            'contragent' => [
                'contragentType' => 'individual',
            ],
            'tags' => [],
            'marginSumm' => 0,
            'totalSumm' => 0,
            'averageSumm' => 0,
            'ordersCount' => 0,
            'costSumm' => 0,
            'customFields' => [],
            'personalDiscount' => 0,
            'address' => [
                'id' => 4053,
                'countryIso' => 'RU',
                'index' => '2170',
                'city' => 'Buenos Aires',
                'street' => 'Good',
                'building' => '17',
                'text' => 'Good, д. 17',
            ],
            'segments' => [],
            'email' => 'test@example.com',
            'firstName' => 'Test',
            'lastName' => 'Test',
            'phones' => [
                'number' => '+79999999999',
            ],
        ];
    }

    private function getHistoryAddressUpdated($orderId)
    {
        return [
            'success' => true,
            'history' => [
                [
                    'id' => 19752,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'api',
                    'field' => 'delivery_address.city',
                    'apiKey' => ['current' => false],
                    'oldValue' => 'Order City old',
                    'newValue' => 'Order City new',
                    'order' => [
                        'id' => 6025,
                        'externalId' => (string) $orderId,
                        'site' => '127.0.0.1:8000',
                        'status' => 'new',
                    ],
                ],
                [
                    'id' => 19753,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'api',
                    'field' => 'delivery_address.index',
                    'apiKey' => ['current' => false],
                    'oldValue' => '111',
                    'newValue' => '222',
                    'order' => [
                        'id' => 6025,
                        'externalId' => (string) $orderId,
                        'site' => '127.0.0.1:8000',
                        'status' => 'new',
                    ],
                ],
                [
                    'id' => 19754,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'api',
                    'field' => 'delivery_address.street',
                    'apiKey' => ['current' => false],
                    'oldValue' => null,
                    'newValue' => 'Test updated address',
                    'order' => [
                        'id' => 6025,
                        'externalId' => (string) $orderId,
                        'site' => '127.0.0.1:8000',
                        'status' => 'new',
                    ],
                ],
            ],
            'pagination' => [
                'limit' => 20,
                'totalCount' => 3,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
        ];
    }

    private function getHistoryNameAndPhoneUpdated($orderId)
    {
        return [
            'success' => true,
            'history' => [
                [
                    'id' => 19752,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'api',
                    'field' => 'first_name',
                    'apiKey' => ['current' => false],
                    'oldValue' => 'name old',
                    'newValue' => 'name new',
                    'order' => [
                        'id' => 6025,
                        'externalId' => (string) $orderId,
                        'site' => '127.0.0.1:8000',
                        'status' => 'new',
                    ],
                ],
                [
                    'id' => 19753,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'api',
                    'field' => 'phone',
                    'apiKey' => ['current' => false],
                    'oldValue' => '111',
                    'newValue' => '222222',
                    'order' => [
                        'id' => 6025,
                        'externalId' => (string) $orderId,
                        'site' => '127.0.0.1:8000',
                        'status' => 'new',
                    ],
                ],
            ],
            'pagination' => [
                'limit' => 20,
                'totalCount' => 2,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
        ];
    }

    private function getApiOrderAddressUpdate($orderId)
    {
        $order = $this->getApiOrder();

        $order['externalId'] = (string) $orderId;
        $order['delivery']['address']['city'] = 'Order City new';
        $order['delivery']['address']['index'] = '222';
        $order['delivery']['address']['text'] = 'Test updated address';
        unset($order['delivery']['address']['id_customer']);

        return $order;
    }

    private function getApiOrderNameAndPhoneUpdate($orderId)
    {
        $order = $this->getApiOrder();

        $order['externalId'] = (string) $orderId;
        $order['firstName'] = 'name new';
        $order['phone'] = '222222';

        return $order;
    }

    private function getLastSinceId()
    {
        return [
            'success' => true,
            'history' => [
                [
                    'id' => 1,
                    'createdAt' => '2018-01-01 00:00:00',
                    'created' => true,
                    'source' => 'api',
                    'field' => 'id',
                    'oldValue' => null,
                    'newValue' => 4949,
                ],
                [
                    'id' => 2,
                    'createdAt' => '2018-02-01 00:00:00',
                    'created' => true,
                    'source' => 'api',
                    'field' => 'id',
                    'oldValue' => null,
                    'newValue' => 5050,
                ],
                [
                    'id' => 3,
                    'createdAt' => '2018-03-01 00:00:00',
                    'created' => true,
                    'source' => 'api',
                    'field' => 'id',
                    'oldValue' => null,
                    'newValue' => 5151,
                ],
            ],
            'pagination' => [
                'limit' => 100,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
        ];
    }
}
