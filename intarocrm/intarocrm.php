<?php

require 'classes/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class IntaroCRM extends Module
{
    function __construct()
    {
        $this->name = 'intarocrm';
        $this->tab = 'market_place';
        $this->version = '0.1';
        $this->author = 'Intaro Ltd.';

        $this->displayName = $this->l('IntaroCRM');
        $this->description = $this->l('Integration module for IntaroCRM');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        parent::__construct();
    }

    function install()
    {
        return (parent::install());
    }

    function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('INTAROCRM_ADDRESS') &&
            Configuration::deleteByName('INTAROCRM_API_TOKEN')
        ;
    }

    public function getContent()
    {
        $output = null;

        $address = Configuration::get('INTAROCRM_ADDRESS');
        $token = Configuration::get('INTAROCRM_API_TOKEN');

        if (!$address || $address == '') {
            $output .= $this->displayError( $this->l('Invalid crm address') );
        } elseif (!$token || $token == '') {
            $output .= $this->displayError( $this->l('Invalid crm api token') );
        }

        if (Tools::isSubmit('submit'.$this->name))
        {
            $address = strval(Tools::getValue('INTAROCRM_ADDRESS'));
            $token = strval(Tools::getValue('INTAROCRM_API_TOKEN'));

            if (!$address || empty($address) || !Validate::isGenericName($address)) {
                $output .= $this->displayError( $this->l('Invalid crm address') );
            } elseif (!$token || empty($token) || !Validate::isGenericName($token)) {
                $output .= $this->displayError( $this->l('Invalid crm api token') );
            } else {
                Configuration::updateValue('INTAROCRM_ADDRESS', $address);
                Configuration::updateValue('INTAROCRM_API_TOKEN', $token);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        $this->displayConfirmation($this->l('Settings updated'));

        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

         $intaroCrm = new \IntaroCrm\RestApi(
            Configuration::get('INTAROCRM_ADDRESS'),
            Configuration::get('INTAROCRM_API_TOKEN')
         );

        /*
         * Network connection form
         */
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Network connection'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('CRM address'),
                    'name' => 'INTAROCRM_ADDRESS',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('CRM token'),
                    'name' => 'INTAROCRM_API_TOKEN',
                    'size' => 20,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        /*
         * Arrays for dictionaries
         */
        $delivery_dict = array();
        $crmDeliveryTypes = array();
        $status_dict = array();
        $crmStatusTypes = array();

        /*
         * Delivery dictionary form
         */
        try {
            $deliveryTypes = $intaroCrm->deliveryTypesList();
        }
        catch (\IntaroCrm\Exception\CurlException $e) {
            error_log('deliveryTypesList: connection error');
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('deliveryTypesList: ' . $e->getMessage());
        }

        if (!empty($deliveryTypes)) {
            $crmDeliveryTypes[] = array();
            foreach ($deliveryTypes as $dType) {
                $crmDeliveryTypes[] = array(
                    'id_option' => $dType['code'],
                    'name' => $dType['name']
                );
            }
        }

        $carriers = Carrier::getCarriers(
            $default_lang, true, false, false,
            null, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE
        );

        if (!empty($carriers)) {
            foreach ($carriers as $carrier) {
                $delivery_dict[] = array(
                    'type' => 'select',
                    'label' => $carrier['name'],
                    'name' => 'carrier_' . $carrier['id_carrier'],
                    'required' => false,
                    'options' => array(
                        'query' => $crmDeliveryTypes,
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                );
            }
        }

        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Delivery'),
            ),
            'input' => $delivery_dict,
        );

        /*
         * Status dictionary form
         */
        try {
            $statusTypes = $intaroCrm->orderStatusesList();
        }
        catch (\IntaroCrm\Exception\CurlException $e) {
            error_log('statusTypesList: connection error');
        }
        catch (\IntaroCrm\Exception\ApiException $e) {
            error_log('statusTypesList: ' . $e->getMessage());
        }

        if (!empty($statusTypes)) {
            $crmStatusTypes[] = array();
            foreach ($statusTypes as $sType) {
                $crmStatusTypes[] = array(
                    'id_option' => $sType['code'],
                    'name' => $sType['name']
                );
            }
        }

        $states = OrderState::getOrderStates($default_lang, true);

        if (!empty($states)) {
            foreach ($states as $state) {
                if ($state['name'] != ' ') {
                    $status_dict[] = array(
                        'type' => 'select',
                        'label' => $state['name'],
                        'name' => 'state_' . $state['id_order_state'],
                        'required' => false,
                        'options' => array(
                            'query' => $crmStatusTypes,
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    );
                }
            }
        }

        $fields_form[2]['form'] = array(
            'legend' => array(
                'title' => $this->l('Order statuses'),
            ),
            'input' => $status_dict,
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $helper->fields_value['INTAROCRM_ADDRESS'] = Configuration::get('INTAROCRM_ADDRESS');
        $helper->fields_value['INTAROCRM_API_TOKEN'] = Configuration::get('INTAROCRM_API_TOKEN');

        return $helper->generateForm($fields_form);
    }
}
