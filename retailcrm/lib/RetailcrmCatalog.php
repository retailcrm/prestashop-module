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

class RetailcrmCatalog
{
    public $default_lang;
    public $default_currency;
    public $default_country;
    public $protocol;
    public $version;
    public $link;
    public $home_category;

    public function __construct()
    {
        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');

        $this->protocol = (Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://';
        $this->version = substr(_PS_VERSION_, 0, 3);
        $this->link = new Link();
        $this->home_category = Configuration::get('PS_HOME_CATEGORY');
    }

    public function getData()
    {
        return [$this->getCategories(), $this->getOffers()];
    }

    public function getCategories()
    {
        $categories = [];
        $categoriesIds = [];

        $types = Category::getCategories($this->default_lang, true, false);

        foreach ($types as $category) {
            $categoryId = (empty($category['id_category']) && isset($category['id']))
                ? $category['id'] : $category['id_category'];

            if (!self::isCategoryActive(new Category($categoryId))) {
                continue;
            }

            $picture = $this->link->getCatImageLink($category['link_rewrite'], $categoryId, 'category_default');

            $categoriesIds[] = $categoryId;
            $categories[] = [
                'id' => $categoryId,
                'parentId' => self::getParentCategoryId($categoryId, $category['id_parent']),
                'name' => htmlspecialchars($category['name']),
                'picture' => $picture ? $this->protocol . $picture : '',
            ];
        }

        foreach ($categories as $key => $innerCategory) {
            if (isset($innerCategory['parentId'])
                && !empty($innerCategory['parentId'])
                && !in_array($innerCategory['parentId'], $categoriesIds)
            ) {
                $innerCategory['parentId'] = $this->home_category;
                $categories[$key] = $innerCategory;
            }
        }

        return $categories;
    }

    public function getOffers()
    {
        $productsCount = 0;
        $offersCount = 0;

        $id_lang = $this->default_lang;
        $homeCategory = $this->home_category;

        $inactiveCategories = [];
        $categoriesIds = [];

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

            $categoriesIds[] = $categoryId;
        }

        $limit = 2000;
        $start = 0;
        $count = self::getProductsCount($id_lang);

        if (0 < $count) {
            do {
                $products = Product::getProducts($id_lang, $start, $limit, 'name', 'asc');

                foreach ($products as $product) {
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
                    ++$productsCount;

                    if ('1.3' == $this->version) {
                        $available_for_order = $product['active'] && $product['quantity'];
                    } else {
                        $available_for_order = $product['active'] && $product['available_for_order'];
                    }

                    $crewrite = Category::getLinkRewrite($product['id_category_default'], $id_lang);
                    $url = $this->link->getProductLink($product['id_product'], $product['link_rewrite'], $crewrite);

                    if (!empty($product['wholesale_price'])) {
                        $purchasePrice = round($product['wholesale_price'], 2);
                    } else {
                        $purchasePrice = null;
                    }

                    $price = !empty($product['rate'])
                        ? round($product['price'], 2) + (round($product['price'], 2) * $product['rate'] / 100)
                        : round($product['price'], 2);

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

                    $weight = $this->getWeightInKg($product['weight']);

                    $width = round($product['width'], 3);
                    $height = round($product['height'], 3);
                    $depth = round($product['depth'], 3);

                    if (0.0 !== $width && 0.0 !== $height) {
                        $dimensions = implode('/', [$depth, $width, $height]);
                    } else {
                        $dimensions = null;
                    }

                    $offers = Product::getProductAttributesIds($product['id_product']);

                    if (!empty($offers)) {
                        $offersCount += count($offers);
                        $productForCombination = new Product($product['id_product']);

                        foreach ($offers as $offer) {
                            $combinations = $productForCombination->getAttributeCombinationsById($offer['id_product_attribute'], $id_lang);

                            if (!empty($combinations)) {
                                foreach ($combinations as $combination) {
                                    $arSet = [
                                        'group_name' => $combination['group_name'],
                                        'id_attribute_group' => $combination['id_attribute_group'],
                                        'attribute_name' => $combination['attribute_name'],
                                    ];

                                    $arComb[] = $arSet;
                                }
                            }

                            $covers = Image::getImages($id_lang, $product['id_product'], $offer['id_product_attribute']);
                            $pictures = $this->getPictures($covers, $product, true);

                            if (!$pictures) {
                                $image = Image::getCover($product['id_product']);
                                $picture = $this->protocol . $this->link->getImageLink($product['link_rewrite'], $image['id_image'], 'large_default');
                                $pictures[] = $picture;
                            }

                            if ('1.3' == $this->version) {
                                $quantity = $product['quantity'];
                            } else {
                                $quantity = (int) StockAvailable::getQuantityAvailableByProduct($product['id_product'], $offer['id_product_attribute']);
                            }

                            $offerCombination = new Combination($offer['id_product_attribute']);

                            $offerCombinationPrice = !empty($product['rate'])
                                ? round($offerCombination->price, 2) + (round($offerCombination->price, 2) * $product['rate'] / 100)
                                : round($offerCombination->price, 2);

                            $offerPrice = round($offerCombinationPrice, 2) + $price;
                            $offerPrice = 0 < $offerPrice ? $offerPrice : $price;

                            if (0 < $offerCombination->wholesale_price) {
                                $offerPurchasePrice = round($offerCombination->wholesale_price, 2);
                            } else {
                                $offerPurchasePrice = $purchasePrice;
                            }

                            if (!empty($offerCombination->reference)) {
                                $offerArticle = htmlspecialchars($offerCombination->reference);
                            } else {
                                $offerArticle = $article;
                            }

                            $item = [
                                'id' => $product['id_product'] . '#' . $offer['id_product_attribute'],
                                'productId' => $product['id_product'],
                                'productActivity' => ($available_for_order) ? 'Y' : 'N',
                                'name' => htmlspecialchars(strip_tags(Product::getProductName($product['id_product'], $offer['id_product_attribute']))),
                                'productName' => htmlspecialchars(strip_tags($product['name'])),
                                'categoryId' => $categoriesLeft,
                                'picture' => $pictures,
                                'url' => $url,
                                'quantity' => 0 < $quantity ? $quantity : 0,
                                'purchasePrice' => $offerPurchasePrice,
                                'price' => round($offerPrice, 2),
                                'vendor' => $vendor,
                                'article' => $offerArticle,
                                'weight' => $weight,
                                'dimensions' => $dimensions,
                                'vatRate' => $product['rate'],
                            ];

                            if (!empty($combinations)) {
                                foreach ($arComb as $itemComb) {
                                    $item['combination'][$itemComb['id_attribute_group']] = [
                                        'group_name' => mb_strtolower($itemComb['group_name']),
                                        'attribute_name' => htmlspecialchars($itemComb['attribute_name']),
                                    ];
                                }
                            }

                            unset($arComb);

                            yield RetailcrmTools::filter(
                                'RetailcrmFilterProcessOffer',
                                $item,
                                [
                                    'product' => $product,
                                    'offer' => $offer,
                                ]
                            );
                        }
                    } else {
                        ++$offersCount;

                        $covers = Image::getImages($id_lang, $product['id_product'], null);
                        $pictures = $this->getPictures($covers, $product);

                        if ('1.3' == $this->version) {
                            $quantity = $product['quantity'];
                        } else {
                            $quantity = (int) StockAvailable::getQuantityAvailableByProduct($product['id_product']);
                        }

                        $item = [
                            'id' => $product['id_product'],
                            'productId' => $product['id_product'],
                            'productActivity' => ($available_for_order) ? 'Y' : 'N',
                            'name' => htmlspecialchars(strip_tags($product['name'])),
                            'productName' => htmlspecialchars(strip_tags($product['name'])),
                            'categoryId' => $categoriesLeft,
                            'picture' => $pictures,
                            'url' => $url,
                            'quantity' => 0 < $quantity ? $quantity : 0,
                            'purchasePrice' => round($purchasePrice, 2),
                            'price' => $price,
                            'vendor' => $vendor,
                            'article' => $article,
                            'weight' => $weight,
                            'dimensions' => $dimensions,
                            'vatRate' => $product['rate'],
                        ];

                        yield RetailcrmTools::filter(
                            'RetailcrmFilterProcessOffer',
                            $item,
                            [
                                'product' => $product,
                            ]
                        );
                    }
                }

                $start += $limit;
            } while ($start < $count && 0 < count($products));
        }

        RetailcrmCatalogHelper::setIcmlFileInfo($productsCount, $offersCount);
    }

    private function getPictures(array $covers, array $product, $offers = false)
    {
        $pictures = [];
        foreach ($covers as $cover) {
            $picture = $this->protocol . $this->link->getImageLink($product['link_rewrite'], $product['id_product'] . '-' . $cover['id_image'], 'large_default');

            if (false === $offers && $cover['cover']) {
                array_unshift($pictures, $picture);
            } else {
                $pictures[] = $picture;
            }
        }

        return $pictures;
    }

    private static function getProductsCount($id_lang, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $front = true;
        if (!in_array($context->controller->controller_type, ['front', 'modulefront'])) {
            $front = false;
        }

        $sql = 'SELECT COUNT(product_shop.`id_product`) AS nb
                FROM `' . _DB_PREFIX_ . 'product` p
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . Shop::addSqlRestrictionOnLang('pl') . ')
                WHERE pl.`id_lang` = ' . (int) $id_lang .
            ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '');

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
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
     * @param int $categoryId
     * @param int $parentId
     *
     * @return null
     */
    private static function getParentCategoryId($categoryId, $parentId)
    {
        $home = Configuration::get('PS_HOME_CATEGORY');

        if (empty($parentId)) {
            return null;
        }

        if ($categoryId == $home) {
            return null;
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

    private function getWeightInKg($weight)
    {
        if (0 == $weight) {
            return null;
        }

        $mg = 1 / 1000 / 1000;
        $g = 1 / 1000;
        $ton = 1 * 1000;
        $oz = 1 / 35.3;
        $pd = 1 * 2.2;
        $st = 1 * 6.35;

        $weightUnits = [
            'mg' => $mg,
            'мг' => $mg,
            'miligramo' => $mg,
            'миллиграмм' => $mg,
            'milligram' => $mg,

            'g' => $g,
            'gram' => $g,
            'grammo' => $g,
            'г' => $g,
            'гр' => $g,
            'грамм' => $g,

            'kg' => 1,
            'kilogram' => 1,
            'kilogramme' => 1,
            'kilo' => 1,
            'kilogramo' => 1,

            'ton' => $ton,
            'т' => $ton,
            'тонна' => $ton,
            'tonelada' => $ton,
            'toneladas' => $ton,

            'oz' => $oz,
            'унция' => $oz,
            'ounce' => $oz,
            'onza' => $oz,

            'pd' => $pd,
            'фунт' => $pd,
            'pound' => $pd,
            'lb' => $pd,
            'libra' => $pd,
            'paladio' => $pd,

            'st' => $st,
            'стоун' => $st,
            'stone' => $st,
        ];

        $weightUnits = RetailcrmTools::filter('RetailcrmFilterWeight', $weightUnits);

        $weightUnit = Configuration::get('PS_WEIGHT_UNIT');

        if (isset($weightUnits[$weightUnit])) {
            return $weight * $weightUnits[$weightUnit];
        }

        return $weight;
    }
}
