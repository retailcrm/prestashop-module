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
class RetailcrmTools
{
    /**
     * @var int
     */
    static $currentStatusCode;

    /** @var int */
    public static $default_lang;

    /**
     * @return int
     */
    public static function defaultLang()
    {
        if (empty(self::$default_lang)) {
            self::$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        }

        return self::$default_lang;
    }

    /**
     * Returns true if corporate customers are enabled in settings
     *
     * @return bool
     */
    public static function isCorporateEnabled()
    {
        return (bool)Configuration::get(RetailCRM::ENABLE_CORPORATE_CLIENTS);
    }

    /**
     * Returns true if customer is corporate
     *
     * @param Customer $customer
     *
     * @return bool
     */
    public static function isCustomerCorporate($customer)
    {
        $addresses = $customer->getAddresses((int)Configuration::get('PS_LANG_DEFAULT'));

        foreach ($addresses as $address) {
            if (($address instanceof Address && !empty($address->company))
                || (is_array($address) && !empty($address['company']))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if order is corporate
     *
     * @param Order $order
     *
     * @return bool
     */
    public static function isOrderCorporate(Order $order)
    {
        if (empty($order->id_address_invoice)) {
            return false;
        }

        $address = new Address($order->id_address_invoice);

        return $address instanceof Address && !empty($address->company);
    }

    /**
     * Returns true if provided crm order is corporate
     *
     * @param array $order
     *
     * @return bool
     */
    public static function isCrmOrderCorporate($order)
    {
        return isset($order['customer']['type']) && $order['customer']['type'] == 'customer_corporate';
    }

    /**
     * Search address for individual customer (not corporate one)
     *
     * @param Customer|CustomerCore $customer
     *
     * @return int
     */
    public static function searchIndividualAddress($customer)
    {
        if (!empty($customer->id)) {
            foreach ($customer->getAddresses(self::defaultLang()) as $addressArray) {
                if ($addressArray['alias'] == 'default') {
                    return (int) $addressArray['id_address'];
                }
            }
        }

        return 0;
    }

    /**
     * Returns 'true' if provided date string is valid
     *
     * @param $date
     * @param string $format
     *
     * @return bool
     */
    public static function verifyDate($date, $format = "Y-m-d")
    {
        return $date !== "0000-00-00" && (bool)date_create_from_format($format, $date);
    }

    /**
     * Split a string to id
     *
     * @param string $ids string with id
     *
     * @return array|string
     */
    public static function partitionId($ids)
    {
        $ids = explode(',', $ids);

        $ranges = array();

        foreach ($ids as $idx => $uid) {
            if (strpos($uid, '-')) {
                $range = explode('-', $uid);
                $ranges = array_merge($ranges, range($range[0], $range[1]));
                unset($ids[$idx]);
            }
        }

        $ids = implode(',', array_merge($ids, $ranges));
        $ids = explode(',', $ids);

        return $ids;
    }

    /**
     * Dumps entity using it's definition mapping.
     *
     * @param \ObjectModel $object
     *
     * @return array|string
     */
    public static function dumpEntity($object)
    {
        if (empty($object)) {
            ob_start();
            var_dump($object);
            return (string) ob_get_clean();
        }

        $data = array();
        $type = get_class($object);

        if (property_exists($type, 'definition')) {
            $defs = $type::$definition;

            if (!empty($defs['fields'])) {
                if (property_exists($object, 'id')) {
                    $data['id'] = $object->id;
                }

                foreach (array_keys($defs['fields']) as $field) {
                    if (property_exists($object, $field)) {
                        $data[$field] = $object->$field;
                    }
                }

            }
        }

        return $data;
    }

    /**
     * Converts CMS address to CRM address
     *
     * @param       $address
     * @param array $customer
     * @param array $order
     * @deprecated Replaced with RetailcrmAddressBuilder
     *
     * @return array
     */
    public static function addressParse($address, &$customer = array(), &$order = array())
    {
        if (!isset($customer)) {
            $customer = array();
        }

        if (!isset($order)) {
            $order = array();
        }

        if ($address instanceof Address) {
            $postcode = $address->postcode;
            $city = $address->city;
            $addres_line = sprintf("%s %s", $address->address1, $address->address2);
            $countryIso = Country::getIsoById($address->id_country);
            $vat = $address->vat_number;
        }

        if (!empty($postcode)) {
            $customer['address']['index'] = $postcode;
            $order['delivery']['address']['index'] = $postcode;
        }

        if (!empty($city)) {
            $customer['address']['city'] = $city;
            $order['delivery']['address']['city'] = $city;
        }

        if (!empty($addres_line)) {
            $customer['address']['text'] = $addres_line;
            $order['delivery']['address']['text'] = $addres_line;
        }

        if (!empty($countryIso)) {
            $order['countryIso'] = $countryIso;
            $customer['address']['countryIso'] = $countryIso;
        }

        $phones = static::getPhone($address, $customer, $order);
        $order = array_merge($order, $phones['order']);
        $customer = array_merge($customer, $phones['customer']);

        return array(
            'order' => RetailcrmTools::clearArray($order),
            'customer' => RetailcrmTools::clearArray($customer),
            'vat' => isset($vat) && !empty($vat) ? $vat : ''
        );
    }

    public static function getPhone($address, &$customer = array(), &$order = array())
    {
        if (!isset($customer)) {
            $customer = array();
        }

        if (!isset($order)) {
            $order = array();
        }

        if (!empty($address->phone_mobile)) {
            $order['phone'] = $address->phone_mobile;
            $customer['phones'][] = array('number'=> $address->phone_mobile);
        }

        if (!empty($address->phone)) {
            $order['additionalPhone'] = $address->phone;
            $customer['phones'][] = array('number'=> $address->phone);
        }

        if (!isset($order['phone']) && !empty($order['additionalPhone'])) {
            $order['phone'] = $order['additionalPhone'];
            unset($order['additionalPhone']);
        }

        $phonesArray = array('customer' => $customer, 'order' => $order);

        return $phonesArray;
    }

    /**
     * Validate crm address
     *
     * @param $address
     *
     * @return bool
     */
    public static function validateCrmAddress($address)
    {
        if (preg_match("/https:\/\/(.*).retailcrm.(pro|ru|es)/", $address) === 1) {
            return true;
        }

        return false;
    }

    public static function getDate($file)
    {
        if (file_exists($file)) {
            $result = file_get_contents($file);
        } else {
            $result = date('Y-m-d H:i:s', strtotime('-1 days', strtotime(date('Y-m-d H:i:s'))));
        }

        return $result;
    }

    public static function explodeFIO($string)
    {
        $result = array();
        $parse = (!$string) ? false : explode(" ", $string, 3);

        switch (count($parse)) {
            case 1:
                $result['firstName'] = $parse[0];
                break;
            case 2:
                $result['firstName'] = $parse[1];
                $result['lastName'] = $parse[0];
                break;
            case 3:
                $result['firstName'] = $parse[1];
                $result['lastName'] = $parse[0];
                $result['patronymic'] = $parse[2];
                break;
            default:
                return false;
        }

        return $result;
    }

    /**
     * Returns externalId for order
     *
     * @param Cart $cart
     *
     * @return string
     */
    public static function getCartOrderExternalId(Cart $cart)
    {
        return sprintf('pscart_%d', $cart->id);
    }

    /**
     * Unset empty fields
     *
     * @param array $arr input array
     * @param callable|null $filterFunc
     *
     * @return array
     * @todo Don't filter out false & all methods MUST NOT use false as blank value.
     */
    public static function clearArray(array $arr, $filterFunc = null)
    {
        if (!is_array($arr)) {
            return $arr;
        }

        $result = array();

        foreach ($arr as $index => $node) {
            $result[$index] = (is_array($node))
                ? self::clearArray($node)
                : $node;

            if ($result[$index] == ''
                || $result[$index] === null
                || (is_array($result[$index]) && count($result[$index]) < 1)
            ) {
                unset($result[$index]);
            }
        }

        if (is_callable($filterFunc)) {
            return array_filter($result, $filterFunc);
        }

        return array_filter($result);
    }

    /**
     * Returns true if PrestaShop in debug mode or _RCRM_MODE_DEV_ const defined to true.
     * Add define('_RCRM_MODE_DEV_', true); to enable extended logging (dev mode) ONLY for retailCRM module.
     * In developer mode module will log every JobManager run and every request and response from retailCRM API.
     *
     * @return bool
     */
    public static function isDebug()
    {
        return (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ == true)
            || (defined('_RCRM_MODE_DEV_') && _RCRM_MODE_DEV_ == true);
    }

    /**
     * Generates placeholder email
     *
     * @param string $name
     *
     * @return string
     */
    public static function createPlaceholderEmail($name)
    {
        return substr(md5($name), 0, 15) . '@example.com';
    }

    /**
     * Returns API client proxy if connection is configured.
     * Returns null if connection is not configured.
     *
     * @return \RetailcrmProxy|\RetailcrmApiClientV5|null
     */
    public static function getApiClient()
    {
        $apiUrl = Configuration::get(RetailCRM::API_URL);
        $apiKey = Configuration::get(RetailCRM::API_KEY);

        if (!empty($apiUrl) && !empty($apiKey)) {
            return new RetailcrmProxy($apiUrl, $apiKey, RetailcrmLogger::getLogFile());
        }

        return null;
    }

    public static function isWebJobsEnabled()
    {
        return '0' !== Configuration::getGlobalValue(RetailCRM::ENABLE_WEB_JOBS);
    }

    /**
     * Merge new address to customer, preserves old phone numbers.
     *
     * @param array $customer
     * @param array $address
     *
     * @return array
     */
    public static function mergeCustomerAddress($customer, $address)
    {
        $customerPhones = isset($customer['phones']) ? $customer['phones'] : array();
        $addressPhones = isset($address['phones']) ? $address['phones'] : array();
        $squashedCustomerPhones = array_filter(array_map(function ($val) {
            return isset($val['number']) ? $val['number'] : null;
        }, $customerPhones));

        foreach ($addressPhones as $newPhone) {
            if (empty($newPhone['number'])) {
                continue;
            }

            if (!in_array($newPhone['number'], $squashedCustomerPhones)) {
                $customerPhones[] = $newPhone;
            }
        }

        return array_merge($customer, $address, array('phones' => $customerPhones));
    }

    /**
     * http_response_code polyfill
     *
     * @param null $code
     *
     * @return int|null
     */
    public static function http_response_code($code = null)
    {
        if (function_exists('http_response_code')) {
            $code = http_response_code($code);
        } else {
            if ($code !== NULL) {
                switch ($code) {
                    case 100: $text = 'Continue'; break;
                    case 101: $text = 'Switching Protocols'; break;
                    case 200: $text = 'OK'; break;
                    case 201: $text = 'Created'; break;
                    case 202: $text = 'Accepted'; break;
                    case 203: $text = 'Non-Authoritative Information'; break;
                    case 204: $text = 'No Content'; break;
                    case 205: $text = 'Reset Content'; break;
                    case 206: $text = 'Partial Content'; break;
                    case 300: $text = 'Multiple Choices'; break;
                    case 301: $text = 'Moved Permanently'; break;
                    case 302: $text = 'Moved Temporarily'; break;
                    case 303: $text = 'See Other'; break;
                    case 304: $text = 'Not Modified'; break;
                    case 305: $text = 'Use Proxy'; break;
                    case 400: $text = 'Bad Request'; break;
                    case 401: $text = 'Unauthorized'; break;
                    case 402: $text = 'Payment Required'; break;
                    case 403: $text = 'Forbidden'; break;
                    case 404: $text = 'Not Found'; break;
                    case 405: $text = 'Method Not Allowed'; break;
                    case 406: $text = 'Not Acceptable'; break;
                    case 407: $text = 'Proxy Authentication Required'; break;
                    case 408: $text = 'Request Time-out'; break;
                    case 409: $text = 'Conflict'; break;
                    case 410: $text = 'Gone'; break;
                    case 411: $text = 'Length Required'; break;
                    case 412: $text = 'Precondition Failed'; break;
                    case 413: $text = 'Request Entity Too Large'; break;
                    case 414: $text = 'Request-URI Too Large'; break;
                    case 415: $text = 'Unsupported Media Type'; break;
                    case 500: $text = 'Internal Server Error'; break;
                    case 501: $text = 'Not Implemented'; break;
                    case 502: $text = 'Bad Gateway'; break;
                    case 503: $text = 'Service Unavailable'; break;
                    case 504: $text = 'Gateway Time-out'; break;
                    case 505: $text = 'HTTP Version not supported'; break;
                    default:
                        exit('Unknown http status code "' . htmlentities($code) . '"');
                        break;
                }

                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

                header($protocol . ' ' . $code . ' ' . $text);
            } else {
                $code = !empty(static::$currentStatusCode) ? static::$currentStatusCode : 200;
            }
        }

        return $code;
    }

    /**
     * This assertion returns true if customer was changed from legal entity to individual person.
     * It doesn't return true if customer was changed from one individual person to another.
     *
     * @param array $assembledOrder Order data, assembled from history
     *
     * @return bool True if customer in order was changed from corporate to regular
     */
    public static function isCustomerChangedToRegular($assembledOrder)
    {
        return isset($assembledOrder['contragentType']) && $assembledOrder['contragentType'] == 'individual';
    }

    /**
     * This assertion returns true if customer was changed from individual person to a legal entity.
     * It doesn't return true if customer was changed from one legal entity to another.
     *
     * @param array $assembledOrder Order data, assembled from history
     *
     * @return bool True if customer in order was changed from corporate to regular
     */
    public static function isCustomerChangedToLegal($assembledOrder)
    {
        return isset($assembledOrder['contragentType']) && $assembledOrder['contragentType'] == 'legal-entity';
    }

    /**
     * Get value by key from array if it exists, returns default value otherwise.
     *
     * @param array|\ArrayObject|\ArrayAccess $arr
     * @param string $key
     * @param string $def
     *
     * @return mixed|string
     */
    public static function arrayValue($arr, $key, $def = '')
    {
        if (!is_array($arr) && !($arr instanceof ArrayObject) && !($arr instanceof ArrayAccess)) {
            return $def;
        }

        if (!array_key_exists($key, $arr) && !empty($arr[$key])) {
            return $def;
        }

        return isset($arr[$key]) ? $arr[$key] : $def;
    }

    /**
     * Assign address ID and customer ID from customer addresses.
     * Customer ID in the address isn't checked (it will be set to id from provided customer, even if it doesn't have ID yet).
     *
     * @param Customer|CustomerCore $customer
     * @param Address|\AddressCore  $address
     */
    public static function assignAddressIdsByFields($customer, $address)
    {
        RetailcrmLogger::writeDebugArray(__METHOD__, array('Called with customer', $customer->id, 'and address', self::dumpEntity($address)));

        foreach ($customer->getAddresses(self::defaultLang()) as $customerInnerAddress) {
            $customerAddress = new Address($customerInnerAddress['id_address']);

            if (self::isAddressesEqualByFields($address, $customerAddress)) {
                if ($address->id_customer != $customerAddress->id_customer) {
                    $address->id_customer = $customerAddress->id_customer;
                }

                $address->id = $customerAddress->id;
            }
        }
    }

    /**
     * Starts JobManager with list of pre-registered jobs
     *
     * @throws \Exception
     */
    public static function startJobManager()
    {
        RetailcrmJobManager::startJobs(array(
            'RetailcrmAbandonedCartsEvent' => null,
            'RetailcrmIcmlEvent' => new \DateInterval('PT4H'),
            'RetailcrmSyncEvent' => new \DateInterval('PT7M'),
            'RetailcrmInventoriesEvent' => new \DateInterval('PT15M')
        ));
    }

    /**
     * Returns true if mapped fields in address are equal. Returns false otherwise.
     *
     * @param \Address $first
     * @param \Address $second
     *
     * @return bool
     */
    protected static function isAddressesEqualByFields($first, $second)
    {
        $equal = true;
        $checkMapping = array(
            'alias',
            'id_country',
            'lastname',
            'firstname',
            'postcode',
            'city',
            'address1',
            'phone',
            'company',
            'vat_number'
        );

        foreach ($checkMapping as $field) {
            if ($first->$field != $second->$field) {
                $equal = false;
                RetailcrmLogger::writeDebugArray(__METHOD__, array(
                    'first' => self::dumpEntity($first),
                    'second' => self::dumpEntity($second),
                    'field' => array(
                        'name' => $field,
                        'firstValue' => $first->$field,
                        'secondValue' => $second->$field
                    )
                ));
                break;
            }
        }

        return $equal;
    }
}
