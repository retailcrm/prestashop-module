<?php

class RetailcrmCatalog
{
    public $default_lang;
    public $default_currency;
    public $default_country;

    public function __construct()
    {
        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
    }

    public function getData()
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $shop_url = (Configuration::get('PS_SSL_ENABLED') ? _PS_BASE_URL_SSL_ : _PS_BASE_URL_);
        $protocol = (Configuration::get('PS_SSL_ENABLED')) ? "https://" : "http://";

        $items = array();
        $categories = array();

        if ($currency->iso_code == 'RUB') {
            $currency->iso_code = 'RUR';
        }

        $currencies = Currency::getCurrencies();
        $link = new Link();
        $types = Category::getCategories($id_lang, true, false);

        foreach ($types as $category) {
            $picture = $link->getCatImageLink($category['link_rewrite'], $category['id_category'], 'category_default');

            $categories[] = array(
                'id' => $category['id_category'],
                'parentId' => $category['id_parent'],
                'name' => $category['name'],
                'picture' => $picture ? $protocol . $picture : ''
            );
        }

        $products = Product::getProducts($id_lang, 0, 0, 'name', 'asc');

        foreach ($products AS $product) {
            $category = $product['id_category_default'];

            if ($category == Configuration::get('PS_HOME_CATEGORY')) {
                $temp_categories = Product::getProductCategories($product['id_product']);

                foreach ($temp_categories AS $category) {
                    if ($category != Configuration::get('PS_HOME_CATEGORY'))
                        break;
                }

                if ($category == Configuration::get('PS_HOME_CATEGORY')) {
                    continue;
                }
            }

            $version = substr(_PS_VERSION_, 0, 3);

            if ($version == "1.3") {
                $available_for_order = $product['active'] && $product['quantity'];
            } else {
                $available_for_order = $product['active'] && $product['available_for_order'];
            }

            $crewrite = Category::getLinkRewrite($product['id_category_default'], $id_lang);
            $url = $link->getProductLink($product['id_product'], $product['link_rewrite'], $crewrite);

            if (!empty($product['wholesale_price'])) {
                $purchasePrice = round($product['wholesale_price'], 2);
            } else {
                $purchasePrice = null;
            }

            $price = !empty($product['rate'])
                ? round($product['price'], 2) + (round($product['price'], 2) * $product['rate'] / 100)
                : round($product['price'], 2)
            ;

            if (!empty($product['manufacturer_name'])) {
                $vendor = $product['manufacturer_name'];
            } else {
                $vendor = null;
            }

            if (!empty($product['reference'])) {
                $article = htmlspecialchars($product['reference']);
            } else {
                $article = null;
            }

            $weight = round($product['weight'], 2);
            if (empty($weight)) {
                $weight = null;
            }

            $width = round($product['width'], 3);
            $height = round($product['height'], 3);
            $depth = round($product['depth'], 3);
            
            if (!empty($width) && !empty($height)) {
                $dimensions = implode('/', array($width, $height, $depth));
            } else {
                $dimensions = null;
            }

            $productForCombination = new Product($product['id_product']);

            $offers = Product::getProductAttributesIds($product['id_product']);

            if (!empty($offers)) {
                foreach($offers as $offer) {
                    $combinations = $productForCombination->getAttributeCombinationsById($offer['id_product_attribute' ], $id_lang);

                    if (!empty($combinations)) {
                        foreach ($combinations as $combination) {
                            $arSet = array(
                                'group_name' => $combination['group_name'],
                                'attribute' => $combination['attribute_name'],
                            );

                            $arComb[] = $arSet;
                        }
                    }

                    $pictures = array();
                    $covers = Image::getImages($id_lang, $product['id_product'], $offer['id_product_attribute']);

                    foreach ($covers as $cover) {
                        $picture = $protocol . $link->getImageLink($product['link_rewrite'], $product['id_product'] . '-' . $cover['id_image'], 'large_default');
                        $pictures[] = $picture;
                    }

                    if (!$pictures) {
                        $image = Image::getCover($product['id_product']);
                        $picture = $protocol . $link->getImageLink($product['link_rewrite'], $image['id_image'], 'large_default');
                        $pictures[] = $picture;
                    }

                    if ($version == "1.3") {
                        $quantity = $product['quantity'];
                    } else {
                        $quantity = (int) StockAvailable::getQuantityAvailableByProduct($product['id_product'], $offer['id_product_attribute']);
                    }

                    $offerPrice = Combination::getPrice($offer['id_product_attribute']);
                    $offerPrice = $offerPrice > 0 ? $offerPrice : $price;

                    $item = array(
                        'id' => $product['id_product'] . '#' . $offer['id_product_attribute'],
                        'productId' => $product['id_product'],
                        'productActivity' => ($available_for_order) ? 'Y' : 'N',
                        'name' => htmlspecialchars(strip_tags(Product::getProductName($product['id_product'], $offer['id_product_attribute']))),
                        'productName' => htmlspecialchars(strip_tags($product['name'])),
                        'categoryId' => array($category),
                        'picture' => $pictures,
                        'url' => $url,
                        'quantity' => $quantity > 0 ? $quantity : 0,
                        'purchasePrice' => $purchasePrice,
                        'price' => round($offerPrice, 2),
                        'vendor' => $vendor,
                        'article' => $article,
                        'weight' => $weight,
                        'dimensions' => $dimensions
                    );

                    if (!empty($combinations)) {
                        foreach ($arComb as $itemComb) {
                            $item[mb_strtolower($itemComb['group_name'])] = htmlspecialchars($itemComb['attribute']);
                        }
                    }

                    $items[] = $item;
                }
            } else {
                $pictures = array();
                $covers = Image::getImages($id_lang, $product['id_product'], null);
                foreach($covers as $cover) {
                    $picture = $protocol . $link->getImageLink($product['link_rewrite'], $product['id_product'] . '-' . $cover['id_image'], 'large_default');
                    $pictures[] = $picture;
                }

                if ($version == "1.3") {
                    $quantity = $product['quantity'];
                } else {
                    $quantity = (int) StockAvailable::getQuantityAvailableByProduct($product['id_product']);
                }

                $item = array(
                    'id' => $product['id_product'],
                    'productId' => $product['id_product'],
                    'productActivity' => ($available_for_order) ? 'Y' : 'N',
                    'name' => htmlspecialchars(strip_tags($product['name'])),
                    'productName' => htmlspecialchars(strip_tags($product['name'])),
                    'categoryId' => array($category),
                    'picture' => $pictures,
                    'url' => $url,
                    'quantity' => $quantity > 0 ? $quantity : 0,
                    'purchasePrice' => round($purchasePrice, 2),
                    'price' => $price,
                    'vendor' => $vendor,
                    'article' => $article,
                    'weight' => $weight,
                    'dimensions' => $dimensions                
                );

                $items[] = $item;
            }
        }

        return array($categories, $items);
    }
}
