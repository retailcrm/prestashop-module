<?php

class RetailcrmApiPaginatedRequestTest extends RetailcrmTestCase
{
    private $apiMock;

    public function setUp()
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
