<?php


class RetailcrmExceptionMiddlewareTest extends RetailcrmTestCase
{
    private $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = RetailcrmTools::getApiClient();
    }

    public function getRequests()
    {
        return [
            [
                'method' => 'ordersGet',
                'params' => [406, 'idd'],
                'errorMsg' => 'Value "idd" for "by" param is not valid. Allowed values are externalId, id.'
            ],
            [
                'method' => 'ordersEdit',
                'params' => [['id' => 406], 'idd'],
                'errorMsg' => 'Value "idd" for "by" param is not valid. Allowed values are externalId, id.'
            ],
            [
                'method' => 'ordersEdit',
                'params' => [['id' => 406], 'externalId'],
                'errorMsg' => 'Order array must contain the "externalId" parameter.'
            ],
            [
                'method' => 'ordersFixExternalIds',
                'params' => [[]],
                'errorMsg' => 'Method parameter must contains at least one IDs pair'
            ],
            [
                'method' => 'ordersCreate',
                'params' => [[]],
                'errorMsg' => 'Parameter `order` must contains a data'
            ],
            [
                'method' => 'ordersUpload',
                'params' => [[]],
                'errorMsg' => 'Parameter `orders` must contains array of the orders'
            ],
            [
                'method' => 'ordersPaymentCreate',
                'params' => [[]],
                'errorMsg' => 'Parameter `payment` must contains a data'
            ],
            [
                'method' => 'ordersPaymentEdit',
                'params' => [['id' => 406], 'idd'],
                'errorMsg' => 'Value "idd" for "by" param is not valid. Allowed values are externalId, id.'
            ],
            [
                'method' => 'ordersPaymentEdit',
                'params' => [['id' => 406], 'externalId'],
                'errorMsg' => 'Order array must contain the "externalId" parameter.'
            ],
        ];
    }

    /**
     * @dataProvider getRequests
     */
    public function testRequest($method, $params, $errorMsg)
    {
        /** @var RetailcrmApiResponse $response */
        $response = call_user_func_array([$this->api, $method], $params);

        $this->assertInstanceOf(RetailcrmApiResponse::class, $response);
        $this->assertFalse($response->isSuccessful());
        $this->assertStringStartsWith('Internal error: ', $response['errorMsg']);
        $this->assertStringEndsWith($errorMsg, $response['errorMsg']);
    }
}
