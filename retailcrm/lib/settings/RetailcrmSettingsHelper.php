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
    public static function getOrderStatuses()
    {
        return [
        ];
    }

    public static function getDeliveryTypes()
    {
        return [
        ];
    }

    public static function getPaymentTypes()
    {
        return [
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

            if ($detail['lastRun'] instanceof DateTimeImmutable) { // todo remove or refactor
                $lastRunDetails[$job]['lastRunFormatted'] = $detail['lastRun']->format('Y-m-d H:i:s');
            } else {
                $lastRunDetails[$job]['lastRunFormatted'] = null;
            }

            $jobsInfo[] = $lastRunDetails[$job]; // todo refactor
        }

        return $jobsInfo;
    }

    /**
     * Returns all settings names in DB
     *
     * @return array
     */
    public static function getSettingsNames() // todo get rid of this function
    {
        return [
            'urlName' => 'url',
            'apiKeyName' => 'apiKey',
            'deliveryName' => 'delivery',
            'statusName' => 'status',
            'outOfStockStatusName' => 'outOfStockStatus',
            'paymentName' => 'payment',
            'deliveryDefaultName' => 'deliveryDefault',
            'paymentDefaultName' => 'paymentDefault',
            'statusExportName' => 'statusExport',
            'collectorActiveName' => 'collectorActive',
            'collectorKeyName' => 'collectorKey',
            'clientIdName' => 'clientId',
            'synchronizeCartsActiveName' => 'synchronizeCartsActive',
            'synchronizedCartStatusName' => 'synchronizedCartStatus',
            'synchronizedCartDelayName' => 'synchronizedCartDelay',
            'consultantScriptName' => 'consultantScript',
            'enableCorporateName' => 'enableCorporate',
            'enableHistoryUploadsName' => 'enableHistoryUploads',
            'enableBalancesReceivingName' => 'enableBalancesReceiving',
            'enableOrderNumberSendingName' => 'enableOrderNumberSending',
            'enableOrderNumberReceivingName' => 'enableOrderNumberReceiving',
            'webJobsName' => 'webJobs',
            'debugModeName' => 'debugMode',
            'uploadOrders' => RetailCRM::UPLOAD_ORDERS,
            'runJobName' => RetailCRM::RUN_JOB,
            'jobsNames' => RetailCRM::JOBS_NAMES,
        ];
    }

    /**
     * Returns all module settings
     *
     * @return array
     */
    public static function getSettings()
    {
        $syncCartsDelay = (string) (Configuration::get(RetailCRM::SYNC_CARTS_DELAY));

        // Use 15 minutes as default interval but don't change immediate interval to it if user already made decision
        if (empty($syncCartsDelay) && '0' !== $syncCartsDelay) {
            $syncCartsDelay = '900';
        }

        return [
            'url' => (string) (Configuration::get(RetailCRM::API_URL)),
            'apiKey' => (string) (Configuration::get(RetailCRM::API_KEY)),
            'delivery' => json_decode(Configuration::get(RetailCRM::DELIVERY), true),
            'status' => json_decode(Configuration::get(RetailCRM::STATUS), true),
            'outOfStockStatus' => json_decode(Configuration::get(RetailCRM::OUT_OF_STOCK_STATUS), true),
            'payment' => json_decode(Configuration::get(RetailCRM::PAYMENT), true),
            'deliveryDefault' => json_decode(Configuration::get(RetailCRM::DELIVERY_DEFAULT), true),
            'paymentDefault' => json_decode(Configuration::get(RetailCRM::PAYMENT_DEFAULT), true),
            'statusExport' => (string) (Configuration::get(RetailCRM::STATUS_EXPORT)),
            'collectorActive' => (Configuration::get(RetailCRM::COLLECTOR_ACTIVE)),
            'collectorKey' => (string) (Configuration::get(RetailCRM::COLLECTOR_KEY)),
            'clientId' => Configuration::get(RetailCRM::CLIENT_ID),
            'synchronizeCartsActive' => (Configuration::get(RetailCRM::SYNC_CARTS_ACTIVE)),
            'synchronizedCartStatus' => (string) (Configuration::get(RetailCRM::SYNC_CARTS_STATUS)),
            'synchronizedCartDelay' => $syncCartsDelay,
            'consultantScript' => (string) (Configuration::get(RetailCRM::CONSULTANT_SCRIPT)),
            'enableCorporate' => (bool) (Configuration::get(RetailCRM::ENABLE_CORPORATE_CLIENTS)),
            'enableHistoryUploads' => (bool) (Configuration::get(RetailCRM::ENABLE_HISTORY_UPLOADS)),
            'enableBalancesReceiving' => (bool) (Configuration::get(RetailCRM::ENABLE_BALANCES_RECEIVING)),
            'enableOrderNumberSending' => (bool) (Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_SENDING)),
            'enableOrderNumberReceiving' => (bool) (Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING)),
            'webJobs' => RetailcrmTools::isWebJobsEnabled(),
            'debugMode' => RetailcrmTools::isDebug(),
        ];
    }
}
