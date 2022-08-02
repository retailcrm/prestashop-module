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
    /**
     * @var RetailcrmSettingsItems
     */
    private $settings;

    /**
     * @var RetailcrmSettingsItemHtml
     */
    private $consultantScript;

    /**
     * @var RetailcrmSettingsValidator
     */
    private $validator;

    public function __construct(RetailCRM $module)
    {
        $this->settings = new RetailcrmSettingsItems();
        $this->consultantScript = new RetailcrmSettingsItemHtml('consultantScript', RetailCRM::CONSULTANT_SCRIPT);

        $this->validator = new RetailcrmSettingsValidator($this->settings, $module->reference);
    }

    /**
     * Save settings handler
     *
     * @return array
     */
    public function save()
    {
        if ($this->validator->validate(true)) {
            $this->settings->updateValueAll();
        }

        $changed = $this->settings->getChanged();

        if ($this->consultantScript->issetValue()) {
            $this->updateConsultantCode();
            $changed['consultantScript'] = $this->consultantScript->getValueStored();
        }

        return [
            'success' => $this->validator->getSuccess(),
            'errors' => $this->validator->getErrors(),
            'warnings' => $this->validator->getWarnings(),
            'changed' => $changed,
        ];
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
}
