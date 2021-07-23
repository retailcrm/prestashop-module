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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to version 3.3.2
 *
 * @param \RetailCRM $module
 *
 * @return bool
 */
function upgrade_module_3_3_2($module)
{
    if ('retailcrm' != $module->name) {
        return false;
    }

    $isMultiStoreActive = Shop::isFeatureActive();

    if ($isMultiStoreActive) {
        $shops = Shop::getShops();
    } else {
        $shops[] = Shop::getContext();
    }

    foreach ($shops as $shop) {
        RetailcrmContextSwitcher::setShopContext((int) $shop['id_shop']);
        $api = RetailcrmTools::getApiClient();

        if (empty($api)) {
            continue;
        }

        if ($isMultiStoreActive) {
            $oldFile = _PS_ROOT_DIR_ . '/' . 'retailcrm_' . $shop['id_shop'] . '.xml';
            $newFile = _PS_ROOT_DIR_ . '/' . 'simla_' . $shop['id_shop'] . '.xml';
        } else {
            $oldFile = _PS_ROOT_DIR_ . '/' . 'retailcrm.xml';
            $newFile = _PS_ROOT_DIR_ . '/' . 'simla.xml';
        }

        if (file_exists($oldFile) && !file_exists($newFile)) {
            rename($oldFile, $newFile);
        } else {
            if (!file_exists($oldFile)) {
                RetailcrmLogger::writeDebug(
                    __METHOD__,
                    sprintf(
                        'Old ICML file [%s] not exist',
                        $oldFile
                    )
                );
            }

            if (file_exists($newFile)) {
                RetailcrmLogger::writeDebug(
                    __METHOD__,
                    sprintf(
                        'New ICML file [%s] already exists',
                        $newFile
                    )
                );
            }
        }

        try {
            $response = $api->credentials();
        } catch (\RetailCrm\Exception\CurlException $e) {
            RetailcrmLogger::writeCaller(
                __METHOD__,
                sprintf(
                    'Connection error: %s',
                    $e->getMessage()
                )
            );
        }

        if (!$response->isSuccessful()
            || $response['siteAccess'] !== 'access_selective'
            || count($response['sitesAvailable']) !== 1
            || !in_array('/api/reference/sites', $response['credentials'])
            || !in_array('/api/reference/sites/{code}/edit', $response['credentials'])
        ) {
            RetailcrmLogger::writeCaller(
                __METHOD__,
                sprintf(
                    'ShopID=%s: Error with CRM credentials: need an valid apiKey assigned to one certain site',
                    $shop['id_shop']
                )
            );
            continue;
        }

        try {
            $response = $api->sitesList();
        } catch (\RetailCrm\Exception\CurlException $e) {
            RetailcrmLogger::writeCaller(
                __METHOD__,
                sprintf(
                    'Connection error: %s',
                    $e->getMessage()
                )
            );
        }

        if ($response->isSuccessful() && $response['sites']) {
            $crmSite = current($response['sites']);
            $site = $crmSite['code'];
            $oldYmlUrl = $crmSite['ymlUrl'];
            $newYmlUrl = str_replace('/retailcrm', '/simla', $oldYmlUrl);

            try {
                $response = $api->sitesEdit([
                    'code' => $site,
                    'ymlUrl' => $newYmlUrl,
                ]);
            } catch (\RetailCrm\Exception\CurlException $e) {
                RetailcrmLogger::writeCaller(
                    __METHOD__,
                    sprintf(
                        'Connection error: %s',
                        $e->getMessage()
                    )
                );
            }
        }
    }

    return true;
}
