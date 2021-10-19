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
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */
class RetailcrmOrderBuilder
{
    /**
     * @var \RetailcrmApiClientV5
     */
    protected $api;

    /**
     * @var int
     */
    protected $default_lang;

    /**
     * @var Order|OrderCore|null
     */
    protected $cmsOrder;

    /**
     * @var Cart|CartCore|null
     */
    protected $cmsCart;

    /**
     * @var Customer|CustomerCore|null
     */
    protected $cmsCustomer;

    /**
     * @var Address|\AddressCore
     */
    protected $invoiceAddress;

    /**
     * @var Address|\AddressCore
     */
    protected $deliveryAddress;

    /**
     * @var array|null
     */
    protected $createdCustomer;

    /**
     * @var array|null
     */
    protected $corporateCompanyExtractCache;

    /**
     * @var string
     */
    protected $apiSite;

    /**
     * @return RetailcrmOrderBuilder
     */
    public function defaultLangFromConfiguration()
    {
        $this->default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        return $this;
    }

    /**
     * @param mixed $default_lang
     *
     * @return RetailcrmOrderBuilder
     */
    public function setDefaultLang($default_lang)
    {
        $this->default_lang = $default_lang;

        return $this;
    }

    /**
     * @param mixed $api
     *
     * @return RetailcrmOrderBuilder
     */
    public function setApi($api)
    {
        $this->api = $api;

        return $this;
    }

    /**
     * @param Order|OrderCore $cmsOrder
     *
     * @return RetailcrmOrderBuilder
     */
    public function setCmsOrder($cmsOrder)
    {
        $this->cmsOrder = $cmsOrder;

        if ($cmsOrder instanceof Order) {
            if (is_null($this->cmsCustomer)) {
                $this->cmsCustomer = $cmsOrder->getCustomer();
            }

            if (is_null($this->invoiceAddress)) {
                $this->invoiceAddress = new Address($cmsOrder->id_address_invoice);
            }

            if (is_null($this->deliveryAddress)) {
                $this->deliveryAddress = new Address($cmsOrder->id_address_delivery);
            }
        }

        return $this;
    }

    /**
     * @param Cart|CartCore $cmsCart
     *
     * @return RetailcrmOrderBuilder
     */
    public function setCmsCart($cmsCart)
    {
        $this->cmsCart = $cmsCart;

        if ($cmsCart instanceof Cart) {
            if (is_null($this->cmsCustomer) && !empty($cmsCart->id_customer)) {
                $this->cmsCustomer = new Customer($cmsCart->id_customer);
            }

            if (is_null($this->invoiceAddress) && !empty($cmsCart->id_address_invoice)) {
                $this->invoiceAddress = new Address($cmsCart->id_address_invoice);
            }

            if (is_null($this->deliveryAddress) && !empty($cmsCart->id_address_delivery)) {
                $this->deliveryAddress = new Address($cmsCart->id_address_delivery);
            }
        }

        return $this;
    }

    /**
     * @param mixed $cmsCustomer
     *
     * @return RetailcrmOrderBuilder
     */
    public function setCmsCustomer($cmsCustomer)
    {
        $this->cmsCustomer = $cmsCustomer;

        return $this;
    }

    /**
     * getApiSite
     *
     * @return string|null
     */
    protected function getApiSite()
    {
        if (empty($this->apiSite)) {
            $response = $this->api->credentials();

            if (
                $response->isSuccessful()
                && $response->offsetExists('sitesAvailable')
                && is_array($response['sitesAvailable'])
                && !empty($response['sitesAvailable'])
                && !empty($response['sitesAvailable'][0])
            ) {
                $this->apiSite = $response['sitesAvailable'][0];
            } else {
                $this->apiSite = null;
            }
        }

        return $this->apiSite;
    }

    /**
     * Clear object
     *
     * @return mixed
     */
    public function reset()
    {
        $this->cmsOrder = null;
        $this->cmsCart = null;
        $this->cmsCustomer = null;
        $this->invoiceAddress = null;
        $this->deliveryAddress = null;
        $this->createdCustomer = null;
        $this->corporateCompanyExtractCache = null;

        return $this;
    }

    /**
     * Returns order with prepared customer data. Customer is created if it's not exist, shouldn't be called to just
     * build order.
     *
     * @param bool $dataFromCart
     *
     * @return array
     */
    public function buildOrderWithPreparedCustomer($dataFromCart = false)
    {
        if (RetailcrmTools::isCorporateEnabled() && RetailcrmTools::isOrderCorporate($this->cmsOrder)) {
            return $this->buildCorporateOrder($dataFromCart);
        }

        return $this->buildRegularOrder($dataFromCart);
    }

    /**
     * Creates customer if it's not present
     *
     * @return array|bool
     */
    private function createCustomerIfNotExist()
    {
        $this->validateCmsCustomer();

        $customer = $this->findRegularCustomer();

        if (empty($customer)) {
            $crmCustomer = static::buildCrmCustomer($this->cmsCustomer, $this->buildRegularAddress());
            $createResponse = $this->api->customersCreate($crmCustomer);

            if (!$createResponse || !$createResponse->isSuccessful()) {
                $this->createdCustomer = [];

                return false;
            }

            $this->createdCustomer = $this->findRegularCustomer();
            $customer = $this->createdCustomer;
        } else {
            $crmCustomer = RetailcrmTools::mergeCustomerAddress($customer, $this->buildRegularAddress());
            if (!RetailcrmTools::isEqualCustomerAddress($customer, $crmCustomer)) {
                if (isset($crmCustomer['tags'])) {
                    unset($crmCustomer['tags']);
                }

                $response = $this->api->customersEdit($crmCustomer);

                if ($response instanceof RetailcrmApiResponse && $response->isSuccessful()) {
                    $customer = $crmCustomer;
                }
            }
        }

        return isset($customer['id']) ? $customer : false;
    }

    private function buildRegularOrder($dataFromCart = false)
    {
        $customer = $this->createCustomerIfNotExist();
        $order = static::buildCrmOrder(
            $this->cmsOrder,
            $this->cmsCustomer,
            $this->cmsCart,
            false,
            false,
            $dataFromCart,
            '',
            '',
            isset($customer['id']) ? $customer['id'] : ''
        );

        return RetailcrmTools::clearArray($order);
    }

    /**
     * Build regular customer address
     *
     * @return array
     */
    private function buildRegularAddress()
    {
        $addressBuilder = new RetailcrmAddressBuilder();

        return $addressBuilder
            ->setAddress($this->invoiceAddress)
            ->build()
            ->getDataArray();
    }

    /**
     * Builds address array for corporate customer, returns empty array in case of failure.
     *
     * @param bool $isMain
     *
     * @return array
     */
    private function buildCorporateAddress($isMain = true)
    {
        if (empty($this->invoiceAddress) || empty($this->invoiceAddress->id)) {
            return [];
        }

        $addressBuilder = new RetailcrmAddressBuilder();

        return $addressBuilder
            ->setMode(RetailcrmAddressBuilder::MODE_CORPORATE_CUSTOMER)
            ->setAddress($this->invoiceAddress)
            ->setIsMain($isMain)
            ->setWithExternalId(true)
            ->build()
            ->getDataArray();
    }

    /**
     * Builds company for corporate customer
     *
     * @param int $addressId
     *
     * @return array
     */
    private function buildCorporateCompany($addressId = 0)
    {
        $companyName = '';
        $vat = '';

        if (!empty($this->invoiceAddress)) {
            if (empty($this->invoiceAddress->company)) {
                $companyName = 'Main Company';
            } else {
                $companyName = $this->invoiceAddress->company;
            }

            if (!empty($this->invoiceAddress->vat_number)) {
                $vat = $this->invoiceAddress->vat_number;
            }
        }

        $company = [
            'isMain' => true,
            'name' => $companyName,
        ];

        if (!empty($addressId)) {
            $company['address'] = [
                'id' => $addressId,
            ];
        }

        if (!empty($vat)) {
            $company['contragent']['INN'] = $vat;
        }

        return RetailcrmTools::clearArray($company);
    }

    /**
     * Creates new corporate customer from data, returns false in case of error
     *
     * @return array|bool
     */
    private function createCorporateIfNotExist()
    {
        $corporateWasFound = true;
        $this->validateCmsCustomerInDb();

        $customer = $this->createCustomerIfNotExist();

        if (!$customer) {
            RetailcrmLogger::writeCaller(__METHOD__, 'Cannot proceed because customer is empty!');

            return false;
        }

        $crmCorporate = $this->findCorporateCustomerByContactAndCompany(
            $customer['id'],
            $this->invoiceAddress->company
        );

        if (empty($crmCorporate)) {
            $crmCorporate = $this->findCorporateCustomerByCompany($this->invoiceAddress->company);
        }

        if (empty($crmCorporate)) {
            $crmCorporate = $this->createCorporateCustomer($customer['externalId']);
            $corporateWasFound = false;
        } elseif (isset($crmCorporate['id'])) {
            $this->appendAdditionalAddressToCorporate($crmCorporate['id']);
        }

        if ($corporateWasFound) {
            $contactList = $this->api->customersCorporateContacts(
                $crmCorporate['id'],
                ['contactIds' => [$customer['id']]],
                null,
                null,
                'id',
                $this->getApiSite()
            );

            if (!$contactList->offsetExists('contacts')) {
                return $crmCorporate;
            }

            if (count($contactList['contacts']) == 0) {
                $contactData = [
                    'isMain' => false,
                    'customer' => [
                        'id' => $customer['id'],
                        'site' => $this->getApiSite(),
                    ],
                ];

                $crmCorporateCompany = $this->extractCorporateCompanyCached(
                    $crmCorporate['id'],
                    $this->invoiceAddress->company
                );

                if (!empty($crmCorporateCompany) && isset($crmCorporateCompany['id'])) {
                    $contactData['companies'] = [[
                        'company' => ['id' => $crmCorporateCompany['id']],
                    ]];
                }

                $this->api->customersCorporateContactsCreate(
                    $crmCorporate['id'],
                    $contactData,
                    'id',
                    $this->getApiSite()
                );
            }
        }

        return $crmCorporate;
    }

    /**
     * createCorporateCustomer
     *
     * @param string $contactPersonExternalId
     *
     * @return bool|array|\RetailcrmApiResponse
     */
    private function createCorporateCustomer($contactPersonExternalId)
    {
        $customerCorporate = static::buildCrmCustomerCorporate(
            $this->cmsCustomer,
            $this->invoiceAddress->company,
            $contactPersonExternalId,
            false,
            false,
            $this->getApiSite()
        );
        $crmCorporate = $this->api->customersCorporateCreate($customerCorporate, $this->getApiSite());

        if (!$crmCorporate || !$crmCorporate->isSuccessful()) {
            return false;
        }

        $address = $this->buildCorporateAddress();
        $createResponse = $this->api->customersCorporateAddressesCreate(
            $crmCorporate['id'],
            $address,
            'id',
            $this->getApiSite()
        );

        if ($createResponse && $createResponse->isSuccessful()) {
            $company = $this->buildCorporateCompany($createResponse['id']);
            $this->api->customersCorporateCompaniesCreate(
                $crmCorporate['id'],
                $company,
                'id',
                $this->getApiSite()
            );
        }

        $crmCorporate = $this->api->customersCorporateGet($crmCorporate['id'], 'id', $this->getApiSite());

        if ($crmCorporate
            && $crmCorporate->isSuccessful()
            && $crmCorporate->offsetExists('customerCorporate')
        ) {
            return $crmCorporate['customerCorporate'];
        }

        return $crmCorporate;
    }

    /**
     * Append new address to corporate customer if new address is not present in corporate customer.
     *
     * @param string|int $corporateId
     */
    private function appendAdditionalAddressToCorporate($corporateId)
    {
        $request = new RetailcrmApiPaginatedRequest();
        $address = $this->buildCorporateAddress(false);
        $addresses = $request
            ->setApi($this->api)
            ->setMethod('customersCorporateAddresses')
            ->setParams([
                $corporateId,
                [],
                '{{page}}',
                '{{limit}}',
                'id',
                $this->getApiSite(),
            ])
            ->setDataKey('addresses')
            ->execute()
            ->getData();

        foreach ($addresses as $addressInCrm) {
            if (!empty($addressInCrm['externalId']) && $addressInCrm['externalId'] == $this->invoiceAddress->id) {
                $this->api->customersCorporateAddressesEdit(
                    $corporateId,
                    $addressInCrm['externalId'],
                    $address,
                    'id',
                    'externalId',
                    $this->getApiSite()
                );

                return;
            }
        }

        $this->api->customersCorporateAddressesCreate(
            $corporateId,
            $address,
            'id',
            $this->getApiSite()
        );
    }

    /**
     * Find self::cmsCustomer in retailCRM by id or by email
     *
     * @return array|mixed
     */
    private function findRegularCustomer()
    {
        $this->validateCmsCustomer();

        if (empty($this->cmsCustomer->id) || $this->cmsCustomer->is_guest) {
            if (!empty($this->cmsCustomer->email)) {
                $customers = $this->api->customersList(['email' => $this->cmsCustomer->email]);

                if ($customers
                    && $customers->isSuccessful()
                    && $customers->offsetExists('customers')
                    && !empty($customers['customers'])
                ) {
                    $customers = $customers['customers'];

                    return reset($customers);
                }
            }
        } else {
            $customer = $this->api->customersGet($this->cmsCustomer->id);

            if ($customer && $customer->isSuccessful() && $customer->offsetExists('customer')) {
                return $customer['customer'];
            }
        }

        return [];
    }

    /**
     * Finds all corporate customers with specified contact id and filters them by provided main company name
     *
     * @param $contactId
     * @param $companyName
     *
     * @return array
     */
    private function findCorporateCustomerByContactAndCompany($contactId, $companyName)
    {
        $crmCorporate = $this->api->customersCorporateList([
            'contactIds' => [$contactId],
            'companyName' => $companyName,
        ]);

        if ($crmCorporate instanceof RetailcrmApiResponse
            && $crmCorporate->isSuccessful()
            && $crmCorporate->offsetExists('customersCorporate')
            && count($crmCorporate['customersCorporate']) > 0
        ) {
            $crmCorporate = $crmCorporate['customersCorporate'];

            return reset($crmCorporate);
        }

        return [];
    }

    /**
     * Find corporate customer by company name
     *
     * @param $companyName
     *
     * @return array
     */
    private function findCorporateCustomerByCompany($companyName)
    {
        $crmCorporate = $this->api->customersCorporateList([
            'companyName' => $companyName,
        ]);

        if ($crmCorporate instanceof RetailcrmApiResponse
            && $crmCorporate->isSuccessful()
            && $crmCorporate->offsetExists('customersCorporate')
            && count($crmCorporate['customersCorporate']) > 0
        ) {
            $crmCorporate = $crmCorporate['customersCorporate'];

            return reset($crmCorporate);
        }

        return [];
    }

    /**
     * Get corporate companies, extract company data by provided identifiers
     *
     * @param int|string $corporateCrmId
     * @param string $companyName
     * @param string $by
     *
     * @return array
     */
    private function extractCorporateCompany($corporateCrmId, $companyName, $by = 'id')
    {
        $companiesResponse = $this->api->customersCorporateCompanies(
            $corporateCrmId,
            [],
            null,
            null,
            $by
        );

        if ($companiesResponse instanceof RetailcrmApiResponse
            && $companiesResponse->isSuccessful()
            && $companiesResponse->offsetExists('companies')
            && count($companiesResponse['companies']) > 0
        ) {
            $company = array_reduce(
                $companiesResponse['companies'],
                function ($carry, $item) use ($companyName) {
                    if (is_array($item) && isset($item['name']) && $item['name'] == $companyName) {
                        $carry = $item;
                    }

                    return $carry;
                }
            );

            if (is_array($company)) {
                return $company;
            }
        }

        return [];
    }

    /**
     * extractCorporateCompany with cache
     *
     * @param int|string $corporateCrmId
     * @param string $companyName
     * @param string $by
     *
     * @return array
     */
    private function extractCorporateCompanyCached($corporateCrmId, $companyName, $by = 'id')
    {
        $cachedItemId = sprintf('%s:%s', (string) $corporateCrmId, $companyName);

        if (!is_array($this->corporateCompanyExtractCache)) {
            $this->corporateCompanyExtractCache = [];
        }

        if (!isset($this->corporateCompanyExtractCache[$cachedItemId])) {
            $this->corporateCompanyExtractCache[$cachedItemId] = $this->extractCorporateCompany(
                $corporateCrmId,
                $companyName,
                $by
            );
        }

        return $this->corporateCompanyExtractCache[$cachedItemId];
    }

    /**
     * Throws exception if cmsCustomer is not set
     *
     * @throws \InvalidArgumentException
     */
    private function validateCmsCustomer()
    {
        if (is_null($this->cmsCustomer)) {
            throw new \InvalidArgumentException('RetailcrmOrderBuilder::cmsCustomer must be set');
        }
    }

    /**
     * Throws exception if cmsCustomer is not set or it's not present in DB yet
     *
     * @throws \InvalidArgumentException
     */
    private function validateCmsCustomerInDb()
    {
        $this->validateCmsCustomer();

        if (empty($this->cmsCustomer->id)) {
            throw new \InvalidArgumentException('RetailcrmOrderBuilder::cmsCustomer must be stored in DB');
        }
    }

    private function buildCorporateOrder($dataFromCart = false)
    {
        $customer = $this->createCorporateIfNotExist();
        $contactPersonId = '';
        $contactPersonExternalId = '';

        if (empty($customer)) {
            return [];
        }

        if (empty($this->cmsCustomer->id)) {
            $contacts = $this->api->customersList(['email' => $this->cmsCustomer->email]);

            if ($contacts
                && $contacts->isSuccessful()
                && $contacts->offsetExists('customers')
                && !empty($contacts['customers'])
            ) {
                $contacts = $contacts['customers'];
                $contactPerson = reset($contacts);

                if (isset($contactPerson['id'])) {
                    $contactPersonId = $contactPerson['id'];
                }
            }
        } else {
            $contacts = $this->api->customersCorporateContacts(
                $customer['id'],
                ['contactExternalIds' => [$this->cmsCustomer->id]],
                null,
                null,
                'id',
                $this->getApiSite()
            );

            if ($contacts
                && $contacts->isSuccessful()
                && $contacts->offsetExists('contacts')
                && count($contacts['contacts']) == 1
            ) {
                $contactPersonExternalId = $this->cmsCustomer->id;
            }
        }

        return static::buildCrmOrder(
            $this->cmsOrder,
            $this->cmsCustomer,
            $this->cmsCart,
            false,
            false,
            $dataFromCart,
            $contactPersonId,
            $contactPersonExternalId,
            $customer['id'],
            $this->getApiSite()
        );
    }

    /**
     * Build array with order data for retailCRM from PrestaShop order data
     *
     * @param Order|\OrderCore $order PrestaShop Order
     * @param Customer|\CustomerCore|null $customer PrestaShop Customer
     * @param Cart|\CartCore|null $orderCart Cart for provided order. Optional
     * @param bool $isStatusExport Use status for export
     * @param bool $preferCustomerAddress Use customer address even if delivery address is
     *                                    provided
     * @param bool $dataFromCart Prefer data from cart
     * @param string $contactPersonId Contact person id to append
     * @param string $contactPersonExternalId contact person externalId to append
     * @param string $customerId Customer id
     * @param string $site Site code (for customer only)
     *
     * @return array retailCRM order data
     *
     * @todo Refactor into OrderBuilder (current order builder should be divided into several independent builders).
     */
    public static function buildCrmOrder(
        $order,
        $customer = null,
        $orderCart = null,
        $isStatusExport = false,
        $preferCustomerAddress = false,
        $dataFromCart = false,
        $contactPersonId = '',
        $contactPersonExternalId = '',
        $customerId = '',
        $site = ''
    ) {
        $statusExport = Configuration::get(RetailCRM::STATUS_EXPORT);
        $delivery = json_decode(Configuration::get(RetailCRM::DELIVERY), true);
        $payment = json_decode(Configuration::get(RetailCRM::PAYMENT), true);
        $status = json_decode(Configuration::get(RetailCRM::STATUS), true);
        $sendOrderNumber = (bool) (Configuration::get(RetailCRM::ENABLE_ORDER_NUMBER_SENDING));
        $orderNumber = $sendOrderNumber ? $order->reference : null;

        if (Module::getInstanceByName('advancedcheckout') === false) {
            $paymentType = $order->module;
        } else {
            $paymentType = $order->payment;
        }

        if ($order->current_state == 0) {
            $order_status = $statusExport;

            if (!$isStatusExport) {
                $order_status =
                    array_key_exists($order->current_state, $status)
                        ? $status[$order->current_state] : 'new';
            }
        } else {
            $order_status = array_key_exists($order->current_state, $status)
                ? $status[$order->current_state]
                : $statusExport;
        }

        $cart = $orderCart;

        if (is_null($cart)) {
            $cart = new Cart($order->getCartIdStatic($order->id));
        }

        if (is_null($customer)) {
            $customer = new Customer($order->id_customer);
        }

        $crmOrder = array_filter([
            'externalId' => $order->id,
            'number' => $orderNumber,
            'createdAt' => RetailcrmTools::verifyDate($order->date_add, 'Y-m-d H:i:s')
                ? $order->date_add : date('Y-m-d H:i:s'),
            'status' => $order_status,
            'firstName' => $customer->firstname,
            'lastName' => $customer->lastname,
            'email' => $customer->email,
        ]);

        $addressCollection = $cart->getAddressCollection();
        $addressDelivery = new Address($order->id_address_delivery);
        $addressInvoice = new Address($order->id_address_invoice);

        if (is_null($addressDelivery->id) || $preferCustomerAddress === true) {
            $addressDelivery = array_filter(
                $addressCollection,
                function ($v) use ($customer) {
                    return $v->id_customer == $customer->id;
                }
            );

            if (is_array($addressDelivery) && count($addressDelivery) == 1) {
                $addressDelivery = reset($addressDelivery);
            }
        }

        $addressBuilder = new RetailcrmAddressBuilder();
        $addressBuilder
            ->setMode(RetailcrmAddressBuilder::MODE_ORDER_DELIVERY)
            ->setAddress($addressDelivery)
            ->build();
        $crmOrder = array_merge($crmOrder, $addressBuilder->getDataArray());

        if ($addressInvoice instanceof Address && !empty($addressInvoice->company)) {
            $crmOrder['contragent']['legalName'] = $addressInvoice->company;

            if (!empty($addressInvoice->vat_number)) {
                $crmOrder['contragent']['INN'] = $addressInvoice->vat_number;
            }
        }

        if (isset($payment[$paymentType]) && !empty($payment[$paymentType])) {
            $order_payment = [
                'externalId' => $order->id . '#' . $order->reference,
                'type' => $payment[$paymentType],
            ];

            if (round($order->total_paid_real, 2) > 0) {
                $order_payment['amount'] = round($order->total_paid_real, 2);
                $order_payment['status'] = 'paid';
            }
            $crmOrder['payments'][] = $order_payment;
        } else {
            $crmOrder['payments'] = [];
        }

        $idCarrier = $dataFromCart ? $cart->id_carrier : $order->id_carrier;

        if (empty($idCarrier)) {
            $idCarrier = $order->id_carrier;
            $totalShipping = $order->total_shipping;
            $totalShippingWithoutTax = $order->total_shipping_tax_excl;
        } else {
            $totalShipping = $dataFromCart ? $cart->getCarrierCost($idCarrier) : $order->total_shipping;

            if (!empty($totalShipping) && $totalShipping != 0) {
                $totalShippingWithoutTax = $dataFromCart
                    ? $totalShipping - $cart->getCarrierCost($idCarrier, false)
                    : $order->total_shipping_tax_excl;
            } else {
                $totalShippingWithoutTax = $order->total_shipping_tax_excl;
            }
        }

        // TODO Shouldn't cause any errors while creating order even if correspondent carrier is not set.
        if (array_key_exists($idCarrier, $delivery) && !empty($delivery[$idCarrier])) {
            $crmOrder['delivery']['code'] = $delivery[$idCarrier];
        }

        if (isset($totalShipping) && $order->total_discounts > $order->total_products_wt) {
            $totalShipping -= $order->total_discounts - $order->total_products_wt;
            $crmOrder['discountManualAmount'] = round($order->total_products_wt, 2);
        } else {
            $crmOrder['discountManualAmount'] = round($order->total_discounts, 2);
        }

        if (isset($totalShipping) && round($totalShipping, 2) > 0) {
            $crmOrder['delivery']['cost'] = round($totalShipping, 2);
        } else {
            $crmOrder['delivery']['cost'] = 0.00;
        }

        if (isset($totalShippingWithoutTax) && round($totalShippingWithoutTax, 2) > 0) {
            $crmOrder['delivery']['netCost'] = round($totalShippingWithoutTax, 2);
        } else {
            $crmOrder['delivery']['netCost'] = 0.00;
        }

        $comment = $order->getFirstMessage();

        if ($comment !== false) {
            $crmOrder['customerComment'] = $comment;
        }

        if ($dataFromCart) {
            $productStore = $cart;
            $converter = function ($product) {
                $map = [
                    'product_attribute_id' => 'id_product_attribute',
                    'product_quantity' => 'cart_quantity',
                    'product_id' => 'id_product',
                    'id_order_detail' => 'id_product',
                    'product_name' => 'name',
                    'product_price' => 'price',
                    'purchase_supplier_price' => 'price',
                    'product_price_wt' => 'price_wt',
                ];

                foreach ($map as $target => $value) {
                    if (isset($product[$value])) {
                        $product[$target] = $product[$value];
                    }
                }

                return $product;
            };
        } else {
            $productStore = $order;
            $converter = function ($product) {
                return $product;
            };
        }

        foreach ($productStore->getProducts() as $productData) {
            $product = $converter($productData);

            if (isset($product['product_attribute_id']) && $product['product_attribute_id'] > 0) {
                $productId = $product['product_id'] . '#' . $product['product_attribute_id'];
            } else {
                $productId = $product['product_id'];
            }

            if (isset($product['attributes']) && $product['attributes']) {
                $arProp = [];
                $count = 0;
                $arAttr = explode(',', $product['attributes']);

                foreach ($arAttr as $valAttr) {
                    $arItem = explode(':', $valAttr);

                    if ($arItem[0] && $arItem[1]) {
                        // Product property code should start with a letter, digit or underscore
                        // and only contain letters, digits, underscores, hyphens and colons
                        $propertyCode = preg_replace('/(^[^\w]+)|([^\w\-:])/', '', $arItem[0]);
                        if (empty($propertyCode)) {
                            $propertyCode = 'prop_' . $count;
                        }

                        $arProp[$count]['code'] = $propertyCode;
                        $arProp[$count]['name'] = trim($arItem[0]);
                        $arProp[$count]['value'] = trim($arItem[1]);
                    }

                    ++$count;
                }
            }

            $item = [
                'externalIds' => [
                    [
                        'code' => 'prestashop',
                        'value' => $productId . '_' . $product['id_order_detail'],
                    ],
                ],
                'offer' => ['externalId' => $productId],
                'productName' => $product['product_name'],
                'quantity' => $product['product_quantity'],
                'initialPrice' => round($product['product_price'], 2),
                /*'initialPrice' => !empty($item['rate'])
                    ? $item['price'] + ($item['price'] * $item['rate'] / 100)
                    : $item['price'],*/
                'purchasePrice' => round($product['purchase_supplier_price'], 2),
            ];

            if (Configuration::get('PS_TAX') == true && isset($product['product_price_wt'])) {
                $item['initialPrice'] = round($product['product_price_wt'], 2);
            }

            if (isset($arProp)) {
                $item['properties'] = $arProp;
            }

            $crmOrder['items'][] = $item;
        }

        if ($order->gift && $order->total_wrapping > 0) {
            self::setOrderGiftItem($order, $crmOrder);
        }

        if ($order->id_customer) {
            if (!empty($customerId)) {
                $crmOrder['customer']['id'] = $customerId;
            }

            if (!empty($contactPersonExternalId)) {
                $crmOrder['contact']['externalId'] = $contactPersonExternalId;
                $crmOrder['contact']['site'] = $site;
            } elseif (!empty($contactPersonId)) {
                $crmOrder['contact']['id'] = $contactPersonId;
                $crmOrder['contact']['site'] = $site;
            }

            if (!empty($site)) {
                $crmOrder['customer']['site'] = $site;
            }

            if (RetailcrmTools::isCorporateEnabled() && RetailcrmTools::isOrderCorporate($order)) {
                $crmOrder['contragent']['contragentType'] = 'legal-entity';
            } else {
                $crmOrder['contragent']['contragentType'] = 'individual';
            }
        }

        return RetailcrmTools::filter(
            'RetailcrmFilterProcessOrder',
            RetailcrmTools::clearArray($crmOrder),
            [
                'order' => $order,
                'customer' => $customer,
                'cart' => $cart,
            ]);
    }

    /**
     * Build array with order data for retailCRM from PrestaShop cart data
     *
     * @param \RetailcrmProxy|\RetailcrmApiClientV5 $api
     * @param Cart $cart Cart with data
     * @param string $externalId External ID for order
     * @param string $paymentType Payment type (buildCrmOrder requires it)
     * @param string $status Status for order
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function buildCrmOrderFromCart($api, $cart = null, $externalId = '', $paymentType = '', $status = '')
    {
        if (empty($cart) || empty($paymentType) || empty($status)) {
            return [];
        }

        try {
            $order = new Order();
            $order->id_cart = $cart->id;
            $order->id_customer = $cart->id_customer;
            $order->id_address_delivery = $cart->id_address_delivery;
            $order->id_address_invoice = $cart->id_address_invoice;
            $order->id_currency = $cart->id_currency;
            $order->id_carrier = $cart->id_carrier;
            $order->total_discounts = $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
            $order->module = $paymentType;
            $order->payment = $paymentType;

            if (!empty($cart->id_carrier)) {
                $order->total_shipping = $cart->getPackageShippingCost();
                $order->total_shipping_tax_excl = $cart->getPackageShippingCost(null, false);
            }

            $orderBuilder = new RetailcrmOrderBuilder();
            $orderData = $orderBuilder
                ->defaultLangFromConfiguration()
                ->setApi($api)
                ->setCmsOrder($order)
                ->setCmsCart($cart)
                ->setCmsCustomer(new Customer($cart->id_customer))
                ->buildOrderWithPreparedCustomer(true);
            $orderData['externalId'] = $externalId;
            $orderData['status'] = $status;

            unset($orderData['payments']);

            return RetailcrmTools::clearArray($orderData);
        } catch (\InvalidArgumentException $exception) {
            RetailcrmLogger::writeCaller(
                'buildCrmOrderFromCart',
                $exception->getMessage()
            );

            return [];
        }
    }

    /**
     * Builds retailCRM customer data from PrestaShop customer data
     *
     * @param Customer $object
     * @param array $address
     *
     * @return array
     */
    public static function buildCrmCustomer(Customer $object, $address = [])
    {
        $customer = array_filter(array_merge(
            [
                'externalId' => !empty($object->id) ? $object->id : null,
                'firstName' => $object->firstname,
                'lastName' => $object->lastname,
                'email' => $object->email,
                'subscribed' => $object->newsletter,
                'createdAt' => RetailcrmTools::verifyDate($object->date_add, 'Y-m-d H:i:s')
                    ? $object->date_add : date('Y-m-d H:i:s'),
                'birthday' => RetailcrmTools::verifyDate($object->birthday, 'Y-m-d')
                    ? $object->birthday : '',
                'sex' => $object->id_gender == '1' ? 'male' : ($object->id_gender == '2' ? 'female' : ''),
            ],
            $address
        ), function ($value) {
            return !($value === '' || $value === null || (is_array($value) ? count($value) == 0 : false));
        });

        return RetailcrmTools::filter(
            'RetailcrmFilterProcessCustomer',
            $customer,
            [
                'customer' => $object,
                'address' => $address,
            ]);
    }

    public static function buildCrmCustomerCorporate(
        Customer $object,
        $nickName = '',
        $contactExternalId = '',
        $appendAddress = false,
        $appendCompany = false,
        $site = ''
    ) {
        $customerAddresses = [];
        $addresses = $object->getAddresses((int) Configuration::get('PS_LANG_DEFAULT'));
        $customer = [
            'addresses' => [],
            'companies' => [],
        ];
        $company = [
            'isMain' => true,
            'externalId' => null,
            'active' => true,
            'name' => '',
        ];

        // TODO: $company['contragent']['INN'] may not work, should check that later...
        foreach ($addresses as $address) {
            $addressBuilder = new RetailcrmAddressBuilder();

            if ($address instanceof Address && !empty($address->company)) {
                $customerAddresses[] = $addressBuilder
                    ->setMode(RetailcrmAddressBuilder::MODE_CORPORATE_CUSTOMER)
                    ->setAddress($address)
                    ->setWithExternalId(true)
                    ->build()
                    ->getDataArray();
                $customer['nickName'] = empty($nickName) ? $address->company : $nickName;
                $company['name'] = $address->company;
                $company['contragent']['INN'] = $address->vat_number;
                $company['externalId'] = 'company_' . $address->id;
            }

            if (is_array($address) && !empty($address['company'])) {
                $customerAddresses[] = $addressBuilder
                    ->setMode(RetailcrmAddressBuilder::MODE_CORPORATE_CUSTOMER)
                    ->setAddressId($address['id_address'])
                    ->setWithExternalId(true)
                    ->build()
                    ->getDataArray();
                $customer['nickName'] = empty($nickName) ? $address->company : $nickName;
                $company['name'] = $address['company'];
                $company['contragent']['INN'] = $address['vat_number'];
                $company['externalId'] = 'company_' . $address['id_address'];
            }
        }

        if ($appendCompany && !is_null($company['externalId'])) {
            $customer['companies'][] = $company;
        }

        if (!empty($contactExternalId) && !empty($site)) {
            $customer['customerContacts'] = [[
                'isMain' => true,
                'customer' => [
                    'externalId' => $contactExternalId,
                    'site' => $site,
                ],
            ]];

            if (!empty($customer['companies'])
                && isset($customer['companies'][0], $customer['companies'][0]['externalId'])
            ) {
                $customer['customerContacts'][0]['companies'] = [[
                    'company' => ['externalId' => $customer['companies'][0]['externalId']],
                ]];
            }
        }

        if ($appendAddress) {
            $customer['addresses'] = $customerAddresses;
        }

        return RetailcrmTools::filter(
            'RetailcrmFilterProcessCustomerCorporate',
            RetailcrmTools::clearArray($customer),
            [
                'customer' => $object,
            ]);
    }

    /**
     * Returns true if provided item array contains placeholder item added for equal price with payment.
     *
     * @param array $item
     *
     * @return bool
     */
    public static function isGiftItem($item)
    {
        if (isset($item['offer'], $item['offer']['externalId'])
            && $item['offer']['externalId'] == RetailcrmReferences::GIFT_WRAPPING_ITEM_EXTERNAL_ID
        ) {
            return true;
        }

        if (isset($item['externalIds'])) {
            foreach ($item['externalIds'] as $externalId) {
                if ($externalId['code'] == 'prestashop'
                    && $externalId['value'] == RetailcrmReferences::GIFT_WRAPPING_ITEM_EXTERNAL_ID
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns gift item
     *
     * @param float $giftItemPrice
     *
     * @return array
     */
    public static function getGiftItem($giftItemPrice)
    {
        return [
            'externalIds' => [[
                'code' => 'prestashop',
                'value' => RetailcrmReferences::GIFT_WRAPPING_ITEM_EXTERNAL_ID,
            ]],
            'offer' => ['externalId' => RetailcrmReferences::GIFT_WRAPPING_ITEM_EXTERNAL_ID],
            'productName' => 'Gift Wrapping Cost',
            'quantity' => 1,
            'initialPrice' => $giftItemPrice,
            'purchasePrice' => $giftItemPrice,
        ];
    }

    /**
     * Sets gift item to order (should be called if order is marked as gift)
     *
     * @param Order|\OrderCore $orderCms
     * @param array $orderCrm
     */
    private static function setOrderGiftItem($orderCms, &$orderCrm)
    {
        $isFound = false;
        $giftItemPrice = round($orderCms->total_wrapping, 2);

        foreach ($orderCrm['items'] as $key => $item) {
            if (self::isGiftItem($item)) {
                $orderCrm['items'][$key] = self::getGiftItem($giftItemPrice);
                $isFound = true;

                break;
            }
        }

        if (!$isFound) {
            $orderCrm['items'][] = self::getGiftItem($giftItemPrice);
        }
    }
}
