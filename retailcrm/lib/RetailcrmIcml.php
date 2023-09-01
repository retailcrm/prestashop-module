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

class RetailcrmIcml
{
    protected $shop;
    protected $file;
    protected $tmpFile;
    protected $properties;
    protected $params;
    protected $dd;
    protected $eCategories;
    protected $eOffers;
    /**
     * @var XMLWriter
     */
    private $writer;

    public function __construct($shop, $file)
    {
        $this->shop = $shop;
        $this->file = $file;
        $this->tmpFile = sprintf('%s.tmp', $this->file);

        $this->properties = [
            'categoryId',
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
            'tax' => 'Наценка',
        ];
    }

    public function generate($categories, $offers)
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }

        $this->loadWriter();
        $this->writeHead();

        if (!empty($categories)) {
            $this->writeCategories($categories);
            unset($categories);
        }

        if (!empty($offers)) {
            $this->writeOffers($offers);
            unset($offers);
        }

        $this->writeEnd();
        $this->formatXml();

        rename($this->tmpFile, $this->file);
    }

    private function loadWriter()
    {
        if (!$this->writer) {
            $writer = new \XMLWriter();
            $writer->openUri($this->tmpFile);

            $this->writer = $writer;
        }
    }

    private function writeHead()
    {
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->startElement('yml_catalog'); // start <yml_catalog>
        $this->writer->writeAttribute('date', date('Y-m-d H:i:s'));
        $this->writer->startElement('shop'); // start <shop>
        $this->writer->WriteElement('name', $this->shop);
    }

    /**
     * @param $categories
     */
    private function writeCategories($categories)
    {
        $this->writer->startElement('categories'); // start <categories>

        $this->addCategories($categories);

        $this->writer->endElement(); // end </categories>
    }

    private function addCategories($categories)
    {
        foreach ($categories as $category) {
            if (!array_key_exists('name', $category) || !array_key_exists('id', $category)) {
                continue;
            }

            $this->writer->startElement('category'); // start <category>

            $this->writer->writeAttribute('id', $category['id']);

            if (array_key_exists('parentId', $category) && 0 < $category['parentId']) {
                $this->writer->writeAttribute('parentId', $category['parentId']);
            }

            $this->writer->writeElement('name', $category['name']);

            if (array_key_exists('picture', $category) && $category['picture']) {
                $this->writer->writeElement('picture', $category['picture']);
            }

            $this->writer->endElement(); // end </category>
        }
    }

    /**
     * @param $offers
     */
    private function writeOffers($offers)
    {
        $this->writer->startElement('offers'); // start <offers>
        $this->addOffers($offers);
        $this->writer->endElement(); // end </offers>
    }

    private function addOffers($offers)
    {
        foreach ($offers as $offer) {
            if (!array_key_exists('id', $offer)) {
                continue;
            }

            $this->writer->startElement('offer'); // start <offer>

            $this->writer->writeAttribute('id', $offer['id']);
            $this->writer->writeAttribute('productId', $offer['productId']);
            $this->writer->writeAttribute('quantity', (int) $offer['quantity']);

            unset($offer['id'], $offer['productId'], $offer['quantity']);

            $this->setOffersProperties($offer);
            $this->setOffersParams($offer);
            $this->setOffersCombinations($offer);
            $this->setOffersFeatures($offer);

            $this->writer->endElement(); // end </offer>
        }
    }

    private function setOffersProperties($offer)
    {
        foreach ($offer as $key => $value) {
            if (!in_array($key, $this->properties)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $element) {
                    $this->writer->writeElement($key, $element);
                }
            } else {
                $this->writer->writeElement($key, $value);
            }
        }
    }

    private function setOffersParams($offer)
    {
        foreach ($offer as $key => $value) {
            if (!array_key_exists($key, $this->params)) {
                continue;
            }

            $this->writer->startElement('param');
            $this->writer->writeAttribute('code', $key);
            $this->writer->writeAttribute('name', $this->params[$key]);
            $this->writer->text($value);
            $this->writer->endElement();
        }
    }

    private function setOffersCombinations($offer)
    {
        if (!array_key_exists('combination', $offer)) {
            return;
        }

        foreach ($offer['combination'] as $id => $combination) {
            if (
                !array_key_exists('group_name', $combination)
                || !array_key_exists('attribute_name', $combination)
                || empty($combination['group_name'])
                || empty($combination['attribute_name'])
            ) {
                continue;
            }

            $this->writer->startElement('param');
            $this->writer->writeAttribute('code', $id);
            $this->writer->writeAttribute('name', $combination['group_name']);
            $this->writer->text($combination['attribute_name']);
            $this->writer->endElement();
        }
    }

    private function setOffersFeatures($offer)
    {
        $lastFeaturesNumberCode = [];

        foreach ($offer['features'] as $feature) {
            if (
                empty($feature['id_feature'])
                || empty($feature['name'])
                || null === $feature['value']
            ) {
                continue;
            }

            $numberCode = 1;

            if (isset($lastFeaturesNumberCode[$feature['id_feature']])) {
                $numberCode = 1 + $lastFeaturesNumberCode[$feature['id_feature']];
            }

            $this->writer->startElement('param');
            $this->writer->writeAttribute('code', 'feature_' . $feature['id_feature'] . '_' . $numberCode);
            $this->writer->writeAttribute('name', $feature['name']);
            $this->writer->text($feature['value']);
            $this->writer->endElement();

            $lastFeaturesNumberCode[$feature['id_feature']] = $numberCode;
        }
    }

    private function writeEnd()
    {
        $this->writer->endElement(); // end </yml_catalog>
        $this->writer->endElement(); // end </shop>
        $this->writer->endDocument();
    }

    private function formatXml()
    {
        $dom = dom_import_simplexml(simplexml_load_file($this->tmpFile))->ownerDocument;
        $dom->formatOutput = true;
        $formatted = $dom->saveXML();

        unset($dom, $this->writer);

        file_put_contents($this->tmpFile, $formatted);
    }
}
