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

        if (!empty($this->carriers)) {
            foreach ($this->carriers as $carrier) {
                $deliveryTypes[] = [
                    'label' => $carrier['name'],
                    'id' => $carrier['id_carrier'],
                    'required' => false,
                ];
            }
        }

        return $deliveryTypes;
    }

    public function getStatuses()
    {
        $statusTypes = [];
        $states = OrderState::getOrderStates($this->default_lang, true);

        if (!empty($states)) {
            foreach ($states as $state) {
                if (' ' != $state['name']) {
                    $statusTypes[] = [
                        'label' => $state['name'],
                        'id' => $state['id_order_state'],
                        'required' => false,
                    ];
                }
            }
        }

        return $statusTypes;
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

    public function getApiStatusesWithGroup()
    {
        if (!$this->api) {
            return [];
        }

        $request = $this->api->statusesList();
        $requestGroups = $this->api->statusGroupsList();

        if (!$request || !$requestGroups) {
            return [];
        }

        $crmStatusTypes = [];
        foreach ($request->statuses as $sType) {
            if (!$sType['active']) {
                continue;
            }

            $crmStatusTypes[$sType['group']]['statuses'][] = [
                'code' => $sType['code'],
                'name' => $sType['name'],
                'ordering' => $sType['ordering'],
            ];
        }

        foreach ($requestGroups->statusGroups as $statusGroup) {
            if (!isset($crmStatusTypes[$statusGroup['code']])) {
                continue;
            }

            $crmStatusTypes[$statusGroup['code']]['code'] = $statusGroup['code'];
            $crmStatusTypes[$statusGroup['code']]['name'] = $statusGroup['name'];
            $crmStatusTypes[$statusGroup['code']]['ordering'] = $statusGroup['ordering'];
        }

        usort($crmStatusTypes, function ($a, $b) {
            if ($a['ordering'] == $b['ordering']) {
                return 0;
            } else {
                return $a['ordering'] < $b['ordering'] ? -1 : 1;
            }
        });

        foreach ($crmStatusTypes as &$crmStatusType) {
            usort($crmStatusType['statuses'], function ($a, $b) {
                if ($a['ordering'] == $b['ordering']) {
                    return 0;
                } else {
                    return $a['ordering'] < $b['ordering'] ? -1 : 1;
                }
            });
        }

        return $crmStatusTypes;
    }

    public function getApiDeliveryTypes()
    {
        if (!$this->api) {
            return [];
        }

        $crmDeliveryTypes = [];
        $request = $this->api->deliveryTypesList();

        if (!$request) {
            return [];
        }

        $crmDeliveryTypes[] = [
            'code' => '',
            'name' => '',
        ];

        foreach ($request->deliveryTypes as $dType) {
            if (!$dType['active']) {
                continue;
            }

            $crmDeliveryTypes[] = [
                'code' => $dType['code'],
                'name' => $dType['name'],
            ];
        }

        return $crmDeliveryTypes;
    }

    /**
     * Used in \RetailcrmSettings::validateStoredSettings to validate api statuses
     *
     * @return array
     */
    public function getApiStatuses()
    {
        if (!$this->api) {
            return [];
        }

        $crmStatusTypes = [];
        $request = $this->api->statusesList();

        if (!$request) {
            return [];
        }

        $crmStatusTypes[] = [
            'code' => '',
            'name' => '',
            'ordering' => '',
        ];
        foreach ($request->statuses as $sType) {
            if (!$sType['active']) {
                continue;
            }

            $crmStatusTypes[] = [
                'code' => $sType['code'],
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

        return $crmStatusTypes;
    }

    public function getApiPaymentTypes()
    {
        if (!$this->api) {
            return [];
        }

        $crmPaymentTypes = [];
        $request = $this->api->paymentTypesList();

        if (!$request) {
            return [];
        }

        $crmPaymentTypes[] = [
            'code' => '',
            'name' => '',
        ];

        foreach ($request->paymentTypes as $pType) {
            if (!$pType['active']) {
                continue;
            }

            $crmPaymentTypes[] = [
                'code' => $pType['code'],
                'name' => $pType['name'],
            ];
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
            RetailcrmLogger::writeException(__METHOD__, $e->getMessage(), null, false);
        } catch (Error $e) {
            RetailcrmLogger::writeException(__METHOD__, $e->getMessage(), null, false);
        }

        return null;
    }
}
