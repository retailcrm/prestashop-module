<?php

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
            array(
                1 => 'delivery'
            )
        );

        $status = json_encode(
            array(
                9 => 'status',
                10 => 'new',
                11 => 'completed'
            )
        );

        $payment = json_encode(
            array(
                'ps_checkpayment' => 'ps_checkpayment',
                'bankwire' => 'bankwire',
                'cheque' => 'cheque'
            )
        );

        Configuration::updateValue('RETAILCRM_API_DELIVERY', $delivery);
        Configuration::updateValue('RETAILCRM_API_STATUS', $status);
        Configuration::updateValue('RETAILCRM_API_PAYMENT', $payment);
    }
}
