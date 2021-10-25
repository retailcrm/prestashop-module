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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmCatalogHelper
{
    const ICML_INFO_NAME = 'RETAILCRM_ICML_INFO';

    public static function getIcmlFileDate()
    {
        $date = null;
        $filePath = self::getIcmlFilePath();
        if (!file_exists($filePath) || ($fileHandler = fopen($filePath, 'r')) === false) {
            return false;
        }

        while ($line = fgets($fileHandler)) {
            if (strpos($line, 'yml_catalog date=') !== false) {
                preg_match_all('/date="([\d\- :]+)"/', $line, $matches);
                if (count($matches) == 2) {
                    $date = $matches[1][0];
                }
                break;
            }
        }

        fclose($fileHandler);

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
    }

    public static function getIcmlFileLink()
    {
        return _PS_BASE_URL_ . '/' . self::getIcmlFilename();
    }

    public static function getIcmlFileName()
    {
        $isMultiStoreActive = Shop::isFeatureActive();
        $shop = Context::getContext()->shop;

        if ($isMultiStoreActive) {
            $icmlFileName = 'simla_' . $shop->id . '.xml';
        } else {
            $icmlFileName = 'simla.xml';
        }

        return $icmlFileName;
    }

    public static function getIcmlFilePath()
    {
        return _PS_ROOT_DIR_ . '/' . self::getIcmlFileName();
    }

    public static function getIcmlFileInfo()
    {
        $icmlInfo = json_decode((string) Configuration::get(self::ICML_INFO_NAME), true);

        if ($icmlInfo === null || json_last_error() !== JSON_ERROR_NONE) {
            $icmlInfo = [];
        }

        $lastGenerated = self::getIcmlFileDate();

        if ($lastGenerated === false) {
            return $icmlInfo;
        }

        $icmlInfo['lastGenerated'] = $lastGenerated;
        $now = new DateTimeImmutable();
        /** @var DateInterval $diff */
        $diff = $lastGenerated->diff($now);

        $icmlInfo['lastGeneratedDiff'] = [
            'days' => $diff->days,
            'hours' => $diff->h,
            'minutes' => $diff->i,
        ];

        $icmlInfo['isOutdated'] = (
            $icmlInfo['lastGeneratedDiff']['days'] > 0
            || $icmlInfo['lastGeneratedDiff']['hours'] > 4
        );

        $api = RetailcrmTools::getApiClient();

        if ($api !== null) {
            $reference = new RetailcrmReferences($api);

            $site = $reference->getSite();
            $icmlInfo['isUrlActual'] = !empty($site['ymlUrl']) && $site['ymlUrl'] === self::getIcmlFileLink();
            if (!empty($site['catalogId'])) {
                $icmlInfo['siteId'] = $site['catalogId'];
            }
        }

        return $icmlInfo;
    }

    public static function getIcmlFileInfoMultistore()
    {
        return RetailcrmContextSwitcher::runInContext([self::class, 'getIcmlFileInfo']);
    }

    /**
     * @param int $productsCount
     * @param int $offersCount
     */
    public static function setIcmlFileInfo($productsCount, $offersCount)
    {
        $icmlInfo = [
            'productsCount' => $productsCount,
            'offersCount' => $offersCount,
        ];
        Configuration::updateValue(self::ICML_INFO_NAME, (string) json_encode($icmlInfo));
    }
}
