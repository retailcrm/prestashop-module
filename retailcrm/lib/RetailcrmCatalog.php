<?php
/**
 * MIT License
 *
 * Copyright (c) 2020 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
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
        $version = substr(_PS_VERSION_, 0, 3);
        $versionSplit = explode('.', $version);
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $shop_url = (Configuration::get('PS_SSL_ENABLED') ? _PS_BASE_URL_SSL_ : _PS_BASE_URL_);
        $protocol = (Configuration::get('PS_SSL_ENABLED')) ? "https://" : "http://";
        $homeCategory = Configuration::get('PS_HOME_CATEGORY');

        $items = array();
        $categories = array();
        $inactiveCategories = array();
        $categoriesIds = array();

        if ($currency->iso_code == 'RUB') {
            $currency->iso_code = 'RUR';
        }

        $currencies = Currency::getCurrencies();
        $link = new Link();
        $types = Category::getCategories($id_lang, true, false);

        foreach ($types as $category) {
            $categoryId = (empty($category['id_category']) && isset($category['id']))
                ? $category['id'] : $category['id_category'];

            if (!self::isCategoryActive(new Category($categoryId))) {
                if (!in_array($categoryId, $inactiveCategories)) {
                    $inactiveCategories[] = $categoryId;
                }

                continue;
            }

            $picture = $link->getCatImageLink($category['link_rewrite'], $categoryId, 'category_default');

            $categoriesIds[] = $categoryId;
            $categories[] = array(
                'id' => $categoryId,
                'parentId' => self::getParentCategoryId($category['id_parent']),
                'name' => $category['name'],
                'picture' => $picture ? $protocol . $picture : ''
            );
        }

        foreach ($categories as $key => $innerCategory) {
            if (isset($innerCategory['parentId'])
                && !empty($innerCategory['parentId'])
                && !in_array($innerCategory['parentId'], $categoriesIds)
            ) {
                $innerCategory['parentId'] = $homeCategory;
                $categories[$key] = $innerCategory;
            }
        }

        $products = Product::getProducts($id_lang, 0, 0, 'name', 'asc');

        foreach ($products AS $product) {
            $category = $product['id_category_default'];

            if (!in_array($category, $categoriesIds)) {
                continue;
            }

            $currentProductCategories = Product::getProductCategories($product['id_product']);
            $categoriesLeft = array_filter(
                $currentProductCategories,
                function ($val) use ($inactiveCategories, $categoriesIds, $homeCategory) {
                    if ($val == $homeCategory) {
                        return false;
                    }

                    if (in_array($val, $inactiveCategories)) {
                        return false;
                    }

                    return in_array($val, $categoriesIds);
                }
            );

            if (empty($categoriesLeft)) {
                continue;
            }

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

                    $offerCombination = new Combination($offer['id_product_attribute']);

                    $offerCombinationPrice = !empty($product['rate'])
                        ? round($offerCombination->price, 2) + (round($offerCombination->price, 2) * $product['rate'] / 100)
                        : round($offerCombination->price, 2);

                    $offerPrice = round($offerCombinationPrice, 2) + $price;
                    $offerPrice = $offerPrice > 0 ? $offerPrice : $price;

                    if ($offerCombination->wholesale_price > 0) {
                        $offerPurchasePrice = round($offerCombination->wholesale_price, 2);
                    } else {
                        $offerPurchasePrice = $purchasePrice; 
                    }

                    if (!empty($offerCombination->reference)) {
                        $offerArticle = htmlspecialchars($offerCombination->reference);
                    } else {
                        $offerArticle = $article;
                    }

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
                        'purchasePrice' => $offerPurchasePrice,
                        'price' => round($offerPrice, 2),
                        'vendor' => $vendor,
                        'article' => $offerArticle,
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

    /**
     * @param \Category|\CategoryCore $category
     *
     * @return bool
     */
    private static function isCategoryActive($category)
    {
        if ($category->id == Configuration::get('PS_HOME_CATEGORY')) {
            return true;
        }

        if (!$category->active) {
            return false;
        }

        if (!empty($category->id_parent)) {
            $parent = new Category($category->id_parent);

            if (!$parent->active) {
                return false;
            }

            return self::isCategoryActive($parent);
        }

        return $category->active;
    }

    /**
     * Returns active parent category
     *
     * @param int $parentId
     *
     * @return null
     */
    private static function getParentCategoryId($parentId)
    {
        $home = Configuration::get('PS_HOME_CATEGORY');

        if (empty($parentId)) {
            return $home;
        }

        if ($parentId == $home) {
            return $home;
        }

        /** @var \Category|\CategoryCore $category */
        $category = new Category($parentId);

        if (empty($category->id) || !$category->active) {
            return $home;
        }

        return $parentId;
    }
}
