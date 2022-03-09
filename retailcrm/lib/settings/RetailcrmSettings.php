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

class RetailcrmSettings
{
    const LATEST_API_VERSION = '5';

    /**
     * @var RetailCRM|Module
     */
    private $module;
    private $reference;

    private $errors;
    private $warnings;
    /**
     * @var RetailcrmSettingsItems
     */
    private $settings;

    /**
     * @var RetailcrmSettingsItemHtml
     */
    private $consultantScript;

    public function __construct(Module $module)
    {
        $this->module = $module;
        $this->settings = new RetailcrmSettingsItems();
        $this->consultantScript = new RetailcrmSettingsItemHtml('consultantScript', RetailCRM::CONSULTANT_SCRIPT);
    }

    /**
     * Save settings handler
     *
     * @return array
     */
    public function save()
    {
        if ($this->validateForm()) {
            $this->settings->updateValueAll();
        }

        $changed = $this->settings->getChanged();

        if ($this->consultantScript->issetValue()) {
            $this->updateConsultantCode();
            $changed['consultantScript'] = $this->consultantScript->getValueStored();
        }

        return [
            'success' => (null === $this->errors || 0 === count($this->errors)), // todo refactor
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'changed' => $changed,
        ];
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

    public function validateStoredSettings() // todo also uses in settings template to show errors on page load
    {
        $tabsWithWarnings = [];
        $tabsNamesAndCheckApiMethods = [
            'delivery' => 'getApiDeliveryTypes', // todo check and replace with new functions
            'status' => 'getApiStatuses',
            'payment' => 'getApiPaymentTypes',
            'deliveryDefault' => null,
            'paymentDefault' => null,
        ];

        foreach ($tabsNamesAndCheckApiMethods as $tabName => $checkApiMethod) {
            if (!$this->settings->issetValue($tabName)) { // todo remove
                continue;
            }

            $storedValues = $this->settings->getValueWithStored($tabName); // todo get encoded value from Tools::

            if (false === $storedValues || null === $storedValues) {
                continue;
            }

            if (!$this->validateMappingSelected($storedValues)) {
                $tabsWithWarnings[] = $tabName;

                continue;
            }

            if (null !== $checkApiMethod) {
                $crmValues = call_user_func([$this->module->reference, $checkApiMethod]); // todo use class own reference
                $crmCodes = array_column($crmValues, 'id_option'); // todo replace with 'code'

                if (!empty(array_diff($storedValues, $crmCodes))) {
                    $tabsWithWarnings[] = $tabName;
                }
            }
        }

        if (!$this->validateCatalogMultistore()) {
            $tabsWithWarnings[] = 'catalog';
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
        } else {
            if (empty($values)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Catalog info validator
     *
     * @return bool
     */
    public function validateCatalog()
    {
        $icmlInfo = RetailcrmCatalogHelper::getIcmlFileInfo();

        if (!$icmlInfo || !isset($icmlInfo['lastGenerated'])) {
            $urlConfiguredAt = RetailcrmTools::getConfigurationCreatedAtByName(RetailCRM::API_KEY);

            if ($urlConfiguredAt instanceof DateTimeImmutable) {
                $now = new DateTimeImmutable();
                /** @var DateInterval $diff */
                $diff = $urlConfiguredAt->diff($now);

                if (($diff->days * 24 + $diff->h) > 4) {
                    return false;
                }
            }
        } elseif ($icmlInfo['isOutdated'] || !$icmlInfo['isUrlActual']) {
            return false;
        }

        return true;
    }

    /**
     * Catalog info validator for multistore
     *
     * @return bool
     */
    private function validateCatalogMultistore()
    {
        $results = RetailcrmContextSwitcher::runInContext([$this, 'validateCatalog']);
        $results = array_filter($results, function ($item) {
            return !$item;
        });

        return empty($results);
    }

    /**
     * Returns true if provided connection supports API v5
     *
     * @param $settings
     *
     * @return bool
     */
    private function validateApiVersion($url, $apiKey)
    {
        /** @var RetailcrmProxy|RetailcrmApiClientV5 $api */
        $api = new RetailcrmProxy(
            $url,
            $apiKey
        );

        $response = $api->apiVersions();

        if (false !== $response && isset($response['versions']) && !empty($response['versions'])) {
            foreach ($response['versions'] as $version) {
                if ($version == static::LATEST_API_VERSION
                    || Tools::substr($version, 0, 1) == static::LATEST_API_VERSION
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Settings form validator
     */
    private function validateForm()
    {
        if ($this->settings->issetValue('url') && !RetailcrmTools::validateCrmAddress($this->settings->getValue('url'))) {
            $this->displayError('errors.url');
        }

        if ($this->settings->issetValue('apiKey') && !$this->settings->getValue('apiKey')) {
            $this->displayError('errors.key');
        }

        if ($this->settings->issetValue('url') || $this->settings->issetValue('apiKey')) {
            if (!$this->validateApiVersion($this->settings->getValueWithStored('url'), $this->settings->getValueWithStored('apiKey'))) {
                $this->displayError('errors.version');
            }
        }

        //  check abandoned carts status
        if ($this->settings->issetValue('status') || $this->settings->issetValue('synchronizedCartStatus')) {
            if (!$this->validateCartStatus(
                $this->settings->getValueWithStored('status'),
                $this->settings->getValueWithStored('synchronizedCartStatus')
            )
            ) {
                $this->displayError('errors.carts'); // todo check if it works
            }
        }

        //  check mapping statuses
        if ($this->settings->issetValue('status')) {
            if (!$this->validateMappingOneToOne($this->settings->getValue('status'))) {
                $this->displayError('errors.status');
            }
        }

        //  check mapping delivery
        if ($this->settings->issetValue('delivery')) {
            if (!$this->validateMappingOneToOne($this->settings->getValue('delivery'))) {
                $this->displayError('errors.delivery');
            }
        }

        //  check mapping payment
        if ($this->settings->issetValue('payment')) {
            if (!$this->validateMappingOneToOne($this->settings->getValue('payment'))) {
                $this->displayError('errors.payment');
            }
        }

        $errorTabs = $this->validateStoredSettings(); // todo maybe refactor

        if (in_array('delivery', $errorTabs)) {
            $this->displayWarning('warnings.delivery');
        }
        if (in_array('status', $errorTabs)) {
            $this->displayWarning('warnings.status');
        }
        if (in_array('payment', $errorTabs)) {
            $this->displayWarning('warnings.payment');
        }
        if (in_array('deliveryDefault', $errorTabs) || in_array('paymentDefault', $errorTabs)) {
            $this->displayWarning('warnings.default');
        }

        return null === $this->errors || 0 === count($this->errors);
    }

    private function updateConsultantCode()
    {
        $consultantCode = $this->consultantScript->getValue();

        if (!empty($consultantCode)) {
            $extractor = new RetailcrmConsultantRcctExtractor();
            $rcct = $extractor->setConsultantScript($consultantCode)->build()->getDataString();

            if (!empty($rcct)) {
                $this->consultantScript->updateValue();
                Configuration::updateValue(RetailCRM::CONSULTANT_RCCT, $rcct);
                Cache::getInstance()->set(RetailCRM::CONSULTANT_RCCT, $rcct);
            } else {
                $this->consultantScript->deleteValue();
                Configuration::deleteByName(RetailCRM::CONSULTANT_RCCT);
                Cache::getInstance()->delete(RetailCRM::CONSULTANT_RCCT);
            }
        }
    }

    private function displayError($message)
    {
        $this->errors[] = $message;
    }

    private function displayWarning($message)
    {
        $this->warnings[] = $message;
    }
}
