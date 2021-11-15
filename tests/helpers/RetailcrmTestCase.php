<?php

if (class_exists('LegacyTests\Unit\ContextMocker')) {
    class_alias('LegacyTests\Unit\ContextMocker', 'Tests\Unit\ContextMocker');
}

abstract class RetailcrmTestCase extends \PHPUnit\Framework\TestCase
{
    private $apiMock;

    protected $apiClientMock;

    protected $contextMock;

    protected function setUp()
    {
        parent::setUp();

        if (version_compare(_PS_VERSION_, '1.7', '>')) {
            $contextMocker = new \Tests\Unit\ContextMocker();
            $this->contextMock = $contextMocker->mockContext();
        }
    }

    protected function getApiMock(array $methods)
    {
        $this->apiClientMock = $this->apiMockBuilder($methods)->getMock();

        $this->apiMock = new RetailcrmProxy('https://test.test', 'test_key');
        $this->apiMock->setClient($this->apiClientMock);

        return $this->apiMock;
    }

    protected function setConfig()
    {
        $delivery = json_encode(
            [
                1 => 'delivery',
            ]
        );

        $status = json_encode(
            [
                9 => 'status',
                10 => 'new',
                11 => 'completed',
            ]
        );

        $payment = json_encode(
            [
                'ps_checkpayment' => 'ps_checkpayment',
                'bankwire' => 'bankwire',
                'cheque' => 'cheque',
            ]
        );

        Configuration::updateValue('RETAILCRM_API_DELIVERY', $delivery);
        Configuration::updateValue('RETAILCRM_API_STATUS', $status);
        Configuration::updateValue('RETAILCRM_API_PAYMENT', $payment);
    }

    private function apiMockBuilder(array $methods)
    {
        return $this->getMockBuilder('RetailcrmApiClientV5')
            ->disableOriginalConstructor()
            ->setMethods($methods)
        ;
    }
}
