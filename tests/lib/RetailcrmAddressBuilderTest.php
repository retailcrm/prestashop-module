<?php

class RetailcrmAddressBuilderTest extends RetailcrmTestCase
{
    /** @var \RetailcrmAddressBuilder $builder */
    protected $builder;

    /** @var \AddressCore|Address $address */
    protected $address;

    /** @var int $defaultLang */
    protected $defaultLang;

    /**
     * setUp test
     */
    public function setUp()
    {
        parent::setUp();

        $this->builder = new RetailcrmAddressBuilder();
        $this->defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');
        $address = new Address();
        $address->id_state = State::getIdByName('Alabama');
        $address->address1 = 'address1';
        $address->address2 = 'address2';
        $address->alias = 'test_address';
        $address->city = 'Montgomery';
        $address->id_country = Country::getByIso('us');
        $address->country = Country::getNameById($this->defaultLang, $address->id_country);
        $address->firstname = 'FirstName';
        $address->lastname = 'LastName';
        $address->phone = '123';
        $address->phone_mobile = '123';
    }

    public function testBuildRegular()
    {
        $result = $this->builder
            ->setAddress($this->address)
            ->setMode(RetailcrmAddressBuilder::MODE_CUSTOMER)
            ->setIsMain(true)
            ->setWithExternalId(true)
            ->setExternalIdSuffix('suffix')
            ->build()
            ->getDataArray();

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('address', $result);
        $this->assertFalse(array_key_exists('externalId', $result['address']));
        $this->assertFalse(array_key_exists('isMain', $result));
        $this->assertFalse(array_key_exists('isMain', $result['address']));
        $this->assertArrayHasKey('phones', $result);
        $this->assertNotEmpty($result['phones']);
        $this->assertFieldsNotEmpty($result['address']);
    }

    public function testBuildCorporate()
    {
        $result = $this->builder
            ->setAddress($this->address)
            ->setMode(RetailcrmAddressBuilder::MODE_CORPORATE_CUSTOMER)
            ->setIsMain(true)
            ->setWithExternalId(true)
            ->setExternalIdSuffix('suffix')
            ->build()
            ->getDataArray();

        $this->assertNotEmpty($result);
        $this->assertNotEmpty($result['externalId']);
        $this->assertTrue($result['isMain']);
        $this->assertFieldsNotEmpty($result);
    }

    public function testBuildOrder()
    {
        $result = $this->builder
            ->setAddress($this->address)
            ->setMode(RetailcrmAddressBuilder::MODE_ORDER_DELIVERY)
            ->setIsMain(true)
            ->setWithExternalId(true)
            ->setExternalIdSuffix('suffix')
            ->build()
            ->getDataArray();

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('delivery', $result);
        $this->assertArrayHasKey('address', $result['delivery']);
        $this->assertFalse(array_key_exists('externalId', $result['address']));
        $this->assertFalse(array_key_exists('isMain', $result));
        $this->assertFalse(array_key_exists('isMain', $result['address']));
        $this->assertArrayHasKey('countryIso', $result);
        $this->assertNotEmpty($result['countryIso']);
        $this->assertArrayHasKey('phone', $result);
        $this->assertArrayHasKey('additionalPhone', $result);
        $this->assertNotEmpty($result['phone']);
        $this->assertNotEmpty($result['additionalPhone']);
        $this->assertFieldsNotEmpty($result['delivery']['address'], array('countryIso'));
    }

    /**
     * Asserts address fields
     *
     * @param array $address
     */
    private function assertFieldsNotEmpty($address, $skip = array())
    {
        foreach (array_diff($this->getCheckableFields(), $skip) as $field) {
            $this->assertArrayHasKey($field, $address);
            $this->assertNotEmpty($address[$field]);
        }
    }

    /**
     * Returns address fields names
     *
     * @return string[]
     */
    private function getCheckableFields()
    {
        return array('index', 'city', 'countryIso', 'text', 'region');
    }
}
