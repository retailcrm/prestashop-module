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

class RetailcrmMissingEvent extends RetailcrmAbstractEvent implements RetailcrmEventInterface
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

        $shortopts = 'o:';
        $options = getopt($shortopts);

        if (!isset($options['o'])) {
            echo 'Parameter -o is missing';

            return true;
        }

        $apiUrl = Configuration::get(RetailCRM::API_URL);
        $apiKey = Configuration::get(RetailCRM::API_KEY);

        if (!empty($apiUrl) && !empty($apiKey)) {
            $api = new RetailcrmProxy($apiUrl, $apiKey, RetailcrmLogger::getLogFile());
        } else {
            echo 'Set api key & url first';

            return true;
        }

        $delivery = json_decode(Configuration::get(RetailCRM::DELIVERY), true);
        $payment = json_decode(Configuration::get(RetailCRM::PAYMENT), true);
        $status = json_decode(Configuration::get(RetailCRM::STATUS), true);

        $orderInstance = new Order($options['o']);

        $order = array(
            'externalId' => $orderInstance->id,
            'createdAt' => $orderInstance->date_add,
        );

        /**
         * Add order customer info
         *
         */

        if (!empty($orderInstance->id_customer)) {
            $orderCustomer = new Customer($orderInstance->id_customer);
            $customer = array(
                'externalId' => $orderCustomer->id,
                'firstName' => $orderCustomer->firstname,
                'lastname' => $orderCustomer->lastname,
                'email' => $orderCustomer->email,
                'createdAt' => $orderCustomer->date_add
            );

            $response = $api->customersEdit($customer);

            if ($response) {
                $order['customer']['externalId'] = $orderCustomer->id;
                $order['firstName'] = $orderCustomer->firstname;
                $order['lastName'] = $orderCustomer->lastname;
                $order['email'] = $orderCustomer->email;
            } else {
                return true;
            }
        }


        /**
         *  Add order status
         *
         */

        if ($orderInstance->current_state == 0) {
            $order['status'] = 'completed';
        } else {
            $order['status'] = array_key_exists($orderInstance->current_state, $status)
                ? $status[$orderInstance->current_state]
                : 'completed'
            ;
        }

        /**
         * Add order address data
         *
         */

        $cart = new Cart($orderInstance->getCartIdStatic($orderInstance->id));
        $addressCollection = $cart->getAddressCollection();
        $address = array_shift($addressCollection);

        if ($address instanceof Address) {
            $phone = is_null($address->phone)
                ? is_null($address->phone_mobile) ? '' : $address->phone_mobile
                : $address->phone
            ;

            $postcode = $address->postcode;
            $city = $address->city;
            $addres_line = sprintf("%s %s", $address->address1, $address->address2);
        }

        if (!empty($postcode)) {
            $order['delivery']['address']['index'] = $postcode;
        }

        if (!empty($city)) {
            $order['delivery']['address']['city'] = $city;
        }

        if (!empty($addres_line)) {
            $order['delivery']['address']['text'] = $addres_line;
        }

        if (!empty($phone)) {
            $order['phone'] = $phone;
        }

        /**
         * Add payment & shippment data
         */

        if (Module::getInstanceByName('advancedcheckout') === false) {
            $paymentType = $orderInstance->module;
        } else {
            $paymentType = $orderInstance->payment;
        }

        if (array_key_exists($paymentType, $payment) && !empty($payment[$paymentType])) {
            $order['paymentType'] = $payment[$paymentType];
        }

        if (array_key_exists($orderInstance->id_carrier, $delivery) && !empty($delivery[$orderInstance->id_carrier])) {
            $order['delivery']['code'] = $delivery[$orderInstance->id_carrier];
        }

        if (isset($orderInstance->total_shipping_tax_incl) && (int) $orderInstance->total_shipping_tax_incl > 0) {
            $order['delivery']['cost'] = round($orderInstance->total_shipping_tax_incl, 2);
        }

        /**
         * Add products
         *
         */

        $products = $orderInstance->getProducts();

        foreach ($products as $product) {
            $item = array(
                //'productId' => $product['product_id'],
                'offer' => array('externalId' => $product['product_id']),
                'productName' => $product['product_name'],
                'quantity' => $product['product_quantity'],
                'initialPrice' => round($product['product_price'], 2),
                'purchasePrice' => round($product['purchase_supplier_price'], 2)
            );

            $order['items'][] = $item;
        }

        $api->ordersEdit($order);

        return true;
    }

    public function getName()
    {
        return 'RetailcrmMissingEvent';
    }
}
