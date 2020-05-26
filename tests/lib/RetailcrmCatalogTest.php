<?php

class RetailcrmCatalogTest extends RetailcrmTestCase
{
    protected $data;

    public function setUp()
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
        $this->assertNotEmpty($products);

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
        $productsPresta = array();
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

            if (strpos($product['id'], '#') !== false) {
                $offerId = explode('#', $product['id']);
                $offerId = $offerId[1];
                $offerCombination = new Combination($offerId);

                $offerCombinationPrice = !empty($prestaProduct['rate'])
                    ? round($offerCombination->price, 2) + (round($offerCombination->price, 2) * $prestaProduct['rate'] / 100)
                    : round($offerCombination->price, 2);
                $offerPrice = round($offerCombinationPrice, 2) + $price;
                $offerPrice = $offerPrice > 0 ? $offerPrice : $price;

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
        $this->assertNotEquals(false, $xml);
    }
}
