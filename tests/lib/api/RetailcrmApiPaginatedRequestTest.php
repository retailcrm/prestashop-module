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
                array(
                    'ordersHistory',
                )
            )
            ->getMock();
    }

    public function getPageLimits()
    {
        return array(
            'Big history' => array(2, 3, 12, 6),
            'Equal history' => array(2, 3, 6, 6),
            'Small history' => array(2, 3, 3, 3),
        );
    }

    /**
     * @dataProvider getPageLimits
     */
    public function testPageLimit($limit, $pageLimit, $totalCount, $expectedTotalCount)
    {
        $this->apiMock->expects($this->any())
            ->method('ordersHistory')
            ->willReturnOnConsecutiveCalls(...$this->getHistory($limit, $totalCount));

        $request = new RetailcrmApiPaginatedRequest();
        $history = $request
            ->setApi($this->apiMock)
            ->setMethod('ordersHistory')
            ->setParams(array(array(), '{{page}}'))
            ->setDataKey('history')
            ->setLimit($limit)
            ->setPageLimit($pageLimit)
            ->execute()
            ->getData();

        $lastId = end($history)['id'];

        $this->assertEquals($expectedTotalCount, count($history));
        $this->assertEquals($expectedTotalCount, $lastId);
    }

    private function getHistory($limit, $totalCount)
    {
        $totalPageCount = ceil($totalCount / $limit);
        $currentPage = 0;

        while ($currentPage < $totalPageCount) {
            $history = array();

            $from = ($limit * $currentPage) + 1;
            $to = ($limit * $currentPage) + $limit;
            if ($to > $totalCount) {
                $to = $totalCount;
            }

            foreach (range($from, $to) as $historyId) {
                $history[] = array(
                    'id' => $historyId,
                );
            }
            $currentPage++;

            yield new RetailcrmApiResponse(
                '200',
                json_encode(
                    array(
                        'success' => true,
                        'history' => $history,
                        'pagination' => array(
                            'limit' => $limit,
                            'totalCount' => $totalCount,
                            'currentPage' => $currentPage,
                            'totalPageCount' => $totalPageCount
                        )
                    )
                )
            );
        }

    }
}