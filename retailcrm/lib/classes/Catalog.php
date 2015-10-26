<?php

class Catalog
{
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

        $items = array();
        $categories = array();

        if ($currency->iso_code == 'RUB') {
            $currency->iso_code = 'RUR';
        }

        // Get currencies
        $currencies = Currency::getCurrencies();

        // Get categories
        $types = Category::getCategories($id_lang, true, false);
        foreach ($types AS $category)
        {
            $categories[] = array(
                'id' => $category['id_category'],
                'parentId' => $category['id_parent'],
                'name' => $category['name']
            );
        }

        // Get products
        $products = Product::getProducts($id_lang, 0, 0, 'name', 'asc');

        foreach ($products AS $product)
        {
            // Check for home category
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

            $link = new Link();
            $cover = Image::getCover($product['id_product']);

            $picture = 'http://' . $link->getImageLink($product['link_rewrite'], $product['id_product'].'-'.$cover['id_image'], 'large_default');
            if (!(substr($picture, 0, strlen($shop_url)) === $shop_url)) {
                $picture = rtrim($shop_url,"/") . $picture;
            }

            $crewrite = Category::getLinkRewrite($product['id_category_default'], $id_lang);
            $url = $link->getProductLink($product['id_product'], $product['link_rewrite'], $crewrite);
            $version = substr(_PS_VERSION_, 0, 3);

            if ($version == "1.3")
                $available_for_order = $product['active'] && $product['quantity'];
            else {
                $prod = new Product($product['id_product']);
                $available_for_order = $product['active'] && $product['available_for_order'] && $prod->checkQty(1);
            }

            $item = array(
                'id' => $product['id_product'],
                'productId' => $product['id_product'],
                'productActivity' => ($available_for_order) ? 'Y' : 'N',
                'name' => htmlspecialchars(strip_tags($product['name'])),
                'productName' => htmlspecialchars(strip_tags($product['name'])),
                'categoryId' => array($category),
                'picture' => $picture,
                'url' => $url
            );

            if (!empty($product['wholesale_price'])) {
                $item['purchasePrice'] = round($product['wholesale_price'], 2);
            }

            $item['initialPrice'] = !empty($product['rate'])
                ? round($product['price'], 2) + (round($product['price'], 2) * $product['rate'] / 100)
                : round($product['price'], 2)
                ;


            if (!empty($product['manufacturer_name'])) {
                $item['vendor'] = $product['manufacturer_name'];
            }

            if (!empty($product['reference'])) {
                $item['article'] = htmlspecialchars($product['reference']);
            }

            $weight = round($product['weight'], 2);

            if (!empty($weight)) {
                $item['weight'] = $weight;
            }

            $width = round($product['width'], 2);
            $height = round($product['height'], 2);
            $depth = round($product['depth'], 2);

            if (!empty($width)) {
                if (!empty($height)) {
                    if (!empty($depth)) {
                        $item['size'] = implode('x', array($width, $height, $depth));
                    } else {
                        $item['size'] = implode('x', array($width, $height));
                    }
                }
            }

            $items[] = $item;

        }

        return array($categories, $items);

    }
}
