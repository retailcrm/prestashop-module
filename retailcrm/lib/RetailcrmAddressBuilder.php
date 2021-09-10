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
class RetailcrmAddressBuilder extends RetailcrmAbstractDataBuilder
{
    /**
     * Mode for regular customer. Default.
     */
    const MODE_CUSTOMER = 0;

    /**
     * Mode for corporate customer.
     */
    const MODE_CORPORATE_CUSTOMER = 1;

    /**
     * Mode for order delivery address
     */
    const MODE_ORDER_DELIVERY = 2;


    /**
     * Divider for order delivery addressline1 and addressline 2
     */
    const ADDRESS_LINE_DIVIDER = '||';

    /**
     * @var Address|\AddressCore
     */
    private $address;

    /**
     * @var bool
     */
    private $isMain;

    /**
     * @var bool
     */
    private $withExternalId;

    /**
     * @var string
     */
    private $externalIdSuffix = '';

    /**
     * @var int
     */
    private $mode;

    /**
     * @param Address|\AddressCore $address
     *
     * @return RetailcrmAddressBuilder
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * @param bool $isMain
     *
     * @return RetailcrmAddressBuilder
     */
    public function setIsMain($isMain)
    {
        $this->isMain = $isMain;
        return $this;
    }

    /**
     * @param int $mode
     *
     * @return RetailcrmAddressBuilder
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * @param bool $withExternalId
     *
     * @return RetailcrmAddressBuilder
     */
    public function setWithExternalId($withExternalId)
    {
        $this->withExternalId = $withExternalId;
        return $this;
    }

    /**
     * @param string $externalIdSuffix
     *
     * @return RetailcrmAddressBuilder
     */
    public function setExternalIdSuffix($externalIdSuffix)
    {
        $this->externalIdSuffix = $externalIdSuffix;
        return $this;
    }

    /**
     * @param int $addressId
     *
     * @return RetailcrmAddressBuilder
     */
    public function setAddressId($addressId)
    {
        $this->address = new Address($addressId);
        return $this;
    }

    /**
     * Reset builder state
     *
     * @return \RetailcrmAbstractDataBuilder|void
     */
    public function reset()
    {
        parent::reset();

        $this->data = array();
        $this->address = null;
        $this->mode = static::MODE_CUSTOMER;
        $this->isMain = false;
        $this->withExternalId = false;
        $this->externalIdSuffix = '';
    }

    /**
     * Build address
     *
     * @return $this|\RetailcrmAbstractDataBuilder
     */
    public function build()
    {
        if (!empty($this->address)) {
            switch ($this->mode) {
                case static::MODE_CUSTOMER:
                    $this->buildCustomerAddress();
                    $this->buildCustomerPhones();
                    break;
                case static::MODE_CORPORATE_CUSTOMER:
                    $this->buildCorporateCustomerAddress();
                    break;
                case static::MODE_ORDER_DELIVERY:
                    $this->buildOrderAddress();
                    $this->buildOrderPhones();
                    $this->buildOrderNames();
                    break;
                default:
                    throw new \InvalidArgumentException("Incorrect builder mode");
            }
        }

        $this->data = RetailcrmTools::filter(
            'RetailcrmFilterProcessAddress',
            $this->data,
            array(
                'address' => $this->address,
                'mode' => $this->mode
            ));

        return $this;
    }

    /**
     * Returns built data. Data for order and for customer should be merged respectively, e.g.
     *      $order = array_merge($order, $builder->getData());
     * or
     *      $customer = array_merge($customer, $builder->getData());
     * Data for corporate customers should be used as address array e.g.
     *      $corporateCustomer["addresses"][] = $builder->getData();
     *
     * @return array
     */
    public function getDataArray()
    {
        if (!empty($this->address)) {
            switch ($this->mode) {
                case static::MODE_CUSTOMER:
                    return $this->data['customer'];
                case static::MODE_CORPORATE_CUSTOMER:
                    return $this->data['customer']['address'];
                case static::MODE_ORDER_DELIVERY:
                    return $this->data['order'];
            }
        }

        return array();
    }

    /**
     * Parse generic address data
     *
     * @return array
     */
    private function parseAddress()
    {
        $state = null;

        if (!empty($this->address->id_state)) {
            $stateName = State::getNameById($this->address->id_state);

            if (!empty($stateName)) {
                $state = $stateName;
            }
        }

        return array_filter(array(
            'index' => $this->address->postcode,
            'city' => $this->address->city,
            'countryIso' => Country::getIsoById($this->address->id_country),
            'text' => (empty($this->address->address2) ? $this->address->address1 :
                implode(self::ADDRESS_LINE_DIVIDER, [
                    $this->address->address1,
                    $this->address->address2,
                ])),
            'notes' => $this->address->other,
            'region' => $state
        ));
    }

    /**
     * Extract customer phones from address
     */
    private function buildCustomerPhones()
    {
        if (!empty($this->address->phone_mobile)) {
            $this->data['customer']['phones'][] = array('number'=> $this->address->phone_mobile);
        }

        if (!empty($this->address->phone)) {
            $this->data['customer']['phones'][] = array('number'=> $this->address->phone);
        }
    }

    /**
     * Extract order phone from address
     */
    private function buildOrderPhones()
    {
        if (!empty($this->address->phone_mobile)) {
            $this->data['order']['phone'] = $this->address->phone_mobile;
        }

        if (!empty($this->address->phone)) {
            if (empty($this->data['order']['phone'])) {
                $this->data['order']['phone'] = $this->address->phone;
            } else {
                $this->data['order']['additionalPhone'] = $this->address->phone;
            }
        }
    }

    /**
     * Extract order first and last names from address
     */
    private function buildOrderNames()
    {
        if (!empty($this->address->firstname)) {
            $this->data['order']['firstName'] = $this->address->firstname;
        }

        if (!empty($this->address->lastname)) {
            $this->data['order']['lastName'] = $this->address->lastname;
        }
    }

    /**
     * Build regular customer address
     */
    private function buildCustomerAddress()
    {
        $this->data['customer']['address'] = $this->parseAddress();
    }

    /**
     * Build corporate customer address. Address's `externalId` should be unique in customer.
     * Attempt to create address with same `externalId` in customer will result in error.
     */
    private function buildCorporateCustomerAddress()
    {
        $this->data['customer']['address'] = $this->parseAddress();
        $this->data['customer']['address']['isMain'] = $this->isMain;

        if ($this->withExternalId) {
            $this->data['customer']['address']['externalId'] = $this->address->id;

            if (!empty($this->externalIdSuffix)) {
                $this->data['customer']['address']['externalId'] .= $this->externalIdSuffix;
            }
        }
    }

    /**
     * Build order address
     */
    private function buildOrderAddress()
    {
        $this->data['order']['delivery']['address'] = $this->parseAddress();
        $this->data['order']['countryIso'] = Country::getIsoById($this->address->id_country);
        unset($this->data['order']['delivery']['address']['countryIso']);
    }
}
