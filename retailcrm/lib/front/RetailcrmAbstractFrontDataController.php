<?php
/**
 * MIT License
 *
 * Copyright (c) 2019 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @copyright 2007-2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
abstract class RetailcrmAbstractFrontDataController
{
    /**
     * @var string
     */
    protected $method;

    /**
     * @var \Context|\ContextCore
     */
    protected $context;

    /**
     * RetailcrmAbstractFrontDataController constructor.
     *
     * @param string $method
     * @param \Context|\ContextCore  $context
     */
    public function __construct($method, $context)
    {
        $this->method = $method;
        $this->context = $context;
    }

    /**
     * Not found route
     */
    protected function notFound()
    {
        RetailcrmTools::http_response_code(404);
        die;
    }

    /**
     * Encodes json and responds with it. Responds with valid JSON in case of marshaling error.
     *
     * @param $data
     */
    protected function json($data)
    {
        $response = json_encode($data);

        if (json_last_error() != JSON_ERROR_NONE) {
            $this->respond('{"error":"cannot assemble response"}');
            die;
        }

        header('Content-Type: application/json');
        $this->respond($response);
    }

    /**
     * Respond with provided response
     *
     * @param string $response
     */
    protected function respond($response)
    {
        echo $response;
    }

    /**
     * Executes provided method
     */
    public function execute()
    {
        $internal = array_merge($this->getInternalMethods(), array('notFound', 'json'));

        if (method_exists($this, $this->method) && !in_array($this->method, $internal)) {
            $this->{$this->method}();
        } else {
            $this->notFound();
        }
    }

    /**
     * Returns internal, protected methods
     *
     * @return array
     */
    protected function getInternalMethods()
    {
        return array();
    }
}
