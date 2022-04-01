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

class RetailcrmSettingsItems
{
    /**
     * @var RetailcrmSettingsItem[]
     */
    private $settings;

    public function __construct()
    {
        $this->settings = [
            'url' => new RetailcrmSettingsItemUrl('url', RetailCRM::API_URL),
            'apiKey' => new RetailcrmSettingsItem('apiKey', RetailCRM::API_KEY),

            'delivery' => new RetailcrmSettingsItemJson('delivery', RetailCRM::DELIVERY),
            'payment' => new RetailcrmSettingsItemJson('payment', RetailCRM::PAYMENT),
            'status' => new RetailcrmSettingsItemJson('status', RetailCRM::STATUS),
            'outOfStockStatus' => new RetailcrmSettingsItemJson('outOfStockStatus', RetailCRM::OUT_OF_STOCK_STATUS),

            'enableHistoryUploads' => new RetailcrmSettingsItemBool('enableHistoryUploads', RetailCRM::ENABLE_HISTORY_UPLOADS),
            'enableBalancesReceiving' => new RetailcrmSettingsItemBool('enableBalancesReceiving', RetailCRM::ENABLE_BALANCES_RECEIVING),
            'collectorActive' => new RetailcrmSettingsItemBool('collectorActive', RetailCRM::COLLECTOR_ACTIVE),
            'synchronizeCartsActive' => new RetailcrmSettingsItemBool('synchronizeCartsActive', RetailCRM::SYNC_CARTS_ACTIVE),
            'enableCorporate' => new RetailcrmSettingsItemBool('enableCorporate', RetailCRM::ENABLE_CORPORATE_CLIENTS),
            'enableOrderNumberSending' => new RetailcrmSettingsItemBool('enableOrderNumberSending', RetailCRM::ENABLE_ORDER_NUMBER_SENDING),
            'enableOrderNumberReceiving' => new RetailcrmSettingsItemBool('enableOrderNumberReceiving', RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING),
            'webJobs' => new RetailcrmSettingsItemBool('webJobs', RetailCRM::ENABLE_WEB_JOBS),
            'debugMode' => new RetailcrmSettingsItemBool('debugMode', RetailCRM::ENABLE_DEBUG_MODE),

            'deliveryDefault' => new RetailcrmSettingsItem('deliveryDefault', RetailCRM::DELIVERY_DEFAULT),
            'paymentDefault' => new RetailcrmSettingsItem('paymentDefault', RetailCRM::PAYMENT_DEFAULT),
            'synchronizedCartStatus' => new RetailcrmSettingsItem('synchronizedCartStatus', RetailCRM::SYNC_CARTS_STATUS),
            'synchronizedCartDelay' => new RetailcrmSettingsItem('synchronizedCartDelay', RetailCRM::SYNC_CARTS_DELAY),
            'collectorKey' => new RetailcrmSettingsItem('collectorKey', RetailCRM::COLLECTOR_KEY),
        ];
    }

    public function getValue($key)
    {
        $this->checkKey($key);

        return $this->settings[$key]->getValue();
    }

    public function issetValue($key)
    {
        $this->checkKey($key);

        return $this->settings[$key]->issetValue();
    }

    public function updateValue($key)
    {
        $this->checkKey($key);

        $this->settings[$key]->updateValue();
    }

    public function updateValueAll()
    {
        foreach ($this->settings as $key => $item) {
            $item->updateValue();
        }
    }

    public function deleteValue($key)
    {
        $this->checkKey($key);

        $this->settings[$key]->deleteValue();
    }

    public function deleteValueAll()
    {
        foreach ($this->settings as $item) {
            $item->deleteValue();
        }
    }

    /**
     * @throws Exception
     */
    private function checkKey($key)
    {
        if (!array_key_exists($key, $this->settings)) {
            throw new Exception("Invalid key `$key`!");
        }
    }

    public function getValueStored($key)
    {
        $this->checkKey($key);

        return $this->settings[$key]->getValueStored();
    }

    public function getValueWithStored($key)
    {
        $this->checkKey($key);

        return $this->settings[$key]->getValueWithStored();
    }

    public function getChanged()
    {
        $changed = [];
//
//        $changed['DEBUG'] = [
//            'GET' => $_GET,
//            'POST' => $_POST,
//        ];

        foreach ($this->settings as $key => $setting) {
            if ($setting->issetValue()) {
                $changed[$key] = $setting->getValueStored();
            }
        }

        return $changed;
    }
}
