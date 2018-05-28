<?php

abstract class RetailcrmTestCase extends \PHPUnit\Framework\TestCase
{
    protected $contextMock;

    public function setUp()
    {
        parent::setUp();

        $contextMocker = new \Tests\Unit\ContextMocker();
        $this->contextMock = $contextMocker->mockContext();
    }

    protected function setConfig()
    {
        $delivery = json_encode(array('delivery' => 'delivery'));
        $status = json_encode(array('status' => 'status', 'new' => 'new', 'completed'=> 'completed'));
        $payment = json_encode(array('ps_checkpayment' => 'ps_checkpayment', 'bankwire' => 'bankwire', 'cheque' => 'cheque'));

        Configuration::updateValue('RETAILCRM_API_DELIVERY', $delivery);
        Configuration::updateValue('RETAILCRM_API_STATUS', $status);
        Configuration::updateValue('RETAILCRM_API_PAYMENT', $payment);
    }
}
