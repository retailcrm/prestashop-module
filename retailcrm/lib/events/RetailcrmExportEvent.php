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

require_once(dirname(__FILE__) . '/../RetailcrmPrestashopLoader.php');

class RetailcrmExportEvent extends RetailcrmAbstractEvent implements RetailcrmEventInterface
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        if ($this->isRunning()) {
            return false;
        }

        $this->setRunning();

        $api = RetailcrmTools::getApiClient();

        if (empty($api)) {
            RetailcrmLogger::writeCaller(__METHOD__, 'Set API key & URL first');

            return true;
        }

        $orders = array();
        $orderRecords = Order::getOrdersWithInformations();
        $orderBuilder = new RetailcrmOrderBuilder();
        $orderBuilder->defaultLangFromConfiguration()->setApi($api);

        foreach ($orderRecords as $record) {
            $order = new Order($record['id_order']);

            $orderCart = new Cart($order->id_cart);
            $orderCustomer = new Customer($order->id_customer);

            if (!empty($orderCustomer->id)) {
                $orderBuilder->setCmsCustomer($orderCustomer);
            } else {
                //TODO
                // Caused crash before because of empty RetailcrmOrderBuilder::cmsCustomer.
                // Current version *shouldn't* do this, but I suggest more tests for guest customers.
                $orderBuilder->setCmsCustomer(null);
            }

            if (!empty($orderCart->id)) {
                $orderBuilder->setCmsCart($orderCart);
            } else {
                $orderBuilder->setCmsCart(null);
            }

            $orderBuilder->setCmsOrder($order);

            try {
                $orders[] = $orderBuilder->buildOrderWithPreparedCustomer();
            } catch (\InvalidArgumentException $exception) {
                RetailcrmLogger::writeCaller('export', $exception->getMessage());
                RetailcrmLogger::writeNoCaller($exception->getTraceAsString());
                RetailcrmLogger::output($exception->getMessage());
            }

            time_nanosleep(0, 500000000);
        }

        unset($orderRecords);

        $orders = array_chunk($orders, 50);

        foreach ($orders as $chunk) {
            $api->ordersUpload($chunk);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'RetailcrmExportEvent';
    }
}
