# Custom filters

## Usage

If you want to modify data, sent between CRM and CMS you can use custom filters.
To use filters you should define a new class in `<prestashop-root>/modules/retailcrm/custom/hooks`. Filename and classname must match the filter name.
Filter class should implement interface *RetailcrmFilterInterface*. In filter class you must define *filter()* function, which will take initial `$object` and return customized `$object`.

## Example

The example below shows you how to customize address data, loaded from CRM history during back sync:

```php
<?php
// custom/hooks/RetailcrmFilterSaveCustomerAddress.php

class RetailcrmFilterSaveCustomerAddress implements RetailcrmFilterInterface
{
    /**
     * @inheritDoc
     */
    public static function filter($object, array $parameters)
    {
        /**
         * @var array $dataCrm CRM address data
         * @var Address $object  CMS Address object
         */

        $dataCrm = $parameters['dataCrm'];

        if (isset($dataCrm['dni'])) {
            $object->dni = $dataCrm['dni'];
        }

        return $object;
    }
}
```

## List of filters

There are list of available filters:

* *RetailcrmFilterProcessOrder* - order array, which will be sent to CRM
* *RetailcrmFilterProcessCustomer* - customer array, which will be sent to CRM
* *RetailcrmFilterProcessCustomerCorporate* - corporate customer array, which will be sent to CRM
* *RetailcrmFilterProcessAddress* - address array, which will be sent to CRM
* *RetailcrmFilterProcessOffer* - offer array, which will be sent to CRM (saved into Icml file)

* *RetailcrmFilterCustomersHistory* - array with assembled history for customer, loaded from CRM
* *RetailcrmFilterCustomersHistoryUpdate* - array with customer info, loaded from CRM
* *RetailcrmFilterOrdersHistory* - array with assembled history for order, loaded from CRM
* *RetailcrmFilterOrdersHistoryCreate* - array with order info, loaded from CRM
* *RetailcrmFilterOrdersHistoryUpdate* - array with assembled history for order, loaded from CRM

* *RetailcrmFilterSaveCustomer* - built customer object, which will be saved to CMS
* *RetailcrmFilterSaveCustomerAddress* - built customer address object, which will be saved to CMS
* *RetailcrmFilterSaveCorporateCustomer* - built corporate customer object, which will be saved to CMS
* *RetailcrmFilterSaveCorporateCustomerAddress* - built corporate customer address object, which will be saved to CMS

* *RetailcrmFilterJobManagerIntervals* - array with jobs as keys and intervals as values
