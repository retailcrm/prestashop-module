<?php

class RetailcrmHistoryTest extends RetailcrmTestCase
{
    private $apiMock;
    private $product;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getMockBuilder('RetailcrmProxy')
            ->disableOriginalConstructor()
            ->setMethods(
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
            )
            ->getMock()
        ;

        $catalog = new RetailcrmCatalog();
        $data = $catalog->getData();
        $this->product = $data[1]->current();

        Configuration::updateValue(RetailCRM::DELIVERY_DEFAULT, 2);
        Configuration::updateValue(RetailCRM::PAYMENT_DEFAULT, 'bankwire');

        $this->setConfig();
    }

    public function testCustomersHistory()
    {
        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->once())
            ->method('ordersEdit')
            ->with($checkArgs)
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        [
                            'order' => [],
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

    private function orderCreate($apiMock, $orderData)
    {
        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $apiMock;

        $oldLastId = RetailcrmTestHelper::getMaxOrderId();
        RetailcrmHistory::ordersHistory();
        $newLastId = RetailcrmTestHelper::getMaxOrderId();

        $this->assertTrue($newLastId > $oldLastId);

        $order = new Order($newLastId);

        $this->assertInstanceOf('Order', $order);

        $order->current_state = 10;
        $order->id_carrier = 1;

        // delivery address
        $address = $this->createAddress($order->id_address_delivery, $orderData['firstName'], $orderData['lastName']);

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

//        $this->assertEquals($orderData['delivery']['address'], $addressDelivery['delivery']['address']);
//        $this->assertEquals($orderData['phone'], $addressDelivery['phone']);

        // customer address
        $address = $this->createAddress($order->id_address_invoice, $orderData['customer']['firstName'], $orderData['customer']['lastName']);

        $this->assertEquals($orderData['customer']['firstName'], $address->firstname);
        $this->assertEquals($orderData['customer']['lastName'], $address->lastname);

        $addressInvoice = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_CUSTOMER)
            ->setAddress($address)
            ->build()
            ->getDataArray();

        if (isset($orderData['customer']['address']['id'])) {
            unset($orderData['customer']['address']['id']);
        }
        $this->assertEquals($orderData['customer']['address'], $addressInvoice['address']);
//        $this->assertEquals($orderData['customer']['phones'][0]['number'], $addressInvoice['phones'][0]['number']);

        // types and totals
        $this->assertEquals($orderData['totalSumm'], $order->total_paid);
        $this->assertEquals(10, $order->current_state);
        $this->assertEquals(1, $order->id_carrier);
        $this->assertEquals($orderData['payments'][0]['type'], $order->module);
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
        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->switchCustomer();
    }

    public function testOrderSwitchCorporateCustomer()
    {
        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->switchCustomer();
    }

    public function testOrderCreate()
    {
        $orderData = $this->getApiOrder();

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->orderCreate($this->apiMock, $orderData);
    }

    public function testOrderCreateWithCorporateCustomer()
    {
        $orderData = $this->getApiOrderWitchCorporateCustomer();

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->orderCreate($this->apiMock, $orderData);
    }

    public function testPaymentStatusUpdate()
    {
        $lastId = RetailcrmTestHelper::getMaxOrderId();

        $this->apiMock->expects($this->any())
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

        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        RetailcrmHistory::ordersHistory();
    }

    public function testOrderAddressUpdate()
    {
        $orderId = RetailcrmTestHelper::getMaxOrderId();
        $crmOrder = $this->getApiOrderAddressUpdate($orderId);

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        $this->apiMock->expects($this->any())
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

        RetailcrmHistory::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $order = new Order($orderId);
        $idAddress = $order->id_address_delivery;

        RetailcrmHistory::ordersHistory();

        $orderAfter = new Order($orderId);
        $idAddressAfter = $orderAfter->id_address_delivery;
        $addressAfter = $this->createAddress($idAddressAfter, $crmOrder['firstName'], $crmOrder['lastName'], $crmOrder['phone']);

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

    private function getApiOrder()
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

    private function getApiOrderWitchCorporateCustomer()
    {
        $orderWithCorporateCustomer = [
            'slug' => 1,
            'id' => 2,
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

    private function createAddress($id, $firstname, $lastname, $phone = null)
    {
        $address = new Address($id);
        $address->firstname = $firstname;
        $address->lastname = $lastname;
        $address->id_country = 177; //RU
        $address->phone = $phone;

        return $address;
    }
}
