<?php

class RetailcrmCorporateCustomerBuilderTest extends RetailcrmTestCase
{
    protected $corporateCustomer;

    public function setUp()
    {
        parent::setUp();
    }

    public function testBuild()
    {
        $this->corporateCustomer = new RetailcrmCorporateCustomerBuilder();

        $this->corporateCustomer
            ->setDataCrm($this->getDataCrm())
            ->build();

        $result = new RetailcrmCustomerBuilderResult(null, null);

        $this->assertInstanceOf(get_class($result), $this->corporateCustomer->getData());
    }

    public function testGetData()
    {
        $this->corporateCustomer = new RetailcrmCorporateCustomerBuilder();

        $this->corporateCustomer
            ->setDataCrm($this->getDataCrm())
            ->build();

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
            ->build();

        $customerResult = $this->corporateCustomer->getData()->getCustomer();

        $this->assertEquals('April', $customerResult->firstname);
        $this->assertEquals('Iphone', $customerResult->lastname);
        $this->assertEquals(false, $customerResult->newsletter);
        $this->assertEquals('1997-04-09', $customerResult->birthday);
        $this->assertEquals(2, $customerResult->id_gender);
        $this->assertEquals('hello@world.ru', $customerResult->email);

        $addressResult = $this->corporateCustomer->getData()->getCustomerAddress();

        $this->assertEquals(9718, $addressResult->id);
        $this->assertEquals(Country::getByIso('RU'), $addressResult->id_country);
        $this->assertEquals('г. Москва', $addressResult->city);
        $this->assertEquals('Test', $addressResult->company);
        $this->assertEquals(5666, $addressResult->vat_number);
    }

    private function getDataBuilder() {
        return array(
            'type' => 'customer_corporate',
            'id' => 9090,
            'nickName' => 'TestName',
            'mainAddress' => array(
                'id' => 4001,
                'name' => 'Test'
            ),
            'createdAt' => '2020-02-17 07:44:31',
            'vip' => false,
            'bad' => false,
            'site' => 'opencart',
            'tags' => array(),
            'marginSumm' => 0,
            'totalSumm' => 0,
            'averageSumm' => 0,
            'ordersCount' => 0,
            'costSumm' => 0,
            'customFields' => array(),
            'personalDiscount' => 0,
            'mainCustomerContact' => array(
                'id' => 37,
                'customer' => array(
                    'id' => 9089
                ),
                'companies' => array()
            ),
            'mainCompany' => array(
                'id' => 34,
                'name' => 'Test',
                'contragentInn' => 5666
            )
        );
    }

    private function getDataCrm() {
        return array(
            'type' => 'customer',
            'id' => 9000,
            'externalId' => '1777754',
            'isContact' => false,
            'createdAt' => '2020-04-09 16:55:59',
            'vip' => true,
            'bad' => true,
            'site' => '127-0-0-1-8080',
            'contragent' => array(
                'contragentType' => 'individual'
            ),
            'tags' => array(),
            'marginSumm' => 0,
            'totalSumm' => 0,
            'averageSumm' => 0,
            'ordersCount' => 0,
            'costSumm' => 0,
            'customFields' => array(),
            'personalDiscount' => 0,
            'address' => array(
                'id' => 9718,
                'countryIso' => 'RU',
                'region' => 'Moscow',
                'city' => 'г. Москва',
                'index' => '344004',
                'text' => 'MAY'
            ),
            'segments' => array(),
            'firstName' => 'April',
            'lastName' => 'Iphone',
            'email' => 'hello@world.ru',
            'sex' => 'female',
            'birthday' =>'1997-04-09'
        );
    }
}

