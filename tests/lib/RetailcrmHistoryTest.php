<?php

class RetailcrmHistoryTest extends RetailcrmTestCase
{
    private $apiMock;
    private $product;

    public function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getMockBuilder('RetailcrmProxy')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'customersHistory',
                    'ordersHistory',
                    'ordersGet',
                    'customersGet',
                    'customersFixExternalIds',
                    'ordersFixExternalIds',
                    'customersCorporateAddressesEdit'
                )
            )
            ->getMock();

        $catalog = new RetailcrmCatalog();
        $data = $catalog->getData();
        $this->product = $data[1][0];

        Configuration::updateValue('RETAILCRM_API_DELIVERY_DEFAULT', 2);
        Configuration::updateValue('RETAILCRM_API_PAYMENT_DEFAULT', 'bankwire');

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
            );

        $this->apiMock->expects($this->any())
            ->method('customersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        array(
                            'customer' => $this->getApiCustomer()
                        )
                    )
                )
            );

        RetailcrmHistory::$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $externalId = isset($this->getApiCustomer()['externalId']) ? $this->getApiCustomer()['externalId'] : null;

        if (!empty($externalId))
        {
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

        $this->assertEquals(true, RetailcrmHistory::customersHistory());
    }

    public function orderCreate($apiMock)
    {
        RetailcrmHistory::$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $apiMock;

        $oldLastId = RetailcrmTestHelper::getMaxOrderId();
        RetailcrmHistory::ordersHistory();
        $newLastId = RetailcrmTestHelper::getMaxOrderId();

        $this->assertTrue($newLastId > $oldLastId);

        $order = new Order($newLastId);

        $this->assertInstanceOf('Order', $order);
    }

    public function switchCustomer()
    {
        RetailcrmHistory::$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
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
            );

        $this->apiMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        array(
                            'order' => $this->getApiOrder()
                        )
                    )
                )
            );

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
            );

        $this->apiMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        array(
                            'order' => $this->getApiOrderWitchCorporateCustomer()
                        )
                    )
                )
            );

        $this->switchCustomer();
    }

    public function testOrderCreate()
    {
        $this->apiMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryDataNewOrder($this->getApiOrder())
                    )
                )
            );

        $this->apiMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        array(
                            'order' => $this->getApiOrder()
                        )
                    )
                )
            );

        $this->orderCreate($this->apiMock);
    }

    public function testOrderCreateWithCorporateCustomer()
    {
        $this->apiMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryDataNewOrder($this->getApiOrderWitchCorporateCustomer())
                    )
                )
            );

        $this->apiMock->expects($this->any())
            ->method('ordersGet')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        array(
                            'order' => $this->getApiOrderWitchCorporateCustomer()
                        )
                    )
                )
            );

        $this->orderCreate($this->apiMock);
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
            );

        RetailcrmHistory::$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        RetailcrmHistory::ordersHistory();
    }

    private function getHistoryExistOrder()
    {
        return array(
            'success' => true,
            'history'  => array(
                array(
                    'id' => 19752,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'api',
                    'field' => 'customer',
                    'apiKey' => array('current' => false),
                    'oldValue' => array(
                        'id' => 7778,
                        'externalId' => '1',
                        'site' => '127.0.0.1:8000'
                    ),
                    'newValue' => array(
                        'id' => 7777,
                        'externalId' => '777',
                        'site' => '127.0.0.1:8000'
                    ),
                    'order' => array(
                        'id' => 6025,
                        'externalId' => '1',
                        'site' => '127.0.0.1:8000',
                        'status' => 'new'
                    )
                )
            ),
            'pagination' => array(
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1
            )
        );
    }

    private function getHistoryDataNewOrder($orderData)
    {
        return array(
            'success' => true,
            'history'  => array(
                array(
                    'id' => 1,
                    'createdAt' => '2018-01-01 00:00:00',
                    'created' => true,
                    'source' => 'user',
                    'user' => array(
                        'id' => 1
                    ),
                    'field' => 'status',
                    'oldValue' => null,
                    'newValue' => array(
                        'code' => 'new'
                    ),
                    'order' => $orderData
                )
            ),
            'pagination' => array(
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1
            )
        );
    }

    private function getApiOrder()
    {
        $order = array(
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
            'customer' => array(
                'segments' => array(),
                'id' => 1,
                'externalId' => '777',
                'type' => 'customer',
                'firstName' => 'Test',
                'lastName' => 'Test',
                'email' => 'email@test.ru',
                'phones' => array(
                    array(
                        'number' => '111111111111111'
                    ),
                    array(
                        'number' => '+7111111111'
                    )
                ),
                'address' => array(
                    'id_customer' => 2222,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Test region',
                    'city' => 'Test',
                    'text' => 'Test text address'
                ),
                'createdAt' => '2018-01-01 00:00:00',
                'managerId' => 1,
                'vip' => false,
                'bad' => false,
                'site' => 'test-com',
                'contragent' => array(
                    'contragentType' => 'individual'
                ),
                'personalDiscount' => 0,
                'cumulativeDiscount' => 0,
                'marginSumm' => 58654,
                'totalSumm' => 61549,
                'averageSumm' => 15387.25,
                'ordersCount' => 4,
                'costSumm' => 101,
                'customFields' => array(
                    'custom' => 'test'
                )
            ),
            'contragent' => array(),
            'delivery' => array(
                'code' => 'delivery',
                'cost' => 100,
                'netCost' => 0,
                'address' => array(
                    'id_customer' => 2222,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Test region',
                    'city' => 'Test',
                    'text' => 'Test text address'
                )
            ),
            'site' => 'test-com',
            'status' => 'new',
            'items' => array(
                array(
                    'id' => 160,
                    'initialPrice' => 100,
                    'createdAt' => '2018-01-01 00:00:00',
                    'quantity' => 1,
                    'status' => 'new',
                    'offer' => array(
                        'id' => 1,
                        'externalId' => $this->product['id'],
                        'xmlId' => '1',
                        'name' => 'Test name',
                        'vatRate' => 'none'
                    ),
                    'properties' => array(),
                    'purchasePrice' => 50
                ),
                array_merge(RetailcrmOrderBuilder::getGiftItem(10), array('id' => 25919))
            ),
            'fromApi' => false,
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'shipmentStore' => 'main',
            'shipped' => false,
            'customFields' => array(),
            'uploadedToExternalStoreSystem' => false
        );

        $order['payments'][] = array(
            'id' => 97,
            'type' => 'cheque',
            'amount' => 210
        );

        return $order;
    }

    private function getApiOrderWitchCorporateCustomer()
    {
        $orderWithCorporateCustomer = array(
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
            'customer' => array(
                'segments' => array(),
                'id' => 1,
                'externalId' => '777',
                'type' => 'customer_corporate',
                'firstName' => 'Test',
                'lastName' => 'Test',
                'email' => 'email@test.ru',
                'phones' => array(
                    array(
                        'number' => '111111111111111'
                    ),
                    array(
                        'number' => '+7111111111'
                    )
                ),
                'address' => array(
                    'id' => 2345,
                    'id_customer' => 2222,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Test region',
                    'city' => 'Test',
                    'text' => 'Test text address'
                ),
                'createdAt' => '2018-01-01 00:00:00',
                'managerId' => 1,
                'vip' => false,
                'bad' => false,
                'site' => 'test-com',
                'contragent' => array(
                    'contragentType' => 'individual'
                ),
                'personalDiscount' => 0,
                'cumulativeDiscount' => 0,
                'marginSumm' => 58654,
                'totalSumm' => 61549,
                'averageSumm' => 15387.25,
                'ordersCount' => 4,
                'costSumm' => 101,
                'customFields' => array(
                    'custom' => 'test'
                )
            ),
            'contact' => array(
                'id' => 1,
                'externalId' => '7777',
                'type' => 'customer_corporate',
                'managerId' => 23,
                'isContact' => true,
                'vip' => false,
                'bad' => false,
            ),
            'contragent' => array(),
            'delivery' => array(
                'code' => 'delivery',
                'cost' => 100,
                'netCost' => 0,
                'address' => array(
                    'id_customer' => 2222,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Test region',
                    'city' => 'Test',
                    'text' => 'Test text address'
                )
            ),
            'company' => array(
                'id' => 7777,
                'contragent' => array(
                    'legalName' => 'test',
                    'INN' => '255222'
                ),
                'address' => array(
                    'id' => 1,
                    'id_customer' => 2222,
                    'index' => '111111',
                    'countryIso' => 'RU',
                    'region' => 'Test region',
                    'city' => 'Test',
                    'text' => 'Test text address'
                )
            ),
            'site' => 'test-com',
            'status' => 'new',
            'items' => array(
                array(
                    'id' => 160,
                    'initialPrice' => 100,
                    'createdAt' => '2018-01-01 00:00:00',
                    'quantity' => 1,
                    'status' => 'new',
                    'offer' => array(
                        'id' => 1,
                        'externalId' => $this->product['id'],
                        'xmlId' => '1',
                        'name' => 'Test name',
                        'vatRate' => 'none'
                    ),
                    'properties' => array(),
                    'purchasePrice' => 50
                ),
                array_merge(RetailcrmOrderBuilder::getGiftItem(10), array('id' => 25919))
            ),
            'fromApi' => false,
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'shipmentStore' => 'main',
            'shipped' => false,
            'customFields' => array(),
            'uploadedToExternalStoreSystem' => false
        );

        $orderWithCorporateCustomer['payments'][] = array(
            'id' => 97,
            'type' => 'cheque',
            'amount' => 210
        );

        return $orderWithCorporateCustomer;
    }

    private function getUpdatePaymentStatus($orderId)
    {
        return array(
            'success' => true,
            'pagination' => array(
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1
            ),
            'history' => array(
                array(
                    'id' => 654,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'user',
                    'user' => array(
                        'id' => 1
                    ),
                    'field' => 'full_paid_at',
                    'oldValue' => null,
                    'newValue' => '2018-01-01 00:00:00',
                    'order' => array(
                        'id' => 1,
                        'externalId' => $orderId,
                        'site' => 'test-com',
                        'status' => 'new'
                    )
                ),
                array(
                    'id'=> 655,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'user',
                    'user' => array(
                        'id' => 1
                    ),
                    'field' => 'payments.paid_at',
                    'oldValue' => null,
                    'newValue' => '2018-01-01 00:00:00',
                    'order' => array(
                        'id' => 1,
                        'externalId' => $orderId,
                        'site' => 'test-com',
                        'status' => 'new'
                    ),
                    'payment'=> array(
                        'id'=> 102,
                        'type'=> 'cheque',
                        'externalId' => 1
                    )
                ),
                array(
                    'id' => 656,
                    'createdAt' => '2018-01-01 00:00:00',
                    'source' => 'user',
                    'user' => array(
                        'id' => 1
                    ),
                    'field' => 'payments.status',
                    'oldValue' => array(
                        'code' => 'not-paid'
                    ),
                    'newValue' => array(
                        'code' => 'paid'
                    ),
                    'order' => array(
                        'id' => 1,
                        'externalId' => $orderId,
                        'site' => 'test-com',
                        'status' => 'new'
                    ),
                    'payment' => array(
                        'id' => 102,
                        'type' => 'cheque',
                        'externalId' => 1
                    )
                )
            )
        );
    }

    private function getHistoryDataNewCustomer()
    {
        return array(
            'success' => true,
            'history' => array(
                array(
                    'id' => 1,
                    'createdAt' => '2018-01-01 00:00:00',
                    'created' => true,
                    'source' => 'api',
                    'field' => 'id',
                    'oldValue' => null,
                    'newValue' => 4949,
                    'customer' => $this->getApiCustomer()
                )
            ),
            'pagination' => array(
                'limit' => 20,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1
            )
        );
    }

    private function getApiCustomer()
    {
        return array(
            'type' => 'customer',
            'id' => 1,
            'externalId' => '1',
            'isContact' => false,
            'createdAt' => '2020-05-08 03:00:38',
            'vip' => false,
            'bad' => false,
            'site' => 'example.com',
            'contragent'=> array(
                'contragentType'=> 'individual'
            ),
            'tags' => array(),
            'marginSumm' => 0,
            'totalSumm' => 0,
            'averageSumm' => 0,
            'ordersCount' => 0,
            'costSumm' => 0,
            'customFields' => [],
            'personalDiscount' => 0,
            'address' => array(
                'id_customer' => 2222,
                'id' => 4053,
                'countryIso' => 'RU',
                'index' => '2170',
                'city' => 'Moscow',
                'street' => 'Good',
                'building' => '17',
                'text' => 'Good, д. 17'
            ),
            'segments' => array(),
            'email' => 'test@example.com',
            'firstName' => 'Test',
            'lastName' => 'Test',
            'phones' => array(
                'number' => '+79999999999'
            )
        );
    }
}

