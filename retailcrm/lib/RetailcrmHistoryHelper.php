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

class RetailcrmHistoryHelper
{
    public static function assemblyOrder($orderHistory)
    {
        if (file_exists(dirname(__FILE__) . '/../objects.xml')) {
            $objects = simplexml_load_file(dirname(__FILE__) . '/../objects.xml');
            foreach ($objects->fields->field as $object) {
                $fields[(string) $object['group']][(string) $object['id']] = (string) $object;
            }
        }
        $orders = [];
        foreach ($orderHistory as $change) {
            $change['order'] = self::removeEmpty($change['order']);

            if (isset($change['order']['items']) && $change['order']['items']) {
                $items = [];

                foreach ($change['order']['items'] as $item) {
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
                if (null == $change['oldValue'] && 'payments' == $change['field']) {
                    $orders[$change['order']['id']]['payments'][$change['payment']['id']]['create'] = true;
                }
                if (null == $change['newValue'] && 'payments' == $change['field']) {
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

                if (empty($change['oldValue']) && 'order_product' == $change['field']) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['create'] = true;
                }
                if (empty($change['newValue']) && 'order_product' == $change['field']) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['delete'] = true;
                }
                if (!isset($orders[$change['order']['id']]['items'][$change['item']['id']]['create'])
                    && isset($fields['item'][$change['field']]) && $fields['item'][$change['field']]
                ) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']][$fields['item'][$change['field']]] = $change['newValue'];
                }
            } else {
                if (isset($fields['delivery'][$change['field']])
                    && 'service' == $fields['delivery'][$change['field']]
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
                } elseif (false !== strripos($change['field'], 'custom_')) {
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

                if (isset($change['deleted'])) {
                    $orders[$change['order']['id']]['deleted'] = 1;
                }
            }
        }

        return $orders;
    }

    public static function assemblyCustomer($customerHistory)
    {
        $fields = [];

        if (file_exists(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml')) {
            $objects = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml');

            foreach ($objects->fields->field as $object) {
                if ('customer' == $object['group']) {
                    $fields[(string) $object['group']][(string) $object['id']] = (string) $object;
                }
            }
        }

        $customers = [];

        foreach ($customerHistory as $change) {
            $change['customer'] = self::removeEmpty($change['customer']);

            if (isset($change['deleted'])
                && $change['deleted']
            ) {
                $customers[$change['customer']['id']]['deleted'] = true;
                continue;
            }

            if ('id' == $change['field']) {
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
            if (isset($change['customer']['id'])
                && 'email_marketing_unsubscribed_at' == $change['field']
            ) {
                if (null == $change['oldValue'] && is_string(self::newValue($change['newValue']))) {
                    $customers[$change['customer']['id']]['subscribed'] = false;
                } elseif (is_string($change['oldValue']) && null == self::newValue($change['newValue'])) {
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
        $fields = [];

        if (file_exists(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml')) {
            $objects = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/retailcrm/objects.xml');

            foreach ($objects->fields->field as $object) {
                if (in_array($object['group'], ['customerCorporate', 'customerAddress'])) {
                    $fields[(string) $object['group']][(string) $object['id']] = (string) $object;
                }
            }
        }

        $customersCorporate = [];
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
                    $customersCorporate[$change['customer']['id']]['address'] = [];
                }

                $customersCorporate[$change['customer']['id']]['address'][$fields['customerAddress'][$change['field']]] = self::newValue($change['newValue']);
            }

            if ('address' == $change['field']) {
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
        $outputArray = [];
        if (!empty($inputArray)) {
            foreach ($inputArray as $key => $element) {
                if (!empty($element) || 0 === $element || '0' === $element) {
                    if (is_array($element)) {
                        $element = self::removeEmpty($element);
                    }
                    $outputArray[$key] = $element;
                }
            }
        }

        return $outputArray;
    }

    /**
     * @param array $address Crm Order address changes
     *
     * @return bool <b>true</b> if changed address field, which is used to generate
     *              <b>address1</b> and <b>address2</b> fields in CMS. <b>false</b> otherwise
     */
    public static function isAddressLineChanged($address)
    {
        // TODO countryIso, city, index and region added because module can't get changes of [text] field with api.
        //  Should be removed when the api logic is updated
        $keys = [
            'countryIso',
            'city',
            'index',
            'region',
            'street',
            'building',
            'flat',
            'floor',
            'block',
            'house',
            'housing',
            'metro',
            'notes',
        ];

        foreach ($address as $key => $value) {
            if (in_array($key, $keys)) {
                return true;
            }
        }

        return false;
    }
}
