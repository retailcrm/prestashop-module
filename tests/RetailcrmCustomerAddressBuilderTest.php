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

class RetailcrmCustomerAddressBuilderTest extends RetailcrmTestCase
{
    protected $customerAddress;
    protected $customer;

    protected function setUp()
    {
        parent::setUp();
    }

    public function testBuild()
    {
        $this->customerAddress = new RetailcrmCustomerAddressBuilder();

        $this->customerAddress
            ->setDataCrm($this->getDataBuilder())
            ->build()
        ;

        $this->assertNotEmpty($this->customerAddress->getData());
    }

    public function setCustomerAddress()
    {
        $this->customerAddress = new RetailcrmCustomerAddressBuilder();

        $this->customerAddress
            ->setCustomerAddress(new AddressCore(9999))
            ->build()
        ;

        $this->assertNotEmpty($this->customerAddress->getData());
    }

    public function testCorrectDataAddress()
    {
        $this->customerAddress = new RetailcrmCustomerAddressBuilder();

        $this->customerAddress
            ->setDataCrm($this->getDataBuilder())
            ->setFirstName('Test')
            ->setLastName('Test2')
            ->setPhone('+7999999999')
            ->build()
        ;

        $addressResult = $this->customerAddress->getData();
        $this->assertEquals('Test', $addressResult->firstname);
        $this->assertEquals('Test2', $addressResult->lastname);
        $this->assertEquals(Country::getByIso('RU'), $addressResult->id_country);
        $this->assertEquals('г. Москва', $addressResult->city);
        $this->assertEquals(State::getIdByName('Moscow'), $addressResult->id_state);
        $this->assertEquals('344004', $addressResult->postcode);
        $this->assertEquals('+7999999999', $addressResult->phone);
    }

    public function testAddressOverriding()
    {
        $this->customerAddress = new RetailcrmCustomerAddressBuilder();
        $this->customerAddress
            ->setDataCrm($this->getDataBuilder())
            ->setFirstName('Test')
            ->setLastName('Test2')
            ->setPhone('+7999999999')
            ->build()
        ;
        $addressResult = $this->customerAddress->getData();

        $this->customerAddress
            ->setCustomerAddress($addressResult)
            ->setDataCrm($this->getDataBuilderOverride())
            ->setFirstName('Test override')
            ->setPhone('+7111111111')
            ->build()
        ;

        $addressResultOverridden = $this->customerAddress->getData();
        $this->assertEquals('Test override', $addressResultOverridden->firstname);
        $this->assertEquals('Test2', $addressResultOverridden->lastname);
        $this->assertEquals(Country::getByIso('RU'), $addressResultOverridden->id_country);
        $this->assertEquals('г. Москва Override', $addressResultOverridden->city);
        $this->assertEquals(State::getIdByName('Moscow'), $addressResultOverridden->id_state);
        $this->assertEquals('444444', $addressResultOverridden->postcode);
        $this->assertEquals('+7111111111', $addressResultOverridden->phone);
    }

    private function getDataBuilder()
    {
        return [
            'id' => 9718,
            'countryIso' => 'RU',
            'region' => 'Moscow',
            'city' => 'г. Москва',
            'index' => '344004',
            'text' => 'MAY',
        ];
    }

    private function getDataBuilderOverride()
    {
        return [
            'id' => 9718,
            'city' => 'г. Москва Override',
            'index' => '444444',
        ];
    }
}
