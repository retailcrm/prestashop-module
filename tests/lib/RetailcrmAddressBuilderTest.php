<?php

class RetailcrmAddressBuilderTest extends RetailcrmTestCase
{
    /** @var \AddressCore|Address */
    protected $address;

    /** @var int */
    protected $defaultLang;

    /**
     * setUp test
     */
    public function setUp()
    {
        parent::setUp();

        $this->defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
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
        $address->postcode = '333000';
        $this->address = $address;
    }

    public function testBuildRegular()
    {
        $builder = new RetailcrmAddressBuilder();
        $result = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_CUSTOMER)
            ->setAddress($this->address)
            ->setIsMain(true)
            ->setWithExternalId(true)
            ->setExternalIdSuffix('suffix')
            ->build()
            ->getDataArray()
        ;

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
        $builder = new RetailcrmAddressBuilder();
        $result = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_CORPORATE_CUSTOMER)
            ->setAddress($this->address)
            ->setIsMain(true)
            ->setWithExternalId(true)
            ->setExternalIdSuffix('suffix')
            ->build()
            ->getDataArray()
        ;

        $this->assertNotEmpty($result);
        $this->assertNotEmpty($result['externalId']);
        $this->assertTrue($result['isMain']);
        $this->assertFieldsNotEmpty($result);
    }

    public function testBuildOrder()
    {
        $builder = new RetailcrmAddressBuilder();
        $result = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_ORDER_DELIVERY)
            ->setAddress($this->address)
            ->setIsMain(true)
            ->setWithExternalId(true)
            ->setExternalIdSuffix('suffix')
            ->build()
            ->getDataArray()
        ;

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('delivery', $result);
        $this->assertArrayHasKey('address', $result['delivery']);
        $this->assertFalse(array_key_exists('externalId', $result['delivery']['address']));
        $this->assertFalse(array_key_exists('isMain', $result));
        $this->assertFalse(array_key_exists('isMain', $result['delivery']['address']));
        $this->assertArrayHasKey('countryIso', $result);
        $this->assertNotEmpty($result['countryIso']);
        $this->assertArrayHasKey('phone', $result);
        $this->assertArrayHasKey('additionalPhone', $result);
        $this->assertNotEmpty($result['phone']);
        $this->assertNotEmpty($result['additionalPhone']);
        $this->assertFieldsNotEmpty($result['delivery']['address'], ['countryIso']);
    }

    /**
     * @dataProvider getAddressLines
     */
    public function testAddressLineConcatenation($addressLine1, $addressLine2, $addressText)
    {
        $address = $this->address;
        $address->address1 = $addressLine1;
        $address->address2 = $addressLine2;

        $builder = new RetailcrmAddressBuilder();
        $result = $builder
            ->setMode(RetailcrmAddressBuilder::MODE_ORDER_DELIVERY)
            ->setAddress($this->address)
            ->setIsMain(true)
            ->setWithExternalId(true)
            ->setExternalIdSuffix('suffix')
            ->build()
            ->getDataArray()
        ;

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('delivery', $result);
        $this->assertArrayHasKey('address', $result['delivery']);
        $this->assertArrayHasKey('text', $result['delivery']['address']);
        $this->assertEquals($result['delivery']['address']['text'], $addressText);
    }

    /**
     * Asserts address fields
     *
     * @param array $address
     */
    private function assertFieldsNotEmpty($address, $skip = [])
    {
        foreach (array_diff($this->getCheckableFields(), $skip) as $field) {
            $this->assertArrayHasKey($field, $address);
            $this->assertNotEmpty($address[$field]);
        }
    }

    public function getAddressLines()
    {
        return [
            [
                'addressline 1',
                'addressline 2',
                'addressline 1' . RetailcrmAddressBuilder::ADDRESS_LINE_DIVIDER . 'addressline 2',
            ],
            [
                'addressline 1',
                '',
                'addressline 1',
            ],
            [
                '',
                'addressline 2',
                RetailcrmAddressBuilder::ADDRESS_LINE_DIVIDER . 'addressline 2',
            ],
        ];
    }

    /**
     * Returns address fields names
     *
     * @return string[]
     */
    private function getCheckableFields()
    {
        return ['index', 'city', 'countryIso', 'text', 'region'];
    }
}
