<?php

class RetailCRMTest extends RetailcrmTestCase
{
    private $retailcrmModule;
    private $apiMock;

    public function setUp()
    {
        parent::setUp();

        $this->setConfig();

        $this->apiMock = $this->getMockBuilder('RetailcrmProxy')
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
            )
            ->getMock();

        $this->retailcrmModule = new RetailCRM();
        $this->retailcrmModule->api = $this->apiMock;
    }

    public function testHookActionCustomerAccountAdd()
    {
        $newCustomer = new Customer(1);
        $params = array('newCustomer' => $newCustomer);
        $customer = $this->retailcrmModule->hookActionCustomerAccountAdd($params);

        $this->assertNotEmpty($customer);
        $this->assertArrayHasKey('externalId', $customer);
        $this->assertArrayHasKey('firstName', $customer);
        $this->assertArrayHasKey('lastName', $customer);
        $this->assertArrayHasKey('email', $customer);
        $this->assertArrayHasKey('createdAt', $customer);
    }

    public function testHookActionCustomerAccountUpdate()
    {
        $customer = new Customer(1);
        $params = array('customer' => $customer);
        $customer = $this->retailcrmModule->hookActionCustomerAccountUpdate($params);

        $this->assertNotEmpty($customer);
        $this->assertArrayHasKey('externalId', $customer);
        $this->assertArrayHasKey('firstName', $customer);
        $this->assertArrayHasKey('lastName', $customer);
        $this->assertArrayHasKey('email', $customer);
        $this->assertArrayHasKey('birthday', $customer);
    }

    public function testHookActionOrderEdited()
    {
        $order = new Order(1);
        $customer = new Customer($order->id_customer);
        $params = array('order' => $order, 'customer' => $customer);

        $orderSend = $this->retailcrmModule->hookActionOrderEdited($params);

        $this->assertNotNull($orderSend);
        $this->assertArrayHasKey('externalId', $orderSend);
        $this->assertArrayHasKey('firstName', $orderSend);
        $this->assertArrayHasKey('lastName', $orderSend);
        $this->assertArrayHasKey('email', $orderSend);
        $this->assertArrayHasKey('delivery', $orderSend);
        $this->assertArrayHasKey('items', $orderSend);
    }

    /**
     * @param $newOrder
     * @param $apiVersion
     * @dataProvider dataProvider
     */
    public function testHookActionOrderStatusPostUpdate($newOrder, $apiVersion)
    {
        $this->retailcrmModule->apiVersion = $apiVersion;
        $order = new Order(1);
        $customer = new Customer($order->id_customer);
        $cart = $this->createMock('Cart');
        $cart->expects($this->any())->method('getProducts')->willReturn($this->getProducts());
        $cart->expects($this->any())->method('getAddressCollection')->willReturn($this->getAddressCollection());
        $status = new StdClass();

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

        $result = $this->retailcrmModule->hookActionOrderStatusPostUpdate($params);

        if ($newOrder === false) {
            $this->assertEquals('completed', $result);
        } else {
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('externalId', $result);
            $this->assertArrayHasKey('firstName', $result);
            $this->assertArrayHasKey('lastName', $result);
            $this->assertArrayHasKey('email', $result);
            $this->assertArrayHasKey('delivery', $result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('customer', $result);
            $this->assertArrayHasKey('externalId', $result['customer']);

            if ($apiVersion == 5) {
                $this->assertArrayHasKey('payments', $result);
                $this->assertInternalType('array', $result['payments']);
            } else {
                $this->assertArrayHasKey('paymentType', $result);
            }
        }
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

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('amount', $result);

        RetailcrmTestHelper::deleteOrderPayment($orderPayment->id);
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return array(
            array(
                'newOrder' => true,
                'apiVersion' => 4
            ),
            array(
                'newOrder' => false,
                'apiVersion' => 4
            ),
            array(
                'newOrder' => true,
                'apiVersion' => 5
            ),
            array(
                'newOrder' => false,
                'apiVersion' => 5
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
