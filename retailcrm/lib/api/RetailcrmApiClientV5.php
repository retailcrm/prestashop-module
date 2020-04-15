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
class RetailcrmApiClientV5
{
    const VERSION = 'v5';

    protected $client;
    protected $unversionedClient;

    /**
     * Site code
     */
    protected $siteCode;

    /**
     * API Key
     */
    protected $apiKey;

    /**
     * Client creating
     *
     * @param string $url    api url
     * @param string $apiKey api key
     * @param string $site   site code
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function __construct($url, $apiKey, $site = null)
    {
        $unversionedUrl = ('/' !== $url[strlen($url) - 1] ? $url . '/' : $url) . 'api';

        if ('/' !== $url[strlen($url) - 1]) {
            $url .= '/';
        }

        $url = $url . 'api/' . self::VERSION;

        $this->client = new RetailcrmHttpClient($url, array('apiKey' => $apiKey));
        $this->apiKey = $apiKey;
        $this->unversionedClient = new RetailcrmHttpClient($unversionedUrl, array('apiKey' => $apiKey));
        $this->siteCode = $site;
    }

    /**
     * getSingleSiteForKey
     *
     * @return string|bool
     */
    public function getSingleSiteForKey()
    {
        $response = $this->credentials();

        if ($response instanceof RetailcrmApiResponse
            && isset($response['sitesAvailable'])
            && is_array($response['sitesAvailable'])
            && !empty($response['sitesAvailable'])
        ) {
            return $response['sitesAvailable'][0];
        }

        return false;
    }

    /**
     * /api/credentials response
     *
     * @return RetailcrmApiResponse|bool
     */
    public function credentials()
    {
        $response = $this->unversionedClient->makeRequest(
            '/credentials',
            RetailcrmHttpClient::METHOD_GET
        );

        if ($response instanceof RetailcrmApiResponse) {
            return $response;
        }

        return false;
    }

    /**
     * @return RetailcrmApiResponse
     * @throws CurlException
     * @throws InvalidArgumentException
     * @throws InvalidJsonException
     */
    public function apiVersions()
    {
        return $this->unversionedClient->makeRequest('/api-versions', RetailcrmHttpClient::METHOD_GET);
    }

    /**
     * Returns users list
     *
     * @param array $filter
     * @param null  $page
     * @param null  $limit
     *
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function usersList(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/users',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Returns user data
     *
     * @param integer $id user ID
     *
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function usersGet($id)
    {
        return $this->client->makeRequest("/users/$id", RetailcrmHttpClient::METHOD_GET);
    }

    /**
     * Change user status
     *
     * @param integer $id     user ID
     * @param string  $status user status
     *
     * @return RetailcrmApiResponse
     */
    public function usersStatus($id, $status)
    {
        $statuses = array("free", "busy", "dinner", "break");

        if (empty($status) || !in_array($status, $statuses)) {
            throw new \InvalidArgumentException(
                'Parameter `status` must be not empty & must be equal one of these values: free|busy|dinner|break'
            );
        }

        return $this->client->makeRequest(
            "/users/$id/status",
            RetailcrmHttpClient::METHOD_POST,
            array('status' => $status)
        );
    }

    /**
     * Get segments list
     *
     * @param array $filter
     * @param null  $limit
     * @param null  $page
     *
     * @return RetailcrmApiResponse
     */
    public function segmentsList(array $filter = array(), $limit = null, $page = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/segments',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Get custom fields list
     *
     * @param array $filter
     * @param null  $limit
     * @param null  $page
     *
     * @return RetailcrmApiResponse
     */
    public function customFieldsList(array $filter = array(), $limit = null, $page = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/custom-fields',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Create custom field
     *
     * @param $entity
     * @param $customField
     *
     * @return RetailcrmApiResponse
     */
    public function customFieldsCreate($entity, $customField)
    {
        if (!count($customField) ||
            empty($customField['code']) ||
            empty($customField['name']) ||
            empty($customField['type'])
        ) {
            throw new \InvalidArgumentException(
                'Parameter `customField` must contain a data & fields `code`, `name` & `type` must be set'
            );
        }

        if (empty($entity) || $entity != 'customer' || $entity != 'order') {
            throw new \InvalidArgumentException(
                'Parameter `entity` must contain a data & value must be `order` or `customer`'
            );
        }

        return $this->client->makeRequest(
            "/custom-fields/$entity/create",
            RetailcrmHttpClient::METHOD_POST,
            array('customField' => json_encode($customField))
        );
    }

    /**
     * Edit custom field
     *
     * @param $entity
     * @param $customField
     *
     * @return RetailcrmApiResponse
     */
    public function customFieldsEdit($entity, $customField)
    {
        if (!count($customField) || empty($customField['code'])) {
            throw new \InvalidArgumentException(
                'Parameter `customField` must contain a data & fields `code` must be set'
            );
        }

        if (empty($entity) || $entity != 'customer' || $entity != 'order') {
            throw new \InvalidArgumentException(
                'Parameter `entity` must contain a data & value must be `order` or `customer`'
            );
        }

        return $this->client->makeRequest(
            "/custom-fields/$entity/edit/{$customField['code']}",
            RetailcrmHttpClient::METHOD_POST,
            array('customField' => json_encode($customField))
        );
    }

    /**
     * Get custom field
     *
     * @param $entity
     * @param $code
     *
     * @return RetailcrmApiResponse
     */
    public function customFieldsGet($entity, $code)
    {
        if (empty($code)) {
            throw new \InvalidArgumentException(
                'Parameter `code` must be not empty'
            );
        }

        if (empty($entity) || $entity != 'customer' || $entity != 'order') {
            throw new \InvalidArgumentException(
                'Parameter `entity` must contain a data & value must be `order` or `customer`'
            );
        }

        return $this->client->makeRequest(
            "/custom-fields/$entity/$code",
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Get custom dictionaries list
     *
     * @param array $filter
     * @param null  $limit
     * @param null  $page
     *
     * @return RetailcrmApiResponse
     */
    public function customDictionariesList(array $filter = array(), $limit = null, $page = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/custom-fields/dictionaries',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Create custom dictionary
     *
     * @param $customDictionary
     *
     * @return RetailcrmApiResponse
     */
    public function customDictionariesCreate($customDictionary)
    {
        if (!count($customDictionary) ||
            empty($customDictionary['code']) ||
            empty($customDictionary['elements'])
        ) {
            throw new \InvalidArgumentException(
                'Parameter `dictionary` must contain a data & fields `code` & `elemets` must be set'
            );
        }

        return $this->client->makeRequest(
            "/custom-fields/dictionaries/{$customDictionary['code']}/create",
            RetailcrmHttpClient::METHOD_POST,
            array('customDictionary' => json_encode($customDictionary))
        );
    }

    /**
     * Edit custom dictionary
     *
     * @param $customDictionary
     *
     * @return RetailcrmApiResponse
     */
    public function customDictionariesEdit($customDictionary)
    {
        if (!count($customDictionary) ||
            empty($customDictionary['code']) ||
            empty($customDictionary['elements'])
        ) {
            throw new \InvalidArgumentException(
                'Parameter `dictionary` must contain a data & fields `code` & `elemets` must be set'
            );
        }

        return $this->client->makeRequest(
            "/custom-fields/dictionaries/{$customDictionary['code']}/edit",
            RetailcrmHttpClient::METHOD_POST,
            array('customDictionary' => json_encode($customDictionary))
        );
    }

    /**
     * Get custom dictionary
     *
     * @param $code
     *
     * @return RetailcrmApiResponse
     */
    public function customDictionariesGet($code)
    {
        if (empty($code)) {
            throw new \InvalidArgumentException(
                'Parameter `code` must be not empty'
            );
        }

        return $this->client->makeRequest(
            "/custom-fields/dictionaries/$code",
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Returns filtered orders list
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersList(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/orders',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Create a order
     *
     * @param array  $order order data
     * @param string $site  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersCreate(array $order, $site = null)
    {
        if (!count($order)) {
            throw new \InvalidArgumentException(
                'Parameter `order` must contains a data'
            );
        }

        return $this->client->makeRequest(
            '/orders/create',
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('order' => json_encode($order)))
        );
    }

    /**
     * Save order IDs' (id and externalId) association in the CRM
     *
     * @param array $ids order identificators
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersFixExternalIds(array $ids)
    {
        if (! count($ids)) {
            throw new \InvalidArgumentException(
                'Method parameter must contains at least one IDs pair'
            );
        }

        return $this->client->makeRequest(
            '/orders/fix-external-ids',
            RetailcrmHttpClient::METHOD_POST,
            array('orders' => json_encode($ids)
            )
        );
    }

    /**
     * Returns statuses of the orders
     *
     * @param array $ids         (default: array())
     * @param array $externalIds (default: array())
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersStatuses(array $ids = array(), array $externalIds = array())
    {
        $parameters = array();

        if (count($ids)) {
            $parameters['ids'] = $ids;
        }
        if (count($externalIds)) {
            $parameters['externalIds'] = $externalIds;
        }

        return $this->client->makeRequest(
            '/orders/statuses',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Upload array of the orders
     *
     * @param array  $orders array of orders
     * @param string $site   (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersUpload(array $orders, $site = null)
    {
        if (!count($orders)) {
            throw new \InvalidArgumentException(
                'Parameter `orders` must contains array of the orders'
            );
        }

        return $this->client->makeRequest(
            '/orders/upload',
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('orders' => json_encode($orders)))
        );
    }

    /**
     * Get order by id or externalId
     *
     * @param string $id   order identificator
     * @param string $by   (default: 'externalId')
     * @param string $site (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersGet($id, $by = 'externalId', $site = null)
    {
        $this->checkIdParameter($by);

        return $this->client->makeRequest(
            "/orders/$id",
            RetailcrmHttpClient::METHOD_GET,
            $this->fillSite($site, array('by' => $by))
        );
    }

    /**
     * Edit a order
     *
     * @param array  $order order data
     * @param string $by    (default: 'externalId')
     * @param string $site  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersEdit(array $order, $by = 'externalId', $site = null)
    {
        if (!count($order)) {
            throw new \InvalidArgumentException(
                'Parameter `order` must contains a data'
            );
        }

        $this->checkIdParameter($by);

        if (!array_key_exists($by, $order)) {
            throw new \InvalidArgumentException(
                sprintf('Order array must contain the "%s" parameter.', $by)
            );
        }

        return $this->client->makeRequest(
            sprintf('/orders/%s/edit', $order[$by]),
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite(
                $site,
                array('order' => json_encode($order), 'by' => $by)
            )
        );
    }

    /**
     * Get orders history
     * @param array $filter
     * @param null $page
     * @param null $limit
     *
     * @return RetailcrmApiResponse
     */
    public function ordersHistory(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/orders/history',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Combine orders
     *
     * @param string $technique
     * @param array  $order
     * @param array  $resultOrder
     *
     * @return RetailcrmApiResponse
     */
    public function ordersCombine($order, $resultOrder, $technique = 'ours')
    {
        $techniques = array('ours', 'summ', 'theirs');

        if (!count($order) || !count($resultOrder)) {
            throw new \InvalidArgumentException(
                'Parameters `order` & `resultOrder` must contains a data'
            );
        }

        if (!in_array($technique, $techniques)) {
            throw new \InvalidArgumentException(
                'Parameter `technique` must be on of ours|summ|theirs'
            );
        }

        return $this->client->makeRequest(
            '/orders/combine',
            RetailcrmHttpClient::METHOD_POST,
            array(
                'technique' => $technique,
                'order' => json_encode($order),
                'resultOrder' => json_encode($resultOrder)
            )
        );
    }

    /**
     * Create an order payment
     *
     * @param array $payment order data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPaymentCreate(array $payment)
    {
        if (!count($payment)) {
            throw new \InvalidArgumentException(
                'Parameter `payment` must contains a data'
            );
        }

        return $this->client->makeRequest(
            '/orders/payments/create',
            RetailcrmHttpClient::METHOD_POST,
            array('payment' => json_encode($payment))
        );
    }

    /**
     * Edit an order payment
     *
     * @param array  $payment order data
     * @param string $by      by key
     * @param null   $site    site code
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPaymentEdit(array $payment, $by = 'externalId', $site = null)
    {
        if (!count($payment)) {
            throw new \InvalidArgumentException(
                'Parameter `payment` must contains a data'
            );
        }

        $this->checkIdParameter($by);

        if (!array_key_exists($by, $payment)) {
            throw new \InvalidArgumentException(
                sprintf('Order array must contain the "%s" parameter.', $by)
            );
        }

        return $this->client->makeRequest(
            sprintf('/orders/payments/%s/edit', $payment[$by]),
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite(
                $site,
                array('payment' => json_encode($payment), 'by' => $by)
            )
        );
    }

    /**
     * Edit an order payment
     *
     * @param string $id payment id
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPaymentDelete($id)
    {
        if (!$id) {
            throw new \InvalidArgumentException(
                'Parameter `id` must be set'
            );
        }

        return $this->client->makeRequest(
            sprintf('/orders/payments/%s/delete', $id),
            RetailcrmHttpClient::METHOD_POST
        );
    }

    /**
     * Returns filtered customers list
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersList(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/customers',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Create a customer
     *
     * @param array  $customer customer data
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCreate(array $customer, $site = null)
    {
        if (! count($customer)) {
            throw new \InvalidArgumentException(
                'Parameter `customer` must contains a data'
            );
        }

        return $this->client->makeRequest(
            '/customers/create',
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('customer' => json_encode($customer)))
        );
    }

    /**
     * Save customer IDs' (id and externalId) association in the CRM
     *
     * @param array $ids ids mapping
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersFixExternalIds(array $ids)
    {
        if (! count($ids)) {
            throw new \InvalidArgumentException(
                'Method parameter must contains at least one IDs pair'
            );
        }

        return $this->client->makeRequest(
            '/customers/fix-external-ids',
            RetailcrmHttpClient::METHOD_POST,
            array('customers' => json_encode($ids))
        );
    }

    /**
     * Upload array of the customers
     *
     * @param array  $customers array of customers
     * @param string $site      (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersUpload(array $customers, $site = null)
    {
        if (! count($customers)) {
            throw new \InvalidArgumentException(
                'Parameter `customers` must contains array of the customers'
            );
        }

        return $this->client->makeRequest(
            '/customers/upload',
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('customers' => json_encode($customers)))
        );
    }

    /**
     * Get customer by id or externalId
     *
     * @param string $id   customer identificator
     * @param string $by   (default: 'externalId')
     * @param string $site (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersGet($id, $by = 'externalId', $site = null)
    {
        $this->checkIdParameter($by);

        return $this->client->makeRequest(
            "/customers/$id",
            RetailcrmHttpClient::METHOD_GET,
            $this->fillSite($site, array('by' => $by))
        );
    }

    /**
     * Edit a customer
     *
     * @param array  $customer customer data
     * @param string $by       (default: 'externalId')
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersEdit(array $customer, $by = 'externalId', $site = null)
    {
        if (!count($customer)) {
            throw new \InvalidArgumentException(
                'Parameter `customer` must contains a data'
            );
        }

        $this->checkIdParameter($by);

        if (!array_key_exists($by, $customer)) {
            throw new \InvalidArgumentException(
                sprintf('Customer array must contain the "%s" parameter.', $by)
            );
        }

        return $this->client->makeRequest(
            sprintf('/customers/%s/edit', $customer[$by]),
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite(
                $site,
                array('customer' => json_encode($customer), 'by' => $by)
            )
        );
    }

    /**
     * Get customers history
     * @param array $filter
     * @param null $page
     * @param null $limit
     *
     * @return RetailcrmApiResponse
     */
    public function customersHistory(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/customers/history',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Combine customers
     *
     * @param array $customers
     * @param array $resultCustomer
     *
     * @return RetailcrmApiResponse
     */
    public function customersCombine(array $customers, $resultCustomer)
    {

        if (!count($customers) || !count($resultCustomer)) {
            throw new \InvalidArgumentException(
                'Parameters `customers` & `resultCustomer` must contains a data'
            );
        }

        return $this->client->makeRequest(
            '/customers/combine',
            RetailcrmHttpClient::METHOD_POST,
            array(
                'customers' => json_encode($customers),
                'resultCustomer' => json_encode($resultCustomer)
            )
        );
    }

    /**
     * Returns filtered customers notes list
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersNotesList(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();
        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }
        return $this->client->makeRequest(
            '/customers/notes',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Create customer note
     *
     * @param array $note (default: array())
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersNotesCreate($note, $site = null)
    {
        if (empty($note['customer']['id']) && empty($note['customer']['externalId'])) {
            throw new \InvalidArgumentException(
                'Customer identifier must be set'
            );
        }
        return $this->client->makeRequest(
            '/customers/notes/create',
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('note' => json_encode($note)))
        );
    }

    /**
     * Delete customer note
     *
     * @param integer $id
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersNotesDelete($id)
    {
        if (empty($id)) {
            throw new \InvalidArgumentException(
                'Note id must be set'
            );
        }
        return $this->client->makeRequest(
            "/customers/notes/$id/delete",
            RetailcrmHttpClient::METHOD_POST
        );
    }

    /**
     * Returns filtered corporate customers list
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateList(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (isset($filter['contactIds'])) {
            $parameters['contactIds'] = $filter['contactIds'];
            unset($filter['contactIds']);
        }

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            '/customers-corporate',
            "GET",
            $parameters
        );
    }
    /**
     * Create a corporate customer
     *
     * @param array  $customerCorporate corporate customer data
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateCreate(array $customerCorporate, $site = null)
    {
        if (! count($customerCorporate)) {
            throw new \InvalidArgumentException(
                'Parameter `customerCorporate` must contains a data'
            );
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            '/customers-corporate/create',
            "POST",
            $this->fillSite($site, array('customerCorporate' => json_encode($customerCorporate)))
        );
    }
    /**
     * Save corporate customer IDs' (id and externalId) association in the CRM
     *
     * @param array $ids ids mapping
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateFixExternalIds(array $ids)
    {
        if (! count($ids)) {
            throw new \InvalidArgumentException(
                'Method parameter must contains at least one IDs pair'
            );
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            '/customers-corporate/fix-external-ids',
            "POST",
            array('customersCorporate' => json_encode($ids))
        );
    }
    /**
     * Get corporate customers history
     * @param array $filter
     * @param null $page
     * @param null $limit
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateHistory(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            '/customers-corporate/history',
            "GET",
            $parameters
        );
    }
    /**
     * Returns filtered corporate customers notes list
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateNotesList(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();
        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            '/customers-corporate/notes',
            "GET",
            $parameters
        );
    }
    /**
     * Create corporate customer note
     *
     * @param array $note (default: array())
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateNotesCreate($note, $site = null)
    {
        if (empty($note['customer']['id']) && empty($note['customer']['externalId'])) {
            throw new \InvalidArgumentException(
                'Customer identifier must be set'
            );
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            '/customers-corporate/notes/create',
            "POST",
            $this->fillSite($site, array('note' => json_encode($note)))
        );
    }
    /**
     * Delete corporate customer note
     *
     * @param integer $id
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateNotesDelete($id)
    {
        if (empty($id)) {
            throw new \InvalidArgumentException(
                'Note id must be set'
            );
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/notes/$id/delete",
            "POST"
        );
    }
    /**
     * Upload array of the corporate customers
     *
     * @param array  $customersCorporate array of corporate customers
     * @param string $site               (default: null)
     *
     * @return RetailcrmApiResponse
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @throws \InvalidArgumentException
     */
    public function customersCorporateUpload(array $customersCorporate, $site = null)
    {
        if (!count($customersCorporate)) {
            throw new \InvalidArgumentException(
                'Parameter `customersCorporate` must contains array of the corporate customers'
            );
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            '/customers-corporate/upload',
            "POST",
            $this->fillSite($site, array('customersCorporate' => json_encode($customersCorporate)))
        );
    }
    /**
     * Get corporate customer by id or externalId
     *
     * @param string $id   corporate customer identifier
     * @param string $by   (default: 'externalId')
     * @param string $site (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateGet($id, $by = 'externalId', $site = null)
    {
        $this->checkIdParameter($by);
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$id",
            "GET",
            $this->fillSite($site, array('by' => $by))
        );
    }
    /**
     * Get corporate customer addresses by id or externalId
     *
     * @param string $id     corporate customer identifier
     * @param array  $filter (default: array())
     * @param int    $page   (default: null)
     * @param int    $limit  (default: null)
     * @param string $by     (default: 'externalId')
     * @param string $site   (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateAddresses(
        $id,
        array $filter = array(),
        $page = null,
        $limit = null,
        $by = 'externalId',
        $site = null
    ) {
        $this->checkIdParameter($by);
        $parameters = array('by' => $by);
        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$id/addresses",
            "GET",
            $this->fillSite($site, $parameters)
        );
    }

    /**
     * Create corporate customer address
     *
     * @param string $id       corporate customer identifier
     * @param array  $address  (default: array())
     * @param string $by       (default: 'externalId')
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateAddressesCreate($id, array $address = array(), $by = 'externalId', $site = null)
    {
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$id/addresses/create",
            "POST",
            $this->fillSite($site, array('address' => json_encode($address), 'by' => $by))
        );
    }

    /**
     * Edit corporate customer address
     *
     * @param string $customerId corporate customer identifier
     * @param string $addressId  corporate customer identifier
     * @param array  $address    (default: array())
     * @param string $customerBy (default: 'externalId')
     * @param string $addressBy  (default: 'externalId')
     * @param string $site       (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateAddressesEdit(
        $customerId,
        $addressId,
        array $address = array(),
        $customerBy = 'externalId',
        $addressBy = 'externalId',
        $site = null
    ) {
        $addressFiltered = array_filter($address);
        if ((count(array_keys($addressFiltered)) <= 1)
            && (!isset($addressFiltered['text'])
                || (isset($addressFiltered['text']) && empty($addressFiltered['text']))
            )
        ) {
            throw new \InvalidArgumentException(
                'Parameter `address` must contain address text or all other address field'
            );
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$customerId/addresses/$addressId/edit",
            "POST",
            $this->fillSite($site, array(
                'address' => json_encode($address),
                'by' => $customerBy,
                'entityBy' => $addressBy
            ))
        );
    }
    /**
     * Get corporate customer companies by id or externalId
     *
     * @param string $id     corporate customer identifier
     * @param array  $filter (default: array())
     * @param int    $page   (default: null)
     * @param int    $limit  (default: null)
     * @param string $by     (default: 'externalId')
     * @param string $site   (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateCompanies(
        $id,
        array $filter = array(),
        $page = null,
        $limit = null,
        $by = 'externalId',
        $site = null
    ) {
        $this->checkIdParameter($by);
        $parameters = array('by' => $by);
        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$id/companies",
            "GET",
            $this->fillSite($site, $parameters)
        );
    }
    /**
     * Create corporate customer company
     *
     * @param string $id       corporate customer identifier
     * @param array  $company  (default: array())
     * @param string $by       (default: 'externalId')
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateCompaniesCreate($id, array $company = array(), $by = 'externalId', $site = null)
    {
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$id/companies/create",
            "POST",
            $this->fillSite($site, array('company' => json_encode($company), 'by' => $by))
        );
    }
    /**
     * Edit corporate customer company
     *
     * @param string $customerId corporate customer identifier
     * @param string $companyId  corporate customer identifier
     * @param array  $company    (default: array())
     * @param string $customerBy (default: 'externalId')
     * @param string $companyBy  (default: 'externalId')
     * @param string $site       (default: null)
     *
     * @return RetailcrmApiResponse
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     */
    public function customersCorporateCompaniesEdit(
        $customerId,
        $companyId,
        array $company = array(),
        $customerBy = 'externalId',
        $companyBy = 'externalId',
        $site = null
    ) {
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$customerId/companies/$companyId/edit",
            "POST",
            $this->fillSite($site, array(
                'company' => json_encode($company),
                'by' => $customerBy,
                'entityBy' => $companyBy
            ))
        );
    }
    /**
     * Get corporate customer contacts by id or externalId
     *
     * @param string $id     corporate customer identifier
     * @param array  $filter (default: array())
     * @param int    $page   (default: null)
     * @param int    $limit  (default: null)
     * @param string $by     (default: 'externalId')
     * @param string $site   (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateContacts(
        $id,
        array $filter = array(),
        $page = null,
        $limit = null,
        $by = 'externalId',
        $site = null
    ) {
        $this->checkIdParameter($by);
        $parameters = array('by' => $by);
        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$id/contacts",
            "GET",
            $this->fillSite($site, $parameters)
        );
    }
    /**
     * Create corporate customer contact
     *
     * @param string $id      corporate customer identifier
     * @param array  $contact (default: array())
     * @param string $by      (default: 'externalId')
     * @param string $site    (default: null)
     *
     * @return RetailcrmApiResponse
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @throws \InvalidArgumentException
     */
    public function customersCorporateContactsCreate($id, array $contact = array(), $by = 'externalId', $site = null)
    {
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$id/contacts/create",
            "POST",
            $this->fillSite($site, array('contact' => json_encode($contact), 'by' => $by))
        );
    }
    /**
     * Edit corporate customer contact
     *
     * @param string $customerId corporate customer identifier
     * @param string $contactId  corporate customer identifier
     * @param array  $contact    (default: array())
     * @param string $customerBy (default: 'externalId')
     * @param string $contactBy  (default: 'externalId')
     * @param string $site       (default: null)
     *
     * @return RetailcrmApiResponse
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     */
    public function customersCorporateContactsEdit(
        $customerId,
        $contactId,
        array $contact = array(),
        $customerBy = 'externalId',
        $contactBy = 'externalId',
        $site = null
    ) {
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            "/customers-corporate/$customerId/contacts/$contactId/edit",
            "POST",
            $this->fillSite($site, array(
                'contact' => json_encode($contact),
                'by' => $customerBy,
                'entityBy' => $contactBy
            ))
        );
    }
    /**
     * Edit a corporate customer
     *
     * @param array  $customerCorporate corporate customer data
     * @param string $by       (default: 'externalId')
     * @param string $site     (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function customersCorporateEdit(array $customerCorporate, $by = 'externalId', $site = null)
    {
        if (!count($customerCorporate)) {
            throw new \InvalidArgumentException(
                'Parameter `customerCorporate` must contains a data'
            );
        }
        $this->checkIdParameter($by);
        if (!array_key_exists($by, $customerCorporate)) {
            throw new \InvalidArgumentException(
                sprintf('Corporate customer array must contain the "%s" parameter.', $by)
            );
        }
        /* @noinspection PhpUndefinedMethodInspection */
        return $this->client->makeRequest(
            sprintf('/customers-corporate/%s/edit', $customerCorporate[$by]),
            "POST",
            $this->fillSite(
                $site,
                array('customerCorporate' => json_encode($customerCorporate), 'by' => $by)
            )
        );
    }

    /**
     * Get orders assembly list
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPacksList(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/orders/packs',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Create orders assembly
     *
     * @param array  $pack pack data
     * @param string $site (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPacksCreate(array $pack, $site = null)
    {
        if (!count($pack)) {
            throw new \InvalidArgumentException(
                'Parameter `pack` must contains a data'
            );
        }

        return $this->client->makeRequest(
            '/orders/packs/create',
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('pack' => json_encode($pack)))
        );
    }

    /**
     * Get orders assembly history
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPacksHistory(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/orders/packs/history',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Get orders assembly by id
     *
     * @param string $id pack identificator
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPacksGet($id)
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Parameter `id` must be set');
        }

        return $this->client->makeRequest(
            "/orders/packs/$id",
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Delete orders assembly by id
     *
     * @param string $id pack identificator
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPacksDelete($id)
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Parameter `id` must be set');
        }

        return $this->client->makeRequest(
            sprintf('/orders/packs/%s/delete', $id),
            RetailcrmHttpClient::METHOD_POST
        );
    }

    /**
     * Edit orders assembly
     *
     * @param array  $pack pack data
     * @param string $site (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function ordersPacksEdit(array $pack, $site = null)
    {
        if (!count($pack) || empty($pack['id'])) {
            throw new \InvalidArgumentException(
                'Parameter `pack` must contains a data & pack `id` must be set'
            );
        }

        return $this->client->makeRequest(
            sprintf('/orders/packs/%s/edit', $pack['id']),
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('pack' => json_encode($pack)))
        );
    }

    /**
     * Get tasks list
     *
     * @param array $filter
     * @param null  $limit
     * @param null  $page
     *
     * @return RetailcrmApiResponse
     */
    public function tasksList(array $filter = array(), $limit = null, $page = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/tasks',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Create task
     *
     * @param array $task
     * @param null  $site
     *
     * @return RetailcrmApiResponse
     *
     */
    public function tasksCreate($task, $site = null)
    {
        if (!count($task)) {
            throw new \InvalidArgumentException(
                'Parameter `task` must contain a data'
            );
        }

        return $this->client->makeRequest(
            "/tasks/create",
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite(
                $site,
                array('task' => json_encode($task))
            )
        );
    }

    /**
     * Edit task
     *
     * @param array $task
     * @param null  $site
     *
     * @return RetailcrmApiResponse
     *
     */
    public function tasksEdit($task, $site = null)
    {
        if (!count($task)) {
            throw new \InvalidArgumentException(
                'Parameter `task` must contain a data'
            );
        }

        return $this->client->makeRequest(
            "/tasks/{$task['id']}/edit",
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite(
                $site,
                array('task' => json_encode($task))
            )
        );
    }

    /**
     * Get custom dictionary
     *
     * @param $id
     *
     * @return RetailcrmApiResponse
     */
    public function tasksGet($id)
    {
        if (empty($id)) {
            throw new \InvalidArgumentException(
                'Parameter `id` must be not empty'
            );
        }

        return $this->client->makeRequest(
            "/tasks/$id",
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Get products groups
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function storeProductsGroups(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/store/product-groups',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Get purchase prices & stock balance
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function storeInventories(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/store/inventories',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Get store settings
     *
     * @param string $code get settings code
     *
     * @return RetailcrmApiResponse
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function storeSettingsGet($code)
    {
        if (empty($code)) {
            throw new \InvalidArgumentException('Parameter `code` must be set');
        }

        return $this->client->makeRequest(
            "/store/setting/$code",
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit store configuration
     *
     * @param array $configuration
     *
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function storeSettingsEdit(array $configuration)
    {
        if (!count($configuration) || empty($configuration['code'])) {
            throw new \InvalidArgumentException(
                'Parameter `configuration` must contains a data & configuration `code` must be set'
            );
        }

        return $this->client->makeRequest(
            sprintf('/store/setting/%s/edit', $configuration['code']),
            RetailcrmHttpClient::METHOD_POST,
            $configuration
        );
    }

    /**
     * Upload store inventories
     *
     * @param array  $offers offers data
     * @param string $site   (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function storeInventoriesUpload(array $offers, $site = null)
    {
        if (!count($offers)) {
            throw new \InvalidArgumentException(
                'Parameter `offers` must contains array of the offers'
            );
        }

        return $this->client->makeRequest(
            '/store/inventories/upload',
            RetailcrmHttpClient::METHOD_POST,
            $this->fillSite($site, array('offers' => json_encode($offers)))
        );
    }

    /**
     * Get products
     *
     * @param array $filter (default: array())
     * @param int   $page   (default: null)
     * @param int   $limit  (default: null)
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function storeProducts(array $filter = array(), $page = null, $limit = null)
    {
        $parameters = array();

        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = (int) $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = (int) $limit;
        }

        return $this->client->makeRequest(
            '/store/products',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Get delivery settings
     *
     * @param string $code
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function deliverySettingsGet($code)
    {
        if (empty($code)) {
            throw new \InvalidArgumentException('Parameter `code` must be set');
        }

        return $this->client->makeRequest(
            "/delivery/generic/setting/$code",
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit delivery configuration
     *
     * @param array $configuration
     *
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function deliverySettingsEdit(array $configuration)
    {
        if (!count($configuration) || empty($configuration['code'])) {
            throw new \InvalidArgumentException(
                'Parameter `configuration` must contains a data & configuration `code` must be set'
            );
        }

        return $this->client->makeRequest(
            sprintf('/delivery/generic/setting/%s/edit', $configuration['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('configuration' => json_encode($configuration))
        );
    }

    /**
     * Delivery tracking update
     *
     * @param string $code
     * @param array  $statusUpdate
     *
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function deliveryTracking($code, array $statusUpdate)
    {
        if (empty($code)) {
            throw new \InvalidArgumentException('Parameter `code` must be set');
        }

        if (!count($statusUpdate)) {
            throw new \InvalidArgumentException(
                'Parameter `statusUpdate` must contains a data'
            );
        }

        return $this->client->makeRequest(
            sprintf('/delivery/generic/%s/tracking', $code),
            RetailcrmHttpClient::METHOD_POST,
            $statusUpdate
        );
    }

    /**
     * Returns available county list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function countriesList()
    {
        return $this->client->makeRequest(
            '/reference/countries',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Returns deliveryServices list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function deliveryServicesList()
    {
        return $this->client->makeRequest(
            '/reference/delivery-services',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit deliveryService
     *
     * @param array $data delivery service data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function deliveryServicesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/delivery-services/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('deliveryService' => json_encode($data))
        );
    }

    /**
     * Returns deliveryTypes list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function deliveryTypesList()
    {
        return $this->client->makeRequest(
            '/reference/delivery-types',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit deliveryType
     *
     * @param array $data delivery type data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function deliveryTypesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/delivery-types/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('deliveryType' => json_encode($data))
        );
    }

    /**
     * Returns orderMethods list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function orderMethodsList()
    {
        return $this->client->makeRequest(
            '/reference/order-methods',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit orderMethod
     *
     * @param array $data order method data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function orderMethodsEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/order-methods/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('orderMethod' => json_encode($data))
        );
    }

    /**
     * Returns orderTypes list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function orderTypesList()
    {
        return $this->client->makeRequest(
            '/reference/order-types',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit orderType
     *
     * @param array $data order type data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function orderTypesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/order-types/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('orderType' => json_encode($data))
        );
    }

    /**
     * Returns paymentStatuses list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function paymentStatusesList()
    {
        return $this->client->makeRequest(
            '/reference/payment-statuses',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit paymentStatus
     *
     * @param array $data payment status data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function paymentStatusesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/payment-statuses/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('paymentStatus' => json_encode($data))
        );
    }

    /**
     * Returns paymentTypes list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function paymentTypesList()
    {
        return $this->client->makeRequest(
            '/reference/payment-types',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit paymentType
     *
     * @param array $data payment type data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function paymentTypesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/payment-types/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('paymentType' => json_encode($data))
        );
    }

    /**
     * Returns productStatuses list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function productStatusesList()
    {
        return $this->client->makeRequest(
            '/reference/product-statuses',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit productStatus
     *
     * @param array $data product status data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function productStatusesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/product-statuses/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('productStatus' => json_encode($data))
        );
    }

    /**
     * Returns sites list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function sitesList()
    {
        return $this->client->makeRequest(
            '/reference/sites',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit site
     *
     * @param array $data site data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function sitesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/sites/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('site' => json_encode($data))
        );
    }

    /**
     * Returns statusGroups list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function statusGroupsList()
    {
        return $this->client->makeRequest(
            '/reference/status-groups',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Returns statuses list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function statusesList()
    {
        return $this->client->makeRequest(
            '/reference/statuses',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit order status
     *
     * @param array $data status data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function statusesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/statuses/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('status' => json_encode($data))
        );
    }

    /**
     * Returns stores list
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function storesList()
    {
        return $this->client->makeRequest(
            '/reference/stores',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit store
     *
     * @param array $data site data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function storesEdit(array $data)
    {
        if (!array_key_exists('code', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "code" parameter.'
            );
        }

        if (!array_key_exists('name', $data)) {
            throw new \InvalidArgumentException(
                'Data must contain "name" parameter.'
            );
        }

        return $this->client->makeRequest(
            sprintf('/reference/stores/%s/edit', $data['code']),
            RetailcrmHttpClient::METHOD_POST,
            array('store' => json_encode($data))
        );
    }

    /**
     * Get telephony settings
     *
     * @param string $code
     *
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function telephonySettingsGet($code)
    {
        if (empty($code)) {
            throw new \InvalidArgumentException('Parameter `code` must be set');
        }

        return $this->client->makeRequest(
            "/telephony/setting/$code",
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit telephony settings
     *
     * @param string  $code        symbolic code
     * @param string  $clientId    client id
     * @param boolean $active      telephony activity
     * @param mixed   $name        service name
     * @param mixed   $makeCallUrl service init url
     * @param mixed   $image       service logo url(svg file)
     *
     * @param array   $additionalCodes
     * @param array   $externalPhones
     * @param bool    $allowEdit
     * @param bool    $inputEventSupported
     * @param bool    $outputEventSupported
     * @param bool    $hangupEventSupported
     * @param bool    $changeUserStatusUrl
     *
     * @return RetailcrmApiResponse
     */
    public function telephonySettingsEdit(
        $code,
        $clientId,
        $active = false,
        $name = false,
        $makeCallUrl = false,
        $image = false,
        $additionalCodes = array(),
        $externalPhones = array(),
        $allowEdit = false,
        $inputEventSupported = false,
        $outputEventSupported = false,
        $hangupEventSupported = false,
        $changeUserStatusUrl = false
    )
    {
        if (!isset($code)) {
            throw new \InvalidArgumentException('Code must be set');
        }

        $parameters['code'] = $code;

        if (!isset($clientId)) {
            throw new \InvalidArgumentException('client id must be set');
        }

        $parameters['clientId'] = $clientId;

        if (!isset($active)) {
            $parameters['active'] = false;
        } else {
            $parameters['active'] = $active;
        }

        if (!isset($name)) {
            throw new \InvalidArgumentException('name must be set');
        }

        if (isset($name)) {
            $parameters['name'] = $name;
        }

        if (isset($makeCallUrl)) {
            $parameters['makeCallUrl'] = $makeCallUrl;
        }

        if (isset($image)) {
            $parameters['image'] = $image;
        }

        if (isset($additionalCodes)) {
            $parameters['additionalCodes'] = $additionalCodes;
        }

        if (isset($externalPhones)) {
            $parameters['externalPhones'] = $externalPhones;
        }

        if (isset($allowEdit)) {
            $parameters['allowEdit'] = $allowEdit;
        }

        if (isset($inputEventSupported)) {
            $parameters['inputEventSupported'] = $inputEventSupported;
        }

        if (isset($outputEventSupported)) {
            $parameters['outputEventSupported'] = $outputEventSupported;
        }

        if (isset($hangupEventSupported)) {
            $parameters['hangupEventSupported'] = $hangupEventSupported;
        }

        if (isset($changeUserStatusUrl)) {
            $parameters['changeUserStatusUrl'] = $changeUserStatusUrl;
        }

        return $this->client->makeRequest(
            "/telephony/setting/$code/edit",
            RetailcrmHttpClient::METHOD_POST,
            array('configuration' => json_encode($parameters))
        );
    }

    /**
     * Call event
     *
     * @param string $phone phone number
     * @param string $type  call type
     * @param array  $codes
     * @param string $hangupStatus
     * @param string $externalPhone
     * @param array  $webAnalyticsData
     *
     * @return RetailcrmApiResponse
     * @internal param string $code additional phone code
     * @internal param string $status call status
     *
     */
    public function telephonyCallEvent(
        $phone,
        $type,
        $codes,
        $hangupStatus,
        $externalPhone = null,
        $webAnalyticsData = array()
    )
    {
        if (!isset($phone)) {
            throw new \InvalidArgumentException('Phone number must be set');
        }

        if (!isset($type)) {
            throw new \InvalidArgumentException('Type must be set (in|out|hangup)');
        }

        if (empty($codes)) {
            throw new \InvalidArgumentException('Codes array must be set');
        }

        $parameters['phone'] = $phone;
        $parameters['type'] = $type;
        $parameters['codes'] = $codes;
        $parameters['hangupStatus'] = $hangupStatus;
        $parameters['callExternalId'] = $externalPhone;
        $parameters['webAnalyticsData'] = $webAnalyticsData;


        return $this->client->makeRequest(
            '/telephony/call/event',
            RetailcrmHttpClient::METHOD_POST,
            array('event' => json_encode($parameters))
        );
    }

    /**
     * Upload calls
     *
     * @param array $calls calls data
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function telephonyCallsUpload(array $calls)
    {
        if (!count($calls)) {
            throw new \InvalidArgumentException(
                'Parameter `calls` must contains array of the calls'
            );
        }

        return $this->client->makeRequest(
            '/telephony/calls/upload',
            RetailcrmHttpClient::METHOD_POST,
            array('calls' => json_encode($calls))
        );
    }

    /**
     * Get call manager
     *
     * @param string $phone   phone number
     * @param bool   $details detailed information
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function telephonyCallManager($phone, $details)
    {
        if (!isset($phone)) {
            throw new \InvalidArgumentException('Phone number must be set');
        }

        $parameters['phone'] = $phone;
        $parameters['details'] = isset($details) ? $details : 0;

        return $this->client->makeRequest(
            '/telephony/manager',
            RetailcrmHttpClient::METHOD_GET,
            $parameters
        );
    }

    /**
     * Update CRM basic statistic
     *
     * @throws \InvalidArgumentException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \RetailCrm\Exception\InvalidJsonException
     *
     * @return RetailcrmApiResponse
     */
    public function statisticUpdate()
    {
        return $this->client->makeRequest(
            '/statistic/update',
            RetailcrmHttpClient::METHOD_GET
        );
    }

    /**
     * Edit module configuration
     *
     * @param array $configuration
     *
     * @throws \RetailCrm\Exception\InvalidJsonException
     * @throws \RetailCrm\Exception\CurlException
     * @throws \InvalidArgumentException
     *
     * @return RetailcrmApiResponse
     */
    public function integrationModulesEdit(array $configuration)
    {
        if (!count($configuration) || empty($configuration['code'])) {
            throw new \InvalidArgumentException(
                'Parameter `configuration` must contains a data & configuration `code` must be set'
            );
        }

        $code = $configuration['code'];

        return $this->client->makeRequest(
            "/integration-modules/$code/edit",
            RetailcrmHttpClient::METHOD_POST,
            array('integrationModule' => json_encode($configuration))
        );
    }

    /**
     * Return current site
     *
     * @return string
     */
    public function getSite()
    {
        return $this->siteCode;
    }

    /**
     * Set site
     *
     * @param string $site site code
     *
     * @return void
     */
    public function setSite($site)
    {
        $this->siteCode = $site;
    }

    /**
     * Check ID parameter
     *
     * @param string $by identify by
     *
     * @throws \InvalidArgumentException
     *
     * @return bool
     */
    protected function checkIdParameter($by)
    {
        $allowedForBy = array(
            'externalId',
            'id'
        );

        if (!in_array($by, $allowedForBy, false)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Value "%s" for "by" param is not valid. Allowed values are %s.',
                    $by,
                    implode(', ', $allowedForBy)
                )
            );
        }

        return true;
    }

    /**
     * Fill params by site value
     *
     * @param string $site   site code
     * @param array  $params input parameters
     *
     * @return array
     */
    protected function fillSite($site, array $params)
    {
        if ($site) {
            $params['site'] = $site;
        } elseif ($this->siteCode) {
            $params['site'] = $this->siteCode;
        }

        return $params;
    }
}
