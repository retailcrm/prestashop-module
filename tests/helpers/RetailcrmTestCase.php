<?php
/**
 * MIT License
 *
 * Copyright (c) 2021 DIGITAL RETAIL TECHNOLOGIES SL
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

if (class_exists('LegacyTests\Unit\ContextMocker')) {
    class_alias('LegacyTests\Unit\ContextMocker', 'Tests\Unit\ContextMocker');
}

abstract class RetailcrmTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RetailcrmProxy
     */
    private $apiMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
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
