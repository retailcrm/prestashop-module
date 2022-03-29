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

class RetailcrmSettingsVueTemplate extends RetailcrmAbstractTemplate
{
    /**
     * @var RetailcrmSettings
     */
    private $settings;

    /**
     * RetailcrmSettingsTemplate constructor.
     *
     * @param \Module $module
     * @param $smarty
     * @param $assets
     */
    public function __construct(Module $module, $smarty, $assets)
    {
        parent::__construct($module, $smarty, $assets);

        $this->settings = new RetailcrmSettingsItems();
        $this->consultantScript = new RetailcrmSettingsItemHtml('consultantScript', RetailCRM::CONSULTANT_SCRIPT);
    }

    /**
     * Build params for template
     *
     * @return mixed
     */
    protected function getParams()
    {
        $params = [];

        $deliveryTypesCMS = $this->module->reference->getDeliveryTypes();
        $paymentTypesCMS  = $this->module->reference->getSystemPaymentModules();
        $statusesCMS      = $this->module->reference->getStatuses();

        $deliveryTypesCRM = $this->module->reference->getApiDeliveryTypes();
        $paymentTypesCRM  = $this->module->reference->getApiPaymentTypes();
        $statusesCRM      = $this->module->reference->getApiStatusesWithGroup();

        $params['vue'] = [
            'locale' => Context::getContext()->getCurrentLocale()->getCode(),
            'controller' => [
                'settings' => RetailcrmTools::getAdminControllerUrl(RetailcrmSettingsController::class),
                'orders' => RetailcrmTools::getAdminControllerUrl(RetailcrmOrdersController::class),
                'export' => RetailcrmTools::getAdminControllerUrl(RetailcrmExportController::class),
                'link' => RetailcrmTools::getAdminControllerUrl(AdminOrdersController::class),
                'jobs' => RetailcrmTools::getAdminControllerUrl(RetailcrmJobsController::class),
                'logs' => RetailcrmTools::getAdminControllerUrl(RetailcrmLogsController::class),
            ],
            'main' => [
                'connection' => [
                    'url' => (string)(Configuration::get(RetailCRM::API_URL)),
                    'apiKey' => (string)(Configuration::get(RetailCRM::API_KEY)),
                ],
                'delivery' => [
                    'cms' => $deliveryTypesCMS,
                    'crm' => $deliveryTypesCRM,
                    'setting' => json_decode(Configuration::get(RetailCRM::DELIVERY), true),
                ],
                'payment' => [
                    'cms' => $paymentTypesCMS,
                    'crm' => $paymentTypesCRM,
                    'setting' => json_decode(Configuration::get(RetailCRM::PAYMENT), true),
                ],
                'status' => [
                    'cms' => $statusesCMS,
                    'crm' => $statusesCRM,
                    'setting' => json_decode(Configuration::get(RetailCRM::STATUS), true),
                ],
            ],
            'additional' => [
                'settings' => [
                    'corporate' => (bool)(Configuration::get(RetailCRM::ENABLE_CORPORATE_CLIENTS)),
                    'numberSend' => (bool)(Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_SENDING)),
                    'numberReceive' => (bool)(Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_RECEIVING)),
                    'webJobs' => RetailcrmTools::isWebJobsEnabled(),
                    'debug' => RetailcrmTools::isDebug(),
                ],
                'history' => [
                    'enabled' => (bool)(Configuration::get(RetailCRM::ENABLE_HISTORY_UPLOADS)),
                    'delivery' => $deliveryTypesCMS,
                    'payment' => $paymentTypesCMS,
                    'deliveryDefault' => $this->settings->getValueStored('deliveryDefault'),
                    'paymentDefault' => $this->settings->getValueStored('paymentDefault'),
                ],
                'stocks' => [
                    'enabled' => $this->settings->getValueStored('enableBalancesReceiving'),
                    'statuses' => $this->settings->getValueStored('outOfStockStatus'),
                ],
                'carts' => [
                    'settings' => [
                        'synchronizeCartsActive' => $this->settings->getValueStored('synchronizeCartsActive'),
                        'synchronizedCartStatus' => $this->settings->getValueStored('synchronizedCartStatus'),
                        'synchronizedCartDelay' => $this->settings->getValueStored('synchronizedCartDelay'),
                    ],
                    'delays' => $this->module->getSynchronizedCartsTimeSelect(), // todo move to helper function
                ],
                'collector' => [
                    'collectorActive' => $this->settings->getValueStored('collectorActive'),
                    'collectorKey' => $this->settings->getValueStored('collectorKey'),
                ],
                'consultant' => [
                    'consultantScript' => $this->consultantScript->getValueStored(),
                ],
            ],
            'catalog' => [
                'info' => RetailcrmCatalogHelper::getIcmlFileInfo(),
                'generateName' => RetailcrmIcmlEvent::class,
                'updateURLName' => RetailcrmIcmlUpdateUrlEvent::class,
            ],
            'advanced' => [
                'jobs' => RetailcrmSettingsHelper::getJobsInfo(),
                'logs' => RetailcrmLoggerHelper::getLogFilesInfo(),
            ],
        ];

        return $params;
    }

    protected function buildParams()
    {
        $this->data = array_merge(
            [
                'assets' => $this->assets,
            ],
            $this->getParams()
        );
    }

    /**
     * Set template data
     */
    protected function setTemplate()
    {
        $this->template = 'vue.tpl';
    }
}
