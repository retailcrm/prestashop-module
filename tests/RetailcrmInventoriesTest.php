<?php

class RetailcrmInventoriesTest extends RetailcrmTestCase
{
    private $apiMock;
    private $product1;
    private $product2;
    
    const PRODUCT1_QUANTITY = 10;
    const PRODUCT2_QUANTITY = 15;

    public function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getMockBuilder('RetailcrmProxy')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'storeInventories'
                )
            )
            ->getMock();

        $catalog = new RetailcrmCatalog();
        $data = $catalog->getData();

        $this->product1 = $data[1][0];
        $this->product2 = $data[1][1];
    }

    /**
     * @param $apiVersion
     * @param $response
     *
     * @dataProvider dataProviderLoadStocks
     */
    public function testLoadStocks($apiVersion, $response)
    {
        if ($response['success'] == true) {
            $this->apiMock->expects($this->any())
                ->method('storeInventories')
                ->willReturn(
                    new RetailcrmApiResponse(
                        '200',
                        json_encode(
                            $this->getApiInventories()
                        )
                    )
                );
        } else {
           $this->apiMock->expects($this->any())
            ->method('storeInventories')
            ->willReturn($response);
        }

        RetailcrmInventories::$api = $this->apiMock;

        RetailcrmInventories::loadStocks();

        $product1Id = explode('#', $this->product1['id']);
        $product2Id = explode('#', $this->product2['id']);
        
        if (isset($product1Id[1])){
            $prod1Quantity = StockAvailable::getQuantityAvailableByProduct($product1Id[0], $product1Id[1]);
        } else {
            $prod1Quantity = StockAvailable::getQuantityAvailableByProduct($product1Id[0], 0);
        }
        
        if (isset($product2Id[1])){
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

        return array(
            array(
                'api_version' => 4,
                'response' => $response['true'],
            ),
            array(
                'api_version' => 5,
                'response' => $response['true']
            ),
            array(
                'api_version' => 4,
                'response' => $response['false']
            ),
            array(
                'api_version' => 5,
                'response' => $response['false']
            )
        );
    }

    private function getResponseData()
    {
        return array(
            'true' => $this->getApiInventories(),
            'false' => false
        );
    }

    private function getApiInventories()
    {
        return array( 
            "success" => true,
            "pagination"=> array(
                "limit"=> 250,
                "totalCount"=> 1,
                "currentPage"=> 1,
                "totalPageCount"=> 1
            ),
            "offers" => array(
                array(
                    'externalId' => $this->product1['id'],
                    'xmlId' => 'xmlId',
                    'quantity' => self::PRODUCT1_QUANTITY,
                ),
                array(
                    'externalId' => $this->product2['id'],
                    'xmlId' => 'xmlId',
                    'quantity' => self::PRODUCT2_QUANTITY,
                )
            )
        );
    }
}
