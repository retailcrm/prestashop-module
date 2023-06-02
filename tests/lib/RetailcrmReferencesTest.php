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

class RetailcrmReferencesTest extends RetailcrmTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $apiMock = $this->getApiMock(
            [
                'sitesList',
                'credentials',
                'isSuccessful',
                'paymentTypesList',
                'deliveryTypesList',
            ]
        );

        $this->apiClientMock->expects($this->any())
            ->method('isSuccessful')
            ->willReturn(new RetailcrmApiResponse('200', json_encode(['success' => true])))
        ;

        $this->apiClientMock->expects($this->any())
            ->method('credentials')
            ->willReturn(new RetailcrmApiResponse('200', json_encode(DataRetailcrmReferences::getCredentials())))
        ;

        $this->apiClientMock->expects($this->any())
            ->method('sitesList')
            ->willReturn(new RetailcrmApiResponse('200', json_encode(['sites' => [['code' => 'prestashop']]])))
        ;

        $this->retailcrmReferences = new RetailcrmReferences($apiMock);
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
        $this->retailcrmReferences->getSystemPaymentModules(false);

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

    public function testGetApiDeliveryTypes()
    {
        $this->apiClientMock->expects($this->once())
            ->method('deliveryTypesList')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(DataRetailcrmReferences::getApiDeliveryTypes())
                )
            )
        ;

        $deliveryTypes = $this->retailcrmReferences->getApiDeliveryTypes();

        $this->assertInternalType('array', $deliveryTypes);
        $this->assertArrayHasKey('code', $deliveryTypes[0]);
        $this->assertArrayHasKey('code', $deliveryTypes[1]);
        $this->assertEquals('delivery1', $deliveryTypes[0]['code']);
        $this->assertEquals('delivery3', $deliveryTypes[1]['code']);
    }

    public function testGetApiPaymentTypes()
    {
        $this->apiClientMock->expects($this->once())
            ->method('paymentTypesList')
            ->willReturn(
                new RetailcrmApiResponse(
                    '200',
                    json_encode(DataRetailcrmReferences::getApiPaymentTypes())
                )
            )
        ;

        $paymentTypes = $this->retailcrmReferences->getApiPaymentTypes();

        $this->assertInternalType('array', $paymentTypes);
        $this->assertInternalType('array', $paymentTypes);
        $this->assertArrayHasKey('code', $paymentTypes[0]);
        $this->assertArrayHasKey('code', $paymentTypes[1]);
        $this->assertEquals('payment1', $paymentTypes[0]['code']);
        $this->assertEquals('payment3', $paymentTypes[1]['code']);
    }
}
