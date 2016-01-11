<?php

class RetailcrmReferences
{

    public function __construct($client)
    {
        $this->api = $client;
        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $this->default_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
    }

    public function getDeliveryTypes()
    {
        $deliveryTypes = array();

        $carriers = Carrier::getCarriers($this->default_lang, true, false, false, null, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

        if (!empty($carriers)) {
            foreach ($carriers as $carrier) {
                $deliveryTypes[] = array(
                    'type' => 'select',
                    'label' => $carrier['name'],
                    'name' => 'RETAILCRM_API_DELIVERY[' . $carrier['id_carrier'] . ']',
                    'required' => false,
                    'options' => array(
                        'query' => $this->getApiDeliveryTypes(),
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
        $states = array_merge(
            OrderState::getOrderStates($this->default_lang, true),
            OrderReturnState::getOrderReturnStates($this->default_lang, true)
        );

        if (!empty($states)) {
            foreach ($states as $state) {
                if ($state['name'] != ' ') {
                    $statusTypes[] = array(
                        'type' => 'select',
                        'label' => $state['name'],
                        'name' => 'RETAILCRM_API_STATUS[' . $state['id_order_state'] . ']',
                        'required' => false,
                        'options' => array(
                            'query' => $this->getApiStatuses(),
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

        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $paymentTypes[] = array(
                    'type' => 'select',
                    'label' => $payment['name'],
                    'name' => 'RETAILCRM_API_PAYMENT[' . $payment['code'] . ']',
                    'required' => false,
                    'options' => array(
                        'query' => $this->getApiPaymentTypes(),
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                );
            }
        }

        return $paymentTypes;
    }

    protected function getSystemPaymentModules()
    {
        $shop_id = Context::getContext()->shop->id;

        /* Get all modules then select only payment ones */
        $modules = Module::getModulesOnDisk(true);

        foreach ($modules as $module) {
            if ($module->tab == 'payments_gateways') {
                if ($module->id) {
                    if (!get_class($module) == 'SimpleXMLElement')
                        $module->country = array();
                    $countries = DB::getInstance()->executeS('SELECT id_country FROM ' . _DB_PREFIX_ . 'module_country WHERE id_module = ' . (int) $module->id . ' AND `id_shop`=' . (int) $shop_id);
                    foreach ($countries as $country)
                        $module->country[] = $country['id_country'];
                    if (!get_class($module) == 'SimpleXMLElement')
                        $module->currency = array();
                    $currencies = DB::getInstance()->executeS('SELECT id_currency FROM ' . _DB_PREFIX_ . 'module_currency WHERE id_module = ' . (int) $module->id . ' AND `id_shop`=' . (int) $shop_id);
                    foreach ($currencies as $currency)
                        $module->currency[] = $currency['id_currency'];
                    if (!get_class($module) == 'SimpleXMLElement')
                        $module->group = array();
                    $groups = DB::getInstance()->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'module_group WHERE id_module = ' . (int) $module->id . ' AND `id_shop`=' . (int) $shop_id);
                    foreach ($groups as $group)
                        $module->group[] = $group['id_group'];
                } else {
                    $module->country = null;
                    $module->currency = null;
                    $module->group = null;
                }

                if ($module->active != 0) {
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

    protected function getApiDeliveryTypes()
    {
        $crmDeliveryTypes = array();
        $request = $this->api->deliveryTypesList();

        if ($request->isSuccessful()) {
            $crmDeliveryTypes[] = array();
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

        if ($request->isSuccessful()) {
            $crmStatusTypes[] = array();
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

        if ($request->isSuccessful()) {
            $crmPaymentTypes[] = array();
            foreach ($request->paymentTypes as $pType) {
                $crmPaymentTypes[] = array(
                    'id_option' => $pType['code'],
                    'name' => $pType['name']
                );
            }
        }

        return $crmPaymentTypes;
    }

}
