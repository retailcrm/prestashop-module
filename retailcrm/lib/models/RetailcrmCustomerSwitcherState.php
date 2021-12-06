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

class RetailcrmCustomerSwitcherState
{
    /** @var \Order */
    private $order;

    /** @var array */
    private $newCustomer;

    /** @var array */
    private $newContact;

    /** @var string */
    private $newCompanyName;

    /** @var array */
    private $companyAddress;

    /** @var array */
    private $crmOrderShippingAddress;

    /**
     * @return \Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param \Order $order
     *
     * @return RetailcrmCustomerSwitcherState
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return array
     */
    public function getNewCustomer()
    {
        return $this->newCustomer;
    }

    /**
     * @param array $newCustomer
     *
     * @return RetailcrmCustomerSwitcherState
     */
    public function setNewCustomer($newCustomer)
    {
        $this->newCustomer = $newCustomer;

        return $this;
    }

    /**
     * @return array
     */
    public function getNewContact()
    {
        return $this->newContact;
    }

    /**
     * @param array $newContact
     *
     * @return RetailcrmCustomerSwitcherState
     */
    public function setNewContact($newContact)
    {
        $this->newContact = $newContact;

        return $this;
    }

    /**
     * @return string
     */
    public function getNewCompanyName()
    {
        return $this->newCompanyName;
    }

    /**
     * @param string $newCompanyName
     *
     * @return RetailcrmCustomerSwitcherState
     */
    public function setNewCompanyName($newCompanyName)
    {
        $this->newCompanyName = $newCompanyName;

        return $this;
    }

    /**
     * @return array
     */
    public function getCompanyAddress()
    {
        return $this->companyAddress;
    }

    /**
     * @param array $companyAddress
     *
     * @return RetailcrmCustomerSwitcherState
     */
    public function setCompanyAddress($companyAddress)
    {
        $this->companyAddress = $companyAddress;

        return $this;
    }

    /**
     * @param array $newCompany
     *
     * @return RetailcrmCustomerSwitcherState
     */
    public function setNewCompany($newCompany)
    {
        if (isset($newCompany['name'])) {
            $this->setNewCompanyName($newCompany['name']);
        }

        if (isset($newCompany['address']) && !empty($newCompany['address'])) {
            $this->setCompanyAddress($newCompany['address']);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getCrmOrderShippingAddress()
    {
        return $this->crmOrderShippingAddress;
    }

    /**
     * @param array $crmOrderShippingAddress
     *
     * @return RetailcrmCustomerSwitcherState
     */
    public function setCrmOrderShippingAddress($crmOrderShippingAddress)
    {
        $this->crmOrderShippingAddress = $crmOrderShippingAddress;

        return $this;
    }

    /**
     * Returns true if current state may be processable (e.g. when customer or related data was changed).
     * It doesn't guarantee state validity.
     *
     * @return bool
     */
    public function feasible()
    {
        return !(empty($this->newCustomer) && empty($this->newContact) && empty($this->newCompanyName));
    }

    /**
     * Throws an exception if state is not valid
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function validate()
    {
        if (empty($this->order)) {
            throw new \InvalidArgumentException('Empty Order.');
        }

        if (empty($this->newCustomer) && empty($this->newContact) && empty($this->newCompanyName)) {
            throw new \InvalidArgumentException('New customer, new contact and new company is empty.');
        }

        if (!empty($this->newCustomer) && !empty($this->newContact)) {
            RetailcrmLogger::writeDebugArray(
                __METHOD__,
                [
                    'State data (customer and contact):' . PHP_EOL,
                    $this->getNewCustomer(),
                    $this->getNewContact(),
                ]
            );
            throw new \InvalidArgumentException(
                'Too much data in state - cannot determine which customer should be used.'
            );
        }
    }
}
