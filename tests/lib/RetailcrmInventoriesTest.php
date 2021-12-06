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
