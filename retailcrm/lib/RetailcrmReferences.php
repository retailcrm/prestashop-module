<?php

class RetailcrmReferences
{

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

        if (!empty($this->carriers)) {
            foreach ($this->carriers as $carrier) {
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
        /**
         * TODO: state ids duplicates between both arrays, temporary disable return states
         *
        $states = array_merge(
            OrderState::getOrderStates($this->default_lang, true),
            OrderReturnState::getOrderReturnStates($this->default_lang, true)
        );
        */

        $states = OrderState::getOrderStates($this->default_lang, true);

        if (!empty($states)) {
            foreach ($states as $state) {
                if ($state['name'] != ' ') {
                    /*$key = isset($state['id_order_state'])
                        ? $state['id_order_state']
                        : $state['id_order_return_state']
                        ;*/
                    $key = $state['id_order_state'];
                    $statusTypes[] = array(
                        'type' => 'select',
                        'label' => $state['name'],
                        'name' => "RETAILCRM_API_STATUS[$key]",
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

public function getPaymentAndDeliveryForDefault()
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
                'label' => "Доставка",
                'name' => 'RETAILCRM_API_DELIVERY_DEFAULT',
                'required' => false,
                'options' => array(
                    'query' =>  $deliveryTypes,
                    'id' => 'id_option',
                    'name' => 'name'
                )
            );
        }
        if (!empty($this->getSystemPaymentModules())) {

            $paymentTypes[] = array(
                'id_option' => '',
                'name' => '',
            );

            foreach ($this->getSystemPaymentModules() as $valPayment) {
                $paymentTypes[$valPayment['id']] = array(
                    'id_option' => $valPayment['code'],
                    'name' => $valPayment['name'],
                );
            }

            $paymentDeliveryTypes[] = array(
                'type' => 'select',
                'label' => "Система оплаты",
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

}
