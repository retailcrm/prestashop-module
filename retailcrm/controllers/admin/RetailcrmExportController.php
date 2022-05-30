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

require_once dirname(__FILE__) . '/../../bootstrap.php';

class RetailcrmExportController extends RetailcrmAdminPostAbstractController
{
    protected function postHandler()
    {
        $api = RetailcrmTools::getApiClient();

        if (empty($api)) {
            throw new Exception('Set API key & URL first');
        }

        RetailcrmExport::init();
        RetailcrmExport::$api = $api;
        RetailcrmHistory::$api = $api;

        if (Tools::getIsset('stepOrders')) {
            $skipUploaded = Tools::getIsset('skipUploaded') && 'true' === Tools::getValue('skipUploaded');

            RetailcrmExport::export(Tools::getValue('stepOrders'), 'order', $skipUploaded);
        } elseif (Tools::getIsset('stepCustomers')) {
            RetailcrmExport::export(Tools::getValue('stepCustomers'), 'customer');
        } elseif (Tools::getIsset('stepSinceId')) {
            RetailcrmHistory::updateSinceId('customers');
            RetailcrmHistory::updateSinceId('orders');
        } else {
            throw new Exception('Invalid request data');
        }

        return RetailcrmJsonResponse::successfullResponse();
    }

    protected function getHandler()
    {
        // todo move to helper
        return [
            'success' => true,
            'orders' => [
                'count' => RetailcrmExport::getOrdersCount(),
                'exportCount' => RetailcrmExport::getOrdersCount(true),
                'exportStepSize' => RetailcrmExport::RETAILCRM_EXPORT_ORDERS_STEP_SIZE_WEB,
            ],
            'customers' => [
                'count' => RetailcrmExport::getCustomersCount(),
                'exportCount' => RetailcrmExport::getCustomersCount(false),
                'exportStepSize' => RetailcrmExport::RETAILCRM_EXPORT_CUSTOMERS_STEP_SIZE_WEB,
            ],
        ];
    }
}
