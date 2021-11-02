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
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmReferences
{
    const GIFT_WRAPPING_ITEM_EXTERNAL_ID = 'giftWrappingCost';

    public $default_lang;
    public $carriers;
    public $payment_modules = [];
    public $apiStatuses;

    /**
     * @var bool|RetailcrmApiClientV5|RetailcrmProxy
     */
    private $api;

    public function __construct($client)
    {
        $this->api = $client;
        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->carriers = Carrier::getCarriers(
            $this->default_lang,
            true,
            false,
            false,
            null,
            PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE
        );
    }

    public function getDeliveryTypes()
    {
        $deliveryTypes = [];
        $apiDeliveryTypes = $this->getApiDeliveryTypes();

        if (!empty($this->carriers)) {
            foreach ($this->carriers as $carrier) {
                $deliveryTypes[] = [
                    'type' => 'select',
                    'label' => $carrier['name'],
                    'name' => 'RETAILCRM_API_DELIVERY[' . $carrier['id_carrier'] . ']',
                    'subname' => $carrier['id_carrier'],
                    'required' => false,
                    'options' => [
                        'query' => $apiDeliveryTypes,
                        'id' => 'id_option',
                        'name' => 'name',
                    ],
                ];
            }
        }

        return $deliveryTypes;
    }

    public function getStatuses()
    {
        $statusTypes = [];
        $states = OrderState::getOrderStates($this->default_lang, true);
        $this->apiStatuses = $this->apiStatuses ?: $this->getApiStatuses();

        if (!empty($states)) {
            foreach ($states as $state) {
                if (' ' != $state['name']) {
                    $key = $state['id_order_state'];
                    $statusTypes[] = [
                        'type' => 'select',
                        'label' => $state['name'],
                        'name' => "RETAILCRM_API_STATUS[$key]",
                        'subname' => $key,
                        'required' => false,
                        'options' => [
                            'query' => $this->apiStatuses,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ];
                }
            }
        }

        return $statusTypes;
    }

    public function getOutOfStockStatuses($arParams)
    {
        $statusTypes = [];
        $this->apiStatuses = $this->apiStatuses ?: $this->getApiStatuses();

        foreach ($arParams as $key => $state) {
            $statusTypes[] = [
                'type' => 'select',
                'label' => $state,
                'name' => "RETAILCRM_API_OUT_OF_STOCK_STATUS[$key]",
                'subname' => $key,
                'required' => false,
                'options' => [
                    'query' => $this->apiStatuses,
                    'id' => 'id_option',
                    'name' => 'name',
                ],
            ];
        }

        return $statusTypes;
    }

    public function getPaymentTypes()
    {
        $payments = $this->getSystemPaymentModules();
        $paymentTypes = [];
        $apiPaymentTypes = $this->getApiPaymentTypes();

        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $paymentTypes[] = [
                    'type' => 'select',
                    'label' => $payment['name'],
                    'name' => 'RETAILCRM_API_PAYMENT[' . $payment['code'] . ']',
                    'subname' => $payment['code'],
                    'required' => false,
                    'options' => [
                        'query' => $apiPaymentTypes,
                        'id' => 'id_option',
                        'name' => 'name',
                    ],
                ];
            }
        }

        return $paymentTypes;
    }

    public function getPaymentAndDeliveryForDefault($arParams)
    {
        $paymentTypes = [];
        $deliveryTypes = [];

        $paymentDeliveryTypes = [];

        if (!empty($this->carriers)) {
            $deliveryTypes[] = [
                'id_option' => '',
                'name' => '',
            ];

            foreach ($this->carriers as $valCarrier) {
                $deliveryTypes[] = [
                    'id_option' => $valCarrier['id_carrier'],
                    'name' => $valCarrier['name'],
                ];
            }

            $paymentDeliveryTypes[] = [
                'type' => 'select',
                'label' => $arParams[0],
                'name' => 'RETAILCRM_API_DELIVERY_DEFAULT',
                'required' => false,
                'options' => [
                    'query' => $deliveryTypes,
                    'id' => 'id_option',
                    'name' => 'name',
                ],
            ];
        }
        $paymentModules = $this->getSystemPaymentModules();
        if (!empty($paymentModules)) {
            $paymentTypes[] = [
                'id_option' => '',
                'name' => '',
            ];

            foreach ($paymentModules as $valPayment) {
                $paymentTypes[$valPayment['id']] = [
                    'id_option' => $valPayment['code'],
                    'name' => $valPayment['name'],
                ];
            }

            $paymentDeliveryTypes[] = [
                'type' => 'select',
                'label' => $arParams[1],
                'name' => 'RETAILCRM_API_PAYMENT_DEFAULT',
                'required' => false,
                'options' => [
                    'query' => $paymentTypes,
                    'id' => 'id_option',
                    'name' => 'name',
                ],
            ];
        }

        return $paymentDeliveryTypes;
    }

    public function getSystemPaymentModules($active = true)
    {
        $shop_id = (int) Context::getContext()->shop->id;

        /**
         * Get all modules then select only payment ones
         */
        $modules = RetailCRM::getCachedCmsModulesList();
        $allPaymentModules = PaymentModule::getInstalledPaymentModules();
        $paymentModulesIds = [];

        foreach ($allPaymentModules as $module) {
            $paymentModulesIds[] = $module['id_module'];
        }

        foreach ($modules as $module) {
            if ((!empty($module->parent_class) && 'PaymentModule' == $module->parent_class)
                || in_array($module->id, $paymentModulesIds)
            ) {
                if ($module->id) {
                    $module_id = (int) $module->id;

                    if ('SimpleXMLElement' == !get_class($module)) {
                        $module->country = [];
                    }
                    $countries = DB::getInstance()->executeS('SELECT id_country FROM ' . _DB_PREFIX_ . 'module_country WHERE id_module = ' . pSQL($module_id) . ' AND `id_shop`=' . pSQL($shop_id));
                    foreach ($countries as $country) {
                        $module->country[] = $country['id_country'];
                    }
                    if ('SimpleXMLElement' == !get_class($module)) {
                        $module->currency = [];
                    }
                    $currencies = DB::getInstance()->executeS('SELECT id_currency FROM ' . _DB_PREFIX_ . 'module_currency WHERE id_module = ' . pSQL($module_id) . ' AND `id_shop`=' . pSQL($shop_id));
                    foreach ($currencies as $currency) {
                        $module->currency[] = $currency['id_currency'];
                    }
                    if ('SimpleXMLElement' == !get_class($module)) {
                        $module->group = [];
                    }
                    $groups = DB::getInstance()->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'module_group WHERE id_module = ' . pSQL($module_id) . ' AND `id_shop`=' . pSQL($shop_id));
                    foreach ($groups as $group) {
                        $module->group[] = $group['id_group'];
                    }
                } else {
                    $module->country = null;
                    $module->currency = null;
                    $module->group = null;
                }

                if (0 != $module->active || false === $active) {
                    $this->payment_modules[] = [
                        'id' => $module->id,
                        'code' => $module->name,
                        'name' => $module->displayName,
                    ];
                }
            }
        }

        return $this->payment_modules;
    }

    public function getStatuseDefaultExport()
    {
        return $this->getApiStatuses();
    }

    public function getApiDeliveryTypes()
    {
        $crmDeliveryTypes = [];
        $request = $this->api->deliveryTypesList();

        if ($request) {
            $crmDeliveryTypes[] = [
                'id_option' => '',
                'name' => '',
            ];
            foreach ($request->deliveryTypes as $dType) {
                if (!$dType['active']) {
                    continue;
                }

                $crmDeliveryTypes[] = [
                    'id_option' => $dType['code'],
                    'name' => $dType['name'],
                ];
            }
        }

        return $crmDeliveryTypes;
    }

    public function getApiStatuses()
    {
        $crmStatusTypes = [];
        $request = $this->api->statusesList();

        if ($request) {
            $crmStatusTypes[] = [
                'id_option' => '',
                'name' => '',
                'ordering' => '',
            ];
            foreach ($request->statuses as $sType) {
                if (!$sType['active']) {
                    continue;
                }

                $crmStatusTypes[] = [
                    'id_option' => $sType['code'],
                    'name' => $sType['name'],
                    'ordering' => $sType['ordering'],
                ];
            }
            usort($crmStatusTypes, function ($a, $b) {
                if ($a['ordering'] == $b['ordering']) {
                    return 0;
                } else {
                    return $a['ordering'] < $b['ordering'] ? -1 : 1;
                }
            });
        }

        return $crmStatusTypes;
    }

    public function getApiPaymentTypes()
    {
        $crmPaymentTypes = [];
        $request = $this->api->paymentTypesList();

        if ($request) {
            $crmPaymentTypes[] = [
                'id_option' => '',
                'name' => '',
            ];
            foreach ($request->paymentTypes as $pType) {
                if (!$pType['active']) {
                    continue;
                }

                $crmPaymentTypes[] = [
                    'id_option' => $pType['code'],
                    'name' => $pType['name'],
                ];
            }
        }

        return $crmPaymentTypes;
    }

    public function getSite()
    {
        try {
            $response = $this->api->credentials();

            if (!($response instanceof RetailcrmApiResponse) || !$response->isSuccessful()
                || 'access_selective' !== $response['siteAccess']
                || 1 !== count($response['sitesAvailable'])
                || !in_array('/api/reference/sites', $response['credentials'])
                || !in_array('/api/reference/sites/{code}/edit', $response['credentials'])
            ) {
                RetailcrmLogger::writeCaller(
                    __METHOD__,
                    sprintf(
                        'ShopID=%s: Error with CRM credentials: need an valid apiKey assigned to one certain site',
                        Shop::getContextShopID()
                    )
                );

                return null;
            }

            $response = $this->api->sitesList();

            if ($response instanceof RetailcrmApiResponse && $response->isSuccessful()
                && $response->offsetExists('sites') && $response['sites']) {
                return current($response['sites']);
            }
        } catch (Exception $e) {
            RetailcrmLogger::writeCaller(
                __METHOD__,
                sprintf(
                    'Error: %s',
                    $e->getMessage()
                )
            );
        }

        return null;
    }

    public function getStores()
    {
        $storesShop = $this->getShopStores();
        $retailcrmStores = $this->getApiStores();

        foreach ($storesShop as $key => $storeShop) {
            $stores[] = [
                'type' => 'select',
                'name' => 'RETAILCRM_STORES[' . $key . ']',
                'label' => $storeShop,
                'options' => [
                    'query' => $retailcrmStores,
                    'id' => 'id_option',
                    'name' => 'name',
                ],
            ];
        }

        return $stores;
    }

    protected function getShopStores()
    {
        $stores = [];
        $warehouses = Warehouse::getWarehouses();

        foreach ($warehouses as $warehouse) {
            $arrayName = explode('-', $warehouse['name']);
            $warehouseName = trim($arrayName[1]);
            $stores[$warehouse['id_warehouse']] = $warehouseName;
        }

        return $stores;
    }

    protected function getApiStores()
    {
        $crmStores = [];
        $response = $this->api->storesList();

        if ($response) {
            $crmStores[] = [
                'id_option' => '',
                'name' => '',
            ];

            foreach ($response->stores as $store) {
                $crmStores[] = [
                    'id_option' => $store['code'],
                    'name' => $store['name'],
                ];
            }
        }

        return $crmStores;
    }
}
