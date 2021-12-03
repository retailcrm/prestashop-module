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

class RetailcrmCatalogTest extends RetailcrmTestCase
{
    protected $data;

    protected function setUp()
    {
        parent::setUp();

        $catalog = new RetailcrmCatalog();
        $this->data = $catalog->getData();
    }

    public function testCatalog()
    {
        $this->assertInternalType('array', $this->data);
        $this->assertCount(2, $this->data);

        $categories = $this->data[0];
        $products = $this->data[1];

        $this->assertNotEmpty($categories);
        $this->assertTrue($products->valid());

        foreach ($categories as $category) {
            $this->assertNotEmpty($category);
            $this->assertArrayHasKey('id', $category);
            $this->assertArrayHasKey('parentId', $category);
            $this->assertArrayHasKey('name', $category);
        }

        foreach ($products as $product) {
            $this->assertNotEmpty($product);
            $this->assertArrayHasKey('id', $product);
            $this->assertArrayHasKey('productId', $product);
            $this->assertArrayHasKey('name', $product);
            $this->assertArrayHasKey('productName', $product);
            $this->assertArrayHasKey('url', $product);
            $this->assertRegExp('/http/', $product['url']);
            $this->assertArrayHasKey('price', $product);
        }
    }

    public function testIsPricesWithTax()
    {
        $products = $this->data[1];
        $productsPresta = [];
        $productsPrestaList = Product::getProducts(
            (int) Configuration::get('PS_LANG_DEFAULT'),
            0,
            0,
            'name',
            'asc'
        );

        foreach ($productsPrestaList as $productData) {
            $productsPresta[$productData['id_product']] = $productData;
        }

        unset($productsPrestaList);

        foreach ($products as $product) {
            $this->assertArrayHasKey('productId', $product);
            $this->assertArrayHasKey('price', $product);

            $prestaProduct = $productsPresta[$product['productId']];
            $price = !empty($prestaProduct['rate'])
                ? round($prestaProduct['price'], 2) + (round($prestaProduct['price'], 2) * $prestaProduct['rate'] / 100)
                : round($prestaProduct['price'], 2);

            if (false !== strpos($product['id'], '#')) {
                $offerId = explode('#', $product['id']);
                $offerId = $offerId[1];
                $offerCombination = new Combination($offerId);

                $offerCombinationPrice = !empty($prestaProduct['rate'])
                    ? round($offerCombination->price, 2) + (round($offerCombination->price, 2) * $prestaProduct['rate'] / 100)
                    : round($offerCombination->price, 2);
                $offerPrice = round($offerCombinationPrice, 2) + $price;
                $offerPrice = 0 < $offerPrice ? $offerPrice : $price;

                $this->assertEquals(round($offerPrice, 2), round($product['price'], 2));
            } else {
                $this->assertEquals(round($price, 2), round($product['price'], 2));
            }
        }
    }

    public function testIcmlGenerate()
    {
        $icml = new RetailcrmIcml(Configuration::get('PS_SHOP_NAME'), _PS_ROOT_DIR_ . '/retailcrm.xml');
        $icml->generate($this->data[0], $this->data[1]);
        $this->assertFileExists(_PS_ROOT_DIR_ . '/retailcrm.xml');
        $xml = simplexml_load_file(_PS_ROOT_DIR_ . '/retailcrm.xml');
        $this->assertNotFalse($xml);
    }
}
