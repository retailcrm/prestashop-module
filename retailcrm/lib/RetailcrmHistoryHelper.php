<?php

class RetailcrmHistoryHelper {
    public static function assemblyOrder($orderHistory)
    {
        if (file_exists(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml')) {
            $objects = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml');
            foreach($objects->fields->field as $object) {
                $fields[(string)$object["group"]][(string)$object["id"]] = (string)$object;
            }
        }
        $orders = array();
        foreach ($orderHistory as $change) {
            $change['order'] = self::removeEmpty($change['order']);
            if (isset($change['order']['items']) && $change['order']['items']) {
                $items = array();
                foreach($change['order']['items'] as $item) {
                    if (isset($change['created'])) {
                        $item['create'] = 1;
                    }
                    $items[$item['id']] = $item;
                }
                $change['order']['items'] = $items;
            }

            if (isset($change['order']['contragent']['contragentType'])) {
                $change['order']['contragentType'] = $change['order']['contragent']['contragentType'];
                unset($change['order']['contragent']);
            }

            if (isset($orders[$change['order']['id']])) {
                $orders[$change['order']['id']] = array_merge($orders[$change['order']['id']], $change['order']);
            } else {
                $orders[$change['order']['id']] = $change['order'];
            }
            
            if (isset($change['payment'])) {
                if (isset($orders[$change['order']['id']]['payments'][$change['payment']['id']])) {
                    $orders[$change['order']['id']]['payments'][$change['payment']['id']] = array_merge($orders[$change['order']['id']]['payments'][$change['payment']['id']], $change['payment']);    
                } else {
                    $orders[$change['order']['id']]['payments'][$change['payment']['id']] = $change['payment'];
                }
                if ($change['oldValue'] == null && $change['field'] == 'payments') {
                    $orders[$change['order']['id']]['payments'][$change['payment']['id']]['create'] = true;
                }
                if ($change['newValue'] == null && $change['field'] == 'payments') {
                    $orders[$change['order']['id']]['payments'][$change['payment']['id']]['delete'] = true;
                }
                if (!$orders[$change['order']['id']]['payments'][$change['payment']['id']]['create'] && $fields['payment'][$change['field']]) {
                    $orders[$change['order']['id']]['payments'][$change['payment']['id']][$fields['payment'][$change['field']]] = $change['newValue'];
                }
            }
            
            if (isset($change['item'])) {
                if (isset($orders[$change['order']['id']]['items'][$change['item']['id']])) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']] = array_merge($orders[$change['order']['id']]['items'][$change['item']['id']], $change['item']);
                } else {
                    $orders[$change['order']['id']]['items'][$change['item']['id']] = $change['item'];
                }

                if (empty($change['oldValue']) && $change['field'] == 'order_product') {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['create'] = true;
                }
                if (empty($change['newValue']) && $change['field'] == 'order_product') {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['delete'] = true;
                }
                if (!$orders[$change['order']['id']]['items'][$change['item']['id']]['create'] && $fields['item'][$change['field']]) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']][$fields['item'][$change['field']]] = $change['newValue'];
                }
            } else {
                if (isset($fields['delivery'][$change['field']])
                    && $fields['delivery'][$change['field']] == 'service'
                ) {
                    $orders[$change['order']['id']]['delivery']['service']['code'] = self::newValue($change['newValue']);
                } elseif (isset($fields['delivery'][$change['field']])
                    && $fields['delivery'][$change['field']]
                ) {
                    $orders[$change['order']['id']]['delivery'][$fields['delivery'][$change['field']]] = self::newValue($change['newValue']);
                } elseif (isset($fields['orderAddress'][$change['field']])
                    && $fields['orderAddress'][$change['field']]
                ) {
                    $orders[$change['order']['id']]['delivery']['address'][$fields['orderAddress'][$change['field']]] = $change['newValue'];
                } elseif (isset($fields['integrationDelivery'][$change['field']])
                    && $fields['integrationDelivery'][$change['field']]
                ) {
                    $orders[$change['order']['id']]['delivery']['service'][$fields['integrationDelivery'][$change['field']]] = self::newValue($change['newValue']);
                } elseif (isset($fields['customerContragent'][$change['field']])
                    && $fields['customerContragent'][$change['field']]
                ) {
                    $orders[$change['order']['id']][$fields['customerContragent'][$change['field']]] = self::newValue($change['newValue']);
                } elseif (strripos($change['field'], 'custom_') !== false) {
                    $orders[$change['order']['id']]['customFields'][str_replace('custom_', '', $change['field'])] = self::newValue($change['newValue']);
                } elseif (isset($fields['order'][$change['field']])
                    && $fields['order'][$change['field']]
                ) {
                    $orders[$change['order']['id']][$fields['order'][$change['field']]] = self::newValue($change['newValue']);
                } elseif (isset($fields['payment'][$change['field']])
                    && $fields['payment'][$change['field']]
                ) {
                    $orders[$change['order']['id']]['payments'][$change['payment']['id']][$fields['payment'][$change['field']]] = self::newValue($change['newValue']);
                }

                if (isset($change['created'])) {
                    $orders[$change['order']['id']]['create'] = 1;
                }

                if(isset($change['deleted'])) {
                    $orders[$change['order']['id']]['deleted'] = 1;
                }
            }
        }

        return $orders;
    }

    public static function assemblyCustomer($customerHistory)
    {
        if (file_exists(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml')) {
            $objects = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml');

            foreach($objects->fields->field as $object) {
                if ($object["group"] == 'customer') {
                    $fields[(string)$object["group"]][(string)$object["id"]] = (string)$object;
                }
            }
        }

        $customers = array();
        foreach ($customerHistory as $change) {
            $change['customer'] = self::removeEmpty($change['customer']);

            if (isset($customers[$change['customer']['id']])) {
                $customers[$change['customer']['id']] = array_merge($customers[$change['customer']['id']], $change['customer']);
            } else {
                $customers[$change['customer']['id']] = $change['customer'];
            }

            if (isset($fields['customer'][$change['field']])
                && $fields['customer'][$change['field']]
            ) {
                $customers[$change['customer']['id']][$fields['customer'][$change['field']]] = self::newValue($change['newValue']);
            }
        }

        return $customers;
    }

    public static function newValue($value)
    {
        if(isset($value['code'])) {
            return $value['code'];
        } else {
            return $value;
        }
    }

    public static function removeEmpty($inputArray)
    {
        $outputArray = array();
        if (!empty($inputArray)) {
            foreach ($inputArray as $key => $element) {
                if(!empty($element) || $element === 0 || $element === '0'){
                    if (is_array($element)) {
                        $element = self::removeEmpty($element);
                    }
                    $outputArray[$key] = $element;
                }
            }
        }

        return $outputArray;
    }
}
