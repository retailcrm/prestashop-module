<?php

class RetailcrmCustomerBuilderTest extends RetailcrmTestCase
{
    protected $customer;

    protected function setUp()
    {
        parent::setUp();
    }

    public function testBuild()
    {
        $this->customer = new RetailcrmCustomerBuilder();

        $this->customer
            ->setDataCrm($this->getDataCrm())
            ->build()
        ;

        $result = new RetailcrmCustomerBuilderResult(null, null);

        $this->assertInstanceOf(get_class($result), $this->customer->getData());
    }

    public function testSetCustomer()
    {
        $this->customer = new RetailcrmCustomerBuilder();

        $this->customer
            ->setCustomer(new Customer(9719))
            ->build()
        ;

        $this->assertNotEmpty($this->customer->getData());
    }

    public function testGetData()
    {
        $this->customer = new RetailcrmCustomerBuilder();

        $this->customer
            ->setDataCrm($this->getDataCrm())
            ->build()
        ;

        $builtCustomer = $this->customer->getData()->getCustomer();
        $builtAddress = $this->customer->getData()->getCustomerAddress();

        $this->assertTrue($builtCustomer instanceof Customer
            || $builtCustomer instanceof CustomerCore);

        $this->assertTrue($builtAddress instanceof Address
            || $builtAddress instanceof AddressCore);
    }

    public function testBuildAddress()
    {
        $this->customer = new RetailcrmCustomerBuilder();

        $this->customer->buildAddress();

        $this->assertNull($this->customer->getData()->getCustomerAddress());
    }

    public function testCorrectDataCustomer()
    {
        $this->customer = new RetailcrmCustomerBuilder();

        $this->customer
            ->setDataCrm($this->getDataCrm())
            ->build()
        ;

        $customerResult = $this->customer->getData()->getCustomer();

        $this->assertEquals('April', $customerResult->firstname);
        $this->assertEquals('Iphone', $customerResult->lastname);
        $this->assertFalse($customerResult->newsletter);
        $this->assertEquals('1997-04-09', $customerResult->birthday);
        $this->assertEquals(2, $customerResult->id_gender);
        $this->assertEquals('hello@world.ru', $customerResult->email);

        $addressResult = $this->customer->getData()->getCustomerAddress();

        $this->assertEquals(Country::getByIso('RU'), $addressResult->id_country);
        $this->assertEquals('г. Москва', $addressResult->city);
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
