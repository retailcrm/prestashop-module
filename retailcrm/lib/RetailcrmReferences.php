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
    public $default_lang;
    public $carriers;
    public $payment_modules = array();

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
        $deliveryTypes = array();
        $apiDeliveryTypes = $this->getApiDeliveryTypes();

        if (!empty($this->carriers)) {
            foreach ($this->carriers as $carrier) {
                $deliveryTypes[] = array(
                    'type' => 'select',
                    'label' => $carrier['name'],
                    'name' => 'RETAILCRM_API_DELIVERY[' . $carrier['id_carrier'] . ']',
                    'subname' => $carrier['id_carrier'],
                    'required' => false,
                    'options' => array(
                        'query' => $apiDeliveryTypes,
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                );
            }
        }

        return $deliveryTypes;
    }

    public function getStatuses()
    {
        $statusTypes = array();
        $states = OrderState::getOrderStates($this->default_lang, true);
        $apiStatuses = $this->getApiStatuses();

        if (!empty($states)) {
            foreach ($states as $state) {
                if ($state['name'] != ' ') {
                    $key = $state['id_order_state'];
                    $statusTypes[] = array(
                        'type' => 'select',
                        'label' => $state['name'],
                        'name' => "RETAILCRM_API_STATUS[$key]",
                        'subname' => $key,
                        'required' => false,
                        'options' => array(
                            'query' => $apiStatuses,
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    );
                }
            }
        }

        return $statusTypes;
    }

    public function getPaymentTypes()
    {
        $payments = $this->getSystemPaymentModules();
        $paymentTypes = array();
        $apiPaymentTypes = $this->getApiPaymentTypes();

        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $paymentTypes[] = array(
                    'type' => 'select',
                    'label' => $payment['name'],
                    'name' => 'RETAILCRM_API_PAYMENT[' . $payment['code'] . ']',
                    'subname' => $payment['code'],
                    'required' => false,
                    'options' => array(
                        'query' => $apiPaymentTypes,
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                );
            }
        }

        return $paymentTypes;
    }

    public function getPaymentAndDeliveryForDefault($arParams)
    {
        $paymentTypes = array();
        $deliveryTypes = array();

        $paymentDeliveryTypes = array();

        if (!empty($this->carriers)) {

            $deliveryTypes[] = array(
                'id_option' => '',
                'name' => '',
            );

            foreach ($this->carriers as $valCarrier) {
                $deliveryTypes[] = array(
                    'id_option' => $valCarrier['id_carrier'],
                    'name' => $valCarrier['name'],
                );
            }

            $paymentDeliveryTypes[] = array(
                'type' => 'select',
                'label' => $arParams[0],
                'name' => 'RETAILCRM_API_DELIVERY_DEFAULT',
                'required' => false,
                'options' => array(
                    'query' =>  $deliveryTypes,
                    'id' => 'id_option',
                    'name' => 'name'
                )
            );
        }
        $paymentModules = $this->getSystemPaymentModules();
        if (!empty($paymentModules)) {

            $paymentTypes[] = array(
                'id_option' => '',
                'name' => '',
            );

            foreach ($paymentModules as $valPayment) {
                $paymentTypes[$valPayment['id']] = array(
                    'id_option' => $valPayment['code'],
                    'name' => $valPayment['name'],
                );
            }

            $paymentDeliveryTypes[] = array(
                'type' => 'select',
                'label' => $arParams[1],
                'name' => 'RETAILCRM_API_PAYMENT_DEFAULT',
                'required' => false,
                'options' => array(
                    'query' => $paymentTypes,
                    'id' => 'id_option',
                    'name' => 'name'
                )
            );
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

        foreach ($modules as $module) {
            if (!empty($module->parent_class) && $module->parent_class == 'PaymentModule') {
                if ($module->id) {
                    $module_id = (int) $module->id;

                    if (!get_class($module) == 'SimpleXMLElement')
                        $module->country = array();
                    $countries = DB::getInstance()->executeS('SELECT id_country FROM ' . _DB_PREFIX_ . 'module_country WHERE id_module = ' . pSQL($module_id) . ' AND `id_shop`=' . pSQL($shop_id));
                    foreach ($countries as $country)
                        $module->country[] = $country['id_country'];
                    if (!get_class($module) == 'SimpleXMLElement')
                        $module->currency = array();
                    $currencies = DB::getInstance()->executeS('SELECT id_currency FROM ' . _DB_PREFIX_ . 'module_currency WHERE id_module = ' . pSQL($module_id) . ' AND `id_shop`=' . pSQL($shop_id));
                    foreach ($currencies as $currency)
                        $module->currency[] = $currency['id_currency'];
                    if (!get_class($module) == 'SimpleXMLElement')
                        $module->group = array();
                    $groups = DB::getInstance()->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'module_group WHERE id_module = ' . pSQL($module_id) . ' AND `id_shop`=' . pSQL($shop_id));
                    foreach ($groups as $group)
                        $module->group[] = $group['id_group'];
                } else {
                    $module->country = null;
                    $module->currency = null;
                    $module->group = null;
                }

                if ($module->active != 0 || $active === false) {
                    $this->payment_modules[] = array(
                        'id' => $module->id,
                        'code' => $module->name,
                        'name' => $module->displayName
                    );
                }
            }
        }

        return $this->payment_modules;
    }

    public function getStatuseDefaultExport()
    {
        return $this->getApiStatuses();
    }

    protected function getApiDeliveryTypes()
    {
        $crmDeliveryTypes = array();
        $request = $this->api->deliveryTypesList();

        if ($request) {
            $crmDeliveryTypes[] = array(
                'id_option' => '',
                'name' => '',
            );
            foreach ($request->deliveryTypes as $dType) {
                $crmDeliveryTypes[] = array(
                    'id_option' => $dType['code'],
                    'name' => $dType['name'],
                );
            }
        }

        return $crmDeliveryTypes;
    }

    protected function getApiStatuses()
    {
        $crmStatusTypes = array();
        $request = $this->api->statusesList();

        if ($request) {
            $crmStatusTypes[] = array(
                'id_option' => '',
                'name' => '',
            );
            foreach ($request->statuses as $sType) {
                $crmStatusTypes[] = array(
                    'id_option' => $sType['code'],
                    'name' => $sType['name']
                );
            }
        }

        return $crmStatusTypes;
    }

    protected function getApiPaymentTypes()
    {
        $crmPaymentTypes = array();
        $request = $this->api->paymentTypesList();

        if ($request) {
            $crmPaymentTypes[] = array(
                'id_option' => '',
                'name' => '',
            );
            foreach ($request->paymentTypes as $pType) {
                $crmPaymentTypes[] = array(
                    'id_option' => $pType['code'],
                    'name' => $pType['name']
                );
            }
        }

        return $crmPaymentTypes;
    }

    public function getStores()
    {
        $storesShop = $this->getShopStores();
        $retailcrmStores = $this->getApiStores();

        foreach ($storesShop as $key => $storeShop) {
            $stores[] = array(
                'type' => 'select',
                'name' => 'RETAILCRM_STORES['. $key .']',
                'label' => $storeShop,
                'options' => array(
                    'query' => $retailcrmStores,
                    'id' => 'id_option',
                    'name' => 'name'
                )
            );
        }

        return $stores;
    }

    protected function getShopStores()
    {
        $stores = array();
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
        $crmStores = array();
        $response = $this->api->storesList();

        if ($response) {
            $crmStores[] = array(
                'id_option' => '',
                'name' => ''
            );

            foreach ($response->stores as $store) {
                $crmStores[] = array(
                    'id_option' => $store['code'],
                    'name' => $store['name']
                );
            }
        }

        return $crmStores;
    }
}
