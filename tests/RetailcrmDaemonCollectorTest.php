<?php

class RetailcrmDaemonCollectorTest extends RetailcrmTestCase
{
    const KEY = 'test-key';
    const ID = '1';

    public function testBuildJsWithCustomer()
    {
        $customer = new Customer;
        $customer->id = self::ID;

        $collector = new RetailcrmDaemonCollector(
            $customer,
            self::KEY
        );

        $js = $collector->buildScript()->getJs();

        $this->assertContains('customerId', $js);
        $this->assertContains('<script', $js);
        $this->assertContains('</script>', $js);
    }

    public function testBuildJsWithoutCustomer()
    {
        $customer = new Customer;

        $collector = new RetailcrmDaemonCollector(
            $customer,
            self::KEY
        );

        $js = $collector->buildScript()->getJs();

        $this->assertNotContains('customerId', $js);
        $this->assertContains('<script', $js);
        $this->assertContains('</script>', $js);
    }
}
