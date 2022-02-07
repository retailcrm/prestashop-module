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

class RetailcrmExportOrdersMiddleware implements RetailcrmMiddlewareInterface
{
    /**
     * {@inheritDoc}
     *
     * @throws Exception|Error
     */
    public function __invoke(RetailcrmApiRequest $request, callable $next = null)
    {
        if ('ordersCreate' === $request->getMethod()
            || 'ordersEdit' === $request->getMethod()
        ) {
            return $this->handleOrdersCreateAndEdit($request, $next);
        }

        if ('ordersUpload' === $request->getMethod()) {
            return $this->handleOrdersUpload($request, $next);
        }

        return $next($request);
    }

    /**
     * @param RetailcrmApiRequest $request
     * @param callable $next
     *
     * @return RetailcrmApiResponse
     *
     * @throws Exception
     */
    private function handleOrdersCreateAndEdit(RetailcrmApiRequest $request, callable $next)
    {
        $order = $request->getData()[0];

        try {
            $response = $next($request);

            if ($response->isSuccessful()) {
                $crmOrderId = isset($response['id']) ? $response['id'] : null;
                RetailcrmExportOrdersHelper::updateExportState($order['externalId'], $crmOrderId);
            } else {
                $errors = $response->offsetExists('errors') ? $response['errors'] : [$response['errorMsg']];
                RetailcrmExportOrdersHelper::updateExportState($order['externalId'], null, $errors);
            }

            return $response;
        } catch (Exception $e) {
            $this->handleError($order, $e);
        } catch (Error $e) {
            $this->handleError($order, $e);
        }
    }

    /**
     * @param RetailcrmApiRequest $request
     * @param callable $next
     *
     * @return RetailcrmApiResponse
     *
     * @throws Exception|Error
     */
    private function handleOrdersUpload(RetailcrmApiRequest $request, callable $next)
    {
        $requestedOrders = array_map(function ($order) {
            return $order['externalId'];
        }, $request->getData()[0]);

        try {
            $response = $next($request);
        } catch (Exception $e) {
            foreach ($requestedOrders as $id_order) {
                RetailcrmExportOrdersHelper::updateExportState($id_order, null, [$e->getMessage()]);
            }

            throw $e;
        } catch (Error $e) {
            foreach ($requestedOrders as $id_order) {
                RetailcrmExportOrdersHelper::updateExportState($id_order, null, [$e->getMessage()]);
            }

            throw $e;
        }

        if ($response->isSuccessful() && $response->offsetExists('uploadedOrders')) {
            foreach ($response['uploadedOrders'] as $uploadedOrder) {
                RetailcrmExportOrdersHelper::updateExportState($uploadedOrder['externalId'], $uploadedOrder['id']);
            }

            return $response;
        }

        $orders = $this->getUploadedOrders($request->getApi(), $requestedOrders);

        $uploadedOrders = [];
        foreach ($orders as $order) {
            RetailcrmExportOrdersHelper::updateExportState($order['externalId'], $order['id']);
            $uploadedOrders[] = (int) ($order['externalId']);
        }

        $notUploadedOrders = array_filter($requestedOrders, function ($orderId) use ($uploadedOrders) {
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
                'notUploadedOrders' => $notUploadedOrders,
            ]
        )));
    }

    /**
     * @param RetailcrmApiClientV5 $api
     * @param array $requestedOrders
     *
     * @return array
     */
    private function getUploadedOrders(RetailcrmApiClientV5 $api, array $requestedOrders)
    {
        $orders = [];

        if (0 < count($requestedOrders)) {
            $getResponse = $api->ordersList(['externalIds' => $requestedOrders], 1, 50);

            if ($getResponse->isSuccessful() && $getResponse->offsetExists('orders')) {
                return $getResponse['orders'];
            }
        }

        return $orders;
    }

    /**
     * @throws Exception|Error
     */
    private function handleError($order, $e)
    {
        if (isset($order['externalId'])) {
            RetailcrmExportOrdersHelper::updateExportState(
                $order['externalId'], null, [$e->getMessage()]
            );
        }

        throw $e;
    }
}
