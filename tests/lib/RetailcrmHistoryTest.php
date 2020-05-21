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
                    'customersFixExternalIds',
                    'ordersFixExternalIds'
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

    public function testOrderCreate()
    {
        $this->apiMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(
                        $this->getHistoryDataNewOrder()
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

        RetailcrmHistory::$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        RetailcrmHistory::$api = $this->apiMock;

        $oldLastId = RetailcrmTestHelper::getMaxOrderId();
        RetailcrmHistory::ordersHistory();
        $newLastId = RetailcrmTestHelper::getMaxOrderId();

        $this->assertNotEquals($oldLastId, $newLastId);
        $this->assertTrue($newLastId > $oldLastId);

        $order = new Order($newLastId);

        $this->assertInstanceOf('Order', $order);
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

    private function getHistoryDataNewOrder()
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
                    'order' => $this->getApiOrder()
                )
            ),
            "pagination" => array(
                "limit" => 20,
                "totalCount" => 1,
                "currentPage" => 1,
                "totalPageCount" => 1
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

    private function getUpdatePaymentStatus($orderId)
    {
        return array(
            'success' => true,
            "pagination" => array(
                "limit" => 20,
                "totalCount" => 1,
                "currentPage" => 1,
                "totalPageCount" => 1
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
}
