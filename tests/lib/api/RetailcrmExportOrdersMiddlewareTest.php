<?php
/**
 * MIT License
 *
 * Copyright (c) 2021 DIGITAL RETAIL TECHNOLOGIES SL
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

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

        $orders = $this->getOrders([$orderIdCMS]);

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

    public function dataRequestsUpload()
    {
        return [
            [
                'result' => true,
                'uploadRequest' => [
                    1 => [
                        'externalId' => 1,
                    ],
                    2 => [
                        'externalId' => 2,
                    ],
                    3 => [
                        'externalId' => 3,
                    ],
                ],
                'uploadedOrders' => [
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
                ],
                'errors' => null,
            ],
            [
                'result' => false,
                'uploadRequest' => [
                    1 => [
                        'externalId' => 1,
                    ],
                    2 => [
                        'externalId' => 2,
                    ],
                    3 => [
                        'externalId' => 3,
                    ],
                ],
                'uploadedOrders' => null,
                'errors' => [
                    1 => 'Order with externalId=1 already exists.',
                    2 => 'Order with externalId=2 already exists.',
                    3 => 'Order with externalId=3 already exists.',
                ],
            ],
            [
                'result' => false,
                'uploadRequest' => [
                    1 => [
                        'externalId' => 1,
                    ],
                    2 => [
                        'externalId' => 2,
                    ],
                    3 => [
                        'externalId' => 3,
                    ],
                ],
                'uploadedOrders' => [
                    1 => [
                        'externalId' => 1,
                        'id' => 111,
                    ],
                    3 => [
                        'externalId' => 3,
                        'id' => 333,
                    ],
                ],
                'errors' => [
                    1 => 'Order with externalId=1 already exists.',
                    2 => 'Test error #2',
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataRequestsUpload
     */
    public function testRequestUploadUnsuccessful($result, $uploadRequest, $uploadedOrders, $errors)
    {
        $this->apiClientMock->expects($this->any())->method('ordersUpload')->willReturn(new RetailcrmApiResponse(
            $result ? 200 : 400,
            json_encode([
                'success' => $result,
                'uploadedOrders' => $result ? $uploadedOrders : null,
                'errors' => $errors,
            ])
        ));

        if (!$result) {
            $this->apiClientMock->expects($this->any())->method('ordersList')->willReturn(new RetailcrmApiResponse(
                200,
                json_encode([
                    'success' => true,
                    'orders' => $uploadedOrders,
                ])
            ));
        }

        $this->makeRequestAndCheckResponse('ordersUpload', $uploadRequest, $result);

        $orders = $this->getOrders(array_keys($uploadRequest));

        foreach ($orders['orders'] as $order) {
            $this->assertNotEmpty($order);

            $orderFromCRM = null;
            if (null !== $uploadedOrders && array_key_exists($order['id_order'], $uploadedOrders)) {
                $orderFromCRM = $uploadedOrders[$order['id_order']];

                $this->assertEquals($order['id_order'], $orderFromCRM['externalId']);
                $this->assertEquals($order['id_order_crm'], $orderFromCRM['id']);
                $this->assertNull($order['errors']);

                continue;
            }

            if (null !== $errors && array_key_exists($order['id_order'], $errors)) {
                if (false !== strpos($errors[$order['id_order']], 'Order with externalId')) {
                    continue;
                }

                $exportResultErrors = json_decode($order['errors'], true);
                $this->assertNotNull($exportResultErrors);

                $this->assertNull($order['id_order_crm']);
                $this->assertNotNull($order['errors']);
                $this->assertCount(1, $exportResultErrors);
                $this->assertEquals('Unknown error', $exportResultErrors[0]);
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

    /**
     * @param $ordersIds
     *
     * @return array
     */
    private function getOrders($ordersIds)
    {
        $orders = RetailcrmExportOrdersHelper::getOrders($ordersIds);

        $this->assertArrayHasKey('orders', $orders);
        $this->assertArrayHasKey('pagination', $orders);
        $this->assertCount(count($ordersIds), $orders['orders']);

        return $orders;
    }
}
