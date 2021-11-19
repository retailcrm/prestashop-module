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
require_once __DIR__ . '/../../bootstrap.php';

class RetailcrmOrdersUploadController extends RetailcrmAdminAbstractController
{
    private $api;
    private $receiveOrderNumber;

    public function __construct()
    {
        parent::__construct();

        $this->api = RetailcrmTools::getApiClient();
    }

    public function postProcess()
    {
        $this->ajaxDie(json_encode($this->getData()));
    }

    protected function getData()
    {
        if (!($this->api instanceof RetailcrmProxy)) {
            return [
                'success' => false,
                'errorMsg' => "Can't upload orders - set API key and API URL first!",
            ];
        }

        $orderIds = Tools::getValue('orders');
        try {
            $isSuccessful = true;
            $skippedOrders = [];
            $uploadedOrders = [];
            $errors = [];

            RetailcrmExport::$api = $this->api;
            foreach ($orderIds as $orderId) {
                $id_order = (int) $orderId;
                $response = false;

                try {
                    $response = RetailcrmExport::exportOrder($id_order);

                    if ($response) {
                        $uploadedOrders[] = $id_order;
                    }
                } catch (PrestaShopObjectNotFoundExceptionCore $e) {
                    $skippedOrders[] = $id_order;
                } catch (Exception $e) {
                    $errors[$id_order][] = $e->getMessage();
                }

                $isSuccessful = $isSuccessful ? $response : false;
                time_nanosleep(0, 50000000);
            }

            return [
                'success' => $isSuccessful,
                'uploadedOrders' => $uploadedOrders,
                'skippedOrders' => $skippedOrders,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errorMsg' => $e->getMessage(),
            ];
        }
    }
}
