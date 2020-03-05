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
class RetailcrmProxy
{
    private $api;
    private $log;

    public function __construct($url, $key, $log)
    {
        $this->api = new RetailcrmApiClientV5($url, $key);
        $this->log = $log;
    }

    /**
     * Reduces error array into string
     *
     * @param $errors
     *
     * @return false|string
     */
    private static function reduceErrors($errors)
    {
        $reduced = '';

        if (is_array($errors)) {
            foreach ($errors as $key => $error) {
                $reduced .= sprintf('%s => %s\n', $key, $error);
            }
        }

        return $reduced;
    }

    public function __call($method, $arguments)
    {   
        $date = date('Y-m-d H:i:s');
        try {
            RetailcrmLogger::writeDebug($method, print_r($arguments, true));
            $response = call_user_func_array(array($this->api, $method), $arguments);

            if (!($response instanceof RetailcrmApiResponse)) {
                RetailcrmLogger::writeDebug($method, $response);
                return $response;
            }

            if (!$response->isSuccessful()) {
                RetailcrmLogger::writeCaller($method, $response->getErrorMsg());

                if (isset($response['errors'])) {
                    RetailcrmApiErrors::set($response['errors'], $response->getStatusCode());
                    $error = static::reduceErrors($response['errors']);
                    RetailcrmLogger::writeNoCaller($error);
                }

                $response = false;
            } else {
                // Don't print long lists in debug logs (errors while calling this will be easy to detect anyway)
                if (in_array($method, array('statusesList', 'paymentTypesList', 'deliveryTypesList'))) {
                    RetailcrmLogger::writeDebug($method, '[request was successful, but response is omitted]');
                } else {
                    RetailcrmLogger::writeDebug($method, $response->getRawResponse());
                }
            }

            return $response;
        } catch (CurlException $e) {
            RetailcrmLogger::writeCaller(get_class($this->api).'::'.$method, $e->getMessage());
            return false;
        } catch (InvalidJsonException $e) {
            RetailcrmLogger::writeCaller(get_class($this->api).'::'.$method, $e->getMessage());
            return false;
        }
    }
}
