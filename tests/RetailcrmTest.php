<?php

class RetailCRMTest extends RetailcrmTestCase
{
    private $retailcrmModule;
    private $apiMock;

    public function setUp()
    {
        parent::setUp();

        $this->setConfig();

        $this->apiMock = $this->apiMockBuilder()->getMock();

        $this->retailcrmModule = new RetailCRM();
        $this->retailcrmModule->api = $this->apiMock;
    }

    private function apiMockBuilder()
    {
        return $this->getMockBuilder('RetailcrmProxy')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'customersCreate',
                    'customersEdit',
                    'customersGet',
                    'ordersCreate',
                    'ordersEdit',
                    'ordersGet',
                    'ordersPaymentEdit',
                    'ordersPaymentCreate'
                )
            );
    }

    public function testUploadOrders()
    {
        Configuration::updateValue(RetailCRM::API_URL, 'https://test.test');
        Configuration::updateValue(RetailCRM::API_KEY, 'test_key');
        $order = new Order(1);
        $reference = $order->reference;
        $updReference = 'test';
        $this->apiMock->expects($this->any())->method('ordersGet')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'order' => array(),
            ))
        ));
        $this->apiMock->expects($this->any())->method('ordersCreate')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'order' => array(
                    'number' => $updReference,
                ),
            ))
        ));

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
        $params = array('newCustomer' => $newCustomer);

        $this->assertTrue($this->retailcrmModule->hookActionCustomerAccountAdd($params));
    }

    public function testHookActionCustomerAccountUpdate()
    {
        $customer = new Customer(1);
        $params = array('customer' => $customer);

        $this->assertTrue($this->retailcrmModule->hookActionCustomerAccountUpdate($params));
    }

    public function testHookActionOrderEdited()
    {
        $order = new Order(1);
        $customer = new Customer($order->id_customer);
        $params = array('order' => $order, 'customer' => $customer);
        $this->apiMock->expects($this->any())->method('ordersGet')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'order' => array()
            ))
        ));

        $this->assertTrue($this->retailcrmModule->hookActionOrderEdited($params));
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

        if ($newOrder === false) {
            $status->id = 11;

            $params = array(
                'newOrderStatus' => $status,
                'id_order' => $order->id
            );
        } else {
            $status->id = 'new';

            $params = array(
                'orderStatus' => $status,
                'customer' => $customer,
                'order' => $order,
                'cart' => $cart,
            );
        }

        $this->apiMock->expects($this->any())->method('ordersCreate')->willReturn(new RetailcrmApiResponse(
            200,
            json_encode(array(
                'success' => true,
                'order' => array(
                    'number' => $updReference,
                ),
            ))
        ));

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, false);

        $this->assertTrue($this->retailcrmModule->hookActionOrderStatusPostUpdate($params));
        $this->assertEquals($reference, $order->reference);

        Configuration::updateValue(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING, true);

        $this->assertTrue($this->retailcrmModule->hookActionOrderStatusPostUpdate($params));
        $this->assertEquals($updReference, $order->reference);
    }

    /**
     * @param $ordersGet
     * @dataProvider ordersGetDataProvider
     */
    public function testHookActionPaymentCCAdd($ordersGet)
    {
        $order = new Order(1);

        $orderPayment = RetailcrmTestHelper::createOrderPayment($order->reference);
        $cart = new Cart($order->id_cart);

        $params = array(
            'paymentCC' => $orderPayment,
            'cart' => $cart
        );

        $referenceMock = $this->createMock('RetailcrmReferences');
        $referenceMock->expects($this->once())->method('getSystemPaymentModules')->willReturn($this->getSystemPaymentModules());
        $this->retailcrmModule->reference = $referenceMock;
        $this->apiMock->expects($this->any())->method('ordersGet')->willReturn($ordersGet);

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
        return array(
            array(
                'newOrder' => true
            ),
            array(
                'newOrder' => false
            )
        );
    }

    /**
     * @return array
     */
    public function ordersGetDataProvider()
    {
        return array(
            array(
                'ordersGet' => array(
                    'success' => true,
                    'order' => array(
                        'payments' => array(
                            array(
                                'type' => 'bankwire'
                            )
                        ),
                        'totalSumm' => 1500
                    )
                )
            ),
            array(
                'ordersGet' => array(
                    'success' => true,
                    'order' => array(
                        'payments' => array(
                            array(
                                'type' => 'cheque'
                            )
                        ),
                        'totalSumm' => 1500
                    )
                )
            )
        );
    }

    /**
     * @return array
     */
    private function getProducts()
    {
        return array(
            array(
                'id_product_attribute' => 1,
                'id_product' => 1,
                'attributes' => '',
                'rate' => 1,
                'price' => 100,
                'name' => 'Test product 1',
                'quantity' => 2
            ),
            array(
                'id_product_attribute' => 1,
                'id_product' => 2,
                'attributes' => '',
                'rate' => 1,
                'price' => 100,
                'name' => 'Test product 2',
                'quantity' => 1
            )
        );
    }

    /**
     * @return array
     */
    private function getAddressCollection()
    {
        $address = new Address(1);

        return array($address);
    }

    /**
     * @return array
     */
    private function getSystemPaymentModules()
    {
        return array (
            array (
                'id' => '3',
                'code' => 'bankwire',
                'name' => 'Bank wire',
            ),
            array (
                'id' => '30',
                'code' => 'cheque',
                'name' => 'Payment by check',
            )
        );
    }
}
