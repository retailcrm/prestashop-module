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
class RetailcrmHistoryHelper {
    public static function assemblyOrder($orderHistory)
    {
        if (file_exists( __DIR__ . '/../objects.xml')) {
            $objects = simplexml_load_file(__DIR__ . '/../objects.xml');
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
                if (!$orders[$change['order']['id']]['payments'][$change['payment']['id']] && $fields['payment'][$change['field']]) {
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
                if (!isset($orders[$change['order']['id']]['items'][$change['item']['id']]['create'])
                    && isset($fields['item'][$change['field']]) && $fields['item'][$change['field']]
                ) {
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
        $fields = array();

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

            if (isset($change['deleted'])
                && $change['deleted']
                && isset($customers[$change['customer']['id']])
            ) {
                $customers[$change['customer']['id']]['deleted'] = true;
                continue;
            }

            if ($change['field'] == 'id') {
                $customers[$change['customer']['id']] = $change['customer'];
            }

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

            // email_marketing_unsubscribed_at old value will be null and new value will be datetime in
            // `Y-m-d H:i:s` format if customer was marked as unsubscribed in retailCRM
            if (isset($change['customer']['id']) &&
                $change['field'] == 'email_marketing_unsubscribed_at'
            ) {
                if ($change['oldValue'] == null && is_string(self::newValue($change['newValue']))) {
                    $customers[$change['customer']['id']]['subscribed'] = false;
                } elseif (is_string($change['oldValue']) && self::newValue($change['newValue']) == null) {
                    $customers[$change['customer']['id']]['subscribed'] = true;
                }
            }

            // Sometimes address can be found in this key.
            if (isset($change['address'])) {
                $customers[$change['customer']['id']]['address'] = $change['address'];
            }
        }

        return $customers;
    }

    public static function assemblyCorporateCustomer($customerHistory)
    {
        $fields = array();

        if (file_exists(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml')) {
            $objects = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml');

            foreach($objects->fields->field as $object) {
                if (in_array($object["group"], array('customerCorporate', 'customerAddress'))) {
                    $fields[(string)$object["group"]][(string)$object["id"]] = (string)$object;
                }
            }
        }

        $customersCorporate = array();
        foreach ($customerHistory as $change) {
            $change['customer'] = self::removeEmpty($change['customer']);

            if (isset($change['deleted'])
                && $change['deleted']
                && isset($customersCorporate[$change['customer']['id']])
            ) {
                $customersCorporate[$change['customer']['id']]['deleted'] = true;
                continue;
            }

            if (isset($customersCorporate[$change['customer']['id']])) {
                if (isset($customersCorporate[$change['customer']['id']]['deleted'])
                    && $customersCorporate[$change['customer']['id']]['deleted']
                ) {
                    continue;
                }

                $customersCorporate[$change['customer']['id']] = array_merge($customersCorporate[$change['customer']['id']], $change['customer']);
            } else {
                $customersCorporate[$change['customer']['id']] = $change['customer'];
            }

            if (isset($fields['customerCorporate'][$change['field']])
                && $fields['customerCorporate'][$change['field']]
            ) {
                $customersCorporate[$change['customer']['id']][$fields['customerCorporate'][$change['field']]] = self::newValue($change['newValue']);
            }

            if (isset($fields['customerAddress'][$change['field']])
                && $fields['customerAddress'][$change['field']]
            ) {
                if (empty($customersCorporate[$change['customer']['id']]['address'])) {
                    $customersCorporate[$change['customer']['id']]['address'] = array();
                }

                $customersCorporate[$change['customer']['id']]['address'][$fields['customerAddress'][$change['field']]] = self::newValue($change['newValue']);
            }

            if ($change['field'] == 'address') {
                $customersCorporate[$change['customer']['id']]['address'] = array_merge($change['address'], self::newValue($change['newValue']));
            }
        }

        foreach ($customersCorporate as $id => &$customer) {
            if (empty($customer['id']) && !empty($id)) {
                $customer['id'] = $id;
                $customer['deleted'] = true;
            }
        }

        return $customersCorporate;
    }

    public static function newValue($value)
    {
        if (isset($value['code'])) {
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
