<?php

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
