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

        $this->setAddressField('alias', $this->alias, 'default');
        $this->setAddressField('lastname', $this->lastName, '');
        $this->setAddressField('firstname', $this->firstName, '');
        $this->setAddressField('phone', $this->phone, '');

        $this->buildAddressLine();

        if (isset($this->dataCrm['notes'])) {
            $this->setAddressField('other', $this->dataCrm['notes'], '');
        }

        if (isset($this->dataCrm['countryIso'])) {
            $countryIso = null;
            if (Validate::isLanguageIsoCode($this->dataCrm['countryIso'])) {
                $countryIso = Country::getByIso($this->dataCrm['countryIso']);
            }

            $this->setAddressField('id_country', $countryIso, Configuration::get('PS_COUNTRY_DEFAULT'));
        }

        if (isset($this->dataCrm['city'])) {
            $this->setAddressField('city', $this->dataCrm['city'], '--');
        }
        if (isset($this->dataCrm['index'])) {
            $this->setAddressField('postcode', $this->dataCrm['index'], '');
        }
        if (isset($this->dataCrm['region'])) {
            $this->setAddressField('id_state', (int) State::getIdByName($this->dataCrm['region']));
        }

        $this->customerAddress = RetailcrmTools::filter(
            'RetailcrmFilterSaveCustomerAddress',
            $this->customerAddress,
            array(
                'dataCrm' => $this->dataCrm
            ));

        return $this;
    }

    private function setAddressField($field, $value, $default = null)
    {
        if (!property_exists($this->customerAddress, $field)) {
            throw new InvalidArgumentException("Property $field not exist in the object");
        }

        if ($value !== null) {
            $this->customerAddress->$field = $value;
        } elseif (empty($this->customerAddress->$field)) {
            $this->customerAddress->$field = $default;
        }
    }

    private function buildAddressLine()
    {
        if (isset($this->dataCrm['text'])) {
            $text = $this->dataCrm['text'];
            if (isset($this->dataCrm['notes'])) {
                $text = str_replace($this->dataCrm['notes'], '', $text);
            }

            $addressLine = explode(RetailcrmAddressBuilder::ADDRESS_LINE_DIVIDER, $text, 2);

            $this->setAddressField('address1', $addressLine[0], '--');
            if (count($addressLine) == 1) {
                $this->setAddressField('address2', '');
            } else {
                $this->setAddressField('address2', $addressLine[1], '');
            }
        }
    }
}

