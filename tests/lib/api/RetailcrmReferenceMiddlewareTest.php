<?php

class RetailcrmReferenceMiddlewareTest extends RetailcrmTestCase
{
    private $apiMock;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getApiMock(
            [
                'ordersCreate',
                'ordersEdit',
                'ordersGet',
            ]
        );
    }

    public function getRequests()
    {
        return [
            [
                'method' => 'ordersGet',
                'params' => [],
                'reference' => 'reference',
            ],
            [
                'method' => 'ordersEdit',
                'params' => ['number' => 'test', 'externalId' => 1],
                'reference' => 'test',
            ],
            [
                'method' => 'ordersCreate',
                'params' => ['number' => 'test', 'externalId' => 1],
                'reference' => 'test',
            ],
        ];
    }

    /**
     * @dataProvider getRequests
     */
    public function testRequest($method, $params, $reference)
    {
        $this->apiClientMock->expects($this->any())->method($method)->willReturn(new RetailcrmApiResponse(
            200,
            json_encode([
                'success' => true,
                'id' => 1,
                'order' => [
                    'number' => 'test',
                    'id' => 1,
                    'externalId' => 1,
                ],
            ])
        ));

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, true);

        $order = new Order(1);
        $order->reference = 'reference';
        $order->update();
        unset($order);

        /** @var RetailcrmApiResponse $response */
        $response = $this->apiMock->$method($params);

        $this->assertInstanceOf(RetailcrmApiResponse::class, $response);
        $this->assertTrue($response->isSuccessful());

        $order = new Order(1);

        $this->assertEquals($reference, $order->reference);
    }
}
