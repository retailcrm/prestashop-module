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
class RetailcrmCustomerSwitcher implements RetailcrmBuilderInterface
{
    /**
     * @var \RetailcrmCustomerSwitcherState $data
     */
    private $data;

    /**
     * @var \RetailcrmCustomerSwitcherResult|null $result
     */
    private $result;

    /**
     * RetailcrmCustomerSwitcher constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * In fact, this will execute customer change in provided order.
     *
     * @return $this|\RetailcrmBuilderInterface
     */
    public function build()
    {
        $this->data->validate();
        $this->debugLogState();
        $newCustomer = $this->data->getNewCustomer();
        $newContact = $this->data->getNewContact();
        $newCompany = $this->data->getNewCompanyName();

        if (!empty($newCustomer)) {
            RetailcrmLogger::writeDebugArray(
                __METHOD__,
                array(
                    'Changing to individual customer for order',
                    $this->data->getOrder()->id
                )
            );
            $this->processChangeToRegular($this->data->getOrder(), $newCustomer, false);

            if (!empty($this->result)) {
                $this->result->getAddress()->company = '';
            }
        } else {
            if (!empty($newContact)) {
                RetailcrmLogger::writeDebugArray(
                    __METHOD__,
                    array(
                        'Changing to contact person customer for order',
                        $this->data->getOrder()->id
                    )
                );
                $this->processChangeToRegular($this->data->getOrder(), $newContact, true);
            }

            if (!empty($newCompany)) {
                RetailcrmLogger::writeDebug(
                    __METHOD__,
                    sprintf(
                        'Replacing old order id=`%d` company `%s` with new company `%s`',
                        $this->data->getOrder()->id,
                        self::getOrderCompany($this->data->getOrder()),
                        $newCompany
                    )
                );
                $this->processCompanyChange();
            }
        }

        return $this;
    }

    /**
     * Change order customer to regular one
     *
     * @param \Order $order
     * @param array  $newCustomer
     * @param bool   $isContact
     */
    public function processChangeToRegular($order, $newCustomer, $isContact)
    {
        $customer = null;
        $address = null;
        $builder = new RetailcrmCustomerBuilder();
        $builder->setDataCrm($newCustomer);

        RetailcrmLogger::writeDebugArray(
            __METHOD__,
            array(
                'Switching in order',
                $order->id,
                'to',
                $newCustomer
            )
        );

        if (isset($newCustomer['externalId'])) {
            $customer = new Customer($newCustomer['externalId']);

            if (!empty($customer->id)) {
                $order->id_customer = $customer->id;
                $address = $builder->build()->getData()->getCustomerAddress();

                if ($isContact) {
                    if ($address->alias == '' || $address->alias == 'default') {
                        $address->alias = '--';
                    }

                    RetailcrmTools::assignAddressIdsByFields($customer, $address);
                } else {
                    RetailcrmTools::searchIndividualAddress($customer);

                    if (empty($address->id)) {
                        $address->alias = 'default';
                        RetailcrmTools::assignAddressIdsByFields($customer, $address);
                    }
                }
            }
        }

        if (empty($customer) || empty($customer->id)) {
            $result = $builder->build()->getData();
            $customer = $result->getCustomer();
            $address = $result->getCustomerAddress();
        }

        $this->result = new RetailcrmCustomerSwitcherResult($customer, $address, $order);
    }

    /**
     * This will update company in the address.
     */
    public function processCompanyChange()
    {
        $customer = $this->data->getOrder()->getCustomer();
        $address = new Address($this->data->getOrder()->id_address_invoice);

        if ($this->data->getNewCompanyName() == $address->company) {
            return;
        }

        if (!empty($this->result)) {
            $newAddress = $this->result->getAddress();
            $newCustomer = $this->result->getCustomer();

            if (!empty($newAddress)) {
                $address = $newAddress;
            }

            if (!empty($newCustomer)) {
                $customer = $newCustomer;
            }
        }

        $oldId = $address->id;
        $address->alias = '--';
        $address->company = $this->data->getNewCompanyName();
        RetailcrmTools::assignAddressIdsByFields($customer, $address);

        if (empty($oldId) || $oldId == $address->id) {
            $address->id = 0;
        }

        if (empty($this->result)) {
            $this->result = new RetailcrmCustomerSwitcherResult($customer, $address, $this->data->getOrder());
        }
    }

    /**
     * @return $this|\RetailcrmBuilderInterface
     */
    public function reset()
    {
        $this->data = new RetailcrmCustomerSwitcherState();
        $this->result = null;
        return $this;
    }

    /**
     * Interface method implementation
     *
     * @param array $data
     *
     * @return $this|\RetailcrmBuilderInterface
     */
    public function setDataCrm($data)
    {
        if (is_array($data)) {
            $data = reset($data);
        }

        return $this->setData($data);
    }

    /**
     * Set initial state into component
     *
     * @param \RetailcrmCustomerSwitcherState $data
     *
     * @return $this|\RetailcrmBuilderInterface
     */
    public function setData($data)
    {
        if (!($data instanceof RetailcrmCustomerSwitcherState)) {
            throw new \InvalidArgumentException('Invalid data type');
        }

        $this->data = $data;
        return $this;
    }

    /**
     * @return \RetailcrmCustomerSwitcherResult|null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return \RetailcrmCustomerSwitcherState
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Debug log for component state
     */
    private function debugLogState()
    {
        if (RetailcrmTools::isDebug()) {
            RetailcrmLogger::writeDebugArray(__METHOD__, array('state', array(
                'newCustomer' => $this->data->getNewCustomer(),
                'newContact' => $this->data->getNewContact(),
                'newCompanyName' => $this->data->getNewCompanyName(),
                'order' => RetailcrmTools::dumpEntity($this->data->getOrder()),
            )));
        }
    }

    /**
     * Returns company name from order.
     *
     * @param \Order $order
     *
     * @return string
     */
    private static function getOrderCompany($order)
    {
        if (!empty($order) && !empty($order->id_address_invoice)) {
            $address = new Address($order->id_address_invoice);

            return $address->company;
        }

        return '';
    }
}
