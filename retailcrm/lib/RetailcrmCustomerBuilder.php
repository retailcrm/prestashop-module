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
class RetailcrmCustomerBuilder extends RetailcrmAbstractBuilder implements RetailcrmBuilderInterface
{
    /** @var Customer|CustomerCore $customer Customer */
    private $customer;

    /** @var Address|AddressCore|null $customerAddress Address */
    private $customerAddress;

    /** @var array $dataCrm customerHistory */
    protected $dataCrm;

    /** @var RetailcrmBuilderInterface $addressBuilder Address builder */
    private $addressBuilder;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * @param Customer|CustomerCore  $customer
     * @return RetailcrmCustomerBuilder
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;
        return $this;
    }

    /**
     * @param RetailcrmBuilderInterface $addressBuilder
     * @return RetailcrmCustomerBuilder
     */
    public function setAddressBuilder($addressBuilder)
    {
        $this->addressBuilder = $addressBuilder;
        return $this;
    }

    public function setDataCrm($dataCrm)
    {
        $this->dataCrm = $dataCrm;
        return $this;
    }

    public function getData()
    {
        return new RetailcrmCustomerBuilderResult($this->customer, $this->customerAddress);
    }

    public function reset()
    {
        $this->customer = new Customer();
        $this->customerAddress = null;
        $this->addressBuilder = null;

        return $this;
    }

    /**
     * Build address for customer
     */
    public function buildAddress()
    {
        if (empty($this->addressBuilder)) {
            $this->addressBuilder = new RetailcrmCustomerAddressBuilder();
        }

        if (isset($this->dataCrm['address'])) {
            $this->addressBuilder
                ->setIdCustomer($this->arrayValue('externalId', 0))
                ->setDataCrm($this->dataCrm['address'])
                ->setFirstName($this->arrayValue('firstName'))
                ->setLastName($this->arrayValue('lastName'))
                ->setPhone( isset($this->dataCrm['phones'][0]['number'])
                && !empty($this->dataCrm['phones'][0]['number'])
                    ?  $this->dataCrm['phones'][0]['number'] : '')
                ->build();

            $this->customerAddress = $this->addressBuilder->getData();
        } else {
            $this->customerAddress = null;
        }
    }

    public function build()
    {
        if (isset($this->dataCrm['externalId'])) {
            $this->customer->id = $this->dataCrm['externalId'];
        }

        $this->customer->firstname = $this->arrayValue('firstName');
        $this->customer->lastname = $this->arrayValue('lastName');

        if (isset($this->dataCrm['subscribed']) && $this->dataCrm['subscribed'] == false) {
            $this->customer->newsletter = false;
        }

        if (empty($this->customer->id_shop)) {
            $this->customer->id_shop = Context::getContext()->shop->id;
        }

        $this->customer->birthday = $this->arrayValue('birthday', '');

        if (isset($this->dataCrm['sex'])) {
            $this->customer->id_gender = $this->dataCrm['sex'] == 'male' ? 1 : 2;
        }

        $this->buildAddress();

        if (isset($this->dataCrm['email']) && Validate::isEmail($this->dataCrm['email'])) {
            $this->customer->email = $this->dataCrm['email'];
        } else {
            $this->customer->email = RetailcrmTools::createPlaceholderEmail($this->arrayValue('firstName', microtime()));
        }

        if (empty($this->customer->passwd )) {
            $this->customer->passwd = Tools::substr(str_shuffle(Tools::strtolower(sha1(rand() . time()))), 0, 5);
        }

        $this->customer = RetailcrmTools::filter(
            'RetailcrmFilterSaveCustomer',
            $this->customer,
            array(
                'dataCrm' => $this->dataCrm,
            ));

        return $this;
    }
}

