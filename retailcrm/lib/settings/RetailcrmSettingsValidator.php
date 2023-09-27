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

class RetailcrmSettingsValidator
{
    const LATEST_API_VERSION = '5';

    private $errors;
    private $warnings;

    /**
     * @var RetailcrmSettingsItems
     */
    private $settings;
    /**
     * @var RetailcrmReferences|null
     */
    private $reference;

    public function __construct(
        RetailcrmSettingsItems $settings,
        RetailcrmReferences $reference = null
    ) {
        $this->settings = $settings;
        $this->reference = $reference;
        $this->errors = [];
        $this->warnings = [];
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    public function getSuccess()
    {
        return 0 === count($this->errors);
    }

    /**
     * Settings form validator
     */
    public function validate($validateFromRequestOnly = false)
    {
        //  check url and apiKey
        $crmUrl = $this->settings->getValueWithStored('url');
        $crmApiKey = $this->settings->getValueWithStored('apiKey');

        if (!$validateFromRequestOnly || $this->settings->issetValue('url') || $this->settings->issetValue('apiKey')) {
            if ($this->validateCrmAddress($crmUrl) && $this->validateCrmApiKey($crmApiKey)) {
                $this->validateApiCredentials($crmUrl, $crmApiKey);
            }
        }

        //  check abandoned carts status
        if (!$validateFromRequestOnly || $this->settings->issetValue('status') || $this->settings->issetValue('synchronizedCartStatus')) {
            if (!$this->validateCartStatus(
                $this->settings->getValueWithStored('status'),
                $this->settings->getValueWithStored('synchronizedCartStatus')
            )
            ) {
                $this->addError('synchronizedCartStatus', 'errors.carts');
            }
        }

        //  check mapping statuses
        if (!$validateFromRequestOnly || $this->settings->issetValue('status')) {
            if (!$this->validateMappingOneToOne($this->settings->getValueWithStored('status'))) {
                $this->addError('status', 'errors.status');
            }
        }

        //  check mapping delivery
        if (!$validateFromRequestOnly || $this->settings->issetValue('delivery')) {
            if (!$this->validateMappingOneToOne($this->settings->getValueWithStored('delivery'))) {
                $this->addError('delivery', 'errors.delivery');
            }
        }

        //  check mapping payment
        if (!$validateFromRequestOnly || $this->settings->issetValue('payment')) {
            if (!$this->validateMappingOneToOne($this->settings->getValueWithStored('payment'))) {
                $this->addError('payment', 'errors.payment');
            }
        }

        //  check collector identifier
        if (!$validateFromRequestOnly || $this->settings->issetValue('collectorActive') || $this->settings->issetValue('collectorKey')) {
            if (!$this->validateCollector(
                $this->settings->getValueWithStored('collectorActive'),
                $this->settings->getValueWithStored('collectorKey')
            )) {
                $this->addError('collectorKey', 'errors.collector');
            }
        }

        if (!array_key_exists('url', $this->getErrors()) && !array_key_exists('apiKey', $this->getErrors())) {
            $errorTabs = $this->validateStoredSettings($validateFromRequestOnly); // todo maybe refactor

            if (in_array('delivery', $errorTabs)) {
                $this->addWarning('delivery', 'warnings.delivery');
            }
            if (in_array('status', $errorTabs)) {
                $this->addWarning('status', 'warnings.status');
            }
            if (in_array('payment', $errorTabs)) {
                $this->addWarning('payment', 'warnings.payment');
            }
            if (in_array('deliveryDefault', $errorTabs) || in_array('paymentDefault', $errorTabs)) {
                $this->addWarning('deliveryDefault', 'warnings.default');
            }
        }

        return $this->getSuccess();
    }

    /**
     * Validate crm address
     *
     * @param $address
     *
     * @return bool
     */
    private function validateCrmAddress($address)
    {
        if (preg_match("/https:\/\/(.*).(retailcrm.(pro|ru|es)|simla.com)/", $address)) {
            if (Validate::isGenericName($address)) {
                return true;
            }
        }

        $this->addError('url', 'errors.url');

        return false;
    }

    /**
     * Validate crm api key
     *
     * @param $apiKey
     *
     * @return bool
     */
    private function validateCrmApiKey($apiKey)
    {
        if (32 === mb_strlen($apiKey)) {
            if (Validate::isGenericName($apiKey)) {
                return true;
            }
        }

        $this->addError('apiKey', 'errors.key');

        return false;
    }

    /**
     * Cart status must be present and must be unique to cartsIds only
     *
     * @param string $statuses
     * @param string $cartStatus
     *
     * @return bool
     */
    private function validateCartStatus($statuses, $cartStatus)
    {
        if (!is_array($statuses)) {
            return true;
        }

        $statusesList = array_filter(array_values($statuses));

        if (0 === count($statusesList)) {
            return true;
        }

        if ('' !== $cartStatus && in_array($cartStatus, $statusesList)) {
            return false;
        }

        return true;
    }

    /**
     * Returns false if mapping is not valid in one-to-one relation
     *
     * @param string $statuses
     *
     * @return bool
     */
    private function validateMappingOneToOne($statuses)
    {
        if (!is_array($statuses)) {
            return true;
        }

        $statusesList = array_filter(array_values($statuses));

        if (count($statusesList) != count(array_unique($statusesList))) {
            return false;
        }

        return true;
    }

    public function validateStoredSettings($validateFromRequestOnly)
    {
        $tabsWithWarnings = [];
        $tabsNamesAndCheckApiMethods = [
            'delivery' => 'getApiDeliveryTypes',
            'status' => 'getApiStatuses',
            'payment' => 'getApiPaymentTypes',
            'deliveryDefault' => null,
            'paymentDefault' => null,
        ];

        foreach ($tabsNamesAndCheckApiMethods as $tabName => $checkApiMethod) {
            if ($validateFromRequestOnly && !$this->settings->issetValue($tabName)) {
                continue;
            }

            $storedValues = $this->settings->getValueWithStored($tabName);

            if (false === $storedValues || null === $storedValues) {
                continue;
            }

            if (!$this->validateMappingSelected($storedValues)) {
                $tabsWithWarnings[] = $tabName;

                continue;
            }

            if (null !== $checkApiMethod) {
                $crmValues = call_user_func([$this->reference, $checkApiMethod]); // todo use class own reference
                $crmCodes = array_column($crmValues, 'code');

                if (!empty(array_diff($storedValues, $crmCodes))) {
                    $tabsWithWarnings[] = $tabName;
                }
            }
        }

        return $tabsWithWarnings;
    }

    private function validateMappingSelected($values)
    {
        if (is_array($values)) {
            foreach ($values as $item) {
                if (empty($item)) {
                    return false;
                }
            }
        } elseif (empty($values)) {
            return false;
        }

        return true;
    }

    public function validateApiCredentials($url, $apiKey)
    {
        /** @var RetailcrmProxy|RetailcrmApiClientV5 $api */
        $api = new RetailcrmProxy($url, $apiKey);

        return $this->validateApiVersion($api) && $this->validateApiAccess($api) && $this->validateCurrency($api);
    }

    private function validateCurrency($api)
    {
        $response = $api->sitesList();

        if ($response instanceof RetailcrmApiResponse && $response->isSuccessful() && isset($response['sites'])) {
            $site = current($response['sites']);
        }

        $currencyId = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $isoCode = Db::getInstance()->getValue(
            'SELECT `iso_code` FROM ' . _DB_PREFIX_ . 'currency WHERE `id_currency` = ' . $currencyId
        );

        if (isset($site['currency']) && $site['currency'] !== $isoCode) {
            $this->addError('apiKey', 'errors.currency');

            return false;
        }

        return true;
    }

    /**
     * Returns true if provided connection supports API v5
     *
     * @return bool
     */
    private function validateApiVersion($api)
    {
        $response = $api->apiVersions();

        if (false !== $response && isset($response['success']) && $response['success']) {
            if (isset($response['versions']) && !empty($response['versions'])) {
                foreach ($response['versions'] as $version) {
                    if ($version == static::LATEST_API_VERSION
                        || Tools::substr($version, 0, 1) == static::LATEST_API_VERSION
                    ) {
                        return true;
                    }
                }

                $this->addError('url', 'errors.version');
            }
        } else {
            $this->addError('apiKey', 'errors.connect');
        }

        return false;
    }

    /**
     * Returns true if provided connection support necessary scopes and access_selective
     *
     * @return bool
     */
    private function validateApiAccess($api)
    {
        $response = $api->credentials();

        if (false !== $response) {
            return $this->validateApiSiteAccess($response) && $this->validateApiScopes($response);
        }

        return false;
    }

    private function validateApiSiteAccess($credentials)
    {
        if (isset($credentials['siteAccess'], $credentials['sitesAvailable'])
            && RetailCRM::REQUIRED_CRM_SITE_ACCESS === $credentials['siteAccess']
            && is_array($credentials['sitesAvailable'])
            && RetailCRM::REQUIRED_CRM_SITE_COUNT === count($credentials['sitesAvailable'])
        ) {
            return true;
        }

        $this->addError('apiKey', 'errors.access');

        return false;
    }

    private function validateApiScopes($credentials)
    {
        if (isset($credentials['scopes'])
            && is_array($credentials['scopes'])
            && !array_diff(RetailCRM::REQUIRED_CRM_SCOPES, $credentials['scopes'])
        ) {
            return true;
        }

        $this->addError('apiKey', 'errors.scopes');

        return false;
    }

    private function validateCollector($collectorActive, $collectorKey)
    {
        return !$collectorActive || '' !== $collectorKey;
    }

    private function addError($field, $message)
    {
        $this->errors[$field][] = $message;
    }

    private function addWarning($field, $message)
    {
        $this->warnings[$field][] = $message;
    }
}
