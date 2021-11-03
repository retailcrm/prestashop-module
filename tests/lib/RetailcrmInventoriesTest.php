<?php

class RetailcrmInventoriesTest extends RetailcrmTestCase
{
    private $apiMock;
    private $product1;
    private $product2;

    const PRODUCT1_QUANTITY = 10;
    const PRODUCT2_QUANTITY = 15;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getMockBuilder('RetailcrmProxy')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'storeInventories',
                ]
            )
            ->getMock()
        ;

        $catalog = new RetailcrmCatalog();
        $data = $catalog->getData();

        $this->product1 = $data[1]->current();
        $data[1]->next();
        $this->product2 = $data[1]->current();
    }

    /**
     * @param $response
     *
     * @dataProvider dataProviderLoadStocks
     */
    public function testLoadStocks($response)
    {
        if (true == $response['success']) {
            $this->apiMock->expects($this->any())
                ->method('storeInventories')
                ->willReturn(
                    new RetailcrmApiResponse(
                        '200',
                        json_encode(
                            $this->getApiInventories()
                        )
                    )
                )
            ;
        } else {
            $this->apiMock->expects($this->any())
                ->method('storeInventories')
                ->willReturn($response)
            ;
        }

        RetailcrmInventories::$api = $this->apiMock;

        RetailcrmInventories::loadStocks();

        $product1Id = explode('#', $this->product1['id']);
        $product2Id = explode('#', $this->product2['id']);

        if (isset($product1Id[1])) {
            $prod1Quantity = StockAvailable::getQuantityAvailableByProduct($product1Id[0], $product1Id[1]);
        } else {
            $prod1Quantity = StockAvailable::getQuantityAvailableByProduct($product1Id[0], 0);
        }

        if (isset($product2Id[1])) {
            $prod2Quantity = StockAvailable::getQuantityAvailableByProduct($product2Id[0], $product2Id[1]);
        } else {
            $prod2Quantity = StockAvailable::getQuantityAvailableByProduct($product2Id[0], 0);
        }

        $this->assertEquals(self::PRODUCT1_QUANTITY, $prod1Quantity);
        $this->assertEquals(self::PRODUCT2_QUANTITY, $prod2Quantity);
    }

    public function dataProviderLoadStocks()
    {
        $response = $this->getResponseData();

        return [
            [
                'response' => $response['true'],
            ],
            [
                'response' => $response['false'],
            ],
        ];
    }

    private function getResponseData()
    {
        return [
            'true' => $this->getApiInventories(),
            'false' => false,
        ];
    }

    private function getApiInventories()
    {
        return [
            'success' => true,
            'pagination' => [
                'limit' => 250,
                'totalCount' => 1,
                'currentPage' => 1,
                'totalPageCount' => 1,
            ],
            'offers' => [
                [
                    'externalId' => $this->product1['id'],
                    'xmlId' => 'xmlId',
                    'quantity' => self::PRODUCT1_QUANTITY,
                ],
                [
                    'externalId' => $this->product2['id'],
                    'xmlId' => 'xmlId',
                    'quantity' => self::PRODUCT2_QUANTITY,
                ],
            ],
        ];
    }
}
