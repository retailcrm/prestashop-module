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

class RetailcrmApiPaginatedRequestTest extends RetailcrmTestCase
{
    private $apiMock;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getMockBuilder('RetailcrmProxy')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'ordersHistory',
                ]
            )
            ->getMock()
        ;
    }

    public function getPageLimits()
    {
        return [
            'Big history' => [2, 3, 12, 6],
            'Equal history' => [2, 3, 6, 6],
            'Small history' => [2, 3, 3, 3],
        ];
    }

    /**
     * @dataProvider getPageLimits
     */
    public function testPageLimit($limit, $pageLimit, $totalCount, $expectedTotalCount)
    {
        $this->apiMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturnOnConsecutiveCalls(...$this->getHistory($limit, $totalCount))
        ;

        $request = new RetailcrmApiPaginatedRequest();
        $history = $request
            ->setApi($this->apiMock)
            ->setMethod('ordersHistory')
            ->setParams([[], '{{page}}'])
            ->setDataKey('history')
            ->setLimit($limit)
            ->setPageLimit($pageLimit)
            ->execute()
            ->getData()
        ;

        $lastId = end($history)['id'];

        $this->assertEquals($expectedTotalCount, count($history));
        $this->assertEquals($expectedTotalCount, $lastId);
    }

    private function getHistory($limit, $totalCount)
    {
        $totalPageCount = ceil($totalCount / $limit);
        $currentPage = 0;

        while ($currentPage < $totalPageCount) {
            $history = [];

            $from = ($limit * $currentPage) + 1;
            $to = ($limit * $currentPage) + $limit;
            if ($to > $totalCount) {
                $to = $totalCount;
            }

            foreach (range($from, $to) as $historyId) {
                $history[] = [
                    'id' => $historyId,
                ];
            }
            ++$currentPage;

            yield new RetailcrmApiResponse(
                '200',
                json_encode(
                    [
                        'success' => true,
                        'history' => $history,
                        'pagination' => [
                            'limit' => $limit,
                            'totalCount' => $totalCount,
                            'currentPage' => $currentPage,
                            'totalPageCount' => $totalPageCount,
                        ],
                    ]
                )
            );
        }
    }
}
