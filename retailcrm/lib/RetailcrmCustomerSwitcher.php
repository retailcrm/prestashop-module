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

    /** @var bool $isContact */
    private $isContact;

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
        $companyAddress = $this->data->getCompanyAddress();

        if (!empty($newCustomer)) {
            RetailcrmLogger::writeDebugArray(
                __METHOD__,
                array(
                    'Changing to individual customer for order',
                    $this->data->getOrder()->id
                )
            );
            $this->isContact = false;
            $this->processChangeToRegular($this->data->getOrder(), $newCustomer);

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
                $this->isContact = true;
                $this->processChangeToRegular($this->data->getOrder(), $newContact);
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

            if (!empty($companyAddress)) {
                $this->processCompanyAddress();
            }
        }

        return $this;
    }

    /**
     * Change order customer to regular one
     *
     * @param \Order $order
     * @param array  $newCustomer
     */
    protected function processChangeToRegular($order, $newCustomer)
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
            RetailcrmLogger::writeDebug(__METHOD__, sprintf(
                'Switching to existing customer id=%s',
                $newCustomer['externalId']
            ));
            $customer = new Customer($newCustomer['externalId']);

            if (!empty($customer->id)) {
                $order->id_customer = $customer->id;
                $address = $this->getCustomerAddress($customer, $builder->build()->getData()->getCustomerAddress());
                $this->processCustomerAddress($customer, $address);
            } else {
                RetailcrmLogger::writeDebug(__METHOD__, sprintf(
                    'Customer id=%s was not found, skipping...',
                    $newCustomer['externalId']
                ));
            }
        }

        if (empty($customer) || empty($customer->id)) {
            RetailcrmLogger::writeDebug(__METHOD__, "Customer wasn't found, generating new...");

            $result = $builder->build()->getData();
            $customer = $result->getCustomer();
            $address = $this->getCustomerAddress($customer, $result->getCustomerAddress());

            RetailcrmLogger::writeDebugArray(__METHOD__, array('Result:', array(
                'customer' => RetailcrmTools::dumpEntity($customer),
                'address' => RetailcrmTools::dumpEntity($address)
            )));
        }

        $this->result = new RetailcrmCustomerSwitcherResult($customer, $address, $order);
    }

    /**
     * Process company address.
     */
    protected function processCompanyAddress()
    {
        $companyAddress = $this->data->getCompanyAddress();
        $customer = $this->data->getOrder()->getCustomer();
        $address = new Address($this->data->getOrder()->id_address_invoice);

        if (!empty($companyAddress)) {
            $firstName = '--';
            $lastName = '--';
            $billingPhone = $address->phone;

            if (empty($billingPhone)) {
                $billingPhone = $address->phone_mobile;
            }

            if (!empty($this->result)) {
                $customer = $this->result->getCustomer();
            }

            if (empty($customer)) {
                $customer = $this->data->getOrder()->getCustomer();
            }

            if (!empty($customer)) {
                $firstName = !empty($customer->firstname) ? $customer->firstname : '--';
                $lastName = !empty($customer->lastname) ? $customer->lastname : '--';
            }

            $builder = new RetailcrmCustomerAddressBuilder();
            $address = $builder
                ->setDataCrm($companyAddress)
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setPhone($billingPhone)
                ->setAlias('--')
                ->build()
                ->getData();
            $address->company = $this->data->getNewCompanyName();
            RetailcrmTools::assignAddressIdsByFields($customer, $address);
        }

        if (empty($this->result)) {
            $this->result = new RetailcrmCustomerSwitcherResult($customer, $address, $this->data->getOrder());
        } else {
            $this->result->setAddress($address);
        }
    }

    /**
     * This will update company in the address.
     */
    protected function processCompanyChange()
    {
        $customer = $this->data->getOrder()->getCustomer();
        $address = new Address($this->data->getOrder()->id_address_invoice);

        if (!empty($this->result)) {
            $newAddress = $this->result->getAddress();
            $newCustomer = $this->result->getCustomer();

            if (!empty($newAddress) && empty($companyAddress)) {
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
                'companyAddress' => $this->data->getCompanyAddress(),
                'order' => RetailcrmTools::dumpEntity($this->data->getOrder()),
            )));
        }
    }

    /**
     * Returns placeholder address if customer hasn't one; returns address without any changes otherwise.
     *
     * @param \Customer|\CustomerCore   $customer
     * @param Address|\AddressCore|null $address
     *
     * @return \Address|\AddressCore|array|mixed
     */
    private function getCustomerAddress($customer, $address)
    {
        if (empty($address)) {
            if ($this->isContact) {
                $address = $this->createPlaceholderAddress($customer, '--');
            } else {
                $address = $this->createPlaceholderAddress($customer);
            }
        }

        return $address;
    }

    /**
     * Process address fields for existing customer.
     *
     * @param Customer|\CustomerCore $customer
     * @param Address|\AddressCore   $address
     */
    private function processCustomerAddress($customer, $address)
    {
        if ($this->isContact) {
            $newCompany = $this->data->getNewCompanyName();
            RetailcrmLogger::writeDebug(__METHOD__, 'Processing address for a contact person');

            if ($address->alias == '' || $address->alias == 'default') {
                $address->alias = '--';
            }

            if (!empty($newCompany)) {
                $address->company = $newCompany;
            } else {
                $oldAddress = new Address($this->data->getOrder()->id_address_invoice);

                if ($oldAddress->company) {
                    $address->company = $oldAddress->company;
                }
            }

            RetailcrmTools::assignAddressIdsByFields($customer, $address);
            RetailcrmLogger::writeDebug(__METHOD__, sprintf('Address id=%d', $address->id));
        } else {
            RetailcrmLogger::writeDebug(__METHOD__, 'Processing address for an individual');
            RetailcrmTools::searchIndividualAddress($customer);

            if (empty($address->id)) {
                $address->alias = 'default';
                RetailcrmTools::assignAddressIdsByFields($customer, $address);
            }

            RetailcrmLogger::writeDebug(__METHOD__, sprintf('Address id=%d', $address->id));
        }
    }

    /**
     * Builds placeholder address for customer if he doesn't have address.
     *
     * @param \Customer $customer
     * @param string    $alias
     *
     * @return \Address|\AddressCore|array|mixed
     */
    private function createPlaceholderAddress($customer, $alias = 'default')
    {
        $addressBuilder = new RetailcrmCustomerAddressBuilder();
        return $addressBuilder
            ->setIdCustomer($customer->id)
            ->setFirstName($customer->firstname)
            ->setLastName($customer->lastname)
            ->setAlias($alias)
            ->build()
            ->getData();
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
