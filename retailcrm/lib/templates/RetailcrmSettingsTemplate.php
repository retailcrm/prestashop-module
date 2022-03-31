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

class RetailcrmSettingsTemplate extends RetailcrmAbstractTemplate
{
    /**
     * @var RetailcrmSettingsItems
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
        $paymentTypesCMS = $this->module->reference->getSystemPaymentModules();
        $statusesCMS = $this->module->reference->getStatuses();

        $deliveryTypesCRM = $this->module->reference->getApiDeliveryTypes();
        $paymentTypesCRM = $this->module->reference->getApiPaymentTypes();
        $statusesCRM = $this->module->reference->getApiStatusesWithGroup();

        $params['vue'] = [
            'locale' => $this->getCurrentLanguageISO(),
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
                    'url' => $this->settings->getValueStored('url'),
                    'apiKey' => $this->settings->getValueStored('apiKey'),
                ],
                'delivery' => [
                    'setting' => $this->settings->getValueStored('delivery'),
                    'cms' => $deliveryTypesCMS,
                    'crm' => $deliveryTypesCRM,
                ],
                'payment' => [
                    'setting' => $this->settings->getValueStored('payment'),
                    'cms' => $paymentTypesCMS,
                    'crm' => $paymentTypesCRM,
                ],
                'status' => [
                    'setting' => $this->settings->getValueStored('status'),
                    'cms' => $statusesCMS,
                    'crm' => $statusesCRM,
                ],
            ],
            'additional' => [
                'settings' => [
                    'corporate' => $this->settings->getValueStored('enableCorporate'),
                    'numberSend' => $this->settings->getValueStored('enableOrderNumberSending'),
                    'numberReceive' => $this->settings->getValueStored('enableOrderNumberReceiving'),
                    'webJobs' => $this->settings->getValueStored('webJobs'),
                    'debug' => $this->settings->getValueStored('debugMode'),
                ],
                'history' => [
                    'enabled' => $this->settings->getValueStored('enableHistoryUploads'),
                    'deliveryDefault' => $this->settings->getValueStored('deliveryDefault'),
                    'paymentDefault' => $this->settings->getValueStored('paymentDefault'),
                    'delivery' => $deliveryTypesCMS,
                    'payment' => $paymentTypesCMS,
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
                    'delays' => RetailcrmSettingsHelper::getCartDelays(),
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
