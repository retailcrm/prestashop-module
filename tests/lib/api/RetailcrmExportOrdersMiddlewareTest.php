<?php

class RetailcrmExportOrdersMiddlewareTest extends RetailcrmTestCase
{
    private $apiMock;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getApiMock(
            [
                'ordersCreate',
                'ordersEdit',
                'ordersUpload',
                'ordersList',
            ]
        );

        RetailcrmExportOrdersHelper::removeOrders();
    }

    public function getRequests()
    {
        return [
            [
                'method' => 'ordersCreate',
                'orderIdCMS' => 1,
                'orderIdCRM' => 111,
                'result' => true,
                'errors' => null,
                'errorMsg' => null,
            ],
            [
                'method' => 'ordersCreate',
                'orderIdCMS' => 2,
                'orderIdCRM' => 222,
                'result' => false,
                'errors' => null,
                'errorMsg' => 'Test error',
            ],
            [
                'method' => 'ordersCreate',
                'orderIdCMS' => 3,
                'orderIdCRM' => 333,
                'result' => false,
                'errors' => [
                    'Test error #1',
                    'Test error #2',
                ],
                'errorMsg' => 'Test error',
            ],
            [
                'method' => 'ordersEdit',
                'orderIdCMS' => 1,
                'orderIdCRM' => 112,
                'result' => true,
                'errors' => null,
                'errorMsg' => null,
            ],
            [
                'method' => 'ordersEdit',
                'orderIdCMS' => 1,
                'orderIdCRM' => 112,
                'result' => false,
                'errors' => null,
                'errorMsg' => 'Test error',
            ],
            [
                'method' => 'ordersEdit',
                'orderIdCMS' => 1,
                'orderIdCRM' => 112,
                'result' => false,
                'errors' => [
                    'Test error #1',
                    'Test error #2',
                ],
                'errorMsg' => 'Test error',
            ],
        ];
    }

    /**
     * @dataProvider getRequests
     */
    public function testRequest($method, $orderIdCMS, $orderIdCRM, $result, $errors, $errorMsg)
    {
        $this->apiClientMock->expects($this->any())->method($method)->willReturn(new RetailcrmApiResponse(
            $result ? 200 : 400,
            json_encode([
                'success' => $result,
                'id' => $orderIdCRM,
                'order' => [
                    'id' => $orderIdCRM,
                    'externalId' => $orderIdCMS,
                ],
                'errors' => $errors,
                'errorMsg' => $errorMsg,
            ])
        ));
        $this->makeRequestAndCheckResponse($method, ['externalId' => $orderIdCMS], $result);

        $orders = RetailcrmExportOrdersHelper::getOrders([$orderIdCMS]);

        $this->assertArrayHasKey('orders', $orders);
        $this->assertArrayHasKey('pagination', $orders);
        $this->assertNotEmpty($orders['orders'][0]);

        $exportResult = $orders['orders'][0];

        $this->assertEquals($exportResult['id_order'], $orderIdCMS);

        if (null === $errors && null === $errorMsg) {
            $this->assertEquals($exportResult['id_order_crm'], $orderIdCRM);
            $this->assertNull($exportResult['errors']);
        } else {
            $this->assertNotNull($exportResult['errors']);
            $exportResultErrors = json_decode($exportResult['errors'], true);
            $this->assertNotNull($exportResultErrors);

            if (null == $errors) {
                $this->assertCount(1, $exportResultErrors);
                $this->assertEquals($errorMsg, $exportResultErrors[0]);
            } else {
                $this->assertCount(count($errors), $exportResultErrors);
                $this->assertEquals($errors, $exportResultErrors);
            }
        }
    }

    public function testRequestUploadSuccessful()
    {
        $result = true;
        $uploadRequest = [
            1 => [
                'externalId' => 1,
            ],
            2 => [
                'externalId' => 2,
            ],
            3 => [
                'externalId' => 3,
            ],
        ];
        $uploadedOrders = [
            1 => [
                'externalId' => 1,
                'id' => 111,
            ],
            2 => [
                'externalId' => 2,
                'id' => 222,
            ],
            3 => [
                'externalId' => 3,
                'id' => 333,
            ],
        ];
        $errors = null;
        $ordersList = null;

        $this->apiClientMock->expects($this->any())->method('ordersUpload')->willReturn(new RetailcrmApiResponse(
            $result ? 200 : 400,
            json_encode([
                'success' => $result,
                'uploadedOrders' => $uploadedOrders,
                'errors' => $errors,
            ])
        ));

        if (null !== $ordersList) {
            $this->apiClientMock->expects($this->any())->method('ordersList')->willReturn(new RetailcrmApiResponse(
                200,
                json_encode([
                    'success' => true,
                    'orders' => $ordersList,
                ])
            ));
        }
        $this->makeRequestAndCheckResponse('ordersUpload', $uploadRequest, $result);

        $orders = RetailcrmExportOrdersHelper::getOrders(array_keys($uploadRequest));

        $this->assertArrayHasKey('orders', $orders);
        $this->assertArrayHasKey('pagination', $orders);

        foreach ($orders['orders'] as $exportResult) {
            $this->assertNotEmpty($exportResult);
            $this->assertArrayHasKey($exportResult['id_order'], $uploadedOrders);
            $orderFromCRM = $uploadedOrders[$exportResult['id_order']];

            $this->assertEquals($exportResult['id_order'], $orderFromCRM['externalId']);
            $this->assertEquals($exportResult['id_order_crm'], $orderFromCRM['id']);
            $this->assertNull($exportResult['errors']);
        }
    }

    public function testRequestUploadUnsuccessful()
    {
        $result = false;
        $uploadRequest = [
            1 => [
                'externalId' => 1,
            ],
            2 => [
                'externalId' => 2,
            ],
            3 => [
                'externalId' => 3,
            ],
        ];
        $uploadedOrders = null;
        $errors = [
            1 => 'Order with externalId=1 already exists.',
            2 => 'Test error #2',
        ];
        $ordersList = [
            3 => [
                'externalId' => 3,
                'id' => 333,
            ],
        ];

        $this->apiClientMock->expects($this->any())->method('ordersUpload')->willReturn(new RetailcrmApiResponse(
            $result ? 200 : 400,
            json_encode([
                'success' => $result,
                'uploadedOrders' => $uploadedOrders,
                'errors' => $errors,
            ])
        ));

        if (null !== $ordersList) {
            $this->apiClientMock->expects($this->any())->method('ordersList')->willReturn(new RetailcrmApiResponse(
                200,
                json_encode([
                    'success' => true,
                    'orders' => $ordersList,
                ])
            ));
        }
        $this->makeRequestAndCheckResponse('ordersUpload', $uploadRequest, $result);

        $orders = RetailcrmExportOrdersHelper::getOrders(array_keys($uploadRequest));

        $this->assertArrayHasKey('orders', $orders);
        $this->assertArrayHasKey('pagination', $orders);
        $this->assertCount(count($uploadRequest), $orders['orders']);

        foreach ($orders['orders'] as $exportResult) {
            $this->assertNotEmpty($exportResult);

            if (null !== $uploadedOrders && array_key_exists($exportResult['id_order'], $uploadedOrders)) {
                $orderFromCRM = $uploadedOrders[$exportResult['id_order']];

                $this->assertEquals($exportResult['id_order'], $orderFromCRM['externalId']);
                $this->assertEquals($exportResult['id_order_crm'], $orderFromCRM['id']);
                $this->assertNull($exportResult['errors']);
            }

            if (null === $errors) {
                continue;
            }

            if (null !== $ordersList && array_key_exists($exportResult['id_order'], $ordersList)) {
                $orderFromCRM = $ordersList[$exportResult['id_order']];

                $this->assertEquals($exportResult['id_order'], $orderFromCRM['externalId']);
                $this->assertEquals($exportResult['id_order_crm'], $orderFromCRM['id']);
                $this->assertNull($exportResult['errors']);
            }

            if (array_key_exists($exportResult['id_order'], $errors)) {
                $error = $errors[$exportResult['id_order']];

                if (false === strpos($error, 'Order with externalId')) {
                    $exportResultErrors = json_decode($exportResult['errors'], true);
                    $this->assertNotNull($exportResultErrors);

                    $this->assertNull($exportResult['id_order_crm']);
                    $this->assertNotNull($exportResult['errors']);
                    $this->assertCount(1, $exportResultErrors);
                    $this->assertEquals('Unknown error', $exportResultErrors[0]);
                } else {
                    $this->assertNull($exportResult['id_order_crm']);
                    $this->assertNull($exportResult['errors']);
                }
            }
        }
    }

    /**
     * @param string $method
     * @param array $params
     * @param bool $result
     */
    private function makeRequestAndCheckResponse($method, $params, $result)
    {
        /** @var RetailcrmApiResponse $response */
        $response = $this->apiMock->$method($params);

        $this->assertInstanceOf(RetailcrmApiResponse::class, $response);
        $this->assertEquals($response->isSuccessful(), $result);
    }
}
