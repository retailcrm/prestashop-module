<?php
/**
 * MIT License
 *
 * Copyright (c) 2019 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @copyright 2007-2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmSettingsTemplate extends RetailcrmAbstractTemplate
{
    protected $settings;
    protected $settingsNames;

    /**
     * RetailcrmSettingsTemplate constructor.
     *
     * @param \Module $module
     * @param         $smarty
     * @param         $assets
     * @param         $settings
     * @param         $settingsNames
     */
    public function __construct(Module $module, $smarty, $assets, $settings, $settingsNames)
    {
        parent::__construct($module, $smarty, $assets);

        $this->settings = $settings;
        $this->settingsNames = $settingsNames;
    }

    /**
     * Build params for template
     *
     * @return mixed
     */
    protected function getParams()
    {
        $params = array();

        if ($this->module->api) {
            $params['statusesDefaultExport'] = $this->module->reference->getStatuseDefaultExport();
            $params['deliveryTypes'] = $this->module->reference->getDeliveryTypes();
            $params['orderStatuses'] = $this->module->reference->getStatuses();
            $params['paymentTypes'] = $this->module->reference->getPaymentTypes();
            $params['methodsForDefault'] = $this->module->reference->getPaymentAndDeliveryForDefault(
                array($this->module->translate('Delivery method'), $this->module->translate('Payment type'))
            );
        }

        return $params;
    }

    protected function buildParams()
    {
        $this->data = array_merge(
            array(
                'assets' => $this->assets,
                'cartsDelays' => $this->module->getSynchronizedCartsTimeSelect(),
            ),
            $this->getParams(),
            $this->settingsNames,
            $this->settings
        );
    }

    /**
     * Set template data
     */
    protected function setTemplate()
    {
        $this->template = "settings.tpl";
    }
}
