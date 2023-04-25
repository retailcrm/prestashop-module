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

class RetailcrmApiPaginatedRequest extends RetailcrmApiRequest
{
    /**
     * @var array
     */
    protected $params;

    /**
     * @var string
     */
    protected $dataKey;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var int|null
     */
    protected $pageLimit;

    /**
     * RetailcrmApiPaginatedRequest constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Sets method params for API client (leave `{{page}}` instead of page and `{{limit}}` instead of limit)
     *
     * @param array $params
     *
     * @return RetailcrmApiPaginatedRequest
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Sets dataKey (key with data in response)
     *
     * @param string $dataKey
     *
     * @return RetailcrmApiPaginatedRequest
     */
    public function setDataKey($dataKey)
    {
        $this->dataKey = $dataKey;

        return $this;
    }

    /**
     * Sets record limit per request
     *
     * @param int $limit
     *
     * @return RetailcrmApiPaginatedRequest
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Sets page limit per call
     *
     * @param int $pageLimit
     *
     * @return RetailcrmApiPaginatedRequest
     */
    public function setPageLimit($pageLimit)
    {
        $this->pageLimit = $pageLimit;

        return $this;
    }

    /**
     * Executes request
     *
     * @return $this
     */
    public function execute()
    {
        $this->data = [];
        $response = true;
        $page = 1;

        do {
            $response = call_user_func_array([$this->api, $this->method], $this->buildParams($this->params, $page));

            if ($response instanceof RetailcrmApiResponse && $response->offsetExists($this->dataKey)) {
                foreach ($response[$this->dataKey] as $data) {
                    $this->data[] = $data;
                }

                $page = $this->getNextPageNumber($page, $response);
            }

            if (null !== $this->pageLimit && $page > $this->pageLimit) {
                break;
            }

            time_nanosleep(0, 300000000);
        } while ($response && (isset($response['pagination'])
            && $response['pagination']['currentPage'] < $response['pagination']['totalPageCount']));

        return $this;
    }

    /**
     * Reset paginated request
     *
     * @return $this
     */
    public function reset()
    {
        $this->method = '';
        $this->limit = 100;
        $this->pageLimit = null;
        $this->data = [];

        return $this;
    }

    /**
     * buildParams for the request
     *
     * @param array $placeholderParams
     * @param int $currentPage
     *
     * @return mixed
     */
    protected function buildParams($placeholderParams, $currentPage)
    {
        foreach ($placeholderParams as $key => $param) {
            // Set page and limit for customersCorporateAddresses method
            if ('{{page}}' == $param) {
                $placeholderParams[$key] = $currentPage;
            }

            if ('{{limit}}' == $param) {
                $placeholderParams[$key] = $this->limit;
            }
        }

        return $placeholderParams;
    }

    /**
     * Get the next page number from the response. Use for customersCorporateAddresses method
     *
     * @param int $page
     * @param RetailcrmApiResponse $response
     *
     * @return int
     */
    protected function getNextPageNumber($page, $response)
    {
        return $response['pagination']['currentPage'] + 1;
    }
}
