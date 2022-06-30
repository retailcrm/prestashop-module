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

class RetailcrmSettingsHelper
{
    public static function getCartDelays()
    {
        return
            [
                '900',
                '1800',
                '2700',
                '3600',
            ];
    }

    public static function getJobsInfo()
    {
        $jobsInfo = [];

        $lastRunDetails = RetailcrmJobManager::getLastRunDetails();
        $currentJob = Configuration::get(RetailcrmJobManager::CURRENT_TASK);
        $currentJobCli = Configuration::get(RetailcrmCli::CURRENT_TASK_CLI);

        foreach ($lastRunDetails as $job => $detail) {
            $lastRunDetails[$job]['name'] = $job;
            $lastRunDetails[$job]['running'] = $job === $currentJob || $job === $currentJobCli;

            $jobsInfo[] = $lastRunDetails[$job]; // todo refactor
        }

        return $jobsInfo;
    }

    public static function getLogFilesInfo()
    {
        $fileNames = [];
        $logFiles = RetailcrmLoggerHelper::getLogFiles();

        foreach ($logFiles as $logFile) {
            $fileNames[] = [
                'name' => basename($logFile),
                'path' => $logFile,
                'size' => number_format(filesize($logFile), 0, '.', ' ') . ' bytes',
                'modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            ];
        }

        $orderBy = 'modified';
        $orderDirection = SORT_DESC;

        array_multisort(
            array_map(function ($item) use ($orderBy) {
                return $item[$orderBy];
            }, $fileNames),
            $orderDirection,
            $fileNames
        );

        return $fileNames;
    }

    public static function getCatalogInfo()
    {
        $icmlInfo = json_decode((string) Configuration::get(RetailcrmCatalogHelper::ICML_INFO_NAME), true);

        if (null === $icmlInfo || JSON_ERROR_NONE !== json_last_error()) {
            $icmlInfo = [];
        }

        $icmlInfo['isCatalogConnected'] = false;
        $icmlInfo['isUrlActual'] = false;
        $icmlInfo['siteId'] = null;

        $lastGenerated = RetailcrmCatalogHelper::getIcmlFileDate();

        if (false === $lastGenerated) {
            return $icmlInfo;
        }

        $icmlInfo['isCatalogConnected'] = true;

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
            0 < $icmlInfo['lastGeneratedDiff']['days']
            || 4 < $icmlInfo['lastGeneratedDiff']['hours']
        );

        $api = RetailcrmTools::getApiClient();

        if (null !== $api) {
            $reference = new RetailcrmReferences($api);

            $site = $reference->getSite();
            $icmlInfo['isUrlActual'] = !empty($site['ymlUrl'])
                                       && $site['ymlUrl'] === RetailcrmCatalogHelper::getIcmlFileLink();
            if (!empty($site['catalogId'])) {
                $icmlInfo['siteId'] = $site['catalogId'];
            }
        }

        return $icmlInfo;
    }

    public static function getSettings()
    {
        $settings = new RetailcrmSettingsItems();
        $result = $settings->getValueStoredAll();

        $result['latestVersion'] = self::getLatestVersion();

        $consultantScript = new RetailcrmSettingsItemHtml('consultantScript', RetailCRM::CONSULTANT_SCRIPT);
        $result['consultantScript'] = $consultantScript->getValueStored();

        return $result;
    }

    public static function getReferences()
    {
        $client = RetailcrmTools::getApiClient();
        $moduleReferences = new RetailcrmReferences($client);

        return [
            'deliveryTypesCMS' => $moduleReferences->getDeliveryTypes(),
            'deliveryTypesCRM' => $moduleReferences->getApiDeliveryTypes(),
            'paymentTypesCMS' => $moduleReferences->getSystemPaymentModules(),
            'paymentTypesCRM' => $moduleReferences->getApiPaymentTypes(),
            'statusesCMS' => $moduleReferences->getStatuses(),
            'statusesCRM' => $moduleReferences->getApiStatusesWithGroup(),
            'cartsDelays' => RetailcrmSettingsHelper::getCartDelays(),
            'linkPayments' => RetailcrmTools::getAdminControllerUrl(AdminPaymentPreferencesController::class),
            'linkOrders' => RetailcrmTools::getAdminControllerUrl(AdminOrdersController::class),
        ];
    }

    private static function getLatestVersion()
    {
        $latestInfo = self::getLatestInfo();

        if (
            $latestInfo
            && isset(
                $latestInfo['tag_name'],
                $latestInfo['body'],
                $latestInfo['assets'],
                current($latestInfo['assets'])['browser_download_url']
            )
        ) {
            return [
                'isSuccess' => true,
                'isLatest' => 'v' . RetailCRM::VERSION === $latestInfo['tag_name'],
                'url' => current($latestInfo['assets'])['browser_download_url'],
                'body' => $latestInfo['body'],
            ];
        }

        return [
            'isSuccess' => false,
        ];
    }

    private static function getLatestInfo()
    {
        $curlHandler = curl_init();

        curl_setopt($curlHandler, CURLOPT_URL, 'https://api.github.com/repos/retailcrm/prestashop-module/releases/latest');
        curl_setopt($curlHandler, CURLOPT_USERAGENT, 'Simla integration module for PrestaShop');
        curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandler, CURLOPT_FAILONERROR, false);
        curl_setopt($curlHandler, CURLOPT_TIMEOUT, 10);
        curl_setopt($curlHandler, CURLOPT_CONNECTTIMEOUT, 10);

        $responseBody = curl_exec($curlHandler);
        $statusCode = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);

        curl_close($curlHandler);

        return 200 === $statusCode ? json_decode($responseBody, true) : null;
    }
}
