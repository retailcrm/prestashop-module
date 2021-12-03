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

class RetailcrmCorporateCustomerBuilderTest extends RetailcrmTestCase
{
    protected $corporateCustomer;

    protected function setUp()
    {
        parent::setUp();
    }

    public function testBuild()
    {
        $this->corporateCustomer = new RetailcrmCorporateCustomerBuilder();

        $this->corporateCustomer
            ->setDataCrm($this->getDataCrm())
            ->build()
        ;

        $result = new RetailcrmCustomerBuilderResult(null, null);

        $this->assertInstanceOf(get_class($result), $this->corporateCustomer->getData());
    }

    public function testGetData()
    {
        $this->corporateCustomer = new RetailcrmCorporateCustomerBuilder();

        $this->corporateCustomer
            ->setDataCrm($this->getDataCrm())
            ->build()
        ;

        $builtCustomer = $this->corporateCustomer->getData()->getCustomer();
        $builtAddress = $this->corporateCustomer->getData()->getCustomerAddress();

        $this->assertTrue($builtCustomer instanceof Customer
            || $builtCustomer instanceof CustomerCore);

        $this->assertTrue($builtAddress instanceof Address
            || $builtAddress instanceof AddressCore);
    }

    public function testCorrectDataCorporateCustomer()
    {
        $this->corporateCustomer = new RetailcrmCorporateCustomerBuilder();

        $this->corporateCustomer
            ->setDataCrm($this->getDataCrm())
            ->setCustomer($this->getDataBuilder())
            ->setCompanyName('Test')
            ->setCompanyInn(5666)
            ->build()
        ;

        $customerResult = $this->corporateCustomer->getData()->getCustomer();
        $this->assertEquals('April', $customerResult->firstname);
        $this->assertEquals('Iphone', $customerResult->lastname);
        $this->assertFalse($customerResult->newsletter);
        $this->assertEquals('1997-04-09', $customerResult->birthday);
        $this->assertEquals(2, $customerResult->id_gender);
        $this->assertEquals('hello@world.ru', $customerResult->email);

        $addressResult = $this->corporateCustomer->getData()->getCustomerAddress();

        $this->assertEquals(Country::getByIso('RU'), $addressResult->id_country);
        $this->assertEquals('г. Москва', $addressResult->city);
        $this->assertEquals('Test', $addressResult->company);
        $this->assertEquals(5666, $addressResult->vat_number);
    }

    private function getDataBuilder()
    {
        return [
            'type' => 'customer_corporate',
            'id' => 9090,
            'nickName' => 'TestName',
            'mainAddress' => [
                'id' => 4001,
                'name' => 'Test',
            ],
            'createdAt' => '2020-02-17 07:44:31',
            'vip' => false,
            'bad' => false,
            'site' => 'opencart',
            'tags' => [],
            'marginSumm' => 0,
            'totalSumm' => 0,
            'averageSumm' => 0,
            'ordersCount' => 0,
            'costSumm' => 0,
            'customFields' => [],
            'personalDiscount' => 0,
            'mainCustomerContact' => [
                'id' => 37,
                'customer' => [
                    'id' => 9089,
                ],
                'companies' => [],
            ],
            'mainCompany' => [
                'id' => 34,
                'name' => 'Test',
                'contragentInn' => 5666,
            ],
        ];
    }

    private function getDataCrm()
    {
        return [
            'type' => 'customer',
            'id' => 9000,
            'externalId' => '1777754',
            'isContact' => false,
            'createdAt' => '2020-04-09 16:55:59',
            'vip' => true,
            'bad' => true,
            'site' => '127-0-0-1-8080',
            'contragent' => [
                'contragentType' => 'individual',
            ],
            'subscribed' => false,
            'tags' => [],
            'marginSumm' => 0,
            'totalSumm' => 0,
            'averageSumm' => 0,
            'ordersCount' => 0,
            'costSumm' => 0,
            'customFields' => [],
            'personalDiscount' => 0,
            'address' => [
                'id' => 9718,
                'countryIso' => 'RU',
                'region' => 'Moscow',
                'city' => 'г. Москва',
                'index' => '344004',
                'text' => 'MAY',
            ],
            'segments' => [],
            'firstName' => 'April',
            'lastName' => 'Iphone',
            'email' => 'hello@world.ru',
            'sex' => 'female',
            'birthday' => '1997-04-09',
        ];
    }
}
