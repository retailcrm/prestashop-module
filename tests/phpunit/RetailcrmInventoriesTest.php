<?php

class RetailcrmInventoriesTest extends RetailcrmTestCase
{
    private $apiMock;
    private $product;
    
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
        $this->product = $data[1][0];

        $this->setConfig();
    }

    private function getResponseData()
    {
        return array(
            'true' => array(
                'success' => true,
                'pagination' => array(
                    'limit' => 250,
                    'totalCount' => 1,
                    'currentPage' => 1,
                    'totalPageCount' => 1
                ),
                'offers' => array(
                    array(
                        'id' => 1,
                        'xmlId' => 'xmlId',
                        'quantity' => 10
                    )
                )
            ),
            'false' => array(
                'success' => false,
                'errorMsg' => 'Forbidden'
            )
        );
    }
    /**
     * @param $retailcrm
     * @param $response
     *
     * @dataProvider dataProviderLoadStocks
     */
    public function test_load_stocks($apiVersion, $response)
    {
        if ($response['success'] == true) {
            $response['offers'][0]['externalId'] = $this->product['id'];
            $this->responseMock->expects($this->any())
                ->method('isSuccessful')
                ->willReturn(true);
        } elseif ($response['success'] == false) {
            $this->responseMock->expects($this->any())
                ->method('isSuccessful')
                ->willReturn(false);
        }
        
        $this->responseMock->setResponse($response);
        
        if ($retailcrm) {
            $retailcrm->expects($this->any())
                ->method('storeInventories')
                ->willReturn($this->responseMock);
        }
        
        RetailcrmInventories::$apiVersion = $apiVersion;
        RetailcrmInventories::$api = $this->apiMock;
        
        RetailcrmInventories::load_stocks();
        
    }

    public function dataProviderLoadStocks()
    {
        $this->setUp();

        $response = $this->getResponseData();

        return array(
            array(
                
                'response' => $response['true'],
                'api_version' => 4
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
}