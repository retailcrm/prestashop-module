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

class RetailcrmCartUploaderTest extends RetailcrmTestCase
{
    const DEFAULT_UPD_CART_TIME = '2023-01-01 12:00:00';

    private $cart;
    private $apiMock;
    private $product;

    protected function setUp()
    {
        parent::setUp();

        $this->apiMock = $this->getApiMock(['cartGet', 'cartSet', 'cartClear']);

        $catalog = new RetailcrmCatalog();
        $data = $catalog->getData();
        $this->product = $data[1]->current();

        RetailcrmCartUploader::init();
        RetailcrmCartUploader::$site = 'test';
        RetailcrmCartUploader::setSyncDelay(Configuration::get(RetailCRM::SYNC_CARTS_DELAY));

        $this->cart = new Cart();
        $this->cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->cart->date_add = self::DEFAULT_UPD_CART_TIME;
        $this->cart->id_customer = 1;
        $this->cart->id_currency = 1;

        $this->cart->save();
        $this->cart->updateQty(1, $this->product['id']);
    }

    public function testCreateCart()
    {
        $this->apiClientMock->expects($this->once())
            ->method('cartGet')
            ->willReturn(new RetailcrmApiResponse('200', json_encode(['cart' => []])))
        ;

        $this->apiClientMock->expects($this->once())
            ->method('cartSet')
            ->willReturn(new RetailcrmApiResponse('200', json_encode(['success' => true])))
        ;

        RetailcrmCartUploader::$api = $this->apiMock;
        RetailcrmCartUploader::run();

        $this->assertNotEquals(self::DEFAULT_UPD_CART_TIME, $this->cart->date_upd);
    }

    public function testUpdateCart()
    {
        $this->apiClientMock->expects($this->any())
            ->method('cartGet')
            ->willReturn(new RetailcrmApiResponse('200', json_encode(['cart' => ['externalId' => $this->cart->id]])))
        ;

        $this->apiClientMock->expects($this->any())
            ->method('cartSet')
            ->willReturn(new RetailcrmApiResponse('200', json_encode(['success' => true])))
        ;

        $this->cart->updateQty(2, $this->product['id']);

        RetailcrmCartUploader::$api = $this->apiMock;
        RetailcrmCartUploader::run();

        $this->assertNotEquals(self::DEFAULT_UPD_CART_TIME, $this->cart->date_upd);
        $this->assertNotEquals($this->getAbandonedCartLastSync($this->cart->id), null);
    }

    private function getAbandonedCartLastSync($cartId)
    {
        $sql = 'SELECT `last_uploaded` FROM `' . _DB_PREFIX_ . 'retailcrm_abandonedcarts`
                WHERE `id_cart` = \'' . pSQL((int) $cartId) . '\'';
        $when = Db::getInstance()->getValue($sql);

        if (empty($when)) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $when);
    }

    // TODO: add method for work cart.
}
