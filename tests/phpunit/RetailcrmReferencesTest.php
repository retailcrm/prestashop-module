<?php

class RetailcrmReferencesTest extends RetailcrmTestCase
{
    private $retailcrmReferences;

    public function setUp()
    {
        parent::setUp();

        $apiMock = $this->createMock('RetailcrmProxy');
        $this->retailcrmReferences = new RetailcrmReferences($apiMock);
        $this->retailcrmReferences->getSystemPaymentModules(false);
    }

    public function testCarriers()
    {
        $this->assertInternalType('array', $this->retailcrmReferences->carriers);
        $this->assertNotEmpty($this->retailcrmReferences->carriers);
        $this->assertArrayHasKey('name', $this->retailcrmReferences->carriers[0]);
        $this->assertArrayHasKey('id_carrier', $this->retailcrmReferences->carriers[0]);
    }

    public function testGetSystemPaymentModules()
    {
        $this->assertInternalType('array', $this->retailcrmReferences->payment_modules);
        $this->assertNotEmpty($this->retailcrmReferences->payment_modules);
        $this->assertArrayHasKey('name', $this->retailcrmReferences->payment_modules[0]);
        $this->assertArrayHasKey('code', $this->retailcrmReferences->payment_modules[0]);
        $this->assertArrayHasKey('id', $this->retailcrmReferences->payment_modules[0]);
    }

    public function testGetStatuses()
    {
        $statuses = $this->retailcrmReferences->getStatuses();

        $this->assertInternalType('array', $statuses);
        $this->assertNotEmpty($statuses);
    }
}
