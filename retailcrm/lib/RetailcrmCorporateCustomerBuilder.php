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
class RetailcrmCorporateCustomerBuilder extends RetailcrmAbstractBuilder implements RetailcrmBuilderInterface
{
    /**
     * @var Customer|CustomerCore $corporateCustomer Corporate customer
     */
    private $corporateCustomer;

    /**
     * @var RetailcrmBuilderInterface $customerBuilder Customer builder
     */
    private $customerBuilder;

    /**
     * @var array $dataCrm customerHistory
     */
    protected $dataCrm;

    /**
     * @var string $companyName
     */
    private $companyName;

    /**
     * @var string $companyInn
     */
    protected $companyInn;

    /**
     * @var Address|AddressCore $corporateAddress
     */
    private $corporateAddress;

    /**
     * RetailcrmCorporateCustomerBuilder constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * @param RetailcrmBuilderInterface $customerBuilder
     * @return RetailcrmCorporateCustomerBuilder
     */
    public function setCustomerBuilder($customerBuilder)
    {
        $this->customerBuilder = $customerBuilder;
        return $this;
    }

    /**
     * @param Customer $corporateCustomer Corporate customer
     * @return RetailcrmCorporateCustomerBuilder
     */
    public function setCustomer($corporateCustomer)
    {
        $this->corporateCustomer = $corporateCustomer;
        return $this;
    }

    /**
     * @param string $companyName
     * @return RetailcrmCorporateCustomerBuilder
     */
    public function setCompanyName($companyName)
    {
        $this->companyName = $companyName;
        return $this;
    }

    /**
     * @param string $companyInn
     * @return RetailcrmCorporateCustomerBuilder
     */
    public function setCompanyInn($companyInn)
    {
        $this->companyInn = $companyInn;
        return $this;
    }

    /**
     * @param Address|AddressCore  $corporateAddress
     * @return RetailcrmCorporateCustomerBuilder
     */
    public function setCorporateAddress($corporateAddress)
    {
        $this->corporateAddress = $corporateAddress;
        return $this;
    }

    /**
     * Set data in address, name and inn company corporate customer
     *
     * @param array $dataCrm
     * @return RetailcrmCorporateCustomerBuilder
     */
    public function extractCompanyDataFromOrder($dataCrm)
    {
        $this->setCompanyName(isset($dataCrm['company']['name']) ? $dataCrm['company']['name'] : '');

        if (isset($dataCrm['company']['contragent']) && !empty($dataCrm['company']['contragent']['INN'])) {
            $this->setCompanyInn($dataCrm['company']['contragent']['INN']);
        }

        return $this;
    }

    public function setDataCrm($dataCrm)
    {
        $this->dataCrm = $dataCrm;
        return $this;
    }

    public function getData()
    {
        return new RetailcrmCustomerBuilderResult($this->corporateCustomer, $this->corporateAddress);
    }

    public function reset()
    {
        $this->corporateCustomer = new Customer();
        $this->customerBuilder = null;
        $this->corporateAddress = null;

        return $this;
    }

    /**
     * Build customer for corporate customer
     */
    private function buildCustomer()
    {
        if (empty($this->customerBuilder)) {
            $this->customerBuilder = new RetailcrmCustomerBuilder();
        }

        if (!empty($this->dataCrm)) {
            $this->customerBuilder->setDataCrm($this->dataCrm);
        }

        if (isset($this->dataCrm['address'])) {
            $this->customerBuilder->build();
        }

        $this->corporateCustomer = $this->customerBuilder->getData()->getCustomer();
        $this->corporateAddress = $this->customerBuilder->getData()->getCustomerAddress();
    }

    public function build()
    {
        $this->buildCustomer();

        if (!empty($this->corporateAddress)) {

            if (empty($this->corporateAddress->alias) || $this->corporateAddress->alias == 'default') {
                $this->corporateAddress->alias = '--';
            }

            $this->corporateAddress->vat_number = !empty($this->companyInn) ? $this->companyInn : '';
            $this->corporateAddress->company = !empty($this->companyName) ? $this->companyName : '';

            if (!empty($this->companyName) && (empty($this->corporateCustomer->firstname) || $this->corporateCustomer->firstname == '--')) {
                $this->corporateCustomer->firstname = $this->companyName;
            }
        }

        $this->corporateCustomer = RetailcrmTools::filter(
            'RetailcrmFilterSaveCorporateCustomer',
            $this->corporateCustomer,
            array(
                'dataCrm' => $this->dataCrm,
            ));

        $this->corporateAddress = RetailcrmTools::filter(
            'RetailcrmFilterSaveCorporateCustomerAddress',
            $this->corporateAddress,
            array(
                'dataCrm' => $this->dataCrm,
            ));

        return $this;
    }
}

