<?php

class RetailCRMTest extends RetailcrmTestCase
{
    private $retailcrmModule;
    private $apiMock;

    protected function setUp()
    {
        parent::setUp();

        $this->setConfig();

        $this->apiMock = $this->apiMockBuilder()->getMock();
        $this->setMethods();

        $this->retailcrmModule = new RetailCRM();
        $this->retailcrmModule->api = new RetailcrmProxy('https://test.test', 'test_key');

        $reflector = new ReflectionClass($this->retailcrmModule->api);

        $clientProp = $reflector->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($this->retailcrmModule->api, $this->apiMock);

        $pipelineProp = $reflector->getProperty('pipeline');
        $pipelineProp->setAccessible(true);
        $pipeline = $pipelineProp->getValue($this->retailcrmModule->api);
        $pipeline
            ->setAction(function ($request) {
                return call_user_func_array([$this->apiMock, $request->getMethod()], $request->getData());
            })
            ->build();
        $pipelineProp->setValue($this->retailcrmModule->api, $pipeline);
    }

    private function apiMockBuilder()
    {
        return $this->getMockBuilder('RetailcrmApiClientV5')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'customersCreate',
                    'customersEdit',
                    'customersGet',
                    'ordersCreate',
                    'ordersEdit',
                    'ordersGet',
                    'ordersPaymentEdit',
                    'ordersPaymentCreate',
                ]
            )
        ;
    }

    public function testUploadOrders()
    {
        Configuration::updateValue(RetailCRM::API_URL, 'https://test.test');
        Configuration::updateValue(RetailCRM::API_KEY, 'test_key');

        $order = new Order(1);
        $reference = $order->reference;
        $updReference = 'test';

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, false);
        $this->retailcrmModule->uploadOrders([1]);
        $firstUpdOrder = new Order(1);

        $this->assertEquals($reference, $firstUpdOrder->reference);

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, true);
        $this->retailcrmModule->uploadOrders([1]);
        $secondUpdOrder = new Order(1);

        $this->assertEquals($updReference, $secondUpdOrder->reference);
    }

    public function testHookActionCustomerAccountAdd()
    {
        $newCustomer = new Customer(1);
        $params = ['newCustomer' => $newCustomer];

        $this->assertTrue($this->retailcrmModule->hookActionCustomerAccountAdd($params));
    }

    public function testHookActionCustomerAccountUpdate()
    {
        $customer = new Customer(1);
        $params = ['customer' => $customer];

        $this->assertTrue($this->retailcrmModule->hookActionCustomerAccountUpdate($params));
    }

    public function testHookActionOrderEdited()
    {
        $order = new Order(1);
        $customer = new Customer($order->id_customer);
        $params = ['order' => $order, 'customer' => $customer];
        $reference = $order->reference;
        $updReference = 'test';

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, false);

        $this->assertTrue($this->retailcrmModule->hookActionOrderEdited($params));
        $order = new Order(1);
        $this->assertEquals($reference, $order->reference);

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, true);

        $this->assertTrue($this->retailcrmModule->hookActionOrderEdited($params));
        $order = new Order(1);
        $this->assertEquals($updReference, $order->reference);
    }

    /**
     * @param $newOrder
     * @dataProvider dataProvider
     */
    public function testHookActionOrderStatusPostUpdate($newOrder)
    {
        $order = new Order(1);
        $customer = new Customer($order->id_customer);
        $cart = $this->createMock('Cart');
        $cart->expects($this->any())->method('getProducts')->willReturn($this->getProducts());
        $cart->expects($this->any())->method('getAddressCollection')->willReturn($this->getAddressCollection());
        $status = new StdClass();
        $reference = $order->reference;
        $updReference = 'test';

        if (false === $newOrder) {
            $status->id = 11;

            $params = [
                'newOrderStatus' => $status,
                'id_order' => $order->id,
            ];
        } else {
            $status->id = 'new';

            $params = [
                'orderStatus' => $status,
                'customer' => $customer,
                'order' => $order,
                'cart' => $cart,
            );
        }

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, false);

        $this->assertTrue($this->retailcrmModule->hookActionOrderStatusPostUpdate($params));
        $this->assertEquals($reference, $order->reference);

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, true);

        $this->assertTrue($this->retailcrmModule->hookActionOrderStatusPostUpdate($params));
        $this->assertEquals($updReference, $order->reference);
    }

    public function testHookActionPaymentCCAdd()
    {
        $order = new Order(1);

        $orderPayment = RetailcrmTestHelper::createOrderPayment($order->reference);
        $cart = new Cart($order->id_cart);

        $params = [
            'paymentCC' => $orderPayment,
            'cart' => $cart,
        ];

        $referenceMock = $this->createMock('RetailcrmReferences');
        $referenceMock->expects($this->once())->method('getSystemPaymentModules')->willReturn($this->getSystemPaymentModules());
        $this->retailcrmModule->reference = $referenceMock;

        $result = $this->retailcrmModule->hookActionPaymentCCAdd($params);

        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);

        RetailcrmTestHelper::deleteOrderPayment($orderPayment->id);
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return [
            [
                'newOrder' => true,
            ],
            [
                'newOrder' => false,
            ],
        ];
    }

    /**
     * @return array
     */
    private function getProducts()
    {
        return [
            [
                'id_product_attribute' => 1,
                'id_product' => 1,
                'attributes' => '',
                'rate' => 1,
                'price' => 100,
                'name' => 'Test product 1',
                'quantity' => 2,
            ],
            [
                'id_product_attribute' => 1,
                'id_product' => 2,
                'attributes' => '',
                'rate' => 1,
                'price' => 100,
                'name' => 'Test product 2',
                'quantity' => 1,
            ],
        ];
    }

    /**
     * @return array
     */
    private function getAddressCollection()
    {
        $address = new Address(1);

        return [$address];
    }

    /**
     * @return array
     */
    private function getSystemPaymentModules()
    {
        return [
            [
                'id' => '3',
                'code' => 'bankwire',
                'name' => 'Bank wire',
            ],
            [
                'id' => '30',
                'code' => 'cheque',
                'name' => 'Payment by check',
            ],
        ];
    }

    private function setMethods()
    {
        $this->apiMock->expects($this->any())->method('customersCreate')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'customer' => [],
            ))
        ));
        $this->apiMock->expects($this->any())->method('customersEdit')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'customer' => [],
            ))
        ));
        $this->apiMock->expects($this->any())->method('customersGet')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'customer' => [
                    'phones' => [],
                ],
            ))
        ));
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
                    'payments' => array(
                        array(
                            'type' => 'bankwire'
                        )
                    ),
                    'totalSumm' => 1500
                ),
            ))
        ));
        $this->apiMock->expects($this->any())->method('ordersPaymentEdit')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'payment' => [],
            ))
        ));
        $this->apiMock->expects($this->any())->method('ordersPaymentCreate')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'payment' => [],
            ))
        ));
    }
}
