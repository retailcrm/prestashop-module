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

class RetailcrmExceptionMiddlewareTest extends RetailcrmTestCase
{
    private $api;

    protected function setUp()
    {
        parent::setUp();

        $this->api = RetailcrmTools::getApiClient();
        $this->apiMock = $this->getApiMock(
            [
                'ordersGet',
                'ordersEdit',
                'ordersPaymentEdit',
                'ordersCreate',
                'ordersUpload',
            ]
        );
    }

    public function getRequestsBadParams()
    {
        return [
            [
                'method' => 'ordersGet',
                'params' => [406, 'idd'],
                'errorMsg' => 'Value "idd" for "by" param is not valid. Allowed values are externalId, id.',
            ],
            [
                'method' => 'ordersEdit',
                'params' => [['id' => 406], 'idd'],
                'errorMsg' => 'Value "idd" for "by" param is not valid. Allowed values are externalId, id.',
            ],
            [
                'method' => 'ordersEdit',
                'params' => [['id' => 406], 'externalId'],
                'errorMsg' => 'Order array must contain the "externalId" parameter.',
            ],
            [
                'method' => 'ordersFixExternalIds',
                'params' => [[]],
                'errorMsg' => 'Method parameter must contains at least one IDs pair',
            ],
            [
                'method' => 'ordersCreate',
                'params' => [[]],
                'errorMsg' => 'Parameter `order` must contains a data',
            ],
            [
                'method' => 'ordersUpload',
                'params' => [[]],
                'errorMsg' => 'Parameter `orders` must contains array of the orders',
            ],
            [
                'method' => 'ordersPaymentCreate',
                'params' => [[]],
                'errorMsg' => 'Parameter `payment` must contains a data',
            ],
            [
                'method' => 'ordersPaymentEdit',
                'params' => [['id' => 406], 'idd'],
                'errorMsg' => 'Value "idd" for "by" param is not valid. Allowed values are externalId, id.',
            ],
            [
                'method' => 'ordersPaymentEdit',
                'params' => [['id' => 406], 'externalId'],
                'errorMsg' => 'Order array must contain the "externalId" parameter.',
            ],
        ];
    }

    /**
     * @dataProvider getRequestsBadParams
     */
    public function testRequestBadParams($method, $params, $errorMsg)
    {
        /** @var RetailcrmApiResponse $response */
        $response = call_user_func_array([$this->api, $method], $params);

        $this->checkResponse($response, $errorMsg);
    }

    public function getRequestsException()
    {
        return [
            [
                'method' => 'ordersGet',
                'params' => [406, 'id'],
            ],
            [
                'method' => 'ordersEdit',
                'params' => [['id' => 406], 'id'],
            ],
            [
                'method' => 'ordersPaymentEdit',
                'params' => [['id' => 406], 'id'],
            ],
            [
                'method' => 'ordersCreate',
                'params' => [['id' => 407]],
            ],
            [
                'method' => 'ordersUpload',
                'params' => [[['externalId' => 1]]],
            ],
        ];
    }

    /**
     * @dataProvider getRequestsException
     */
    public function testRequestException($method, $params)
    {
        $errorMsg = 'Test exception ' . md5(json_encode([$method, $params]));

        $this->makeRequestException($method, $params, $errorMsg, function () use ($errorMsg) {
            throw new Exception($errorMsg);
        });

        if (class_exists('Error')) {
            $this->makeRequestException($method, $params, $errorMsg, function () use ($errorMsg) {
                throw new Error($errorMsg);
            });
        }
    }

    private function makeRequestException($method, $params, $errorMsg, $return)
    {
        $this->apiClientMock->expects($this->any())->method($method)->willReturnCallback($return);

        /** @var RetailcrmApiResponse $response */
        $response = call_user_func_array([$this->apiMock, $method], $params);

        $this->checkResponse($response, $errorMsg);
    }

    private function checkResponse(RetailcrmApiResponse $response, $errorMsg)
    {
        $this->assertInstanceOf(RetailcrmApiResponse::class, $response);
        $this->assertFalse($response->isSuccessful());
        $this->assertStringStartsWith('Internal error: ', $response['errorMsg']);
        $this->assertStringEndsWith($errorMsg, $response['errorMsg']);
    }
}
