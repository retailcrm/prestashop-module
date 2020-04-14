<?php
/**
 * MIT License
 *
 * Copyright (c) 2020 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmApiPaginatedRequest
{
    /**
     * @var \RetailcrmProxy|\RetailcrmApiClientV5
     */
    private $api;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $params;

    /**
     * @var string
     */
    private $dataKey;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var array
     */
    private $data;

    /**
     * RetailcrmApiPaginatedRequest constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Sets retailCRM api client to request
     *
     * @param \RetailcrmApiClientV5|\RetailcrmProxy $api
     *
     * @return RetailcrmApiPaginatedRequest
     */
    public function setApi($api)
    {
        $this->api = $api;
        return $this;
    }

    /**
     * Sets API client method to request
     *
     * @param string $method
     *
     * @return RetailcrmApiPaginatedRequest
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
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
     * Executes request
     *
     * @return $this
     */
    public function execute()
    {
        $this->data = array();
        $response = true;
        $page = 1;

        do {
            $response = call_user_func_array(
                array($this->api, $this->method),
                $this->buildParams($this->params, $page)
            );

            if ($response instanceof RetailcrmApiResponse && $response->offsetExists($this->dataKey)) {
                $this->data = array_merge($response[$this->dataKey]);
                $page = $response['pagination']['currentPage'] + 1;
            }

            time_nanosleep(0, 300000000);
        } while ($response && (isset($response['pagination'])
            && $response['pagination']['currentPage'] < $response['pagination']['totalPageCount']));

        return $this;
    }

    /**
     * Returns data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
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
        $this->data = array();

        return $this;
    }

    /**
     * buildParams
     *
     * @param array $placeholderParams
     * @param int   $currentPage
     *
     * @return mixed
     */
    private function buildParams($placeholderParams, $currentPage)
    {
        foreach ($placeholderParams as $key => $param) {
            if ($param == '{{page}}') {
                $placeholderParams[$key] = $currentPage;
            }

            if ($param == '{{limit}}') {
                $placeholderParams[$key] = $this->limit;
            }
        }

        return $placeholderParams;
    }
}
