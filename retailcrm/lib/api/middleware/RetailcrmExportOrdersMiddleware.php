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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */


class RetailcrmExportOrdersMiddleware implements RetailcrmMiddlewareInterface
{
    /**
     * @inheritDoc
     * @throws Exception
     */
    public function __invoke(RetailcrmApiRequest $request, callable $next = null)
    {
        if (!in_array($request->getMethod(), [
            'ordersUpload',
            'ordersCreate',
            'ordersEdit',
        ])) {
            return $next($request);
        }

        if ($request->getMethod() === 'ordersUpload') {
            /** @var RetailcrmApiResponse $response */
            $response = $next($request);

            if ($response->isSuccessful() && $response->offsetExists('uploadedOrders')) {
                foreach ($response['uploadedOrders'] as $uploadedOrder) {
                    RetailcrmExportOrdersHelper::updateExportState($uploadedOrder['externalId'], $uploadedOrder['id']);
                }
            } else {
                $uploadedOrders = [];
                if ($response->offsetExists('errors')) {
                    foreach ($response['errors'] as $error) {
                        if (preg_match('/Order with externalId=(\d+) already exists\./i', $error, $matches)) {
                            RetailcrmExportOrdersHelper::updateExportState(intval($matches[1]));
                            $uploadedOrders[] = intval($matches[1]);
                        }
                    }
                }

                $notUploadedOrders = array_filter(array_map(function ($order) {
                    return $order['externalId'];
                }, $request->getData()[0]), function ($orderId) use ($uploadedOrders) {
                    return !in_array($orderId, $uploadedOrders);
                });

                $orders = [];
                if (count($notUploadedOrders) > 0) {
                    $getRequest = new RetailcrmApiRequest();
                    $getRequest->setApi($request->getApi())
                        ->setMethod('ordersList')
                        ->setData([
                            'filter' => [
                                'externalIds' => $notUploadedOrders,
                            ],
                            'page' => 1,
                            'limit' => 50,
                        ]);

                    /** @var RetailcrmApiResponse $getResponse */
                    $getResponse = $next($getRequest);

                    if ($getResponse->isSuccessful() && $getResponse->offsetExists('orders')) {
                        $orders = $getResponse['orders'];

                        foreach ($orders as $order) {
                            RetailcrmExportOrdersHelper::updateExportState($order['externalId'], $order['id']);
                            $uploadedOrders[] = intval($order['externalId']);
                        }
                    }
                }

                $notUploadedOrders = array_filter($notUploadedOrders, function ($orderId) use ($uploadedOrders) {
                    return !in_array($orderId, $uploadedOrders);
                });

                foreach ($notUploadedOrders as $id_order) {
                    RetailcrmExportOrdersHelper::updateExportState($id_order, null, ['Unknown error']);
                }

                return new RetailcrmApiResponse($response->getStatusCode(), json_encode(array_merge(
                    json_decode($response->getRawResponse(), true),
                    [
                        'orders' => $orders,
                        'uploadedOrders' => $uploadedOrders,
                        'notUploadedOrders' => $notUploadedOrders
                    ]
                )));
            }

            return $response;
        }

        $order = $request->getData()[0];

        try {
            /** @var RetailcrmApiResponse $response */
            $response = $next($request);

            if ($response->isSuccessful()) {
                RetailcrmExportOrdersHelper::updateExportState($order['externalId'], $response['id']);
            } else {
                $errors = $response->offsetExists('errors') ? $response['errors'] : [$response['errorMsg']];
                RetailcrmExportOrdersHelper::updateExportState($order['externalId'], null, $errors);
            }

            return $response;
        } catch (Exception $e) {
            if (isset($order['externalId'])) {
                RetailcrmExportOrdersHelper::updateExportState($order['externalId'], null, [$e->getMessage()]);
            }

            throw $e;
        }
    }
}
