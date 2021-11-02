<?php

class RetailcrmOrderBuilderTest extends RetailcrmTestCase
{
    protected function setUp()
    {
        parent::setUp();
    }

    public function testInitialPriceZero()
    {
        $item = $this->getDataItemInitialPriceZero();
        $resultItem = RetailcrmTools::clearArray($item);

        $this->assertTrue(isset($resultItem['initialPrice']));
        $this->assertEquals(0, $resultItem['initialPrice']);
    }

    public function testBuildCrmOrder()
    {
        $order = new Order(1);
        $order->reference = 'test_n';
        $order->current_state = 0;
        Configuration::updateValue('RETAILCRM_API_DELIVERY', '{"1":"test_delivery"}');
        Configuration::updateValue('RETAILCRM_API_STATUS', '{"1":"test_status"}');
        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_SENDING, false);
        $crmOrder = RetailcrmOrderBuilder::buildCrmOrder($order);

        $this->assertArrayNotHasKey('number', $crmOrder);

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_SENDING, true);
        $crmOrder = RetailcrmOrderBuilder::buildCrmOrder($order);

        $this->assertEquals($order->reference, $crmOrder['number']);
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
}
