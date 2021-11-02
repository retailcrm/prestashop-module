<?php

if (class_exists('LegacyTests\Unit\ContextMocker')) {
    class_alias('LegacyTests\Unit\ContextMocker', 'Tests\Unit\ContextMocker');
}

abstract class RetailcrmTestCase extends \PHPUnit\Framework\TestCase
{
    protected $contextMock;

    public function setUp()
    {
        parent::setUp();

        if (version_compare(_PS_VERSION_, '1.7', '>')) {
            $contextMocker = new \Tests\Unit\ContextMocker();
            $this->contextMock = $contextMocker->mockContext();
        }
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
}
