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
class RetailcrmIcml
{
    protected $shop;
    protected $file;
    protected $properties;
    protected $params;
    protected $dd;
    protected $eCategories;
    protected $eOffers;

    public function __construct($shop, $file)
    {
        $this->shop = $shop;
        $this->file = $file;

        $this->properties = [
            'name',
            'productName',
            'price',
            'purchasePrice',
            'vendor',
            'picture',
            'url',
            'xmlId',
            'productActivity',
            'dimensions',
            'vatRate',
            'weight',
        ];

        $this->params = [
            'article' => 'Артикул',
            'color' => 'Цвет',
            'weight' => 'Вес',
            'tax' => 'Наценка',
        ];
    }

    public function generate($categories, $offers)
    {
        $string = '<?xml version="1.0" encoding="UTF-8"?>
            <yml_catalog date="' . date('Y-m-d H:i:s') . '">
                <shop>
                    <name>' . $this->shop . '</name>
                    <categories/>
                    <offers/>
                </shop>
            </yml_catalog>
        ';

        $xml = new SimpleXMLElement(
            $string, LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        $this->dd = new DOMDocument();
        $this->dd->preserveWhiteSpace = false;
        $this->dd->formatOutput = true;
        $this->dd->loadXML($xml->asXML());

        $this->eCategories = $this->dd
            ->getElementsByTagName('categories')->item(0);
        $this->eOffers = $this->dd
            ->getElementsByTagName('offers')->item(0);

        $this->addCategories($categories);
        $this->addOffers($offers);

        $this->dd->saveXML();
        $this->dd->save($this->file);
    }

    private function addCategories($categories)
    {
        foreach ($categories as $category) {
            $e = $this->eCategories->appendChild(
                $this->dd->createElement(
                    'category'
                )
            );

            $e->setAttribute('id', $category['id']);
            $e->appendChild($this->dd->createElement('name', $category['name']));

            if ($category['picture']) {
                $e->appendChild($this->dd->createElement('picture', $category['picture']));
            }

            if (0 < $category['parentId']) {
                $e->setAttribute('parentId', $category['parentId']);
            }
        }
    }

    private function addOffers($offers)
    {
        foreach ($offers as $offer) {
            $e = $this->eOffers->appendChild(
                $this->dd->createElement('offer')
            );

            $e->setAttribute('id', $offer['id']);
            $e->setAttribute('productId', $offer['productId']);

            if (!empty($offer['quantity'])) {
                $e->setAttribute('quantity', (int) $offer['quantity']);
            } else {
                $e->setAttribute('quantity', 0);
            }

            foreach ($offer['categoryId'] as $categoryId) {
                $e->appendChild(
                    $this->dd->createElement('categoryId', $categoryId)
                );
            }

            $offerKeys = array_keys($offer);

            if (isset($offer['weight'])) {
                $offer['weight'] = $this->getWeightInKg($offer['weight']);
            }

            foreach ($offerKeys as $key) {
                if (null == $offer[$key]) {
                    continue;
                }

                if (in_array($key, $this->properties)) {
                    if (is_array($offer[$key])) {
                        foreach ($offer[$key] as $property) {
                            $e->appendChild(
                                $this->dd->createElement($key)
                            )->appendChild(
                                $this->dd->createTextNode(trim($property))
                            );
                        }
                    } else {
                        $e->appendChild(
                            $this->dd->createElement($key)
                        )->appendChild(
                            $this->dd->createTextNode(trim($offer[$key]))
                        );
                    }
                }

                if (in_array($key, array_keys($this->params))) {
                    $param = $this->dd->createElement('param');
                    $param->setAttribute('code', $key);
                    $param->setAttribute('name', $this->params[$key]);
                    $param->appendChild(
                        $this->dd->createTextNode($offer[$key])
                    );
                    $e->appendChild($param);
                }
            }

            if (isset($offer['combination'])) {
                foreach ($offer['combination'] as $id => $comb) {
                    $param = $this->dd->createElement('param');
                    $param->setAttribute('code', $id);
                    $param->setAttribute('name', $comb['group_name']);
                    $param->appendChild(
                        $this->dd->createTextNode($comb['attribute_name'])
                    );
                    $e->appendChild($param);
                }
            }
        }
    }

    private function getWeightInKg($weight)
    {
        $mg = $weight / 1000 / 1000;
        $g = $weight / 1000;
        $ton = $weight * 1000;
        $oz = $weight / 35.3;
        $pd = $weight * 2.2;
        $st = $weight * 6.35;

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

            'kg' => $weight,
            'kilogram' => $weight,
            'kilogramme' => $weight,
            'kilo' => $weight,
            'kilogramo' => $weight,

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

        return RetailcrmTools::filter('RetailcrmFilterWeight', $weight, $weightUnits);
    }
}
