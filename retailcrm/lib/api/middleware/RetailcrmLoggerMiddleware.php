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

class RetailcrmLoggerMiddleware implements RetailcrmMiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(RetailcrmApiRequest $request, callable $next = null)
    {
        $method = $request->getMethod();

        if (null !== $method) {
            RetailcrmLogger::writeDebug($method, print_r($request->getData(), true));
        }

        /** @var RetailcrmApiResponse $response */
        $response = $next($request);

        if (null === $response) {
            RetailcrmLogger::writeCaller($method, 'Response is null');

            return $response;
        }

        if ($response->isSuccessful()) {
            // Don't print long lists in debug logs (errors while calling this will be easy to detect anyway)
            if (in_array($method, ['statusesList', 'paymentTypesList', 'deliveryTypesList'])) {
                RetailcrmLogger::writeDebug($method, '[request was successful, but response is omitted]');
            } else {
                RetailcrmLogger::writeDebug($method, $response->getRawResponse());
            }
        } else {
            if ($response->offsetExists('errorMsg')) {
                RetailcrmLogger::writeCaller($method, $response['errorMsg']);
            }

            if ($response->offsetExists('errors')) {
                RetailcrmApiErrors::set($response['errors'], $response->getStatusCode());
                $error = RetailcrmLogger::reduceErrors($response['errors']);
                RetailcrmLogger::writeNoCaller($error);
            }
        }

        return $response;
    }
}
