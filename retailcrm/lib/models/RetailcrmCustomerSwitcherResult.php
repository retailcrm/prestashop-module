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
class RetailcrmCustomerSwitcherResult
{
    /** @var \Customer */
    private $customer;

    /** @var \Address */
    private $address;

    /** @var \Order $order */
    private $order;

    /**
     * RetailcrmCustomerSwitcherResult constructor.
     *
     * @param \Customer $customer
     * @param \Address  $address
     * @param \Order    $order
     */
    public function __construct($customer, $address, $order)
    {
        $this->customer = $customer;
        $this->order = $order;
        $this->address = $address;

        if (!($this->customer instanceof Customer) || !($this->order instanceof Order)) {
            throw new \InvalidArgumentException(sprintf('Incorrect data provided to %s', __CLASS__));
        }
    }

    /**
     * @return \Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @return \Address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param \Address $address
     *
     * @return RetailcrmCustomerSwitcherResult
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * @return \Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Save customer (if exists) and order.
     *
     * @return $this
     * @throws \PrestaShopException
     */
    public function save()
    {
        RetailcrmLogger::writeDebugArray(
            __METHOD__,
            array(
                'Saving customer, address and order:',
                array(
                    'customer' => RetailcrmTools::dumpEntity($this->customer),
                    'address' => RetailcrmTools::dumpEntity($this->address),
                    'order' => RetailcrmTools::dumpEntity($this->order)
                )
            )
        );

        if (!empty($this->customer)) {
            $this->customer->save();

            if (!empty($this->address) && !empty($this->customer->id)) {
                $this->address->id_customer = $this->customer->id;
                $this->address->save();

                if (!empty($this->order) && !empty($this->address->id)) {
                    $this->order->id_customer = $this->customer->id;
                    $this->order->id_address_invoice = $this->address->id;
                    $this->order->id_address_delivery = $this->address->id;
                    $this->order->save();
                } else {
                    $this->address->delete();
                }
            }
        }

        return $this;
    }
}
