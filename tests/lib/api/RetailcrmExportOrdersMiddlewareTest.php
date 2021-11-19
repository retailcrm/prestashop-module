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
                'ordersGet',
                'ordersUpload',
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

        /** @var RetailcrmApiResponse $response */
        $response = $this->apiMock->$method(['externalId' => $orderIdCMS]);

        $this->assertInstanceOf(RetailcrmApiResponse::class, $response);
        $this->assertEquals($response->isSuccessful(), $result);

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
}
