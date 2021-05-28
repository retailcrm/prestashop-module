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
class RetailcrmCustomerAddressBuilder extends RetailcrmAbstractBuilder implements RetailcrmBuilderInterface
{
    /**
     * @var Address|AddressCore customerAddress
     */
    private $customerAddress;

    /**
     * @var array $dataCrm
     */
    private $dataCrm;

    /**
     * @var int $idCustomer
     */
    private $idCustomer;

    /**
     * @var string $firstName
     */
    private $firstName;

    /**
     * @var string $lastName
     */
    private $lastName;

    /**
     * @var string $phone
     */
    private $phone;

    /**
     * @var string $alias
     */
    private $alias;

    /**
     * RetailcrmCustomerAddressBuilder constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * @param Address|AddressCore $customerAddress
     * @return RetailcrmCustomerAddressBuilder
     */
    public function setCustomerAddress($customerAddress)
    {
        $this->customerAddress = $customerAddress;
        return $this;
    }

    public function setDataCrm($dataCrm)
    {
        $this->dataCrm = $dataCrm;
        return $this;
    }

    /**
     * @param int $idCustomer
     * @return RetailcrmCustomerAddressBuilder
     */
    public function setIdCustomer($idCustomer)
    {
        $this->idCustomer = $idCustomer;
        return $this;
    }

    /**
     * @param string $alias
     * @return RetailcrmCustomerAddressBuilder
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @param string $firstName
     * @return RetailcrmCustomerAddressBuilder
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @param string $lastName
     * @return RetailcrmCustomerAddressBuilder
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @param string $phone
     * @return RetailcrmCustomerAddressBuilder
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
        return $this;
    }

    public function getData()
    {
        if (!empty($this->customerAddress)) {
            return $this->customerAddress;
        }

        return array();
    }

    public function reset()
    {
        $this->customerAddress = new Address();

        return $this;
    }

    public function build()
    {
        if (empty($this->customerAddress)) {
            $this->customerAddress = new Address();
        }

        $this->customerAddress->id_customer = $this->idCustomer;
        $this->customerAddress->alias = !empty($this->alias) ? $this->alias : 'default';
        $this->customerAddress->lastname = $this->lastName;
        $this->customerAddress->firstname = $this->firstName;
        $this->customerAddress->address1 = isset($this->dataCrm['text']) ? $this->dataCrm['text'] : '--';

        $this->customerAddress->id_country = isset($this->dataCrm['countryIso'])
            ? Country::getByIso($this->dataCrm['countryIso'])
            : Configuration::get('PS_COUNTRY_DEFAULT');

        if (isset($this->dataCrm['region'])) {
            $state = State::getIdByName($this->dataCrm['region']);

            if (!empty($state)) {
                $this->customerAddress->id_state = $state;
            }
        }

        $this->customerAddress->city = isset($this->dataCrm['city']) ? $this->dataCrm['city'] : '--';
        $this->customerAddress->postcode = isset($this->dataCrm['index']) ? $this->dataCrm['index'] : '';
        $this->customerAddress->phone = !empty($this->phone) ? $this->phone : '';

        $this->customerAddress = RetailcrmTools::filter(
            'RetailcrmFilterSaveCustomerAddress',
            $this->customerAddress,
            array(
                'dataCrm' => $this->dataCrm
            ));

        return $this;
    }
}

