<?php


class RetailcrmReferenceMiddlewareTest extends RetailcrmTestCase
{
    private $apiMock;

    private $apiProxy;

    public function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->apiMockBuilder()->getMock();
        $this->setMethods();

        $this->apiProxy = new RetailcrmProxy('https://test.test', 'test_key');

        $reflector = new ReflectionClass($this->apiProxy);

        $clientProp = $reflector->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($this->apiProxy, $this->apiMock);

        $pipelineProp = $reflector->getProperty('pipeline');
        $pipelineProp->setAccessible(true);
        $pipeline = $pipelineProp->getValue($this->apiProxy);
        $pipeline
            ->setAction(function ($request) {
                return call_user_func_array([$this->apiMock, $request->getMethod()], $request->getData());
            })
            ->build();
        $pipelineProp->setValue($this->apiProxy, $pipeline);

    }

    private function apiMockBuilder()
    {
        return $this->getMockBuilder('RetailcrmApiClientV5')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'ordersCreate',
                    'ordersEdit',
                    'ordersGet'
                )
            );
    }

    public function getRequests()
    {
        return [
            [
                'method' => 'ordersGet',
                'params' => [[]],
                'reference' => 'reference',
            ],
            [
                'method' => 'ordersEdit',
                'params' => [['number' => 'test', 'externalId' => 1]],
                'reference' => 'test',
            ],
            [
                'method' => 'ordersCreate',
                'params' => [['number' => 'test', 'externalId' => 1]],
                'reference' => 'test',
            ],
        ];
    }

    /**
     * @dataProvider getRequests
     */
    public function testRequest($method, $params, $reference)
    {
        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, true);

        $order = new Order(1);
        $order->reference = 'reference';
        $order->update();
        unset($order);

        /** @var RetailcrmApiResponse $response */
        $response = $this->apiProxy->$method($params);

        $this->assertInstanceOf(RetailcrmApiResponse::class, $response);
        $this->assertTrue($response->isSuccessful());

        $order = new Order(1);

        $this->assertEquals($reference, $order->reference);
    }

    private function setMethods()
    {
        $this->apiMock->expects($this->any())->method('ordersCreate')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'order' => array(
                    'number' => 'test',
                    'externalId' => 1,
                ),
            ))
        ));
        $this->apiMock->expects($this->any())->method('ordersEdit')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'order' => array(
                    'number' => 'test',
                    'externalId' => 1,
                ),
            ))
        ));
        $this->apiMock->expects($this->any())->method('ordersGet')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'order' => array(
                    'number' => 'test',
                    'externalId' => 1,
                ),
            ))
        ));

    }
}
